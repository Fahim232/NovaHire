# Company Logo Feature

## Overview
Companies can now set and display their company logo throughout the job portal system. The logo appears in multiple locations to enhance brand visibility and professional appearance.

## Features Implemented

### 1. Logo Upload Functionality
- **Location**: Company Profile Page (`company/profile.php`)
- **Supported Formats**: JPG, PNG, GIF
- **Maximum Size**: 2MB
- **Recommended Dimensions**: 200x100 pixels
- **Storage Location**: `uploads/company_logos/`

### 2. Logo Display Locations

#### A. Company Dashboard Pages
The logo appears in the navbar of all company pages:
- ✅ Dashboard (`company/index.php`)
- ✅ My Jobs (`company/my_jobs.php`)
- ✅ Post Job (`company/post_job.php`)
- ✅ Profile (`company/profile.php`)
- ✅ View Applicants (`company/view_applicants.php`)
- ✅ View Applicant Details (`company/view_applicant_detail.php`)
- ✅ Manage Quiz (`company/manage_quiz.php`)

#### B. Public Job Pages
The logo appears on pages visible to job seekers:
- ✅ Browse Jobs Page (`browse_jobs.php`) - Shows logo next to each job listing
- ✅ Job Details Page (`job_details.php`) - Shows logo in:
  - Job header section
  - Company information section

#### C. Profile Display
- Company profile header shows the logo
- Current information section displays the logo
- Edit profile form shows current logo with upload option

## How to Use

### For Companies:

1. **Login** to your company account
2. **Navigate** to the Profile page
3. **Scroll** to the "Edit Profile" section
4. **Locate** the "Company Logo" field
5. **Click** "Choose File" and select your logo image
6. **Click** "Update Profile" to save

### Technical Details:

#### Database Schema
```sql
-- companies table already has logo field
ALTER TABLE companies ADD COLUMN logo VARCHAR(255) DEFAULT NULL;
```

#### File Upload Process
- Files are validated for type (image/jpeg, image/png, image/jpg, image/gif)
- Files are validated for size (max 2MB)
- Files are renamed to: `company_{company_id}_{timestamp}.{extension}`
- Old logos are automatically deleted when new ones are uploaded
- File path is stored relative to project root

#### Session Management
- Logo path is stored in `$_SESSION['company_logo']` during login
- Logo is updated in session when profile is updated
- Logo persists across all company dashboard pages

#### Display Logic
```php
<?php if (!empty($_SESSION['company_logo']) && file_exists('../' . $_SESSION['company_logo'])): ?>
    <img src="../<?php echo $_SESSION['company_logo']; ?>" alt="Company" 
         style="height: 35px; width: auto; object-fit: contain;">
<?php else: ?>
    <i class="fas fa-building mr-2"></i>
<?php endif; ?>
```

## Security Features

1. **File Type Validation**: Only image files allowed
2. **File Size Limit**: Maximum 2MB to prevent large uploads
3. **Secure Naming**: Files renamed with company ID and timestamp
4. **Directory Protection**: Index.php prevents directory listing
5. **Path Validation**: File existence checked before display
6. **SQL Injection Prevention**: Uses mysqli_real_escape_string

## Directory Structure
```
uploads/
└── company_logos/
    ├── index.php (prevents directory listing)
    ├── company_1_1234567890.png
    ├── company_2_1234567891.jpg
    └── ...
```

## File Permissions (Linux/Unix)
```bash
# Make directory writable
chmod 777 uploads/company_logos/

# Or more secure (if PHP runs as www-data)
chown www-data:www-data uploads/company_logos/
chmod 755 uploads/company_logos/
```

## Migration Script
Run `add_company_logo_feature.sql` for setup instructions.

## Testing Checklist

- [ ] Upload logo in company profile
- [ ] Logo appears in company navbar
- [ ] Logo appears on browse jobs page
- [ ] Logo appears on job details page
- [ ] Logo appears in company profile header
- [ ] Old logo is deleted when new one is uploaded
- [ ] Fallback icon shows when no logo exists
- [ ] File size validation works (reject >2MB)
- [ ] File type validation works (reject non-images)
- [ ] Logo persists after logout/login

## Troubleshooting

### Logo Not Showing
1. Check if file exists in `uploads/company_logos/`
2. Verify file permissions (must be readable)
3. Check browser console for 404 errors
4. Ensure path in database is correct
5. Clear browser cache

### Upload Failed
1. Check directory exists: `uploads/company_logos/`
2. Verify directory is writable (chmod 777 or appropriate)
3. Check file size is under 2MB
4. Verify file is valid image format (JPG/PNG/GIF)
5. Check PHP upload_max_filesize in php.ini

### Logo Not in Session
1. Ensure user logged out and logged back in after upload
2. Check company_login.php includes logo in session
3. Verify profile.php updates session on logo change

## Future Enhancements
- [ ] Image cropping/resizing tool
- [ ] Logo preview before upload
- [ ] Multiple logo sizes (thumbnail, full)
- [ ] CDN integration for faster loading
- [ ] Watermark support
- [ ] SVG format support

## Code Files Modified

1. `company/profile.php` - Logo upload form and logic
2. `company_login.php` - Store logo in session
3. `company/index.php` - Display logo in navbar
4. `company/my_jobs.php` - Display logo in navbar
5. `company/post_job.php` - Display logo in navbar
6. `company/view_applicants.php` - Display logo in navbar
7. `company/view_applicant_detail.php` - Display logo in navbar
8. `company/manage_quiz.php` - Display logo in navbar
9. `browse_jobs.php` - Display logo in job listings
10. `job_details.php` - Display logo in job header and company section

## Support
For issues or questions, check:
- Database has logo field in companies table
- uploads/company_logos/ directory exists and is writable
- Session includes company_logo variable
- File paths are relative to correct location
