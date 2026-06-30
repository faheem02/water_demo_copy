# Water Supply Management System

## Stack
- **PHP 8+** vanilla (no framework), **MySQL** via `mysqli_*`, Bootstrap 5.3.3, jQuery 3.7.1, Font Awesome 6.5.2
- DataTables jQuery plugin used on listing pages for search/sort/pagination
- Runs on **XAMPP** (localhost). DB: `water_supply_system`, user: `root`, pass: empty (`includes/db.php:3-6`)

## Setup
1. Create DB `water_supply_system` and import `database/schema.sql` (21 tables + default admin + expense categories)
2. Or hit `http://localhost/water_demo_copy/database/database.php` once
3. Login: `admin` / `admin123` at `login.php`

## Architecture
- **Entrypoints**: `index.php` (dashboard), `login.php`, `logout.php`
- **Feature pages**: 24 PHP files in `pages/`
- **Includes**: `db.php` (session + DB), `header.php` (layout + sidebar + mobile menu JS), `footer.php` (jQuery + Bootstrap JS), `txt.php` (site config strings)
- Auth guard: `$_SESSION['admin_logged_in']` at top of every page

## Site config (`includes/txt.php`)
Defines `$software_name`, `$company_name`, `$owner_name`, `$owner_address`, `$owner_phone`. Included by both `db.php` and `header.php`. **Also has its own `mysqli_connect` call** (line 2) — redundant connection.

## Quirks
- **Plain-text passwords** — auth compares `$_POST['password']` directly against DB (`login.php:14`)
- **No prepared statements** — SQL uses `mysqli_real_escape_string` string interpolation throughout
- **Paths** use auto-detected `$base_url`: `'../'` when in `pages/`, `''` otherwise (`header.php:20-24`)
- All CSS/JS is inline in PHP files — `assets/css/style.css` is loaded; `assets/js/script.js` is empty (unused)
- `error_log` files accumulate at root and in `pages/` (runtime errors)
- No build tools, tests, linters, or CI

## Testing
- No test framework — manual smoke test: log in, visit each `pages/` endpoint, verify CRUD

## Key schema notes
- **Routes** (`routes` table): `route_name`, `block`, `area`, `salesman` (no `description`)
- **Customers** (`customers` table): has own `block`, `area`, `salesman` fields, pre-filled from selected route on add/edit (`pages/customers.php`)
- **Deliveries** (`pages/deliveries.php`): when a customer is selected, shows route name, block, area, salesman inline

## UI Conventions
- **"button ui"**: buttons that sit next to form inputs must match the input fields' size. Use `style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;"` so the button height, border-radius, and text alignment are identical to `form-control`. Apply `w-100` or `flex-fill` as needed for width.
- **Route table action buttons**: use custom class `btn-xs` with `padding: 6px 10px; font-size: 12px; line-height: 1.3; border-radius: 6px;` for compact edit/delete buttons.
