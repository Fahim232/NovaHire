# Job Application Portal

A modern, professional Job Application Portal built with PHP and MySQL. This project features a clean "Glassmorphism" aesthetic, an interactive assessment system, and a robust candidate management dashboard.

## 🚀 Features

- **Premium UI/UX**: Professional minimalist design with glassmorphism effects and smooth animations.
- **Skill Assessment**: Category-based quiz system (PHP, Java, Python) to evaluate candidates.
- **Grooming Session**: Interactive learning module for candidates to improve before retaking assessments.
- **Application Dashboard**: A dedicated area for users to track and update their applications.
- **CV Generation**: Automatic PDF CV generation for registered candidates.
- **Admin Panel**: Full control over candidate data, applications, and portal settings.

## 🛠️ Technologies Used

- **Frontend**: HTML5, CSS3 (Vanilla), Bootstrap 4.6.1, Font Awesome.
- **Backend**: PHP 7.4+.
- **Database**: MySQL.
- **Icons/Illustrations**: Font Awesome, Popsy Illustrations.

## 📦 Installation Guide

Follow these steps to set up the project locally:

### 1. Prerequisites
Ensure you have **XAMPP** or a similar environment installed (PHP & MySQL).

### 2. Database Setup
1. Open **phpMyAdmin**.
2. Create a new database named **`projects`**.
3. Import the `database.sql` file provided in the root directory:
   - Select the `projects` database.
   - Go to the **Import** tab.
   - Choose `database.sql` and click **Go**.

### 3. File Configuration
1. Clone or extract the project into your `htdocs` folder (e.g., `C:/xampp/htdocs/Job-Application-Portal-master`).
2. Verify the database connection in `admin/dbcon.php`:
   ```php
   $database = 'projects';
   ```

### 4. Running the Project
1. Start the Apache and MySQL modules in XAMPP.
2. Open your browser and navigate to: `http://localhost/Job-Application-Portal-master/index.php`.

## 🔑 Default Credentials

### Admin Access
- **URL**: `http://localhost/Job-Application-Portal-master/admin/admin_login.php`
- **Username**: `admin`
- **Password**: `admin123`

## 📂 Project Structure

- `/admin`: Administrative panel and backend logic.
- `/css`: Stylesheets including the core `style.css`.
- `/files`: Storage for uploaded candidate CVs.
- `/js`: Client-side scripts.
- `index.php`: The main landing page.
- `database.sql`: MySQL database schema and sample data.

---
Developed with ❤️ for a professional job seeking experience.
