<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('location: login.php');
    exit();
}
include 'admin/dbcon.php';

$user_id = $_SESSION['id'];
$query = "SELECT * FROM user_info WHERE id = '$user_id'";
$result = mysqli_query($con, $query);
$user = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Resume - <?php echo $user['username']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"/>
    <style>
        :root {
            --primary: #1a3a52;
            --secondary: #2980b9;
            --accent: #e74c3c;
            --light-bg: #f8f9fa;
            --text: #2c3e50;
            --text-light: #7f8c8d;
            --border: #e1e8ed;
        }
        
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 40px 20px;
            color: var(--text);
            min-height: 100vh;
        }

        .cv-page {
            max-width: 210mm; 
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            display: grid;
            grid-template-columns: 35% 65%;
            position: relative;
            border-radius: 8px;
            overflow: hidden;
        }

        /* Sidebar */
        .cv-sidebar {
            background: linear-gradient(135deg, var(--primary) 0%, #2c3e50 100%);
            padding: 50px 35px;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .profile-img-container {
            width: 180px;
            height: 180px;
            margin: 0 auto 35px;
            border-radius: 50%;
            overflow: hidden;
            border: 6px solid white;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            background: white;
            flex-shrink: 0;
        }

        .profile-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .sidebar-section {
            width: 100%;
            margin-bottom: 45px;
        }

        .sidebar-title {
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 2px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 3px solid rgba(255,255,255,0.3);
            color: #fff;
            display: flex;
            align-items: center;
        }

        .sidebar-title::before {
            content: '';
            display: inline-block;
            width: 6px;
            height: 6px;
            background: var(--accent);
            border-radius: 50%;
            margin-right: 12px;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 18px;
            font-size: 0.95rem;
            color: rgba(255,255,255,0.95);
            line-height: 1.4;
        }

        .contact-icon {
            width: 35px;
            min-width: 35px;
            margin-right: 15px;
            text-align: center;
            color: var(--accent);
            font-size: 1.1rem;
        }

        .skill-tag {
            background: rgba(255,255,255,0.15);
            border: 1.5px solid rgba(255,255,255,0.3);
            color: white;
            padding: 7px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
            margin: 0 8px 10px 0;
            font-weight: 600;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .skill-tag:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
        }

        .skills-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }


        .cv-main {
            padding: 50px 45px;
            background: white;
        }

        .header-name {
            font-family: 'Playfair Display', serif;
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 8px 0;
            line-height: 1.1;
            letter-spacing: -1px;
        }

        .header-role {
            font-size: 1.05rem;
            text-transform: uppercase;
            letter-spacing: 2.5px;
            color: var(--secondary);
            margin: 0 0 35px 0;
            font-weight: 600;
        }

        .header-divider {
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--accent));
            margin-bottom: 35px;
            border-radius: 2px;
        }

        .section {
            margin-bottom: 45px;
        }
        
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0 0 25px 0;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--secondary);
            display: flex;
            align-items: center;
            position: relative;
        }

        .section-title::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background: var(--accent);
            border-radius: 50%;
            margin-right: 15px;
        }

        .experience-item, .education-item {
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .experience-item:last-child, .education-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .item-title {
            font-weight: 700;
            font-size: 1.15rem;
            color: var(--primary);
            margin: 0;
        }

        .item-date {
            font-style: italic;
            color: var(--secondary);
            font-size: 0.9rem;
            font-weight: 600;
            white-space: nowrap;
            margin-left: 15px;
        }

        .item-subtitle {
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.98rem;
            margin: 8px 0;
            display: flex;
            align-items: center;
        }

        .item-subtitle::before {
            content: '';
            display: inline-block;
            width: 4px;
            height: 4px;
            background: var(--accent);
            border-radius: 50%;
            margin-right: 10px;
        }

        .item-desc {
            color: var(--text);
            font-size: 0.95rem;
            line-height: 1.7;
            margin: 0;
        }

        .profile-summary {
            background: linear-gradient(135deg, rgba(41, 128, 185, 0.1), rgba(231, 76, 60, 0.05));
            padding: 25px;
            border-left: 4px solid var(--secondary);
            border-radius: 4px;
            margin-bottom: 0;
            line-height: 1.8;
        }

        .print-controls {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 1000;
        }

        .btn-fab {
            background: var(--secondary);
            color: white;
            border: none;
            width: 65px;
            height: 65px;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-fab:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.35);
        }

        .btn-home {
            background: #27ae60;
        }

        .btn-home:hover {
            background: #229954;
        }

        @media print {
            body { padding: 0; background: white; }
            .print-controls { display: none; }
            .cv-page { box-shadow: none; width: 100%; max-width: 100%; margin: 0; min-height: 100vh; border-radius: 0; }
        }

        @media screen and (max-width: 768px) {
            .cv-page { grid-template-columns: 1fr; width: 100%; border-radius: 0; }
            .cv-sidebar { padding: 40px 30px; border-right: none; border-bottom: none; }
            .cv-main { padding: 30px; }
            body { padding: 10px; }
            .header-name { font-size: 2.2rem; }
            .btn-fab { width: 55px; height: 55px; font-size: 1.3rem; }
        }
    </style>
