# Default Category Application System - Implementation Guide

## 📋 Overview
This feature allows job seekers to apply directly to companies based on **category qualifications** (passing quiz) instead of specific job postings. Companies can view all applicants who have passed category quizzes and applied to them.

## 🎯 Key Features

### For Job Seekers:
- ✅ Pass category quiz (PHP, Java, Python, etc.)
- ✅ View all registered companies
- ✅ Apply to any company in qualified categories
- ✅ Submit cover message with application
- ✅ Track application status (Pending/Interview/Approved/Rejected)
- ✅ One application per company per category

### For Companies:
- ✅ View all category-based applications
- ✅ Filter by category and status
- ✅ Search applicants by name/email
- ✅ View applicant details and quiz scores
- ✅ Update application status
- ✅ Add internal notes about applicants
- ✅ Statistics dashboard (Total/Pending/Interview/Approved/Rejected)

## 🗂️ Database Structure

### Table: `category_applications`
```sql
CREATE TABLE IF NOT EXISTS category_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT NOT NULL,
    category VARCHAR(100) NOT NULL,
    application_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending', 'Approved', 'Rejected', 'Interview') DEFAULT 'Pending',
    company_notes TEXT DEFAULT NULL,
    user_message TEXT DEFAULT NULL,
    interview_date DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES user_info(user_id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_application (user_id, company_id, category),
    
    INDEX idx_user_id (user_id),
    INDEX idx_company_id (company_id),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_application_date (application_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 📁 Files Created

### Job Seeker Side:
1. **`available_companies.php`**
   - Lists all companies
   - Filters by passed quiz categories
   - Apply modal with cover message
   - Shows already applied status
   - Responsive design with category badges

### Company Side:
1. **`company/category_applicants.php`**
   - Main applicants management page
   - Statistics cards (Total/Pending/Interview/Approved/Rejected)
   - Filter by category/status/search
   - Table view with applicant details
   - Modal for detailed view

2. **`company/get_application_details.php`**
   - AJAX endpoint for loading applicant details
   - Shows user profile, quiz scores
   - Status update form
   - Company notes textarea
   - Link to view full CV

### Database Migration:
1. **`add_category_applications.sql`**
   - Table creation script
   - Proper indexes and foreign keys
   - Unique constraint to prevent duplicate applications

## 🚀 Installation Steps

### 1. Import Database Table
```bash
# Method 1: Via phpMyAdmin
- Open phpMyAdmin
- Select your database
- Click "Import" tab
- Choose file: add_category_applications.sql
- Click "Go"

