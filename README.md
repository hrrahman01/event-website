# Global Science Conference 2027 Website

A conference website with online registration, abstract submission and a secure admin area. Built with plain PHP, MySQL and Bootstrap 5. No framework and no build step, so it runs as-is on XAMPP.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL%2FMariaDB-supported-4479A1?logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)

## Table of contents
- [Features](#features)
- [Tech stack](#tech-stack)
- [Getting started](#getting-started)
- [Configuration](#configuration)
- [Project structure](#project-structure)
- [Database](#database)
- [API reference](#api-reference)
- [Pricing](#pricing)
- [Security](#security)
- [Deploying to production](#deploying-to-production)
- [Troubleshooting](#troubleshooting)
- [Roadmap](#roadmap)
- [Author](#author)

## Features

**Public site**
- Animated hero slider (Swiper) with registration and abstract calls to action
- Registration form with category cards, participant stepper and a live price summary (price, subtotal, 10% GST, total)
- Abstract submission with file upload (PDF, DOC, DOCX up to 5 MB), word counter and keywords
- Country and phone-code dropdowns, inline validation, and SweetAlert confirmation with a reference number
- Responsive layout, reduced-motion support

**Backend**
- JSON endpoints for registration and abstract submission
- **Server-side pricing**: the server calculates price, GST and total from the category and participant count and stores them in cents. Prices sent by the browser are ignored.
- Unique reference numbers such as `REG-2027-K7M3QX` and `ABS-2027-P4T8NW`
- Open/closed switch and deadline per form, enforced on the server
- Duplicate protection: one registration per email, one abstract per email and title

**Admin**
- Login by username or email, logout by POST with CSRF token
- Password hashing with automatic rehash, 30-minute idle session timeout
- Login rate limiting per account and per IP, activity log
- CLI script to create the first admin

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.1+, PDO (prepared statements only) |
| Database | MySQL 5.7+ or MariaDB 10.4+ (utf8mb4) |
| Frontend | HTML, Bootstrap 5.3, vanilla JavaScript |
| Libraries (CDN) | Swiper, AOS, SweetAlert2, Font Awesome |
| Local server | XAMPP (Apache with `.htaccess` enabled) |

## Getting started

### Requirements
- PHP 8.1 or newer with the `pdo_mysql` and `fileinfo` extensions (`zip` is recommended for stricter DOCX checks)
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `.htaccess` support (XAMPP includes this)

### Installation
1. **Clone** the repository into your web root:
   ```bash
   git clone https://github.com/hrrahman01/event-website.git
   cd event-website
   ```
   On XAMPP the folder is `C:\xampp\htdocs\event-website`.

2. **Create the config file:**
   ```bash
   copy includes\config.example.php includes\config.php      # Windows
   cp includes/config.example.php includes/config.php        # macOS / Linux
   ```
   Open `includes/config.php` and set the database values (see [Configuration](#configuration)).

3. **Create the database.** Start Apache and MySQL in XAMPP, open phpMyAdmin and import `database/database.sql`. Then run each file in `database/` whose name starts with `add-`, **once**:
   - `add-registration-participant-count.sql`
   - `add-registration-pricing.sql`

4. **Check folder permissions.** `storage/abstracts` and `storage/logs` must be writable by the web server.

5. **Create the first admin:**
   ```bash
   C:\xampp\php\php.exe scripts\create-admin.php
   ```
   The password needs at least 12 characters.

6. **Open the site:** `http://localhost/event-website/`
   Admin login: `http://localhost/event-website/admin/login.php`

## Configuration

All settings live in `includes/config.php` (this file is git-ignored).

| Constant | Default | Description |
|---|---|---|
| `APP_ENV` | `development` | `development` shows PHP errors, `production` hides them |
| `APP_TIMEZONE` | `Asia/Kolkata` | PHP timezone |
| `DB_HOST` / `DB_NAME` | `127.0.0.1` / `event_management` | Database connection |
| `DB_USER` / `DB_PASS` | `root` / empty | XAMPP defaults. Change these for production. |
| `MAX_UPLOAD_BYTES` | 5 MB | Maximum abstract file size |
| `SESSION_TIMEOUT` | 1800 | Idle seconds before the admin session expires |
| `LOGIN_MAX_ATTEMPTS` / `LOGIN_WINDOW` | 5 / 900 | Failed logins allowed per 15 minutes |
| `FORM_MAX_PER_HOUR` | 10 | Public form submissions per IP per hour |

**Event settings** are stored in the `event_settings` table:

| Key | Example | Used for |
|---|---|---|
| `registration_open` / `abstract_open` | `1` | Manual open/closed switch |
| `registration_deadline` / `abstract_deadline` | `2026-12-31` | Forms close after this date |
| `abstract_word_limit` | `300` | Maximum words in the abstract description |
| `event_name`, `venue`, `city_country`, `contact_email` | | Event details |

## Project structure

```
event-website/
├── admin/                  Admin login, logout and dashboard
│   ├── login.php
│   ├── index.php
│   └── actions.php
├── api/                    JSON endpoints used by the forms
│   ├── registration-submit.php
│   └── abstract-submit.php
├── assets/
│   ├── css/                styles.css, registration-pricing.css
│   ├── js/                 main.js, registration-pricing.js, admin.js
│   └── images/             hero slides and favicon
├── database/               database.sql and add-*.sql migrations
├── includes/               config.php and functions.php (shared helpers)
├── scripts/                create-admin.php (CLI only)
├── storage/                Private uploads and logs (not web accessible)
│   ├── abstracts/
│   └── logs/
├── index.html              Home
├── registration.html       Registration form
├── abstract.html           Abstract submission form
└── admin-panel.html        Admin dashboard design (static mockup)
```

## Database

| Table | Purpose |
|---|---|
| `admins` | Admin accounts (hashed passwords, roles `super_admin` and `admin`) |
| `registrations` | Registrations with category, participant count and amounts in cents (`price_cents`, `subtotal_cents`, `gst_cents`, `total_cents`) |
| `abstracts` | Abstract submissions with file metadata, status and reviewer notes |
| `event_settings` | Key/value event settings, open/closed switches and deadlines |
| `hero_slides` | Hero slide content (prepared for admin editing, not rendered from the database yet) |
| `activity_logs` | Admin actions and failed logins |
| `rate_limits` | Throttling data for logins and public forms |

Registration status values: `Pending`, `Confirmed`, `Rejected`.
Abstract status values: `Pending`, `Under Review`, `Accepted`, `Rejected`, `Revision Required`.

## API reference

Both endpoints use a session-bound CSRF token. Call `GET` first to receive the token and the current open/closed state, then send it with the `POST`.

### `GET /api/registration-submit.php`
```json
{ "success": true, "message": "", "token": "<csrf token>", "open": true }
```

### `POST /api/registration-submit.php`
Send as `application/x-www-form-urlencoded` or `multipart/form-data`, with the token in a `csrf_token` field or an `X-CSRF-Token` header.

| Field | Rules |
|---|---|
| `title` | `Dr.`, `Prof.`, `Mr.`, `Ms.` or `Other` |
| `first_name`, `last_name` | Required, max 60 characters |
| `email` | Valid email, max 120 characters, one registration per email |
| `phone_code`, `phone` | Code like `+971`, number of 6 to 15 digits |
| `country`, `organization`, `designation` | Required |
| `registration_category` | One of the categories in [Pricing](#pricing) |
| `participant_count` | Whole number from 1 to 999 |
| `participation_type` | `In-person` or `Virtual` |
| `dietary_requirements`, `message` | Optional |
| `consent` | Must be `1` |

Success response:
```json
{ "success": true, "message": "Registration received.", "reference": "REG-2027-K7M3QX", "total": "385.00" }
```

### `/api/abstract-submit.php`
Same flow. The `POST` is `multipart/form-data` and adds `abstract_title`, `research_track`, `presentation_type`, `co_authors`, `abstract_description`, `keywords` and the file field `abstract_file` (PDF, DOC or DOCX, up to 5 MB).

### Status codes
`200` success, `400` invalid upload, `403` closed, `405` wrong method, `409` duplicate, `419` invalid or expired token, `422` validation error, `429` rate limited, `500` server error.

## Pricing

Prices are in USD and defined in `api/registration-submit.php`. The registration page shows the same values for display only. If you change a price, update both places.

| Category | Price |
|---|---|
| Delegate | $350 |
| Speaker | $300 |
| Student | $175 |
| Researcher | $275 |
| Academic | $300 |
| Industry Professional | $450 |
| Other | $350 |

`total = price x participants + 10% GST`

## Security

- Prepared statements for every query
- CSRF tokens on all forms and on logout
- Honeypot field and per-IP rate limiting on public forms
- Uploads are checked by extension, MIME type (`finfo`), magic bytes and DOCX structure, then stored under random names in a private folder
- Sessions use strict mode, `HttpOnly` and `SameSite=Strict` cookies, and expire after 30 minutes of inactivity
- Admin login hashes a password even for unknown users, so response time does not reveal valid usernames
- Output is escaped with `htmlspecialchars` and CSV exports neutralise spreadsheet formulas

## Deploying to production

- [ ] Set `APP_ENV` to `'production'`
- [ ] Create a dedicated MySQL user with a strong password. Do not use `root`.
- [ ] Serve over HTTPS
- [ ] Confirm `includes/`, `storage/`, `database/` and `scripts/` return **403** in the browser
- [ ] Remove `scripts/create-admin.php` if you no longer need it
- [ ] Keep `includes/config.php` out of git (already in `.gitignore`)

## Troubleshooting

| Problem | Fix |
|---|---|
| Registration returns "Something went wrong" | Check `storage/logs/app.log`. Most often a migration in `database/` has not been run. |
| "Your session expired" on submit | Refresh the page to get a new CSRF token. Cookies must be enabled. |
| Abstract upload fails | Check that `storage/abstracts` is writable and the file is under 5 MB. Raise `upload_max_filesize` and `post_max_size` in `php.ini` if needed. |
| `Unknown column 'price_cents'` | Run `database/add-registration-pricing.sql` once. |
| Blank page or database error | Verify the values in `includes/config.php` and that MySQL is running. |

## Roadmap

- [ ] Admin dashboard: registration and abstract lists, status changes, secure file download, CSV export
- [ ] Admin screens for event settings and hero slides
- [ ] Early bird, regular and final pricing tiers
- [ ] Confirmation emails
- [ ] Privacy Policy page
- [ ] Unique index on `registrations.email`

## Author

**Hifzur Rahman** - Frontend Developer

## License

No license has been chosen yet. Add a `LICENSE` file before sharing the code publicly.
