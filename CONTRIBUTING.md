# Contributing to NovaHire

Thank you for contributing to NovaHire! This guide will help you get started.

## Team Structure

| Role | Branch | Responsibility |
|------|--------|----------------|
| Project Lead | `infrastructure` | Core infrastructure, auth, security, database |
| Job Seeker Dev | `dev-seeker` | Job seeker portal, AI tools, applications |
| Company Dev | `dev-company` | Company portal, job posting, payments |
| Admin & AI Dev | `dev-admin-ai` | Admin panel, mentor portal, AI engine |

## Getting Started

### 1. Clone the Repository
```bash
git clone https://github.com/Fahim232/NovaHire.git
cd NovaHire
```

### 2. Switch to Your Branch
```bash
# For Job Seeker Developer
git checkout dev-seeker

# For Company Developer
git checkout dev-company

# For Admin & AI Developer
git checkout dev-admin-ai

# For Infrastructure (Project Lead)
git checkout infrastructure
```

### 3. Set Up Development Environment
1. Install XAMPP
2. Copy project to `htdocs/NovaHire`
3. Start Apache & MySQL
4. Create database `projects` in phpMyAdmin
5. Import SQL files from `database/` folder in order:
   - `database.sql`
   - `ai_db.sql`
   - `features_v3.sql`
   - `job_categories_v2.sql`

## Development Guidelines

### Code Style
- Use PHP 7.4+ features
- Follow existing code conventions
- Add comments for complex logic
- Use prepared statements for all SQL queries

### Commit Messages
```bash
# Format: <type>: <description>

# Examples:
feat: add job seeker dashboard
fix: resolve login authentication issue
docs: update README with setup instructions
style: improve responsive design
refactor: optimize database queries
```

### File Organization
```
NovaHire/
├── admin/          # Admin portal (Member 4)
├── ai/             # AI engine (Member 4)
├── api/            # AJAX endpoints (shared)
├── assets/         # Frontend assets (Infrastructure)
├── auth/           # Authentication (Infrastructure)
├── blog/           # Blog (Member 2)
├── company/        # Company portal (Member 3)
├── database/       # SQL schemas (Infrastructure)
├── includes/       # Shared libraries (Infrastructure)
├── mentor/         # Mentor portal (Member 4)
├── public/         # PWA assets (Infrastructure)
├── seeker/         # Job seeker portal (Member 2)
└── uploads/        # User uploads (Infrastructure)
```

### Testing Changes
1. Test on local XAMPP environment
2. Check for PHP errors in `php_error.log`
3. Test database operations
4. Verify cross-browser compatibility
5. Test responsive design on mobile

### Pull Request Process
1. Create a feature branch from your development branch
2. Make your changes
3. Test thoroughly
4. Commit with descriptive message
5. Push to your branch
6. Create Pull Request to `main` branch
7. Request review from Project Lead

## Database Changes

If you need to modify database schema:
1. Create a new SQL file in `database/` folder
2. Name it descriptively (e.g., `add_user_preferences.sql`)
3. Document the changes in comments
4. Notify Project Lead before merging

## Security Guidelines

- Never commit API keys or passwords
- Use environment variables for sensitive data
- Validate all user inputs
- Use prepared statements for SQL
- Implement CSRF protection
- Sanitize output to prevent XSS

## Getting Help

- Check existing documentation in `README.md`
- Review code comments in `includes/` files
- Contact Project Lead: Kazi Fahim

## Code of Conduct

- Be respectful to team members
- Communicate clearly
- Help others when possible
- Focus on project goals

---

Thank you for contributing to NovaHire! 🚀
