# NovaHire - InfinityFree Free Hosting Deployment Guide

Step-by-step guide to deploy your NovaHire Job Portal on InfinityFree for FREE.

---

## Prerequisites

Before you start, make sure you have:
- A working copy of the NovaHire project on your computer
- All 3 SQL files: `database.sql`, `ai_db.sql`, `job_categories_v2.sql`
- A valid email address
- WinSCP or FileZilla (for uploading files)

---

## Step 1: Create InfinityFree Account

1. Open your browser and go to: **https://infinityfree.com**
2. Click the **Sign Up** button
3. Enter your **email address**
4. Create a **password**
5. Solve the CAPTCHA
6. Click **Register**
7. Go to your email inbox and **verify your email** by clicking the link

---

## Step 2: Create Free Hosting Account

1. Login to InfinityFree with your email and password
2. Click **Create Account** button
3. Select **Free Hosting** (the free plan)
4. Choose a **subdomain** - this will be your website URL:
   - Example: `novahire.infinityfree.com` or `myjobportal.epizy.com`
   - Pick something easy to remember
5. Leave **Domain Name** as "I'll use a free subdomain"
6. Type your desired subdomain name
7. Click **Check Availability**
8. If available, click **Continue**
9. Leave all other settings as default
10. Click **Create Account**
11. **Save your account details** (you'll need them later)

---

## Step 3: Create MySQL Database

1. In your InfinityFree control panel, scroll down
2. Click **MySQL Databases**
3. Under **Create New Database**:
   - Database Name: type `projects` (or any name)
   - Click **Create Database**
4. **Write down these details** (important!):
   - Database Name: `epiz_12345678_projects` (the full name with prefix)
   - Database Username: `epiz_12345678` (your username)
   - Database Password: (set a password)
5. You can also create a **Database User** separately if needed

---

## Step 4: Access phpMyAdmin

1. In InfinityFree control panel, find **phpMyAdmin** (under MySQL Databases)
2. Click on **phpMyAdmin** link
3. Login with your database credentials from Step 3
4. You should see your database in the left sidebar

---

## Step 5: Import Database Files

### Import database.sql (Main Database)
1. Click on your database name in the left sidebar
2. Click the **Import** tab at the top
3. Click **Choose File**
4. Select `database.sql` from your project folder
5. Click **Go** or **Import**
6. Wait for the success message

### Import ai_db.sql (AI Tables)
1. Click **Import** tab again
2. Click **Choose File**
3. Select `ai_db.sql`
4. Click **Go**
5. Wait for success

### Import job_categories_v2.sql (Job Categories)
1. Click **Import** tab again
2. Click **Choose File**
3. Select `job_categories_v2.sql`
4. Click **Go**
5. Wait for success

**All 3 SQL files should now be imported.**

---

## Step 6: Upload Project Files

### Method A: Using File Manager (Recommended)

1. Go back to InfinityFree control panel
2. Click **File Manager** (under Files section)
3. You'll see the `htdocs` folder - this is your website root
4. Open `htdocs` folder
5. Click **Upload** button
6. Select **Upload Folder** or upload files one by one
7. Upload ALL your project files and folders:
   - `admin/` folder
   - `ai/` folder
   - `api/` folder
   - `assets/` folder
   - `auth/` folder
   - `company/` folder
   - `files/` folder
   - `images/` folder
   - `includes/` folder
   - `seeker/` folder
   - `uploads/` folder
   - `index.php`
   - `landing.php`
   - `company_login.php`
   - `company_registration.php`
   - All other `.php` files in root

### Method B: Using WinSCP (Faster for Large Projects)

1. Download and install **WinSCP** from https://winscp.net
2. Open WinSCP
3. Create new connection:
   - **Host Name**: `ftpupload.net`
   - **Port**: `21`
   - **Username**: (from your InfinityFree control panel → FTP Details)
   - **Password**: (from your InfinityFree control panel)
4. Click **Login**
5. Navigate to `htdocs` folder on the right side (server)
6. Navigate to your project folder on the left side (computer)
7. Select all project files and drag them to `htdocs` folder
8. Wait for upload to complete

---

## Step 7: Edit Database Connection

This is the most important step - you need to tell your project how to connect to the online database.

1. Go to **File Manager** in InfinityFree
2. Navigate to `htdocs/admin/`
3. Find `dbcon.php` file
4. Click **Edit** (or download, edit, re-upload)
5. Change the database credentials:

**Before (Local XAMPP):**
```php
$host     = 'localhost';
$user     = 'root';
$password = '';
$database = 'projects';
```

**After (InfinityFree):**
```php
$host     = 'sql.infinityfree.com';
$user     = 'epiz_12345678_projects';  // Your full DB username from Step 3
$password = 'your_database_password';   // Your DB password from Step 3
$database = 'epiz_12345678_projects';  // Your full DB name from Step 3
```

6. **Save the file**

---

## Step 8: Set File Permissions

Some folders need write permissions for uploads to work:

1. In File Manager, right-click on `files` folder
2. Click **Permissions** or **CHMOD**
3. Set to **755** or **777**
4. Do the same for `uploads` folder
5. Do the same for `uploads/company_logos` folder

---

## Step 9: Test Your Website

1. Open your browser
2. Go to your website URL:
   ```
   https://your-subdomain.infinityfree.com/landing.php
   ```
   or
   ```
   https://your-subdomain.infinityfree.com/index.php
   ```
3. You should see the NovaHire homepage

---

## Step 10: Test All Features

### Test Job Seeker:
1. Go to Registration page
2. Create a new account
3. Login
4. Browse jobs
5. Apply to a job

### Test Company:
1. Go to Company Registration
2. Create a company account
3. Login
4. Post a job
5. Add quiz questions
6. View applicants

### Test Admin:
1. Go to Admin Login: `https://your-site.com/admin/admin_login.php`
2. Login with:
   - Username: `admin`
   - Password: `admin123`
3. Check dashboard

---

## Default Credentials (Same as Local)

### Admin
- **URL**: `https://your-site.com/admin/admin_login.php`
- **Username**: `admin`
- **Password**: `admin123`

### Sample Companies
- **Email**: `hr@techsolutions.com` / **Password**: `password`
- **Email**: `jobs@digitalinnovations.com` / **Password**: `password`

---

## Troubleshooting

### Problem: "Database Connection Unsuccessful"
**Solution**:
- Check `admin/dbcon.php` credentials are correct
- Make sure you used the FULL database username (with prefix like `epiz_12345678_projects`)
- Verify database was created successfully in MySQL Databases

### Problem: "Table doesn't exist"
**Solution**:
- Make sure all 3 SQL files are imported
- Check in phpMyAdmin that tables exist
- Re-import the SQL files if needed

### Problem: "Cannot upload CV/Files"
**Solution**:
- Check `files/` folder permissions (set to 755 or 777)
- Check `uploads/` folder permissions
- Make sure `uploads/company_logos/` exists and is writable

### Problem: "Page not found" or 404 Error
**Solution**:
- Make sure all files are in the `htdocs` folder
- Check the URL is correct
- Files should NOT be in a subfolder inside htdocs

### Problem: CSS/JS not loading
**Solution**:
- Check if `assets/` folder was uploaded correctly
- Clear browser cache (Ctrl+Shift+R)
- Check browser console for errors

### Problem: Session errors
**Solution**:
- InfinityFree supports PHP sessions by default
- Make sure you're not using `session_start()` before headers are sent

---

## Important Notes

1. **Free Hosting Limitations**:
   - 5 GB disk space
   - 5 GB bandwidth per month
   - Limited CPU and RAM
   - Your site may be slow during high traffic

2. **Keep SQL Files Safe**:
   - Keep `database.sql`, `ai_db.sql`, `job_categories_v2.sql` on your computer
   - You may need them if database gets corrupted

3. **Backup Regularly**:
   - Download your database from phpMyAdmin periodically
   - Keep a copy of all project files

4. **Security**:
   - Change default admin password after first login
   - Change sample company passwords
   - Don't share your database credentials

5. **AI Features**:
   - AI works offline by default (no API key needed)
   - To enable live AI, add your OpenAI/Gemini key in Admin → AI Settings

---

## Quick Reference

| Item | Value |
|------|-------|
| Website URL | `https://your-subdomain.infinityfree.com` |
| Admin URL | `https://your-subdomain.infinityfree.com/admin/admin_login.php` |
| phpMyAdmin | From InfinityFree control panel |
| FTP Host | `ftpupload.net` |
| FTP Username | From control panel |
| Database Host | `sql.infinityfree.com` |
| Database Name | `epiz_XXXXX_projects` (from Step 3) |

---

## Need Help?

If you face any issues:
1. Check InfinityFree knowledge base: https://help.infinityfree.net
2. Verify all steps above are followed correctly
3. Check PHP error logs in InfinityFree control panel
4. Make sure MySQL service is working (check in phpMyAdmin)

---

**Congratulations! Your NovaHire Job Portal is now live on the internet!**

Share your website URL with others and start recruiting!
