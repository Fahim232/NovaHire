# 🚀 Quick Installation Guide

## Step-by-Step Setup Instructions

### 1️⃣ Database Setup

#### Import Databases (In Order):
1. Open phpMyAdmin or MySQL Workbench
2. Create a database named `projects` (if not exists)
3. Import the databases in this exact order:
   ```sql
   -- First import original database
   database.sql
   
   -- Then import company system
   company_database.sql
   ```

#### Using Command Line:
```bash
mysql -u root -p projects < database.sql
mysql -u root -p projects < company_database.sql
```

### 2️⃣ Configure Database Connection

Edit `admin/dbcon.php`:
```php
<?php
    $host = 'localhost';        // Usually 'localhost'
    $user = 'root';             // Your MySQL username
    $password = 'your_password'; // Your MySQL password
    $database = 'projects';     // Database name
    
    $con = mysqli_connect($host, $user, $password, $database);
?>
```

### 3️⃣ Set File Permissions

Ensure the files directory has write permissions:
```bash
chmod 755 files/
```

### 4️⃣ Access the Application

#### Main Pages:
- **Homepage**: `http://localhost/Job-Application-Portal-master/index.php`
- **Job Seeker Login**: `http://localhost/Job-Application-Portal-master/login.php`
- **Job Seeker Register**: `http://localhost/Job-Application-Portal-master/registration.php`
- **Company Login**: `http://localhost/Job-Application-Portal-master/company_login.php`
- **Company Register**: `http://localhost/Job-Application-Portal-master/company_registration.php`
- **Browse Jobs**: `http://localhost/Job-Application-Portal-master/browse_jobs.php`
- **Admin Panel**: `http://localhost/Job-Application-Portal-master/admin/admin_login.php`

### 5️⃣ Test Accounts

#### Sample Company Accounts:
**Company 1:**
- Email: `hr@techsolutions.com`
- Password: `password` (change after first login)

**Company 2:**
- Email: `jobs@digitalinnovations.com`
- Password: `password` (change after first login)

#### Admin Account:
- Username: `admin`
- Password: `admin123`

#### Job Seeker:
- Create your own account via registration

## 📋 Verification Checklist

After installation, verify:

- [ ] Can access homepage
- [ ] Job seeker can register
- [ ] Job seeker can login
- [ ] Company can register
- [ ] Company can login
- [ ] Company can post jobs
- [ ] Job seeker can browse jobs
- [ ] Database tables are created properly

## 🔧 Troubleshooting

### Issue: "Connection Unsuccessful"
**Solution**: Check `admin/dbcon.php` credentials

### Issue: "Table doesn't exist"
**Solution**: Import both SQL files in correct order

### Issue: "Cannot upload CV"
**Solution**: Check `files/` directory permissions (755 or 777)

### Issue: "Session errors"
**Solution**: Ensure PHP sessions are enabled in php.ini

### Issue: "Page not found"
**Solution**: Check your Apache document root and virtual host configuration

## 🗄️ Database Tables Created

### Original Tables:
- `admin_login`
- `jobregistration`
- `quiz_questions`
- `user_info`
- `user_quiz_status`
- `grooming_content`
- `grooming_videos`
- `user_video_progress`

### New Company System Tables:
- `companies` - Company profiles
- `company_jobs` - Job postings
- `company_job_questions` - Custom quiz questions
- `job_applications` - Application tracking
- `job_quiz_attempts` - Quiz results

## 🎯 Quick Test Workflow

### For Companies:
1. Register a new company at `company_registration.php`
2. Login at `company_login.php`
3. Post a job from dashboard
4. Add quiz questions for the job
5. View applicants (will be empty initially)

### For Job Seekers:
1. Register at `registration.php`
2. Login at `login.php`
3. Browse jobs at `browse_jobs.php`
4. Apply to a job
5. Take the quiz
6. Check application status

## 📁 Important Directories

```
Job-Application-Portal-master/
├── admin/                  # Admin panel
│   └── dbcon.php          # Database config (EDIT THIS!)
├── company/               # Company dashboard (NEW)
│   ├── index.php
│   ├── post_job.php
│   ├── my_jobs.php
│   ├── manage_quiz.php
│   ├── view_applicants.php
│   ├── view_applicant_detail.php
│   └── profile.php
├── files/                 # CV uploads (set permissions!)
├── company_login.php      # Company login (NEW)
├── company_registration.php # Company signup (NEW)
├── browse_jobs.php        # Browse jobs (NEW)
├── database.sql           # Original database
└── company_database.sql   # Company system database (NEW)
```

## 🌐 URL Structure

### Public Access:
- Homepage: `/index.php`
- Browse Jobs: `/browse_jobs.php`

### Job Seekers:
- Login: `/login.php`
- Register: `/registration.php`
- Profile: `/profile.php`
- Applications: `/my_application.php`

### Companies:
- Login: `/company_login.php`
- Register: `/company_registration.php`
- Dashboard: `/company/index.php`
- Post Job: `/company/post_job.php`
- Manage Jobs: `/company/my_jobs.php`
- View Applicants: `/company/view_applicants.php`

### Admin:
- Login: `/admin/admin_login.php`
- Dashboard: `/admin/index.php`

## ✅ Success Indicators

You'll know the installation is successful when:

1. ✅ Homepage loads without errors
2. ✅ Company can register and login
3. ✅ Company can post jobs
4. ✅ Jobs appear in browse_jobs.php
5. ✅ Job seekers can apply
6. ✅ Company can see applicants
7. ✅ No MySQL connection errors
8. ✅ All statistics show on dashboards

## 🆘 Need Help?

1. Check `COMPANY_SYSTEM_README.md` for detailed documentation
2. Verify all SQL files are imported
3. Check PHP error logs
4. Ensure MySQL service is running
5. Verify Apache/XAMPP is running

## 🎉 You're All Set!

Once everything is working:
1. Change default passwords
2. Configure email settings (if needed)
3. Customize company categories
4. Add more quiz questions
5. Update styling to match your brand

---

**Happy Recruiting! 🎊**
