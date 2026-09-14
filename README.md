# Smart Baggage Tracking — PHP Source Snapshot

PHP pages for baggage registration, search, status/tracking, reporting, and administration. Source includes role checks through an `Auth` class and database access through PDO.

## Current completeness

Source lives under `SmartBaggageTracking/SmartBaggageTrackingSystem_Pro/`. Required `includes/config.php`, `includes/database.php`, `includes/auth.php`, and `includes/functions.php` are absent. No database schema, authentication implementation, or complete entry point is supplied.

A fresh clone cannot run as a complete system. No fictional setup command or schema is provided.

## Source map

- `baggage/`: registration, reports, search, status, tracking.
- `admin/`: dashboard, analytics, settings/users, backups.
- `admin/analytics/`, `admin/backup/`, `admin/audit/`: additional variants.
- `blueprint_sync_log.txt`: retained synchronization artifact pending review.

## Required next steps

1. Recover original dependencies and a MySQL-compatible schema from the complete project.
2. Select canonical administration variants before moving files.
3. Replace request-value SQL interpolation with validated parameters, including flight/type lookups in `baggage/register.php`.
4. Review CSRF, authorization, backup access, and destructive admin actions once authentication source is available.
5. Add local setup, PHP linting, role-based integration tests, and a demo with non-sensitive data.

## Portfolio status

Restore complete source before pinning. Keep this snapshot or consider making it private until reproducibility is established; do not delete the only source. No visibility change or bulk deletion was performed.

See [review notes](docs/REVIEW.md). Confirm authorship and licensing before adding a license.

## Runtime repair — 2026-09-14

Run `php check_runtime.php` from the repository root for a read-only prerequisite check. It exits nonzero when source files or PDO/MySQL support are missing. PHP include paths now resolve relative to each page (`__DIR__`) rather than the process working directory.

All 14 PHP files (13 original pages plus the checker) passed PHP 8.4.25 syntax lint. With PDO/MySQL enabled, the checker reports missing `includes/config.php`, `database.php`, `auth.php`, `functions.php`, and `security/role_guard.php`. The database schema is also absent. These were not fabricated; login, registration, tracking and administration remain blocked until original source and schema are recovered. Syntax lint does not establish a working system.
