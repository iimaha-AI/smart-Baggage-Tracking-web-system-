# Smart Baggage Tracking System

A PHP/MySQL web application for airport baggage operations. The project includes passenger, staff, and administration workflows for baggage registration, status updates, tracking history, reporting, flight information, and role-based access.

## Project structure

The main source lives under:

`SmartBaggageTracking/SmartBaggageTrackingSystem_Pro/`

Key components include:

- `baggage/` — baggage registration, search, status, reports, and tracking.
- `admin/` — administration dashboard, analytics, settings, users, audit, and backup pages.
- `includes/` — configuration, PDO database access, authentication, shared helpers, staff configuration, validation, and role protection.
- `includes/security/role_guard.php` — centralized role/page access rules.
- `schema.sql` — the recovered MySQL schema and initial sample data from the original project archive.
- `check_runtime.php` — read-only prerequisite checker.

## Recovered source

The original project archive was recovered and the core files that were previously missing from this repository were restored, including:

- `includes/config.php`
- `includes/database.php`
- `includes/auth.php`
- `includes/functions.php`
- `includes/security/role_guard.php`
- `includes/config_staff.php`
- `includes/security.php`
- `includes/validation.php`
- `includes/logout.php`
- `schema.sql`

The restored configuration contains local development defaults only; database and SMTP passwords are blank.

## Local requirements

- PHP with PDO and `pdo_mysql`
- MySQL/MariaDB
- A local web server such as Apache/XAMPP or PHP's development server

Run the repository checker from the repository root:

```bash
php check_runtime.php
```

The checker verifies the core source files and PHP PDO/MySQL extensions. A successful prerequisite check does **not** by itself prove that every page, database query, or role workflow works end-to-end.

## Database

`schema.sql` contains the original recovered MySQL-compatible schema, relationships, indexes, sample records, and baggage-status trigger. Review sample data before using it outside a local development environment.

## Current validation status

The recovered PHP include files pass PHP syntax lint. In the current audit environment, the remaining environment-level blocker is the missing `pdo_mysql` PHP extension, so a full MySQL integration test could not be completed there.

Further validation should cover:

1. Schema import into a clean MySQL/MariaDB instance.
2. Passenger registration and login.
3. Staff baggage registration/search/status updates.
4. Passenger tracking/history views.
5. Admin role restrictions and destructive actions.
6. CSRF/session/security review before any public deployment.

## Portfolio note

This repository represents a university/co-op web-system project and should be presented as a prototype, not as a production airport system. The restored source improves reproducibility, while full end-to-end deployment validation remains future work.
