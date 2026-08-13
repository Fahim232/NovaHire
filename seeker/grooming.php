<?php
// Core setup: session, DB, BASE_URL, helpers
require_once __DIR__ . '/../includes/bootstrap.php';
if (!isset($_SESSION['id'])) {
    header('location: ' . BASE_URL . '/auth/login.php');
    exit();
}
require_once __DIR__ . '/../admin/dbcon.php';

$category = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : 'PHP';
$user_id = $_SESSION['id'];
$job_id = isset($_GET['job_id']) ? intval($_GET['job_id']) : 0;

// Handle Video Progress Update via AJAX
if (isset($_POST['update_progress'])) {
    $video_id = intval($_POST['video_id']);
    $watched_duration = intval($_POST['watched_duration']);
    $is_completed = intval($_POST['is_completed']);
    
    $check_query = "SELECT * FROM user_video_progress WHERE user_id='$user_id' AND video_id='$video_id'";
    $check_res = mysqli_query($con, $check_query);
    
    if (mysqli_num_rows($check_res) > 0) {
        $update = "UPDATE user_video_progress SET watched_duration=GREATEST(watched_duration, '$watched_duration'), is_completed=GREATEST(is_completed, '$is_completed') WHERE user_id='$user_id' AND video_id='$video_id'";
        mysqli_query($con, $update);
    } else {
        $insert = "INSERT INTO user_video_progress (user_id, video_id, watched_duration, is_completed) VALUES ('$user_id', '$video_id', '$watched_duration', '$is_completed')";
        mysqli_query($con, $insert);
    }
    echo json_encode(['success' => true]);
    exit();
}

// Handle Mark as Completed
if (isset($_POST['mark_completed'])) {
    // Block exhausted users from completing grooming
    if ($job_id > 0) {
        $exhaust_check_mc = "SELECT COUNT(*) as cnt FROM job_quiz_attempts WHERE user_id='$user_id' AND job_id='$job_id'";
        $exhaust_res_mc = mysqli_query($con, $exhaust_check_mc);
        if (mysqli_num_rows($exhaust_res_mc) > 0) {
            $exhaust_row_mc = mysqli_fetch_assoc($exhaust_res_mc);
            if (intval($exhaust_row_mc['cnt']) >= 2) {
                echo "<script>alert('You have exhausted all assessment attempts for this position.'); window.location.href='job_details.php?id=$job_id';</script>";
                exit();
            }
        }
    }

    $category_filter = ($category === 'Frontend') ? "(category='Frontend' OR category='javascript')" : "category='$category'";
    $videos_query = "SELECT COUNT(*) as total FROM grooming_videos WHERE $category_filter";
    $videos_res = mysqli_query($con, $videos_query);
    $videos_row = mysqli_fetch_assoc($videos_res);
    $total_videos = $videos_row['total'];
    
    $completed_query = "SELECT COUNT(*) as completed FROM user_video_progress 
                        WHERE user_id='$user_id' AND is_completed=1 
                        AND video_id IN (SELECT id FROM grooming_videos WHERE $category_filter)";
    $completed_res = mysqli_query($con, $completed_query);
    $completed_row = mysqli_fetch_assoc($completed_res);
    $completed_videos = $completed_row['completed'];
    
    if ($completed_videos >= $total_videos) {
        // Try to update with status='failed' first
        $update_query = "UPDATE user_quiz_status SET grooming_completed=1 WHERE user_id='$user_id' AND category='$category' AND status='failed'";
        mysqli_query($con, $update_query);
        
        // If no rows were affected, try without status filter (covers edge cases)
        if (mysqli_affected_rows($con) == 0) {
            $update_query2 = "UPDATE user_quiz_status SET grooming_completed=1 WHERE user_id='$user_id' AND category='$category'";
            mysqli_query($con, $update_query2);
        }
        
        // If still no record exists, create one
        if (mysqli_affected_rows($con) == 0) {
            mysqli_query($con, "INSERT INTO user_quiz_status (user_id, category, status, grooming_completed, last_attempt) VALUES ('$user_id', '$category', 'failed', 1, NOW())");
        }
        
        // Clear quiz session locks so user can retake
        if ($job_id > 0) {
            unset($_SESSION['quiz_taken_' . $job_id]);
            unset($_SESSION['quiz_submitted_' . $job_id]);
            echo "<script>alert('Congratulations! All videos completed. You can now retake the assessment.'); window.location.href='company_job_quiz.php?job_id=$job_id';</script>";
        } else {
            echo "<script>alert('Congratulations! All videos completed. You can now retake the assessment.'); window.location.href='quiz.php?category=" . urlencode($category) . "';</script>";
        }
    } else {
        echo "<script>alert('Please complete all videos before proceeding. ($completed_videos/$total_videos completed)');</script>";
    }
    exit();
}

