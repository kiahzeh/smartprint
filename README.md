# SmartPrint WorkFlow

Simple role-based web app for a modern printing shop. Built with plain PHP, HTML/CSS, and MySQL.

## Roles
- Super Admin: Manage users and monitor all jobs.
- Admin: Create print jobs, assign artists, and update job status.
- Graphic Artist: View assigned jobs and update design progress.
- Client User: Submit print requests and track status.

## Project Structure
- `index.php`: Login page.
- `dashboard.php`: Role-based dashboard router.
- `pages/`: Role-specific pages.
- `db/schema.sql`: Database schema + demo seed data.
- `config/db.php`: MySQL connection config.

## Setup Steps
1. Create/import database:
```bash
mysql -u root -p < db/schema.sql
```
2. Update DB credentials in `config/db.php`.
3. Start PHP server from the parent `IPT1` folder (one level above `smartprint-workflow`):
```bash
php -S localhost:8000
```
4. Open:
- `http://localhost:8000/smartprint-workflow/index.php`

## Demo Accounts
- Super Admin: `super@smartprint.com` / `super123`
- Admin: `admin@smartprint.com` / `admin123`
- Artist: `artist@smartprint.com` / `artist123`
- Client: `client@smartprint.com` / `client123`

## Notes for IPT1 Final
- This is a clean starter that you can extend with reports, file uploads, and approval flows.
- You can add screenshots and ERD based on `db/schema.sql` for documentation.
