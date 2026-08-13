# NovaHire - Job Application Portal

A modern job application portal with AI-powered features, built with PHP and MySQL. Features a complete recruitment system for companies, job seekers, and administrators.

## Features

**For Job Seekers**
- Browse and search jobs with AI match scoring
- Apply to jobs with CV upload and cover letter
- Take job-specific skill assessments (quizzes)
- Track application status in real-time
- AI Career Center: resume analyzer, cover letter generator, mock interviews
- Live chat with companies

**For Companies**
- Register and manage company profile with logo
- Post jobs with custom quiz questions
- View and filter applicants by quiz performance
- Download CVs and update application status
- AI-powered job description generator
- Real-time recruitment statistics dashboard

**For Admin**
- Manage users, companies, and applications
- Configure AI engine settings (OpenAI/Gemini)
- System-wide notifications
- Dashboard with analytics

**AI Features (Hybrid - works offline + optional LLM)**
- Job-candidate matching algorithm
- Resume/CV scoring and analysis
- Cover letter generation
- Mock interview practice
- Grooming coach with personalized study plans
- AI chat assistant (floating widget)

## Tech Stack

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Bootstrap 4, Font Awesome 6
- **JavaScript**: jQuery
- **AI**: OpenAI / Google Gemini (optional, rule-based fallback included)

## Prerequisites

- XAMPP (or any PHP/MySQL environment)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser

## Installation

### 1. Clone or Download

Copy the project to your XAMPP `htdocs` directory:

```
C:\xampp\htdocs\Job-portal-and-grooming\
```

### 2. Import Database

Open **phpMyAdmin** (`http://localhost/phpmyadmin`) and import the SQL files in this order:

```bash
# Via MySQL CLI
mysql -u root -p projects < database.sql
mysql -u root -p projects < ai_db.sql
mysql -u root -p projects < job_categories_v2.sql
```

Or via phpMyAdmin:
1. Create database named `projects`
2. Import `database.sql` first
3. Import `ai_db.sql` second
4. Import `job_categories_v2.sql` third

### 3. Configure Database Connection

Edit `admin/dbcon.php`:

```php
$host = 'localhost';
$user = 'root';          // Your MySQL username
$password = '';           // Your MySQL password
$database = 'projects';  // Database name
```

### 4. Set File Permissions

Ensure the `files/` and `uploads/` directories are writable:

```bash
chmod 755 files/
chmod 755 uploads/
chmod 755 uploads/company_logos/
```

### 5. Start the Application

1. Start **Apache** and **MySQL** in XAMPP
2. Open browser and navigate to:

```
http://localhost/Job-portal-and-grooming/index.php
```

## Default Credentials

### Admin
- **URL**: `http://localhost/Job-portal-and-grooming/admin/admin_login.php`
- **Username**: `admin`
- **Password**: `admin123`

### Sample Companies
- **Email**: `hr@techsolutions.com` / **Password**: `password`
- **Email**: `jobs@digitalinnovations.com` / **Password**: `password`

### Job Seeker
Register a new account at `http://localhost/Job-portal-and-grooming/auth/registration.php`

## Project Structure

```
Job-portal-and-grooming/
├── index.php                 # Landing page
├── landing.php               # Public welcome page
├── auth/                     # Authentication (login, register, forgot password)
├── seeker/                   # Job seeker portal
│   ├── seeker_dashboard.php  # Dashboard
│   ├── browse_jobs.php       # Browse all jobs
│   ├── job_details.php       # Job detail view
│   ├── profile.php           # User profile & CV
│   ├── my_application.php    # Application tracking
│   ├── ai_hub.php            # AI Career Center
│   └── ...                   # Other seeker pages
├── company/                  # Company portal
│   ├── index.php             # Company dashboard
│   ├── post_job.php          # Create job posting
│   ├── my_jobs.php           # Manage jobs
│   ├── manage_quiz.php       # Create quiz questions
│   ├── view_applicants.php   # View applicants
│   └── profile.php           # Company profile
├── admin/                    # Admin portal
│   ├── dbcon.php             # Database connection (EDIT THIS)
│   ├── admin_dashboard.php   # Admin dashboard
│   └── ai_settings.php       # AI configuration
├── ai/                       # AI engine (offline + LLM)
├── api/                      # AJAX endpoints (JSON)
├── includes/                 # Shared code (bootstrap, headers, functions)
├── assets/                   # CSS, JS files
├── files/                    # CV uploads
├── uploads/                  # Company logos, images
├── database.sql              # Main database schema
├── ai_db.sql                 # AI tables schema
└── job_categories_v2.sql     # Job categories
```

## Key URLs

| Page | URL |
|------|-----|
| Homepage | `/index.php` |
| Browse Jobs | `/seeker/browse_jobs.php` |
| Seeker Login | `/auth/login.php` |
| Seeker Register | `/auth/registration.php` |
| Company Login | `/company_login.php` |
| Company Register | `/company_registration.php` |
| Company Dashboard | `/company/index.php` |
| Admin Login | `/admin/admin_login.php` |
| Admin Dashboard | `/admin/admin_dashboard.php` |
| AI Career Center | `/seeker/ai_hub.php` |

## AI Configuration (Optional)

The AI engine works offline by default. To enable live AI responses:

1. Import `ai_db.sql` into your database
2. Login to Admin Panel
3. Go to **AI Settings**
4. Select provider (OpenAI or Gemini)
5. Enter your API key and select model
6. Click **Save & Test Connection**

Without an API key, all AI features use rule-based fallbacks.

## Troubleshooting

**"Connection Unsuccessful"**
- Check `admin/dbcon.php` credentials

**"Table doesn't exist"**
- Import all SQL files in correct order: `database.sql` → `ai_db.sql` → `job_categories_v2.sql`

**"Cannot upload CV"**
- Check `files/` directory permissions (755 or 777)

**"Session errors"**
- Ensure PHP sessions are enabled in `php.ini`

**"Page not found"**
- Verify Apache document root points to `htdocs`
- Check project folder name matches URL

**AI features not working**
- Run `ai_db.sql` import
- Check Admin → AI Settings for API key configuration
- Offline features work without any configuration

## License

This project is for educational purposes.