// Check if user came from a company job quiz (for "Back" link)
$from_company_quiz = $job_id > 0;

// Check if user has exhausted all attempts for this job
$is_exhausted = false;
if ($job_id > 0) {
    $exhaust_check = "SELECT COUNT(*) as cnt FROM job_quiz_attempts WHERE user_id='$user_id' AND job_id='$job_id'";
    $exhaust_res = mysqli_query($con, $exhaust_check);
    if (mysqli_num_rows($exhaust_res) > 0) {
        $exhaust_row = mysqli_fetch_assoc($exhaust_res);
        $is_exhausted = (intval($exhaust_row['cnt']) >= 2);
    }
}

// Check User Status - force grooming if they failed quiz
$needs_grooming = false;
$can_retake = true;

$status_query = "SELECT * FROM user_quiz_status WHERE user_id='$user_id' AND category='$category'";
$status_res = mysqli_query($con, $status_query);

if (mysqli_num_rows($status_res) > 0) {
    $status_row = mysqli_fetch_assoc($status_res);
    if ($status_row['status'] == 'failed' && $status_row['grooming_completed'] == 0) {
        $needs_grooming = true;
        $can_retake = false;
    }
} else {
    // No status record yet - check if they have a failed quiz attempt for this category
    // This covers the case where company_job_quiz.php redirected them here
    $needs_grooming = true;
    $can_retake = false;
    
    // Create the status record
    mysqli_query($con, "INSERT INTO user_quiz_status (user_id, category, status, grooming_completed, last_attempt) VALUES ('$user_id', '$category', 'failed', 0, NOW())");
}

// If exhausted, override needs_grooming to false - show exhaustion message instead
if ($is_exhausted) {
    $needs_grooming = false;
}

// Fetch Videos for Category (merge Frontend + javascript)
$category_filter = ($category === 'Frontend') ? "(category='Frontend' OR category='javascript')" : "category='$category'";
$videos_query = "SELECT * FROM grooming_videos WHERE $category_filter ORDER BY order_index ASC";
$videos_result = mysqli_query($con, $videos_query);
$videos = [];
while ($video = mysqli_fetch_assoc($videos_result)) {
    $progress_query = "SELECT * FROM user_video_progress WHERE user_id='$user_id' AND video_id='{$video['id']}'";
    $progress_res = mysqli_query($con, $progress_query);
    $progress = mysqli_fetch_assoc($progress_res);
    
    $video['watched_duration'] = $progress ? $progress['watched_duration'] : 0;
    $video['is_completed'] = $progress ? $progress['is_completed'] : 0;
    $video['progress_percent'] = $video['duration'] > 0 ? min(100, ($video['watched_duration'] / $video['duration']) * 100) : 0;
    $videos[] = $video;
}

$total_videos = count($videos);
$completed_count = 0;
foreach ($videos as $v) {
    if ($v['is_completed']) $completed_count++;
}
$overall_progress = $total_videos > 0 ? ($completed_count / $total_videos) * 100 : 0;

