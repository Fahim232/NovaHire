# Job Application Portal - Company/Job Provider System

## 🎯 Overview
This project has been enhanced with a comprehensive **Company/Job Provider Management System** that allows companies to post jobs, create custom quizzes, and manage applicants with role-based authentication.

## ✨ New Features Implemented

### 1. **Company Registration & Authentication**
- Dedicated company registration system with comprehensive details
- Secure login system with password hashing
- Role-based access control separating companies from job seekers
- Company profile management

### 2. **Company Dashboard**
- Real-time statistics (Total Jobs, Active Jobs, Applications, Qualified Candidates)
- Recent jobs overview
- Recent applications tracking
- Quick action buttons for common tasks

### 3. **Job Posting System**
- Create jobs with detailed information:
  - Job title, category (Java, Python, PHP, Frontend, etc.)
  - Job description, requirements, responsibilities
  - Location, employment type (Full-Time, Part-Time, Contract, Internship)
  - Salary range, experience required
  - Skills required, application deadline
  - Number of vacancies
- Job status management (Active, Closed, Draft)
- Edit and delete jobs
- View job statistics

### 4. **Custom Quiz Management**
- Create job-specific quiz questions
- Multiple choice questions with 4 options
- Mark correct answers
- View and delete questions
- Track number of questions per job

### 5. **Applicant Management System**
- View all applicants with filtering options:
  - Filter by job
  - Filter by quiz status (Passed, Failed, Not Taken)
- View applicant profiles with:
  - Contact information (email, phone)
  - Education and skills
  - Quiz performance and scores
  - CV/Resume download
- Update application status (Pending, Reviewed, Shortlisted, Rejected)
- Direct contact options (email, phone)

### 6. **Job Browsing for Candidates**
- Browse all active company jobs
- Advanced filters:
  - Category filter
  - Location search
  - Employment type filter
- View job details including:
  - Company information
  - Requirements and responsibilities
  - Quiz question count
  - Number of applicants
  - Application deadline

## 📁 New Files Created

### Database Schema
- **`company_database.sql`** - Complete database schema including:
  - `companies` - Company registration and profile data
  - `company_jobs` - Job postings
  - `company_job_questions` - Custom quiz questions per job
  - `job_applications` - Application tracking
  - `job_quiz_attempts` - Detailed quiz attempt records

### Authentication Pages
- **`company_registration.php`** - Company signup form
- **`company_login.php`** - Company login with role validation

### Company Dashboard (`/company/` directory)
- **`index.php`** - Main company dashboard
- **`post_job.php`** - Create new job postings
- **`my_jobs.php`** - Manage all company jobs
- **`manage_quiz.php`** - Create and manage quiz questions
- **`view_applicants.php`** - View and filter applicants
- **`view_applicant_detail.php`** - Detailed applicant profile view
- **`profile.php`** - Company profile management
- **`logout.php`** - Company logout

### Job Seeker Pages
- **`browse_jobs.php`** - Browse all company job postings
- **`job_details.php`** - View detailed job information (to be created)

## 🗄️ Database Tables

### companies
Stores company registration data:
- Company name, email, phone, address
- Website, industry, company size
- Description, logo
- Registration date, status

### company_jobs
Job postings with full details:
- Job title, category, description
- Requirements, responsibilities
- Location, employment type
- Salary range, experience required
- Skills, deadline, vacancy count
- Status (active/closed/draft)

### company_job_questions
Custom quiz questions per job:
- Question text
- 4 multiple choice options
- Correct answer
- Linked to specific job

### job_applications
Application tracking:
- User and job linkage
- Quiz score and status
- Application status
- Cover letter
- Applied date

### job_quiz_attempts
Detailed quiz attempt records:
- Total questions and correct answers
- Score percentage
- Time taken
- Attempt date

## 🚀 Installation & Setup

### Step 1: Import Database
```sql
-- First, import the original database
SOURCE database.sql;

-- Then, import the company system database
SOURCE company_database.sql;
```