</head>
<body>

    <div class="print-controls">
        <a href="profile.php" class="btn-fab btn-home" title="Back to Dashboard"><i class="fas fa-home"></i></a>
        <button onclick="window.print()" class="btn-fab" title="Download PDF"><i class="fas fa-download"></i></button>
    </div>

    <div class="cv-page">
        <!-- Sidebar -->
        <div class="cv-sidebar">
            <div class="profile-img-container">
                <?php if($user['profile']): ?>
                    <img src="./images/profile4.png<?php echo htmlspecialchars($user['profile']); ?>" class="profile-img">
                <?php else: ?>
                    <img src="https://via.placeholder.com/150" class="profile-img">
                <?php endif; ?>
            </div>

            <div class="sidebar-section">
                <h3 class="sidebar-title">Contact</h3>
                <div class="contact-item">
                    <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                    <div><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon"><i class="fas fa-phone-alt"></i></div>
                    <div><?php echo htmlspecialchars($user['phone']); ?></div>
                </div>
                <div class="contact-item">
                    <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div>Bangladesh</div>
                </div>
            </div>

            <div class="sidebar-section">
                <h3 class="sidebar-title">Expertise</h3>
                <div class="skills-list">
                    <?php 
                        $skills = explode(',', $user['user_skills']);
                        foreach($skills as $skill) {
                            if(trim($skill) != '') {
                                echo '<span class="skill-tag">'.htmlspecialchars(trim($skill)).'</span>';
                            }
                        }
                    ?>
                </div>
            </div>

            <div class="sidebar-section">
                <h3 class="sidebar-title">Languages</h3>
                <span class="skill-tag">English</span>
                <span class="skill-tag">Hindi</span>
            </div>
        </div>

        <!-- Main Content -->
        <div class="cv-main">
            <h1 class="header-name"><?php echo htmlspecialchars($user['username']); ?></h1>
            <p class="header-role"><?php echo htmlspecialchars($user['user_degree'] ?? 'Professional'); ?></p>
            <div class="header-divider"></div>

            <div class="section">
                <h2 class="section-title">About</h2>
                <p class="item-desc profile-summary">
                    Motivated and detail-oriented <?php echo htmlspecialchars($user['user_degree']); ?> with a strong foundation in <?php echo htmlspecialchars($user['user_skills']); ?>. 
                    Eager to join the workforce and contribute to projects that require innovative thinking and problem-solving skills.
                    Available for immediate employment and willing to relocate if necessary.
                </p>
            </div>

            <div class="section" style="margin-top: 30px;">
                <h2 class="section-title">Education</h2>
                
                <div class="education-item">
                    <div class="item-header">
                        <div class="item-title"><?php echo htmlspecialchars($user['user_degree']); ?></div>
                        <div class="item-date">2019 - 2023</div>
                    </div>
                    <div class="item-subtitle">United Internationa University</div>
                    <p class="item-desc">
                        Successfully completed degree with focus on core computing principles. 
                        Participated in various technical workshops and coding hackathons to enhance practical skills.
                        I have a strong foundation in programming, data structures, and software development methodologies.

                    </p>
                </div>

                <div class="education-item">
                    <div class="item-header">
                        <div class="item-title">Higher Secondary</div>
                        <div class="item-date">2017 - 2019</div>
                    </div>
                    <div class="item-subtitle">APBN School and College, Bogra</div>
                    <p class="item-desc">Completed with First Class distinction in Science stream with excellent academic performance.</p>
                </div>
            </div>

            <div class="section">
                <h2 class="section-title">Declaration</h2>
                <p class="item-desc">
                   I hereby declare that the details furnished above are true and correct to the best of my knowledge and belief.
                </p>
            </div>
        </div>
    </div>

</body>
</html>
