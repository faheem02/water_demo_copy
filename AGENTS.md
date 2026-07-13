# Water Supply Management System

## Stack
<<<<<<< HEAD
- **PHP 7.4+/8+** vanilla (no framework), **MySQL** via `mysqli_*`, Bootstrap 5.3.3, jQuery 3.7.1, Font Awesome 6.5.2
- DataTables jQuery plugin (CDN v1.13.6) loaded per-page on ~7 listing pages — not in global header/footer
- XAMPP localhost. DB: `water_supply_system`, user: `root`, pass: empty (`includes/db.php:3-6`)
=======
- **PHP 8+** vanilla (no framework), **MySQL** via `mysqli_*`, Bootstrap 5.3.3, jQuery 3.7.1, Font Awesome 6.5.2
- DataTables jQuery plugin used on listing pages for search/sort/pagination
- Runs on **XAMPP** (localhost). DB: `water_supply_system`, user: `root`, pass: empty (`includes/db.php:3-6`)
>>>>>>> 822b3970b8ba4fb65fc5798e403232c42cbe8bb7

## Setup
1. Create DB `water_supply_system`, import `database/schema.sql` (21 tables + default admin + expense categories)
2. Or visit `http://localhost/<deploy_folder>/database/database.php` once (file internally references `water_demo_copy` — adjust to actual folder)
3. Login: `admin` / `admin123` at `login.php`

## Architecture
- **Entrypoints**: `index.php` (dashboard), `login.php`, `logout.php`
- **Feature pages**: 24 PHP files in `pages/`
<<<<<<< HEAD
- **Includes**: `db.php` (session + DB), `header.php` (layout + sidebar + mobile menu JS + all inline CSS), `footer.php` (jQuery + Bootstrap JS), `txt.php` (site config strings)
- **`includes/sidebar.php`** is a dead stub — sidebar lives entirely in `header.php`
- **`assets/js/script.js`** is empty (unused)
- Auth guard: `$_SESSION['admin_logged_in']` at top of every page

## Quirks
- **Plain-text passwords** — `login.php:12-14` compares `$_POST['password']` directly in SQL
- **No prepared statements** — all SQL uses `mysqli_real_escape_string` interpolation
- **`includes/txt.php`** defines `$software_name`, `$company_name`, `$owner_name`, `$owner_address`, `$owner_phone` — but also has its own `mysqli_connect` at line 2. It's included by both `db.php` (`require_once`) and `header.php` (`include`), creating a redundant connection
- **`$base_url`** auto-detected: `'../'` when URL contains `/pages/`, `''` otherwise (`header.php:20-24`)
- **Inline CSS/JS** — most pages have huge `<style>`/`<script>` blocks despite `assets/css/style.css` being loaded
- **`error_log`** files accumulate at root and in `pages/`
- No build tools, tests, linters, or CI

## Schema notes
- **Routes** (`routes`): `route_name`, `block`, `area`, `salesman` (no `description`)
- **Customers** (`customers`): owns `block`, `area`, `salesman` fields, pre-filled from selected route on add/edit
- **Deliveries** (`deliveries.php`): supports walk-in customers (inserts on the fly); shows route info inline
- **Bottle tracking**: two pages — `empty_bottle_return.php` (main entry, linked in sidebar) and `bottle_tracking.php` (per-customer detail view)

## UI Conventions
- **Button matching inputs**: buttons next to `<input class="form-control">` must match 46px height. Use `style="height: 46px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;"`
- **Route table actions**: custom `btn-xs` class with `padding: 6px 10px; font-size: 12px; line-height: 1.3; border-radius: 6px;`
=======
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
>>>>>>> 822b3970b8ba4fb65fc5798e403232c42cbe8bb7
