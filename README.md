# NovaHire

**AI-Powered Job Portal & Career Grooming Platform**

A full-featured recruitment ecosystem connecting job seekers, companies, and mentors — powered by a hybrid AI engine that works both online and offline.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Default Credentials](#default-credentials)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [AI System](#ai-system)
- [Payment & Monetization](#payment--monetization)
- [URL Routes](#url-routes)
- [Configuration](#configuration)
- [Troubleshooting](#troubleshooting)

---

## Overview

NovaHire is a complete job portal built with vanilla PHP and MySQL. It provides separate dashboards for **Job Seekers**, **Companies**, **Mentors**, and **Admins** — with AI-powered tools, a mentor marketplace, skill certificates, and SSLCOMMERZ payment integration.

| Metric | Count |
|--------|-------|
| PHP Pages | 137 |
| Database Tables | 38+ |
| AI Tools | 8 |
| User Roles | 4 |
| API Endpoints | 16 |

---

## Key Features

### Job Seeker
- Browse & search jobs with AI match scoring
- Apply with cover letter + take job-specific quizzes
- Track applications through pipeline stages (Pending → Reviewed → Shortlisted → Rejected)
- Save/bookmark jobs
- Resume Builder with 4 templates
- Live chat with companies
- Skill certificates (verifiable)
- Mentor directory — browse, book, and review sessions
- Job alerts & notifications
- Pro subscription (unlimited AI + applications)

### Company
- Register & manage company profile with logo
- Post jobs with custom quiz questions per job
- View applicants filtered by quiz score, status, pipeline stage
- Schedule interviews (Online / Phone / In-Person)
- AI-powered job description generator
- Talent pool discovery
- Subscription plans (Free / Basic / Professional / Enterprise)

### Mentor
- Mentor profile with category, bio, hourly rate (BDT)
- Set weekly availability slots
- Accept booking sessions (mock interview, resume review, career coaching)
- Track earnings & session history
- 20% platform commission

### Admin
- Dashboard with analytics
- Manage users, companies, mentors
- AI settings (provider, API key, model)
- Email settings (SMTP configuration)
- Revenue tracking
- System notifications

### AI Engine (Hybrid — Works Offline + Optional LLM)
| Tool | Description |
|------|-------------|
| Job Matching | Skill-based candidate-job matching algorithm |
| Resume Analyzer | Scores resume against job requirements |
| Cover Letter Generator | Multi-tone cover letters with skill match scoring |
| Mock Interview | Category-based Q&A with scoring & feedback |
| Grooming Coach | Personalized study plans with videos & quizzes |
| Career Path Explorer | Suggests career progressions from current skills |
| Skill Gap Analyzer | Identifies missing skills for target roles |
| AI Chatbot | Floating widget with 22+ intents & sentiment detection |

> **No API key required** — all AI features have rule-based fallbacks. Optional: OpenAI or Google Gemini for enhanced responses.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 7.4+ (procedural, MySQLi) |
| Database | MySQL 5.7+ |
| Frontend | HTML5, CSS3, Bootstrap 4 |
| Icons | Font Awesome 6 |
| Fonts | Inter, Sora, Manrope, Plus Jakarta Sans |
| JavaScript | jQuery |
| Email | PHPMailer 7.1 (SMTP + mail() fallback) |
| Payments | SSLCOMMERZ (bKash, cards) |
| Auth | Session-based + Google OAuth |
| AI | OpenAI API / Google Gemini (optional) |

---

## Prerequisites

- **XAMPP** (Apache + MySQL + PHP) or any PHP/MySQL environment
- **PHP 7.4** or higher
- **MySQL 5.7** or higher
- **Composer** (for PHPMailer — already installed in `vendor/`)

---

## Installation

### Step 1: Copy Project

Copy the project folder to your XAMPP `htdocs` directory:

```
/Applications/XAMPP/xamppfiles/htdocs/Job-portal-and-grooming/   (macOS)
C:\xampp\htdocs\Job-portal-and-grooming\                         (Windows)
/opt/lampp/htdocs/Job-portal-and-grooming/                        (Linux)
```

### Step 2: Start Services

Start **Apache** and **MySQL** from XAMPP Control Panel.

### Step 3: Create Database

Open **phpMyAdmin** (`http://localhost/phpmyadmin`) and create a database named `projects`:

```sql
CREATE DATABASE projects CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

### Step 4: Import SQL Files

Import in this **exact order** (each builds on the previous):

```bash
# Via MySQL CLI
mysql -u root projects < database/database.sql
mysql -u root projects < database/ai_db.sql
mysql -u root projects < database/features_v3.sql
mysql -u root projects < database/job_categories_v2.sql
```

Or via **phpMyAdmin** → Import tab → select each file one by one.

| Order | File | Purpose |
|-------|------|---------|
| 1 | `database/database.sql` | Core tables (users, companies, jobs, applications) |
| 2 | `database/ai_db.sql` | AI tables (chat history, cover letters, analyses) |
| 3 | `database/features_v3.sql` | Monetization (payments, mentors, certificates) |
| 4 | `database/job_categories_v2.sql` | Extra job categories + quiz data |

### Step 5: Configure Database Connection

Edit `admin/dbcon.php` if your MySQL credentials differ:

```php
$host = '127.0.0.1';
$user = 'root';
$password = '';          // default XAMPP has no password
$database = 'projects';
```

### Step 6: Open in Browser

```
http://localhost/Job-portal-and-grooming/
```

The landing page loads automatically. Register as a job seeker or company to get started.

---

## Default Credentials

### Admin Panel

| Field | Value |
|-------|-------|
| URL | `http://localhost/Job-portal-and-grooming/admin/admin_login.php` |
| Username | `admin` |
| Password | `admin123` |

### Sample Job Seeker

Register at: `http://localhost/Job-portal-and-grooming/auth/registration.php`

### Mentor

Register at: `http://localhost/Job-portal-and-grooming/mentor/register.php`

---

## Project Structure

```
Job-portal-and-grooming/
│
├── index.php                    # Landing page (auto-redirects logged-in users)
│
├── auth/                        # Authentication
│   ├── login.php                #   Multi-role login (seeker/company/admin)
│   ├── registration.php         #   Job seeker registration
│   ├── company_registration.php #   Company registration wizard
│   ├── forgot_password.php      #   Password reset request
│   ├── reset_password.php       #   Password reset form
│   ├── google_callback.php      #   Google OAuth callback
│   └── logout.php               #   Session destroy
│
├── seeker/                      # Job Seeker Portal (35 pages)
│   ├── seeker_dashboard.php     #   Dashboard with stats & recommendations
│   ├── browse_jobs.php          #   Search & filter jobs
│   ├── job_details.php          #   Job detail view
│   ├── application.php          #   Apply to a job
│   ├── my_application.php       #   Track applications
│   ├── saved_jobs.php           #   Bookmarked jobs
│   ├── profile.php              #   Edit profile & upload CV
│   ├── ai_hub.php               #   AI Career Center (hub page)
│   ├── ai_assistant.php         #   AI Chatbot
│   ├── ai_resume_analyzer.php   #   Resume scoring
│   ├── ai_cover_letter_generator.php # Cover letter tool
│   ├── ai_mock_interview.php    #   Mock interview practice
│   ├── ai_grooming_coach.php    #   Study plans & quizzes
│   ├── browse_jobs.php          #   Job browser
│   ├── available_companies.php  #   Company directory
│   ├── live_chat.php            #   Real-time chat with companies
│   ├── message_center.php       #   Direct messaging
│   ├── notifications.php        #   Notification center
│   ├── mentors.php              #   Mentor directory
│   ├── book_session.php         #   Book a mentor session
│   ├── my_sessions.php          #   My booked sessions
│   ├── certificates.php         #   Skill certificates
│   ├── grooming.php             #   Category study materials
│   ├── quiz.php                 #   Skill quizzes
│   ├── recommendations.php      #   AI job recommendations
│   ├── skill_gap.php            #   Skill gap analysis
│   ├── career_path.php          #   Career path explorer
│   ├── resume_builder.php       #   Resume builder (4 templates)
│   ├── resume_preview.php       #   Resume preview/download
│   ├── view_cv.php              #   CV viewer
│   ├── pro.php                  #   NovaHire Pro subscription
│   ├── job_alerts.php           #   Job alert preferences
│   ├── company_reviews.php      #   Company reviews
│   ├── company_job_application.php # Direct company application
│   ├── company_job_quiz.php     #   Company-specific quiz
│   └── video_interview.php      #   Video interview
│
├── company/                     # Company Portal (18 pages)
│   ├── index.php                #   Company dashboard
│   ├── post_job.php             #   Create job posting
│   ├── my_jobs.php              #   Manage job listings
│   ├── manage_quiz.php          #   Create quiz questions per job
│   ├── view_applicants.php      #   View & filter applicants
│   ├── view_applicant_detail.php #  Applicant profile + quiz results
│   ├── category_applicants.php  #   Applicants by category
│   ├── update_application_status.php # Update application stage
│   ├── schedule_interview.php   #   Schedule interview
│   ├── profile.php              #   Company profile settings
│   ├── talent_pool.php          #   Discover candidates
│   ├── subscription.php         #   Subscription plans
│   ├── live_chat.php            #   Chat with seekers
│   ├── message_center.php       #   Direct messaging
│   ├── notifications.php        #   Notifications
│   ├── company_header.php       #   Shared navigation
│   ├── get_application_details.php # AJAX application details
│   └── logout.php               #   Session destroy
│
├── mentor/                      # Mentor Portal (8 pages)
│   ├── index.php                #   Mentor dashboard
│   ├── register.php             #   Mentor registration
│   ├── login.php                #   Mentor login
│   ├── availability.php         #   Set weekly slots
│   ├── sessions.php             #   Manage bookings
│   ├── mentor_header.php        #   Shared navigation
│   ├── mentor_footer.php        #   Shared footer
│   └── logout.php               #   Session destroy
│
├── admin/                       # Admin Portal (22 pages)
│   ├── admin_login.php          #   Admin login
│   ├── admin_dashboard.php      #   Dashboard with stats
│   ├── show_users.php           #   List all users
│   ├── showdata.php             #   Company listing
│   ├── update_user.php          #   Edit user
│   ├── update_company.php       #   Edit company
│   ├── update_application.php   #   Edit application
│   ├── delete_user.php          #   Remove user
│   ├── delete_application.php   #   Remove application
│   ├── add_admin.php            #   Create admin account
│   ├── add_details.php          #   Add system data
│   ├── view_cv.php              #   View user CV
│   ├── mentors.php              #   Manage mentors
│   ├── ai_settings.php          #   AI provider config
│   ├── email_settings.php       #   SMTP config
│   ├── revenue.php              #   Revenue analytics
│   ├── notifications.php        #   System notifications
│   ├── toggle_company_status.php # Approve/suspend companies
│   ├── dbcon.php                #   Database connection config
│   ├── header.php               #   Shared navigation
│   ├── index.php                #   Redirect to dashboard
│   └── logout.php               #   Session destroy
│
├── ai/                          # AI Engine
│   ├── config.php               #   Settings loader (DB + fallbacks)
│   ├── engine.php               #   Core LLM abstraction (OpenAI/Gemini/offline)
│   ├── matching.php             #   Job-candidate matching
│   ├── resume.php               #   Resume analysis
│   ├── cover_letter.php         #   Cover letter generation
│   ├── interview.php            #   Mock interview
│   ├── grooming.php             #   Grooming coach
│   ├── chatbot.php              #   Chatbot engine
│   ├── helpers.php              #   UI helpers (chat widget, nav, badges)
│   └── assets/                  #   AI-specific CSS/JS
│       ├── css/ai.css
│       └── js/chat.js
│
├── api/                         # AJAX JSON Endpoints (16 files)
│   ├── ai_chat.php              #   Chatbot API
│   ├── ai_generate_jd.php       #   Job description generator
│   ├── chat_send.php            #   Send chat message
│   ├── chat_poll.php            #   Poll for new messages
│   ├── chat_conversations.php   #   List conversations
│   ├── toggle_save_job.php      #   Save/unsave job
│   ├── check_saved_job.php      #   Check saved status
│   ├── checkout.php             #   Payment checkout
│   ├── initiate_payment.php     #   Start SSLCOMMERZ payment
│   ├── payment_success.php      #   Payment success handler
│   ├── payment_fail.php         #   Payment failure handler
│   ├── payment_cancel.php       #   Payment cancel handler
│   ├── get_notification_count.php # Unread notification count
│   ├── mark_notification_read.php # Mark single notification read
│   ├── mark_all_read.php        #   Mark all notifications read
│   └── live_chat_alerts.php     #   Live chat polling
│
├── includes/                    # Shared Libraries (18 files)
│   ├── bootstrap.php            #   Core init (session, DB, BASE_URL, guards)
│   ├── security.php             #   CSRF, rate limiting, security headers
│   ├── functions.php            #   Helper functions (notifications, etc.)
│   ├── links.php                #   CSS/JS asset includes
│   ├── header.php               #   Seeker navbar (glass nav, panels)
│   ├── mail.php                 #   PHPMailer SMTP email system
│   ├── payment.php              #   SSLCOMMERZ company subscriptions
│   ├── monetization.php         #   Pricing, Pro plan, fulfillment
│   ├── premium.php              #   Feature gating + usage tracking
│   ├── placement.php            #   Recommendation engine + pipeline
│   ├── sessions.php             #   Mentor marketplace logic
│   ├── certificates.php         #   Skill certificate system
│   ├── resume_builder.php       #   4-template resume generator
│   ├── search.php               #   Advanced job search
│   ├── google_auth.php          #   Google OAuth integration
│   ├── job_alerts.php           #   Job alert system
│   ├── reviews.php              #   Company reviews
│   └── verify_certificate.php   #   Public certificate verification
│
├── database/                    # SQL Schemas
│   ├── database.sql             #   Core tables (~25 tables)
│   ├── ai_db.sql                #   AI tables (6 tables)
│   ├── features_v3.sql          #   Monetization tables (10 tables)
│   └── job_categories_v2.sql    #   Extra categories + quiz data
│
├── assets/                      # Global Frontend Assets
│   ├── css/style.css            #   Master stylesheet (CSS variables)
│   ├── css/premium.css          #   Premium feature styles
│   └── js/script.js             #   Global JavaScript
│
├── public/                      # PWA Assets
│   ├── manifest.json            #   PWA manifest
│   └── sw.js                    #   Service worker
│
├── images/                      # Static Images
├── uploads/                     # User Uploads
│   └── company_logos/           #   Company logo uploads
├── vendor/                      # Composer (PHPMailer)
├── composer.json                #   Dependencies
├── .htaccess                    #   URL rewriting
├── .gitignore                   #   Git ignore rules
└── README.md                    #   This file
```

---

## Database Schema

The database contains **38+ tables** across 4 SQL files. Key table groups:

### Core Tables (`database.sql`)
| Table | Purpose |
|-------|---------|
| `user_info` | Job seeker accounts |
| `companies` | Company accounts |
| `admin_login` | Admin accounts |
| `company_jobs` | Job postings |
| `job_applications` | Applications with pipeline stages |
| `company_job_questions` | Per-job quiz questions |
| `interviews` | Scheduled interviews |
| `notifications` | System notifications |
| `messages` | Direct messaging |
| `live_chats` | Real-time chat |
| `saved_jobs` | Bookmarked jobs |
| `quiz_questions` | Grooming quiz bank |
| `grooming_content` | Study materials |
| `grooming_videos` | Video lessons |

### AI Tables (`ai_db.sql`)
| Table | Purpose |
|-------|---------|
| `ai_settings` | Provider config (API keys, model) |
| `ai_chat_history` | Chatbot conversation log |
| `ai_cover_letters` | Generated cover letters |
| `ai_resume_analyses` | Resume analysis results |
| `ai_mock_interviews` | Mock interview attempts |
| `ai_recommendations` | Job match scores |

### Monetization Tables (`features_v3.sql`)
| Table | Purpose |
|-------|---------|
| `payments` | Payment ledger (BDT) |
| `company_subscriptions` | Company plans |
| `user_subscriptions` | NovaHire Pro subscriptions |
| `mentors` | Mentor accounts |
| `mentor_slots` | Bookable availability |
| `grooming_sessions` | Paid 1-on-1 bookings |
| `session_reviews` | Mentor reviews |
| `mentor_earnings` | Payout ledger |
| `certificates` | Verifiable skill certificates |
| `placements` | Confirmed hires |

---

## AI System

### How It Works

NovaHire uses a **hybrid AI engine** (`ai/engine.php`):

1. **Offline Mode (Default)** — All AI features work immediately with no configuration. Uses rule-based algorithms, keyword matching, and template generation.

2. **Online Mode (Optional)** — Connect OpenAI or Google Gemini for enhanced, conversational responses. Configure in Admin → AI Settings.

### Enable LLM (Optional)

1. Login to Admin Panel
2. Navigate to **AI Settings**
3. Select provider: **OpenAI** or **Google Gemini**
4. Enter your API key
5. Select model (default: `gpt-3.5-turbo` or `gemini-1.5-flash`)
6. Toggle **LLM Enabled** on
7. Save

> Without an API key, all 8 AI tools continue to work using offline fallbacks.

---

## Payment & Monetization

### NovaHire Pro (Job Seekers)
| Feature | Free | Pro (৳499/month) |
|---------|------|-------------------|
| Job Applications | 10/month | Unlimited |
| AI Resume Analyzer | Locked | Unlimited |
| AI Mock Interview | Locked | Unlimited |
| AI Cover Letter | Locked | Unlimited |
| AI Chatbot | Locked | Unlimited |
| Skill Gap Analyzer | Locked | Unlimited |
| Career Path Explorer | Locked | Unlimited |
| Resume Builder | Locked | Unlimited |
| Certificates | ৳299 each | Free |
| Pro Badge | No | Yes |

### Company Subscriptions
| Plan | Price (BDT) | Duration | Job Posts |
|------|-------------|----------|-----------|
| Free | 0 | 30 days | 2 |
| Basic | ৳2,999 | 30 days | 10 |
| Professional | ৳7,999 | 30 days | 50 |
| Enterprise | ৳19,999 | 365 days | Unlimited |

### Other Revenue
| Item | Price |
|------|-------|
| Featured Job Boost | ৳1,499 (14 days) |
| Verified Certificate | ৳299 |
| Placement Fee | ৳9,999 flat or 8% of salary |
| Mentor Session Commission | 20% platform cut |

Payment Gateway: **SSLCOMMERZ** (bKash, cards, net banking)

---

## URL Routes

### Public
| Page | URL |
|------|-----|
| Landing Page | `/index.php` |
| Browse Jobs (public) | `/seeker/browse_jobs.php` |
| Company Registration | `/auth/company_registration.php` |
| Certificate Verification | `/includes/verify_certificate.php?code=XXX` |

### Job Seeker
| Page | URL |
|------|-----|
| Login | `/auth/login.php` |
| Register | `/auth/registration.php` |
| Dashboard | `/seeker/seeker_dashboard.php` |
| Browse Jobs | `/seeker/browse_jobs.php` |
| AI Career Center | `/seeker/ai_hub.php` |
| Profile | `/seeker/profile.php` |
| My Applications | `/seeker/my_application.php` |
| Pro Subscription | `/seeker/pro.php` |

### Company
| Page | URL |
|------|-----|
| Dashboard | `/company/index.php` |
| Post Job | `/company/post_job.php` |
| My Jobs | `/company/my_jobs.php` |
| View Applicants | `/company/view_applicants.php` |
| Subscription | `/company/subscription.php` |

### Mentor
| Page | URL |
|------|-----|
| Login | `/mentor/login.php` |
| Register | `/mentor/register.php` |
| Dashboard | `/mentor/index.php` |
| Availability | `/mentor/availability.php` |
| Sessions | `/mentor/sessions.php` |

### Admin
| Page | URL |
|------|-----|
| Login | `/admin/admin_login.php` |
| Dashboard | `/admin/admin_dashboard.php` |
| AI Settings | `/admin/ai_settings.php` |
| Email Settings | `/admin/email_settings.php` |
| Revenue | `/admin/revenue.php` |

---

## Configuration

### Database (`admin/dbcon.php`)
```php
$host = '127.0.0.1';
$user = 'root';
$password = '';
$database = 'projects';
```

### Email (SMTP)
Configure in **Admin → Email Settings** or via `site_settings` DB table:

| Setting | Default |
|---------|---------|
| SMTP Host | `smtp.gmail.com` |
| SMTP Port | `587` |
| Encryption | TLS |
| From Email | Configurable |
| From Name | NovaHire |

> Falls back to PHP `mail()` if SMTP is unavailable.

### AI Provider
Configure in **Admin → AI Settings** or via `ai_settings` DB table:

| Setting | Default |
|---------|---------|
| Provider | `openai` |
| OpenAI Model | `gpt-3.5-turbo` |
| Gemini Model | `gemini-1.5-flash` |
| LLM Enabled | `0` (offline) |
| Timeout | `30` seconds |

### Google OAuth (`includes/google_auth.php`)
```php
define('GOOGLE_CLIENT_ID', 'your-client-id');
define('GOOGLE_CLIENT_SECRET', 'your-client-secret');
define('GOOGLE_REDIRECT_URI', BASE_URL . '/auth/google_callback.php');
```

### Currency
All prices are in **BDT (Bangladeshi Taka)** — ৳ symbol.

---

## Troubleshooting

### "Connection Unsuccessful" or "Access Denied"
- Verify MySQL is running in XAMPP
- Check `admin/dbcon.php` credentials match your MySQL setup
- Ensure database `projects` exists

### "Table doesn't exist"
- Import SQL files in order: `database.sql` → `ai_db.sql` → `features_v3.sql` → `job_categories_v2.sql`
- Check for import errors in phpMyAdmin

### "Page Not Found" (404)
- Verify Apache document root points to `htdocs`
- Check `.htaccess` is present and `RewriteEngine` is enabled
- Ensure the project folder is named exactly `Job-portal-and-grooming`

### Session Errors
- Ensure PHP sessions are enabled in `php.ini`
- Check `session.save_path` is writable

### File Upload Fails
- Ensure `uploads/` directory exists and is writable (chmod 755)
- Check `php.ini` for `upload_max_filesize` and `post_max_size`

### AI Features Not Working
- AI works offline by default — no configuration needed
- For enhanced responses: Admin → AI Settings → enter API key → enable LLM
- Check `ai_settings` table has `llm_enabled = 1`

### Email Not Sending
- Check Admin → Email Settings for SMTP configuration
- For Gmail: use App Password (not regular password)
- Falls back to PHP `mail()` automatically

### Composer / PHPMailer Error
- `vendor/` directory is pre-installed. If missing, run:
  ```bash
  composer install
  ```

---

## License

This project is for educational purposes.
