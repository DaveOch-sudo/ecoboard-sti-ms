# EcoBoard STI Management System

PHP 8 + MySQL + JavaScript. Labs e-waste, asset lifecycle, carbon estimates, plastics, reports, feedback, AI + voice board.

## Setup
1. XAMPP/Laragon with PHP 8+ and MySQL. Unzip into htdocs as `ecoboard-sti-ms`.
2. Import `schema.sql` in phpMyAdmin (fresh database; includes sample labs and assets).
3. Edit `config.php` (DB login, optional Anthropic key, carbon factors).
4. Open http://localhost/ecoboard-sti-ms/ . The first visit asks you to create the admin account.
5. Students sign up at register.php. Admins promote users to technician/admin under Users.

## Roles
- Student: sign up, send reports (e-waste, faulty device, plastic), track status, rate resolved reports, view board.
- Technician: everything above plus all reports (update status + reply), assets, plastics, analytics, CSV export, board messages.
- Admin: technician rights plus users and roles.

## Carbon model (config.php)
Embodied (manufacturing) kg CO2e per device type, electricity = watts x hours x school days x grid factor,
credits for reuse (30%) and recycling (10%), plastic credit per kg. All values are placeholder estimates:
replace them with sourced figures for your country and cite them.

## Security notes
Passwords hashed (bcrypt), prepared statements, CSRF tokens, login throttling, role checks on every page.
Before public hosting: use HTTPS, set a strong DB password, delete install.php after creating the admin.
