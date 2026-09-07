<?php
/**
 * NovaHire — Resume Builder Engine
 * Professional resume generation with 4 templates
 * High-contrast colors, modern fonts, clear readability
 */

if (defined('NOVAHIRE_RESUME')) return;
define('NOVAHIRE_RESUME', true);

/* ── Get User Resume Data ───────────────────────────────────────────────── */
function get_resume_data($con, $user_id) {
    $stmt = mysqli_prepare($con, "SELECT * FROM user_info WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$user) return null;
    
    // Get work experience from applications
    $applications = [];
    $app_check = @mysqli_query($con, "SHOW TABLES LIKE 'job_applications'");
    if ($app_check && mysqli_num_rows($app_check) > 0) {
        $app_stmt = mysqli_prepare($con, "SELECT ja.*, cj.job_title, cj.job_category, cj.employment_type, c.company_name 
                                           FROM job_applications ja 
                                           LEFT JOIN company_jobs cj ON ja.job_id = cj.id 
                                           LEFT JOIN companies c ON cj.company_id = c.id 
                                           WHERE ja.user_id = ? 
                                           ORDER BY ja.applied_date DESC LIMIT 10");
        mysqli_stmt_bind_param($app_stmt, "i", $user_id);
        mysqli_stmt_execute($app_stmt);
        $app_result = mysqli_stmt_get_result($app_stmt);
        while ($row = mysqli_fetch_assoc($app_result)) {
            $applications[] = $row;
        }
        mysqli_stmt_close($app_stmt);
    }
    
    // Get certifications from quiz results
    $certifications = [];
    $quiz_check = @mysqli_query($con, "SHOW TABLES LIKE 'user_quiz_status'");
    if ($quiz_check && mysqli_num_rows($quiz_check) > 0) {
        $quiz_stmt = mysqli_prepare($con, "SELECT DISTINCT category FROM user_quiz_status WHERE user_id = ? AND status = 'passed'");
        mysqli_stmt_bind_param($quiz_stmt, "i", $user_id);
        mysqli_stmt_execute($quiz_stmt);
        $quiz_result = mysqli_stmt_get_result($quiz_stmt);
        while ($row = mysqli_fetch_assoc($quiz_result)) {
            $certifications[] = [
                'name' => ucfirst($row['category']) . ' Proficiency',
                'issuer' => 'NovaHire Assessment',
            ];
        }
        mysqli_stmt_close($quiz_stmt);
    }
    
    // Get interests from saved jobs
    $interests = [];
    $saved_check = @mysqli_query($con, "SHOW TABLES LIKE 'saved_jobs'");
    if ($saved_check && mysqli_num_rows($saved_check) > 0) {
        $saved_stmt = mysqli_prepare($con, "SELECT cj.job_category FROM saved_jobs sj 
                                             JOIN company_jobs cj ON sj.job_id = cj.id 
                                             WHERE sj.user_id = ? GROUP BY cj.job_category LIMIT 5");
        mysqli_stmt_bind_param($saved_stmt, "i", $user_id);
        mysqli_stmt_execute($saved_stmt);
        $saved_result = mysqli_stmt_get_result($saved_stmt);
        while ($row = mysqli_fetch_assoc($saved_result)) {
            $interests[] = $row['job_category'];
        }
        mysqli_stmt_close($saved_stmt);
    }
    
    $skills = array_filter(array_map('trim', explode(',', $user['user_skills'] ?? '')));
    
    // Build experience entries
    $experience = [];
    if (!empty($applications)) {
        foreach ($applications as $i => $app) {
            $exp_years = max(1, intval($user['experience_years'] ?? 1));
            $start_year = date('Y') - $exp_years + $i;
            $experience[] = [
                'title' => $app['job_title'] ?? 'Job Seeker',
                'company' => $app['company_name'] ?? 'Company',
                'type' => $app['employment_type'] ?? '',
                'category' => $app['job_category'] ?? '',
                'period' => date('M Y', strtotime("-{$i} years")) . ' - ' . ($i === 0 ? 'Present' : date('M Y', strtotime("-" . ($i+1) . " years"))),
                'status' => $app['application_status'] ?? '',
            ];
        }
    }
    if (empty($experience)) {
        $experience[] = [
            'title' => $user['user_degree'] ?: 'Fresher',
            'company' => 'Entry Level',
            'type' => '',
            'category' => '',
            'period' => date('Y') . ' - Present',
            'status' => 'seeking',
        ];
    }
    
    return [
        'name'           => $user['username'] ?? '',
        'email'          => $user['email'] ?? '',
        'phone'          => $user['phone'] ?? '',
        'degree'         => $user['user_degree'] ?? '',
        'about'          => $user['about_me'] ?? '',
        'skills'         => array_values($skills),
        'experience_years' => intval($user['experience_years'] ?? 0),
        'expected_salary'  => intval($user['expected_salary'] ?? 0),
        'experience'     => $experience,
        'certifications' => $certifications,
        'interests'      => $interests,
        'profile'        => $user['profile'] ?? '',
        'location'       => $user['address'] ?? '',
    ];
}

/* ── Save Custom Resume Data ────────────────────────────────────────────── */
function save_resume_data($con, $user_id, $data) {
    $fields = [
        'about_me' => $data['about'] ?? '',
        'experience_years' => intval($data['experience_years'] ?? 0),
        'expected_salary' => intval($data['expected_salary'] ?? 0),
    ];
    
    $sql_parts = [];
    $params = [];
    $types = '';
    foreach ($fields as $field => $value) {
        $sql_parts[] = "$field = ?";
        $params[] = $value;
        $types .= ($field === 'about_me' ? 's' : 'i');
    }
    
    if (!empty($sql_parts)) {
        $params[] = $user_id;
        $types .= 'i';
        $sql = "UPDATE user_info SET " . implode(', ', $sql_parts) . " WHERE id = ?";
        $stmt = mysqli_prepare($con, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        $result = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $result;
    }
    return false;
}

/* ── Render Resume HTML ─────────────────────────────────────────────────── */
function render_resume_template($data, $template = 'professional') {
    if (empty($data)) {
        $data = [
            'name' => 'Your Name', 'email' => 'email@example.com', 'phone' => '+880 XXXX',
            'degree' => '', 'about' => '', 'skills' => [], 'experience_years' => 0,
            'experience' => [], 'certifications' => [], 'interests' => [], 'location' => '',
        ];
    }
    
    switch ($template) {
        case 'modern':    return resume_modern($data);
        case 'minimal':   return resume_minimal($data);
        case 'creative':  return resume_creative($data);
        default:          return resume_professional($data);
    }
}

/* ── Helper: Escape HTML ────────────────────────────────────────────────── */
function esc($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/* ═══════════════════════════════════════════════════════════════════════════
   TEMPLATE 1: PROFESSIONAL — Clean, high-contrast, corporate
   ═══════════════════════════════════════════════════════════════════════════ */
function resume_professional($d) {
    $name = esc($d['name'] ?? 'Your Name');
    $email = esc($d['email'] ?? '');
    $phone = esc($d['phone'] ?? '');
    $location = esc($d['location'] ?? '');
    $degree = esc($d['degree'] ?? '');
    $about = esc($d['about'] ?? '');
    $skills = $d['skills'] ?? [];
    $experience = $d['experience'] ?? [];
    $certs = $d['certifications'] ?? [];
    $interests = $d['interests'] ?? [];
    
    $contact = array_filter([$email, $phone, $location]);
    
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; color: #111827; line-height: 1.6; background: #fff; -webkit-font-smoothing: antialiased; }
        .resume { max-width: 800px; margin: 0 auto; padding: 48px 52px; }
        
        /* Header */
        .header { text-align: center; margin-bottom: 28px; }
        .header h1 { font-size: 32px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; margin-bottom: 6px; }
        .header .degree { font-size: 14px; color: #1a56db; font-weight: 600; text-transform: uppercase; letter-spacing: 2.5px; margin-bottom: 12px; }
        .header .divider { width: 60px; height: 3px; background: linear-gradient(90deg, #1a56db, #0ea5e9); margin: 0 auto 14px; border-radius: 2px; }
        .contact-row { display: flex; justify-content: center; gap: 24px; flex-wrap: wrap; }
        .contact-item { font-size: 12.5px; color: #334155; display: flex; align-items: center; gap: 6px; font-weight: 500; }
        .contact-item i { color: #1a56db; font-size: 12px; width: 16px; text-align: center; }
        
        /* Sections */
        .section { margin-bottom: 24px; }
        .section-title { 
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; 
            color: #1e40af; margin-bottom: 14px; padding-bottom: 8px; 
            border-bottom: 2px solid #e2e8f0; display: flex; align-items: center; gap: 8px; 
        }
        .section-title i { font-size: 13px; }
        
        /* About */
        .about-text { font-size: 13.5px; color: #1e293b; line-height: 1.75; }
        
        /* Skills */
        .skills-grid { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill-chip { 
            background: #eef2ff; color: #3730a3; padding: 5px 16px; border-radius: 20px; 
            font-size: 12px; font-weight: 600; border: 1px solid #c7d2fe; 
        }
        
        /* Experience */
        .exp-item { margin-bottom: 16px; padding-left: 16px; border-left: 3px solid #1a56db; }
        .exp-item:last-child { margin-bottom: 0; }
        .exp-title { font-size: 15px; font-weight: 700; color: #0f172a; }
        .exp-company { font-size: 13.5px; color: #1a56db; font-weight: 600; }
        .exp-meta { font-size: 12px; color: #64748b; margin-top: 3px; font-weight: 500; }
        
        /* Certifications */
        .cert-item { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; font-size: 13px; }
        .cert-dot { width: 8px; height: 8px; background: #059669; border-radius: 50%; flex-shrink: 0; }
        .cert-name { font-weight: 600; color: #0f172a; }
        .cert-issuer { color: #64748b; font-size: 12px; }
        
        /* Interests */
        .interests-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .interest-tag { 
            background: #f8fafc; border: 1px solid #cbd5e1; padding: 5px 14px; 
            border-radius: 6px; font-size: 12px; color: #334155; font-weight: 500; 
        }
        
        @media print { body { padding: 0; } .resume { padding: 24px 32px; } }
    </style></head><body><div class="resume">';
    
    $html .= '<div class="header">';
    $html .= '<h1>' . $name . '</h1>';
    if ($degree) $html .= '<div class="degree">' . $degree . '</div>';
    $html .= '<div class="divider"></div>';
    if (!empty($contact)) {
        $html .= '<div class="contact-row">';
        foreach ($contact as $c) $html .= '<span class="contact-item"><i class="fas fa-circle" style="font-size:4px;color:#1a56db;"></i>' . $c . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    
    if ($about) {
        $html .= '<div class="section"><div class="section-title"><i class="fas fa-user"></i> Professional Summary</div>';
        $html .= '<p class="about-text">' . $about . '</p></div>';
    }
    
    if (!empty($skills)) {
        $html .= '<div class="section"><div class="section-title"><i class="fas fa-cogs"></i> Skills</div><div class="skills-grid">';
        foreach ($skills as $s) $html .= '<span class="skill-chip">' . esc($s) . '</span>';
        $html .= '</div></div>';
    }
    
    if (!empty($experience)) {
        $html .= '<div class="section"><div class="section-title"><i class="fas fa-briefcase"></i> Experience</div>';
        foreach ($experience as $exp) {
            $html .= '<div class="exp-item">';
            $html .= '<div class="exp-title">' . esc($exp['title'] ?? '') . '</div>';
            $html .= '<div class="exp-company">' . esc($exp['company'] ?? '') . '</div>';
            $html .= '<div class="exp-meta">' . esc($exp['period'] ?? '');
            if (!empty($exp['type'])) $html .= ' &bull; ' . esc($exp['type']);
            $html .= '</div></div>';
        }
        $html .= '</div>';
    }
    
    if (!empty($certs)) {
        $html .= '<div class="section"><div class="section-title"><i class="fas fa-certificate"></i> Certifications</div>';
        foreach ($certs as $cert) {
            $html .= '<div class="cert-item"><span class="cert-dot"></span>';
            $html .= '<span class="cert-name">' . esc($cert['name'] ?? '') . '</span>';
            if (!empty($cert['issuer'])) $html .= ' <span class="cert-issuer">&mdash; ' . esc($cert['issuer']) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    if (!empty($interests)) {
        $html .= '<div class="section"><div class="section-title"><i class="fas fa-heart"></i> Interests</div><div class="interests-row">';
        foreach ($interests as $i) $html .= '<span class="interest-tag">' . esc($i) . '</span>';
        $html .= '</div></div>';
    }
    
    $html .= '</div></body></html>';
    return $html;
}

/* ═══════════════════════════════════════════════════════════════════════════
   TEMPLATE 2: MODERN — Dark sidebar, high contrast white text
   ═══════════════════════════════════════════════════════════════════════════ */
function resume_modern($d) {
    $name = esc($d['name'] ?? 'Your Name');
    $email = esc($d['email'] ?? '');
    $phone = esc($d['phone'] ?? '');
    $location = esc($d['location'] ?? '');
    $degree = esc($d['degree'] ?? '');
    $about = esc($d['about'] ?? '');
    $skills = $d['skills'] ?? [];
    $experience = $d['experience'] ?? [];
    $certs = $d['certifications'] ?? [];
    $interests = $d['interests'] ?? [];
    
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #fff; -webkit-font-smoothing: antialiased; }
        .resume { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { 
            width: 280px; background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%); 
            color: #f1f5f9; padding: 44px 28px; flex-shrink: 0; 
        }
        .sidebar .name { font-size: 26px; font-weight: 800; margin-bottom: 6px; line-height: 1.2; color: #ffffff; }
        .sidebar .degree { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 32px; font-weight: 500; }
        .sidebar .side-section { margin-bottom: 28px; }
        .sidebar .side-title { 
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; 
            color: #94a3b8; margin-bottom: 12px; padding-bottom: 8px; 
            border-bottom: 1px solid rgba(148,163,184,0.2); 
        }
        .sidebar .contact-item { font-size: 12.5px; margin-bottom: 10px; display: flex; align-items: center; gap: 10px; color: #e2e8f0; font-weight: 400; }
        .sidebar .contact-item i { width: 16px; text-align: center; color: #60a5fa; font-size: 13px; }
        .sidebar .skill-pill { 
            display: inline-block; background: rgba(96,165,250,0.15); color: #c7d2fe; 
            padding: 5px 12px; border-radius: 14px; font-size: 11.5px; margin: 0 5px 7px 0; 
            font-weight: 500; border: 1px solid rgba(96,165,250,0.2); 
        }
        .sidebar .cert-item { font-size: 12.5px; margin-bottom: 10px; padding-left: 14px; border-left: 2px solid #60a5fa; color: #e2e8f0; line-height: 1.5; }
        .sidebar .interest-item { font-size: 12.5px; margin-bottom: 8px; color: #cbd5e1; display: flex; align-items: center; gap: 8px; }
        .sidebar .interest-item i { font-size: 8px; color: #60a5fa; }
        
        /* Main */
        .main { flex: 1; padding: 44px 40px; }
        .main .section { margin-bottom: 26px; }
        .main .section-title { 
            font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1.5px; 
            color: #1e40af; margin-bottom: 14px; padding-bottom: 8px; 
            border-bottom: 2px solid #e2e8f0; 
        }
        .main .about-text { font-size: 13.5px; color: #1e293b; line-height: 1.75; }
        .main .exp-item { margin-bottom: 18px; padding-left: 16px; border-left: 3px solid #1a56db; }
        .main .exp-title { font-size: 15px; font-weight: 700; color: #0f172a; }
        .main .exp-company { font-size: 13.5px; color: #1a56db; font-weight: 600; }
        .main .exp-meta { font-size: 12px; color: #64748b; margin-top: 3px; font-weight: 500; }
        
        @media print { .resume { min-height: auto; } .sidebar { width: 250px; padding: 32px 22px; } }
    </style></head><body><div class="resume">';
    
    $html .= '<div class="sidebar">';
    $html .= '<div class="name">' . $name . '</div>';
    if ($degree) $html .= '<div class="degree">' . $degree . '</div>';
    
    $html .= '<div class="side-section"><div class="side-title">Contact</div>';
    if ($email) $html .= '<div class="contact-item"><i class="fas fa-envelope"></i>' . $email . '</div>';
    if ($phone) $html .= '<div class="contact-item"><i class="fas fa-phone"></i>' . $phone . '</div>';
    if ($location) $html .= '<div class="contact-item"><i class="fas fa-map-marker-alt"></i>' . $location . '</div>';
    $html .= '</div>';
    
    if (!empty($skills)) {
        $html .= '<div class="side-section"><div class="side-title">Skills</div>';
        foreach ($skills as $s) $html .= '<span class="skill-pill">' . esc($s) . '</span>';
        $html .= '</div>';
    }
    
    if (!empty($certs)) {
        $html .= '<div class="side-section"><div class="side-title">Certifications</div>';
        foreach ($certs as $c) {
            $html .= '<div class="cert-item">' . esc($c['name'] ?? '') . '</div>';
        }
        $html .= '</div>';
    }
    
    if (!empty($interests)) {
        $html .= '<div class="side-section"><div class="side-title">Interests</div>';
        foreach ($interests as $i) $html .= '<div class="interest-item"><i class="fas fa-chevron-right"></i>' . esc($i) . '</div>';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    $html .= '<div class="main">';
    
    if ($about) {
        $html .= '<div class="section"><div class="section-title">Professional Summary</div>';
        $html .= '<p class="about-text">' . $about . '</p></div>';
    }
    
    if (!empty($experience)) {
        $html .= '<div class="section"><div class="section-title">Experience</div>';
        foreach ($experience as $exp) {
            $html .= '<div class="exp-item">';
            $html .= '<div class="exp-title">' . esc($exp['title'] ?? '') . '</div>';
            $html .= '<div class="exp-company">' . esc($exp['company'] ?? '') . '</div>';
            $html .= '<div class="exp-meta">' . esc($exp['period'] ?? '');
            if (!empty($exp['type'])) $html .= ' &bull; ' . esc($exp['type']);
            $html .= '</div></div>';
        }
        $html .= '</div>';
    }
    
    $html .= '</div></div></body></html>';
    return $html;
}

/* ═══════════════════════════════════════════════════════════════════════════
   TEMPLATE 3: MINIMAL — Elegant, high-contrast typography
   ═══════════════════════════════════════════════════════════════════════════ */
function resume_minimal($d) {
    $name = esc($d['name'] ?? 'Your Name');
    $email = esc($d['email'] ?? '');
    $phone = esc($d['phone'] ?? '');
    $location = esc($d['location'] ?? '');
    $degree = esc($d['degree'] ?? '');
    $about = esc($d['about'] ?? '');
    $skills = $d['skills'] ?? [];
    $experience = $d['experience'] ?? [];
    $certs = $d['certifications'] ?? [];
    
    $contact = array_filter([$email, $phone, $location]);
    
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #fff; -webkit-font-smoothing: antialiased; }
        .resume { max-width: 750px; margin: 0 auto; padding: 50px 55px; }
        
        h1 { font-size: 38px; font-weight: 300; letter-spacing: -1px; color: #0f172a; margin-bottom: 8px; }
        .divider { width: 40px; height: 2px; background: #0f172a; margin-bottom: 16px; }
        .contact-line { font-size: 12.5px; color: #475569; margin-bottom: 32px; padding-bottom: 18px; border-bottom: 1px solid #e2e8f0; display: flex; gap: 20px; flex-wrap: wrap; }
        .contact-line span { display: flex; align-items: center; gap: 6px; font-weight: 500; }
        .contact-line i { color: #64748b; font-size: 11px; }
        
        .section { margin-bottom: 24px; }
        .section-label { 
            font-size: 11px; text-transform: uppercase; letter-spacing: 3px; 
            color: #64748b; font-weight: 700; margin-bottom: 12px; 
        }
        .about-text { font-size: 13.5px; color: #1e293b; line-height: 1.8; font-weight: 400; }
        
        .skills-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill-tag { 
            font-size: 12px; color: #1e293b; border: 1px solid #d1d5db; 
            padding: 5px 14px; border-radius: 4px; font-weight: 500; 
            background: #f8fafc; 
        }
        
        .exp-block { margin-bottom: 18px; }
        .exp-block h3 { font-size: 15px; font-weight: 700; color: #0f172a; }
        .exp-block .meta { font-size: 12.5px; color: #475569; font-weight: 500; margin-top: 2px; }
        
        .cert-line { font-size: 13px; color: #1e293b; margin-bottom: 8px; line-height: 1.6; }
        .cert-line strong { font-weight: 700; color: #0f172a; }
        .cert-line .issuer { color: #64748b; }
        
        @media print { .resume { padding: 28px 38px; } }
    </style></head><body><div class="resume">';
    
    $html .= '<h1>' . $name . '</h1>';
    $html .= '<div class="divider"></div>';
    if (!empty($contact)) {
        $html .= '<div class="contact-line">';
        foreach ($contact as $c) $html .= '<span><i class="fas fa-circle" style="font-size:4px;"></i>' . $c . '</span>';
        $html .= '</div>';
    }
    
    if ($degree) {
        $html .= '<div class="section"><div class="section-label">Education</div><p style="font-size:14px;color:#111827;font-weight:500;">' . $degree . '</p></div>';
    }
    
    if ($about) {
        $html .= '<div class="section"><div class="section-label">Profile</div><p class="about-text">' . $about . '</p></div>';
    }
    
    if (!empty($skills)) {
        $html .= '<div class="section"><div class="section-label">Skills</div><div class="skills-row">';
        foreach ($skills as $s) $html .= '<span class="skill-tag">' . esc($s) . '</span>';
        $html .= '</div></div>';
    }
    
    if (!empty($experience)) {
        $html .= '<div class="section"><div class="section-label">Experience</div>';
        foreach ($experience as $exp) {
            $html .= '<div class="exp-block"><h3>' . esc($exp['title'] ?? '') . '</h3>';
            $html .= '<div class="meta">' . esc($exp['company'] ?? '') . ' &bull; ' . esc($exp['period'] ?? '') . '</div></div>';
        }
        $html .= '</div>';
    }
    
    if (!empty($certs)) {
        $html .= '<div class="section"><div class="section-label">Certifications</div>';
        foreach ($certs as $c) {
            $html .= '<div class="cert-line"><strong>' . esc($c['name'] ?? '') . '</strong>';
            if (!empty($c['issuer'])) $html .= ' <span class="issuer">&mdash; ' . esc($c['issuer']) . '</span>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    $html .= '</div></body></html>';
    return $html;
}

/* ═══════════════════════════════════════════════════════════════════════════
   TEMPLATE 4: CREATIVE — Bold, colorful, high contrast
   ═══════════════════════════════════════════════════════════════════════════ */
function resume_creative($d) {
    $name = esc($d['name'] ?? 'Your Name');
    $email = esc($d['email'] ?? '');
    $phone = esc($d['phone'] ?? '');
    $location = esc($d['location'] ?? '');
    $degree = esc($d['degree'] ?? '');
    $about = esc($d['about'] ?? '');
    $skills = $d['skills'] ?? [];
    $experience = $d['experience'] ?? [];
    $certs = $d['certifications'] ?? [];
    
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #fff; -webkit-font-smoothing: antialiased; }
        .resume { max-width: 800px; margin: 0 auto; padding: 0; }
        
        /* Hero Header */
        .hero { 
            background: linear-gradient(135deg, #dc2626 0%, #ea580c 50%, #d97706 100%); 
            color: #ffffff; padding: 44px 48px 34px; 
        }
        .hero h1 { font-size: 34px; font-weight: 800; margin-bottom: 6px; color: #ffffff; }
        .hero .sub { font-size: 13px; color: rgba(255,255,255,0.92); text-transform: uppercase; letter-spacing: 2.5px; margin-bottom: 16px; font-weight: 600; }
        .hero .contact { display: flex; gap: 20px; flex-wrap: wrap; font-size: 12.5px; }
        .hero .contact span { display: flex; align-items: center; gap: 6px; color: rgba(255,255,255,0.95); font-weight: 500; }
        .hero .contact i { color: rgba(255,255,255,0.8); font-size: 12px; }
        
        /* Content */
        .content { padding: 34px 48px 44px; }
        .section { margin-bottom: 26px; }
        .section-title { 
            font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; 
            color: #dc2626; margin-bottom: 14px; display: flex; align-items: center; gap: 10px; 
        }
        .section-title::after { content: ""; flex: 1; height: 2px; background: #f1f5f9; }
        
        .about-text { font-size: 13.5px; color: #1e293b; line-height: 1.75; }
        
        .skills-flex { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill-bubble { 
            background: #fef2f2; color: #991b1b; padding: 6px 16px; border-radius: 20px; 
            font-size: 12px; font-weight: 600; border: 1px solid #fecaca; 
        }
        
        .exp-card { 
            background: #f8fafc; border-radius: 12px; padding: 18px 20px; margin-bottom: 14px; 
            border-left: 4px solid #dc2626; 
        }
        .exp-card .title { font-size: 15px; font-weight: 700; color: #0f172a; }
        .exp-card .company { font-size: 13.5px; color: #dc2626; font-weight: 600; }
        .exp-card .meta { font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 500; }
        
        .cert-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; font-size: 13px; }
        .cert-icon { 
            width: 30px; height: 30px; background: #fef3c7; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center; 
            color: #92400e; font-size: 13px; flex-shrink: 0; 
        }
        .cert-info strong { color: #0f172a; font-weight: 700; }
        .cert-info small { color: #64748b; display: block; margin-top: 1px; }
        
        @media print { .hero { padding: 28px 32px 22px; } .content { padding: 24px 32px 32px; } }
    </style></head><body><div class="resume">';
    
    $html .= '<div class="hero">';
    $html .= '<h1>' . $name . '</h1>';
    if ($degree) $html .= '<div class="sub">' . $degree . '</div>';
    $html .= '<div class="contact">';
    if ($email) $html .= '<span><i class="fas fa-envelope"></i> ' . $email . '</span>';
    if ($phone) $html .= '<span><i class="fas fa-phone"></i> ' . $phone . '</span>';
    if ($location) $html .= '<span><i class="fas fa-map-marker-alt"></i> ' . $location . '</span>';
    $html .= '</div></div>';
    
    $html .= '<div class="content">';
    
    if ($about) {
        $html .= '<div class="section"><div class="section-title">About Me</div>';
        $html .= '<p class="about-text">' . $about . '</p></div>';
    }
    
    if (!empty($skills)) {
        $html .= '<div class="section"><div class="section-title">Skills</div><div class="skills-flex">';
        foreach ($skills as $s) $html .= '<span class="skill-bubble">' . esc($s) . '</span>';
        $html .= '</div></div>';
    }
    
    if (!empty($experience)) {
        $html .= '<div class="section"><div class="section-title">Experience</div>';
        foreach ($experience as $exp) {
            $html .= '<div class="exp-card">';
            $html .= '<div class="title">' . esc($exp['title'] ?? '') . '</div>';
            $html .= '<div class="company">' . esc($exp['company'] ?? '') . '</div>';
            $html .= '<div class="meta">' . esc($exp['period'] ?? '');
            if (!empty($exp['type'])) $html .= ' &bull; ' . esc($exp['type']);
            $html .= '</div></div>';
        }
        $html .= '</div>';
    }
    
    if (!empty($certs)) {
        $html .= '<div class="section"><div class="section-title">Certifications</div>';
        foreach ($certs as $c) {
            $html .= '<div class="cert-row"><div class="cert-icon"><i class="fas fa-check"></i></div>';
            $html .= '<div class="cert-info"><strong>' . esc($c['name'] ?? '') . '</strong>';
            if (!empty($c['issuer'])) $html .= '<small>' . esc($c['issuer']) . '</small>';
            $html .= '</div></div>';
        }
        $html .= '</div>';
    }
    
    $html .= '</div></body></html>';
    return $html;
}

/* ── Get Available Templates ────────────────────────────────────────────── */
function get_resume_templates() {
    return [
        'professional' => [
            'name' => 'Professional',
            'description' => 'Clean corporate layout with indigo accents — best for formal roles',
            'preview_color' => '#1a56db',
            'icon' => 'fa-building',
        ],
        'modern' => [
            'name' => 'Modern',
            'description' => 'Dark sidebar with light content — great for tech roles',
            'preview_color' => '#0f172a',
            'icon' => 'fa-laptop-code',
        ],
        'minimal' => [
            'name' => 'Minimal',
            'description' => 'Elegant typography with thin accents — perfect for academic',
            'preview_color' => '#111827',
            'icon' => 'fa-pen-fancy',
        ],
        'creative' => [
            'name' => 'Creative',
            'description' => 'Bold red-orange gradient — ideal for designers & marketers',
            'preview_color' => '#dc2626',
            'icon' => 'fa-palette',
        ],
    ];
}
?>