# Method 2: Via MySQL CLI
mysql -u root -p your_database_name < add_category_applications.sql
```

### 2. Verify Files
Ensure these files exist:
- ✅ `available_companies.php` (root)
- ✅ `company/category_applicants.php`
- ✅ `company/get_application_details.php`
- ✅ `add_category_applications.sql`

### 3. Navigation Links Updated
- ✅ **User Header**: Added "Companies" link
- ✅ **Company Navigation**: Added "Category Applicants" link in all pages:
  - index.php
  - my_jobs.php
  - post_job.php
  - profile.php
  - view_applicants.php
  - view_applicant_detail.php
  - manage_quiz.php

## 🎮 How to Use

### For Job Seekers:

1. **Pass a Quiz**
   - Go to homepage and select a category (PHP, Java, Python, etc.)
   - Take the quiz and pass it

2. **View Available Companies**
   - Click "Companies" in navigation menu
   - OR visit: `available_companies.php`
   - Select category tab (only shows categories where you passed)

3. **Apply to Company**
   - Click "Apply Now" button
   - Write optional cover message
   - Submit application

4. **Track Application Status**
   - Returns to same page showing "Applied - Status: Pending/Interview/Approved/Rejected"
   - Status updates visible immediately

### For Companies:

1. **Access Category Applicants**
   - Login to company dashboard
   - Click "Category Applicants" in navigation
   - OR visit: `company/category_applicants.php`

2. **View Statistics**
   - Dashboard shows:
     - Total Applications
     - Pending count
     - Interview scheduled
     - Approved
     - Rejected

3. **Filter Applicants**
   - Select category dropdown (PHP, Java, etc.)
   - Select status dropdown (All/Pending/Interview/Approved/Rejected)
   - Search by name or email
   - Click "Apply Filters"

4. **Manage Applications**
   - Click "View" button on any applicant
   - Modal shows:
     - Full name, email, phone, location
     - Applied category
     - Quiz score and percentage
     - Application date
     - Cover message (if provided)
   
5. **Update Status**
   - In modal, select new status:
     - **Pending**: Initial state
     - **Interview**: Call candidate for interview
     - **Approved**: Hire the candidate
     - **Rejected**: Decline the application
   - Add optional notes (visible only to company)
   - Click "Update Status"

6. **View Full CV**
   - Click "View Full CV" button in modal
   - Opens full profile and CV in new tab

## 🔄 Workflow Example

### Scenario: PHP Developer Application

1. **Job Seeker (John Doe)**:
   - Takes PHP quiz → Scores 9/10 → Status: Passed ✅
   - Goes to "Companies" page
   - Sees "PHP Developer" category tab (active because passed)
   - Applies to "Tech Solutions Inc." with message: "I have 3 years PHP experience"

2. **Company (Tech Solutions Inc.)**:
   - Logs into company dashboard
   - Clicks "Category Applicants"
   - Sees statistics: Total Applications: 1, Pending: 1
   - Filters by "PHP" category
   - Sees John Doe in table with:
     - Name: John Doe
     - Category: PHP
     - Quiz Score: 9/10
     - Applied Date: Jan 27, 2026
     - Status: Pending
   
3. **Company Reviews**:
   - Clicks "View" on John Doe
   - Sees:
     - Full profile details
     - 90% quiz score with green badge
     - Cover message
   - Updates status to "Interview"
   - Adds note: "Good score, schedule phone interview"
   - Clicks "Update Status"

4. **Job Seeker Sees Update**:
   - Returns to "Companies" page
   - Sees "Applied - Status: Interview" badge on Tech Solutions Inc.
   - Knows company is interested! 🎉

## 🎨 Built-in Categories

The system supports these default categories from `index.php`:

1. **PHP Developer**
   - Backend Logic, MySQL, Server-side scripting
   - Remote, Full-time

2. **Java Developer**
   - OOPs, Spring Boot, Enterprise Applications
   - New York, Full-time

3. **Python Developer**
   - Scripting, Automation, Django/Flask
   - London, Contract

4. **Frontend Developer**
   - HTML5, CSS3, Responsive Design
   - Remote, Part-time

5. **JavaScript Developer**
   - ES6+, DOM Manipulation, Async Programming
   - Berlin, Full-time

6. **UI/UX Designer**
   - User Research, Wireframing, Prototyping
   - Remote, Freelance

7. **Data Scientist**
   - Data Analysis, ML, Python/Pandas
   - Remote, Full-time

8. **Digital Marketing**
   - SEO, Content Strategy, Social Media
   - Remote, Contract

9. **Database Admin**
   - SQL Optimization, Schema Design, Security
   - Chicago, Full-time

## 🔐 Security Features

- ✅ Session-based authentication
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ UNIQUE constraint prevents duplicate applications
- ✅ Foreign key constraints ensure data integrity
- ✅ Company can only see applications for their company
- ✅ Users can only apply if quiz passed
- ✅ CSRF protection via POST method

## 📊 Key Differences from Job Application System

| Feature | **Job Application** | **Category Application** |
|---------|---------------------|--------------------------|
| Based on | Specific job postings | General categories |
| Company posts | Job with description | No posting needed |
| User applies to | Specific job | Company in general |
| Quiz required | Yes (per job) | Yes (per category) |
| Application shows | Job details | Category details |
| Visibility | Only for that job | All companies |

## 🎯 Benefits

### For Job Seekers:
- Don't need to wait for specific job postings
- Can apply to multiple companies at once
- Showcase skills via quiz scores
- Get noticed by companies proactively

### For Companies:
- Access to pre-qualified talent pool
- No need to post job for every opening
- See candidates before they apply to jobs
- Filter by skill category easily
- Reduces hiring time

## 🐛 Troubleshooting

### Issue: "You haven't passed any quiz yet"
**Solution**: Go to homepage, select a category, and pass the quiz first.

### Issue: "Already applied to this company"
**Solution**: You can only apply once per company per category. Check your existing application status.

### Issue: Modal not loading
**Solution**: 
- Check browser console for JavaScript errors
- Ensure jQuery is loaded
- Verify `get_application_details.php` file exists

### Issue: No companies showing
**Solution**: 
- Ensure companies are registered in database
- Check if you selected correct category
- Verify quiz pass status in `user_quiz_status` table

### Issue: Status update not working
**Solution**:
- Verify company is logged in
- Check application_id is correct
- Ensure ENUM values match (Pending/Interview/Approved/Rejected)

## 📝 Notes

- Applications are sorted by date (newest first)
- Quiz must be passed before applying
- Cover message is optional but recommended
- Company notes are private (not visible to users)
- Status changes are tracked with timestamps
- Email notifications can be added in future enhancement

## 🚀 Future Enhancements

- [ ] Email notifications on status change
- [ ] Interview scheduling calendar
- [ ] Chat system between company and applicant
- [ ] Application withdrawal option
- [ ] Export applicants to CSV
- [ ] Bulk status update
- [ ] Application analytics for companies
- [ ] Recommendation system based on quiz scores

---

**Version**: 1.0  
**Created**: January 27, 2026  
**Author**: Job Portal Development Team