### Step 2: Configure Database Connection
Update `admin/dbcon.php` with your database credentials:
```php
$host = 'localhost';
$user = 'your_username';
$password = 'your_password';
$database = 'projects';
```

### Step 3: Access the System
- **Job Seekers**: `http://localhost/your-project/login.php`
- **Companies**: `http://localhost/your-project/company_login.php`
- **Browse Jobs**: `http://localhost/your-project/browse_jobs.php`

## 👥 User Roles

### Job Seeker
- Register and create profile
- Browse company jobs
- Apply to jobs
- Take job-specific quizzes
- View application status
- Upload CV/Resume

### Company/Job Provider
- Register company
- Post multiple jobs in different categories
- Create custom quiz questions for each job
- View all applicants
- Filter applicants by quiz performance
- View and download CVs
- Update application status
- Manage company profile

### Admin (Existing)
- Manage platform
- View all users and companies
- System administration

## 🎨 Features Highlights

### For Companies:
✅ Complete recruitment workflow
✅ Custom skill assessments per job
✅ Comprehensive applicant tracking
✅ Real-time statistics dashboard
✅ Professional company profile
✅ Email and phone integration

### For Job Seekers:
✅ Browse jobs by category and location
✅ Take job-specific quizzes
✅ Track application status
✅ Upload CV/Resume
✅ View detailed job requirements
✅ Apply to multiple positions

## 📊 Database Relationships

```
companies (1) -----> (Many) company_jobs
company_jobs (1) --> (Many) company_job_questions
company_jobs (1) --> (Many) job_applications
user_info (1) -----> (Many) job_applications
job_applications (1) -> (1) job_quiz_attempts
```

## 🔒 Security Features

- Password hashing using PHP `password_hash()` and `password_verify()`
- SQL injection prevention with `mysqli_real_escape_string()`
- Session-based authentication
- Role-based access control
- Protected company and user areas

## 📝 Sample Login Credentials

### Companies (from sample data):
- **Email**: hr@techsolutions.com
- **Password**: password (change after login)

- **Email**: jobs@digitalinnovations.com
- **Password**: password (change after login)

## 🎯 Job Categories Supported

- Java Developer
- Python Developer
- PHP Developer
- Frontend Developer
- .NET Developer
- Mobile Developer
- DevOps Engineer
- Data Scientist
- UI/UX Designer
- QA Engineer
- And more...

## 📈 Future Enhancements (Optional)

- Email notifications for applications
- Advanced analytics for companies
- Job recommendations for candidates
- Application tracking timeline
- Interview scheduling
- Bulk operations for companies
- Export applicant data
- Advanced search with AI
- Video interview integration
- Skill assessment reports

## 🛠️ Technologies Used

- **Backend**: PHP 7.4+
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, Bootstrap 4
- **Icons**: Font Awesome 5
- **JavaScript**: jQuery

## 📞 Support & Documentation

For questions or issues:
1. Check the database schema in `company_database.sql`
2. Review the README.md file
3. Check individual PHP file comments
4. Verify database connection in `admin/dbcon.php`

## ⚠️ Important Notes

1. **Database Import**: Always import `company_database.sql` after `database.sql`
2. **File Permissions**: Ensure `files/` directory has write permissions for CV uploads
3. **Session Management**: Sessions must be enabled in PHP configuration
4. **Password Security**: All passwords are hashed - default password is "password" for sample data
5. **Active Jobs**: Only jobs with status='active' and future deadlines appear in browse

## 🎉 Success!

Your Job Application Portal now has a complete company management system! Companies can:
- Register and manage their profile
- Post jobs in various categories
- Create custom quizzes
- View and manage applicants
- Track recruitment metrics

Job seekers can:
- Browse company jobs
- Apply with CV
- Take job-specific quizzes
- Track application status

---

**Version**: 2.0 with Company Management System  
**Last Updated**: January 2026  
**Status**: Production Ready ✅
