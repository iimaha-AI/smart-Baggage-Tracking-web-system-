# Smart Baggage Tracking System

A PHP/MySQL airport baggage-management prototype with separate passenger, staff, supervisor, airline-management and administration workflows.

## Main features

- Passenger registration/login, baggage tracking, flight information and travel history
- Staff baggage registration, search, tracking updates and operational reports
- Flight status and management pages
- Administration dashboards, analytics, users, settings, audit and backup pages
- Role-based access control
- Notifications and tracking APIs
- Customer feedback, support, quality/inspection and multilingual modules
- MySQL schema plus local setup/sample-data scripts

## Project structure

The application source is under:

`SmartBaggageTracking/SmartBaggageTrackingSystem_Pro/`

Important directories:

- `passenger/` — passenger dashboard, tracking, flight information and history
- `staff/` — staff dashboard, baggage registration/search, tracking updates and reports
- `baggage/` — baggage registration, status, tracking and reporting workflows
- `flights/` — flight status/schedule/management
- `admin/` — administration, analytics, settings, users, audit and backup
- `includes/` — database, authentication, validation, shared helpers, APIs and role protection
- `support/`, `crm/`, `quality/`, `maps/`, `multilingual/` — supporting system modules
- `schema.sql` — recovered MySQL schema and sample records

## Restored archive

The repository originally contained only a partial snapshot. The available project archive was used to restore the missing application structure and core dependencies. Previously missing items such as the `includes` layer, database schema, passenger/staff pages, APIs and supporting modules are now present.

A few archive artifacts were intentionally **not** restored because they do not belong in a clean source repository: the bundled `cloudflared` Windows executable, debug scratch files, a redundant legacy SQL dump, zero-byte vendor JavaScript files, and placeholder image files. CDN versions are used where appropriate for frontend libraries.

Existing demo accounts/passwords and the project's original local-development credential assumptions were not changed as part of the restoration.

## Local requirements

- PHP 8+ with PDO and `pdo_mysql`
- MySQL/MariaDB
- Apache/XAMPP or another PHP-capable local web server

The default local database configuration expects:

- database: `smart_baggage_pro`
- host: `localhost`
- user: `root`
- password: blank

Import `schema.sql`, or review the included local setup scripts before using them.

From the repository root, the read-only prerequisite checker can be run with:

```bash
php check_runtime.php
```

## Validation status

The recovered archive's PHP source was syntax-checked during restoration. Core runtime dependencies and include paths were also reviewed. A complete MySQL end-to-end workflow still requires a local environment with `pdo_mysql` and a running MySQL/MariaDB server.

For a full integration check, validate this sequence locally:

1. Import the schema into a clean database.
2. Log in using the existing demo accounts.
3. Register baggage as staff.
4. Update baggage status/location.
5. Confirm the passenger can view tracking/history.
6. Verify role restrictions for staff/admin/passenger pages.

## Security maintenance performed

Without changing account passwords or core role behavior, the restored code includes safer prepared queries in the baggage-registration lookup flow, improved session cleanup, and restored role/check-in helper dependencies.

## Portfolio note

This repository is a university/co-op prototype demonstrating PHP/MySQL full-stack development, role-based workflows, operational dashboards and baggage-tracking concepts. It should not be represented as a production airport system without further integration, deployment and security testing.