$back_url = $from_company_quiz ? "job_details.php?id=$job_id" : "browse_jobs.php";

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../ai/grooming.php';
$ai_plan = ai_grooming_plan($category, $user_id);
?>
    <style>
        .grooming-container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .learning-hub-card {
            background: var(--bg-card);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-light);
        }

        .hub-header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #a855f7 100%);
            padding: 44px 36px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hub-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .hub-body {
            padding: 36px;
        }

        .overall-progress {
            background: var(--border-light);
            border-radius: 50px;
            height: 10px;
            overflow: hidden;
            margin: 20px 0;
        }

        .overall-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #34d399);
            transition: width 0.6s ease;
            border-radius: 50px;
        }

        .progress-text {
            text-align: center;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .video-card {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-bottom: 20px;
            border: 1px solid var(--border-light);
            transition: var(--transition);
        }

        .video-card:hover {
            box-shadow: var(--shadow-md);
        }

        .video-card.completed {
            border-color: var(--success);
            background: linear-gradient(145deg, #ffffff, #f0fdf4);
        }

        .video-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
            gap: 12px;
        }

        .video-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 4px;
        }

        .video-description {
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .video-badge {
            background: var(--success);
            color: white;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 0.72rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .video-badge.pending {
            background: var(--text-light);
        }

        .video-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            margin-bottom: 12px;
            background: #000;
        }

        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .video-progress-bar {
            background: var(--border-light);
            border-radius: 8px;
            height: 6px;
            overflow: hidden;
            margin-top: 10px;
        }

        .video-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.3s ease;
            border-radius: 8px;
        }

        .video-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .completion-banner {
            background: linear-gradient(135deg, #10b981, #34d399);
            color: white;
            padding: 36px;
            border-radius: var(--radius-lg);
            text-align: center;
            margin: 30px 0;
        }

        .completion-banner h3 { color: white; margin-bottom: 12px; }

        .alert-info-custom {
            background: rgba(79, 70, 229, 0.06);
            border-left: 4px solid var(--primary);
            padding: 18px 20px;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            margin-bottom: 28px;
        }

        .watching-indicator {
            display: none;
            align-items: center;
            gap: 8px;
            background: rgba(79, 70, 229, 0.08);
            color: var(--primary);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 8px;
        }
        .watching-indicator.active { display: inline-flex; }
        .watching-indicator .dot-pulse {
            width: 8px; height: 8px; border-radius: 50%; background: var(--primary);
            animation: pulse 1s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }

        @media (max-width: 768px) {
            .hub-header { padding: 32px 20px; }
            .hub-body { padding: 24px 16px; }
            .video-card { padding: 16px; }
            .video-header { flex-direction: column; }
        }

        .ai-coach-card {
            background: linear-gradient(135deg, #f8f7ff 0%, #eef2ff 100%);
            border: 1px solid #e0e7ff;
            border-radius: 16px;
            padding: 24px;
            margin-top: 28px;
            box-shadow: 0 8px 24px rgba(124,58,237,0.08);
        }
        .ai-coach-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #64748b;
            margin-bottom: 8px;
        }
        .ai-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin: 2px 4px 2px 0;
        }
        .ai-tag-warn { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; }
        .ai-tag-ok { background: #ecfdf5; color: #059669; border: 1px solid #d1fae5; }
        .ai-coach-tips {
            background: rgba(255,255,255,0.7);
            border-radius: 12px;
            padding: 14px 16px;
        }
        .ai-coach-llm {
            background: #7c3aed;
            color: white;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 0.9rem;
            margin-bottom: 16px;
        }
    </style>

    <div class="container grooming-container">
        <div class="learning-hub-card">
            
            <div class="hub-header">
                <i class="fas fa-graduation-cap fa-3x mb-3" style="opacity:0.9"></i>
                <h1 class="font-weight-bold mb-2" style="font-size: 1.8rem;">Skill Improvement Program</h1>
                <p class="mb-0" style="opacity:0.85; font-size: 0.95rem;">Master <?php echo htmlspecialchars($category); ?> through curated video lessons</p>
            </div>
            
            <div class="hub-body">
                <?php if ($needs_grooming): ?>
                    <div class="alert-info-custom">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-info-circle fa-2x mr-3" style="color: var(--primary);"></i>
                            <div>
                                <h5 class="mb-1" style="color: var(--primary); font-weight: 700; font-size: 1rem;">Complete Video Training Required</h5>
                                <p class="mb-0" style="color: var(--text-muted); font-size: 0.88rem;">Watch all videos completely (actual playback required) to unlock your retake opportunity. Videos must be played in full - pausing or skipping won't count.</p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h4 style="color: var(--text); font-weight: 700; font-size: 1.1rem;">
                            <i class="fas fa-chart-line mr-2" style="color: var(--success);"></i>
                            Your Learning Progress
                        </h4>
                        <div class="overall-progress">
                            <div class="overall-progress-bar" id="overallProgressBar" style="width: <?php echo $overall_progress; ?>%"></div>
                        </div>
                        <div class="progress-text" id="progressText">
                            <?php echo $completed_count; ?> of <?php echo $total_videos; ?> videos completed (<?php echo round($overall_progress); ?>%)
                        </div>
                    </div>

                    <h4 style="color: var(--text); font-weight: 700; margin-top: 32px; font-size: 1.1rem;">
                        <i class="fas fa-play-circle mr-2" style="color: var(--primary);"></i>
                        Video Lessons
                    </h4>

                    <?php foreach ($videos as $index => $video): ?>
                        <div class="video-card <?php echo $video['is_completed'] ? 'completed' : ''; ?>" id="video-card-<?php echo $video['id']; ?>">
                            <div class="video-header">
                                <div>
                                    <div class="video-title">
                                        <?php echo ($index + 1); ?>. <?php echo htmlspecialchars($video['title']); ?>
                                    </div>
                                    <div class="video-description">
                                        <?php echo htmlspecialchars($video['description']); ?>
                                    </div>
                                </div>
                                <span class="video-badge <?php echo $video['is_completed'] ? '' : 'pending'; ?>" id="badge-<?php echo $video['id']; ?>">
                                    <?php if ($video['is_completed']): ?>
                                        <i class="fas fa-check-circle"></i> Completed
                                    <?php else: ?>
                                        <i class="fas fa-clock"></i> Pending
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="video-container">
                                <iframe 
                                    id="player-<?php echo $video['id']; ?>"
                                    src="<?php echo $video['video_url']; ?>?enablejsapi=1&rel=0&modestbranding=1&playsinline=1" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen
                                    data-video-id="<?php echo $video['id']; ?>"
                                    data-duration="<?php echo $video['duration']; ?>">
                                </iframe>
                            </div>

                            <div class="video-progress-bar">
                                <div class="video-progress-fill" id="progress-<?php echo $video['id']; ?>" style="width: <?php echo $video['progress_percent']; ?>%"></div>
                            </div>

                            <div class="video-meta">
                                <span><i class="fas fa-clock mr-1"></i> Duration: <?php echo gmdate("i:s", $video['duration']); ?></span>
                                <span id="watched-<?php echo $video['id']; ?>">
                                    Watched: <?php echo gmdate("i:s", $video['watched_duration']); ?> / <?php echo gmdate("i:s", $video['duration']); ?>
                                </span>
                            </div>
                            <div class="watching-indicator" id="watching-<?php echo $video['id']; ?>">
                                <span class="dot-pulse"></span> Recording actual watch time...
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($overall_progress >= 100): ?>
                        <div class="completion-banner">
                            <i class="fas fa-trophy fa-3x mb-3"></i>
                            <h3>All Videos Completed!</h3>
                            <p class="mb-4" style="opacity:0.9;">You've successfully completed all training videos. You're now ready to retake the assessment.</p>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                                <button type="submit" name="mark_completed" class="btn btn-light btn-lg rounded-pill px-5 font-weight-bold" style="box-shadow: 0 4px 15px rgba(0,0,0,0.15);">
                                    <i class="fas fa-arrow-right mr-2"></i> Proceed to Assessment
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center mt-4 pt-3">
                            <div style="background: #fef3c7; color: #92400e; padding: 12px 24px; border-radius: 50px; font-weight: 600; font-size: 0.9rem; display: inline-block;">
                                <i class="fas fa-lock mr-2"></i> Complete all videos to unlock the assessment
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if ($is_exhausted): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-ban fa-4x mb-3" style="color: #e53e3e;"></i>
                            <h3 class="font-weight-bold mb-2" style="color: #c53030;">Assessment Attempts Exhausted</h3>
                            <p class="mb-4" style="color: var(--text-muted);">You have used all available assessment attempts for this position. You can no longer apply for this job.</p>
                            <a href="browse_jobs.php" class="btn btn-danger btn-lg rounded-pill px-5">
                                <i class="fas fa-search mr-2"></i> Browse Other Jobs
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle fa-4x mb-3" style="color: var(--success);"></i>
                            <h3 class="font-weight-bold mb-2">Training Completed!</h3>
                            <p class="mb-4" style="color: var(--text-muted);">You've successfully completed the skill improvement program. You now have ONE final attempt.</p>
                            <?php if ($from_company_quiz): ?>
                                <a href="company_job_quiz.php?job_id=<?php echo $job_id; ?>" class="btn btn-primary btn-lg rounded-pill px-5">
                                    <i class="fas fa-redo-alt mr-2"></i> Retake Assessment (Final)
                                </a>
                            <?php else: ?>
                                <a href="quiz.php?category=<?php echo urlencode($category); ?>" class="btn btn-primary btn-lg rounded-pill px-5">
                                    <i class="fas fa-redo-alt mr-2"></i> Retake Assessment (Final)
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- AI Study Coach -->
                <div class="ai-coach-card">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-robot" style="font-size:1.5rem; color:#7c3aed; background:rgba(139,92,246,0.12); width:46px; height:46px; border-radius:12px; display:flex; align-items:center; justify-content:center; margin-right:14px;"></i>
                        <div>
                            <div style="font-weight:700; color:#1e293b;">AI Study Coach</div>
                            <div style="font-size:0.8rem; color:#64748b;">Personalised plan for <?php echo htmlspecialchars($category); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($ai_plan['llm_summary'])): ?>
                        <div class="ai-coach-llm">
                            <i class="fas fa-magic mr-2"></i><?php echo nl2br(htmlspecialchars($ai_plan['llm_summary'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($ai_plan['weak_topics'])): ?>
                        <div class="mb-3">
                            <div class="ai-coach-label"><i class="fas fa-exclamation-triangle mr-1"></i> Focus On These Topics</div>
                            <div class="job-tags">
                                <?php foreach ($ai_plan['weak_topics'] as $t): ?>
                                    <span class="ai-tag ai-tag-warn"><?php echo htmlspecialchars($t); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($ai_plan['strong_topics'])): ?>
                        <div class="mb-3">
                            <div class="ai-coach-label"><i class="fas fa-check-circle mr-1"></i> Strong Areas</div>
                            <div class="job-tags">
                                <?php foreach ($ai_plan['strong_topics'] as $t): ?>
                                    <span class="ai-tag ai-tag-ok"><?php echo htmlspecialchars($t); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($ai_plan['tips'])): ?>
                        <div class="ai-coach-tips">
                            <?php foreach ($ai_plan['tips'] as $i => $tip): ?>
                                <div class="d-flex mb-1">
                                    <i class="fas fa-check-circle mr-2" style="color:#7c3aed; margin-top:3px;"></i>
                                    <span style="font-size:0.88rem; color:#334155;"><?php echo htmlspecialchars($tip); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="mt-3">
                        <a href="ai_mock_interview.php?category=<?php echo urlencode($category); ?>" class="btn btn-sm btn-outline-primary mr-2">
                            <i class="fas fa-comments mr-1"></i>Practice Interview
                        </a>
                        <a href="ai_grooming_coach.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-graduation-cap mr-1"></i>Full Coaching Plan
                        </a>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="<?php echo $back_url; ?>" style="color: var(--text-muted); font-size: 0.9rem;"><i class="fas fa-arrow-left mr-2"></i>Back</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://www.youtube.com/iframe_api"></script>
    <script>
        const videoData = <?php echo json_encode($videos); ?>;
        const CATEGORY = '<?php echo addslashes($category); ?>';
        const players = {};
        const videoStates = {};
        let currentlyPlayingId = null;

        function onYouTubeIframeAPIReady() {
            videoData.forEach(video => {
                const playerDiv = document.getElementById(`player-${video.id}`);
                if (!playerDiv) return;

                // Initialize state tracker
                videoStates[video.id] = {
                    watchedTime: parseInt(video.watched_duration) || 0,
                    maxWatchedTime: parseInt(video.watched_duration) || 0,
                    isPlaying: false,
                    lastTick: null,
                    duration: video.duration,
                    is_completed: video.is_completed == 1
                };

                // Create YouTube player
                players[video.id] = new YT.Player(`player-${video.id}`, {
                    videoId: extractVideoId(video.video_url),
                    playerVars: {
                        'enablejsapi': 1,
                        'rel': 0,
                        'modestbranding': 1,
                        'playsinline': 1,
                        'origin': window.location.origin
                    },
                    events: {
                        'onStateChange': function(event) {
                            onPlayerStateChange(video.id, event);
                        },
                        'onError': function(event) {
                            onPlayerError(video.id, event);
                        }
                    }
                });
            });
        }

        function extractVideoId(url) {
            const match = url.match(/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([^&?#]+)/);
            return match ? match[1] : url;
        }

        function onPlayerStateChange(videoId, event) {
            const state = videoStates[videoId];
            const YT = window.YT;

            if (event.data === YT.PlayerState.PLAYING) {
                // Video is playing
                state.isPlaying = true;
                state.lastTick = Date.now();
                currentlyPlayingId = videoId;
                
                // Show watching indicator
                const indicator = document.getElementById(`watching-${videoId}`);
                if (indicator) indicator.classList.add('active');

            } else if (event.data === YT.PlayerState.PAUSED) {
                // Video is paused
                state.isPlaying = false;
                saveProgress(videoId);
                
                const indicator = document.getElementById(`watching-${videoId}`);
                if (indicator) indicator.classList.remove('active');

            } else if (event.data === YT.PlayerState.ENDED) {
                // Video ended
                state.isPlaying = false;
                state.watchedTime = state.duration;
                state.maxWatchedTime = state.duration;
                saveProgress(videoId);
                
                const indicator = document.getElementById(`watching-${videoId}`);
                if (indicator) indicator.classList.remove('active');
            }
        }

        function onPlayerError(videoId, event) {
            const card = document.getElementById(`video-card-${videoId}`);
            if (card) {
                const container = card.querySelector('.video-container');
                if (container) {
                    container.innerHTML = '<div style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#1a1a2e;color:#e53e3e;text-align:center;padding:20px;border-radius:var(--radius-md);"><i class="fas fa-exclamation-triangle fa-3x mb-3" style="color:#f6ad55;"></i><h5 style="color:white;margin-bottom:8px;">Video Unavailable</h5><p style="color:#a0aec0;font-size:0.85rem;margin:0;">This video could not be loaded. It may have been removed or made private.</p></div>';
                }
                const badge = document.getElementById(`badge-${videoId}`);
                if (badge) {
                    badge.classList.remove('pending');
                    badge.style.background = '#e53e3e';
                    badge.innerHTML = '<i class="fas fa-exclamation-circle"></i> Unavailable';
                }
            }
        }

        // Main tracking loop - runs every second
        setInterval(() => {
            Object.keys(videoStates).forEach(videoId => {
                const state = videoStates[videoId];
                if (!state.isPlaying || !state.lastTick) return;

                const now = Date.now();
                const elapsed = Math.floor((now - state.lastTick) / 1000);
                state.lastTick = now;

                if (elapsed > 0 && elapsed <= 5) { // Sanity check: skip if >5s gap (tab was hidden)
                    state.watchedTime += elapsed;
                    if (state.watchedTime > state.maxWatchedTime) {
                        state.maxWatchedTime = state.watchedTime;
                    }
                    updateUI(videoId);
                }
            });
        }, 1000);

        // Save progress to server every 10 seconds
        setInterval(() => {
            Object.keys(videoStates).forEach(videoId => {
                const state = videoStates[videoId];
                if (state.maxWatchedTime > (parseInt(videoData.find(v => v.id == videoId)?.watched_duration) || 0)) {
                    saveProgress(videoId);
                }
            });
        }, 10000);

        function updateUI(videoId) {
            const state = videoStates[videoId];
            const progressPercent = Math.min(100, (state.maxWatchedTime / state.duration) * 100);
            
            const progressBar = document.getElementById(`progress-${videoId}`);
            if (progressBar) progressBar.style.width = progressPercent + '%';
            
            const watchedText = document.getElementById(`watched-${videoId}`);
            if (watchedText) {
                watchedText.innerHTML = `Watched: ${formatTime(state.maxWatchedTime)} / ${formatTime(state.duration)}`;
            }
        }

        function saveProgress(videoId) {
            const state = videoStates[videoId];
            const progressPercent = (state.maxWatchedTime / state.duration) * 100;
            const isCompleted = progressPercent >= 90 ? 1 : 0;

            fetch('grooming.php?category=' + encodeURIComponent(CATEGORY), {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `update_progress=1&video_id=${videoId}&watched_duration=${state.maxWatchedTime}&is_completed=${isCompleted}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && isCompleted && !state.is_completed) {
                    state.is_completed = true;
                    const card = document.getElementById(`video-card-${videoId}`);
                    if (card) {
                        card.classList.add('completed');
                        const badge = document.getElementById(`badge-${videoId}`);
                        if (badge) {
                            badge.classList.remove('pending');
                            badge.innerHTML = '<i class="fas fa-check-circle"></i> Completed';
                        }
                    }
                    // Reload to update overall progress
                    setTimeout(() => location.reload(), 800);
                }
            });
        }

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        // Tab visibility - pause tracking when tab is hidden
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Save all playing videos
                Object.keys(videoStates).forEach(videoId => {
                    const state = videoStates[videoId];
                    if (state.isPlaying) {
                        state.isPlaying = false;
                        saveProgress(videoId);
                    }
                });
            }
        });

        // Stop tracking when leaving page
        window.addEventListener('beforeunload', () => {
            Object.keys(videoStates).forEach(videoId => {
                saveProgress(videoId);
            });
        });
    </script>
