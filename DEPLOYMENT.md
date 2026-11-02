# EventEase Deployment Notes

## Database

- XAMPP's `C:\xampp\mysql\bin\mysqld.exe` is its bundled MariaDB-compatible server; this setup uses that XAMPP instance and the existing `sakthi` database only.
- Use the existing `sakthi` database only. Do not create a replacement database.
- Take and verify a restorable database backup before applying any migration.
- Run approved migrations from the project root with the PHP CLI. The password-reset migration is:

  ```powershell
  C:\xampp\php\php.exe database\migrate_password_reset_tokens.php
  ```

- The password-reset migration refuses to run unless both the configured and selected database are `sakthi`; it is safe to rerun.
- The organizer-profile extension was approved and applied to `sakthi` on 2026-10-20. It added only nullable `location` and `logo_path` columns to the existing `organizer_profiles` table; it did not create a table, assign event owners, or alter existing rows. A fresh, verified backup was taken before the migration and is retained outside the repository. For a future installation or migration rerun, first take a fresh backup and pass it to the CLI migration:

  ```powershell
  C:\xampp\php\php.exe database\migrate_organizer_profile_fields.php --backup-file="C:\path\to\fresh-sakthi-backup.sql"
  ```

- Event pricing is stored as an INR ticket price. Before enabling the updated event forms and attendee pages, take a fresh, verified backup and run the idempotent Phase 8 migration from the project root:

  ```powershell
  C:\xampp\php\php.exe database\migrate_phase8.php
  ```

  Existing events receive a `0.00` price (free); the migration preserves event rows. Event payments remain offline only; the application does not collect or track payment.

- Existing events with no organizer remain unassigned and manageable by administrators. Assign ownership only through the administrator event-management page after confirming the intended, verified organizer; never infer or bulk-assign owners.
- Use a dedicated runtime account with only required DML privileges. For approved CLI migrations, provide a separately managed migration account with the required temporary DDL privileges; do not give those privileges to the web runtime account.
- If XAMPP reports Aria checksum errors in `mysql.db`, stop and take a verified backup before any system-table recovery. Do not proceed with application login or account grants while the XAMPP privilege table is damaged.
- In this local XAMPP installation, `mysql.db` was recovered from an intact archived Aria data file; `CHECK TABLE` and a temporary grant/drop test now pass. The existing `sakthi` application tables and baseline row counts were preserved.

## Configuration and current deployment blocker

- The local XAMPP database connection is configured only in `includes/db.php`: `localhost`, the existing `sakthi` database, and this installation's default `root` account. No external database config file or database environment variables are used.
- The blank-password root connection is restricted to Windows XAMPP requests on `localhost`/`127.0.0.1` and local CLI migration commands. It is for this local development installation only; never expose it to a network or deploy it.
- Before deployment, replace the local connection settings in `includes/db.php` with credentials for a dedicated least-privilege account, and enforce TLS and secret-management requirements for the production host.
- Configure `APP_BASE_URL` as the canonical absolute URL of this installation, without query parameters or a fragment.
- Configure `MAIL_FROM` as a valid sender address and configure PHP's mail transport to deliver reset emails.
- Password reset links are one-time, expire after 30 minutes, and are stored as SHA-256 token hashes. Password resets remain unavailable until the application URL, sender address, and mail transport are configured.
- **Do not deploy to production until configured:** provision a dedicated least-privilege MySQL account for `sakthi` and replace the local-only settings in `includes/db.php`.

## Web server and PHP

- Serve the application over HTTPS. The session cookie's `Secure` attribute follows the HTTPS request state.
- Keep the web root limited to the application public files; keep database backups, logs, environment files, and operational scripts outside public access.
- Disable PHP error display for users and enable protected server-side error logging.
- Ensure the PHP runtime has `mysqli` and that upload limits are compatible with the application's 5 MB image limit.
- Verify writable upload directories cannot execute uploaded files.

## Release checklist

1. Back up `sakthi` and confirm the backup can be restored.
2. Review each approved migration and run it with the CLI against `sakthi`; confirm expected table/column changes and preserved row counts.
3. Set production environment values and verify email delivery using a controlled test account.
4. Run the PHP syntax checks and role/workflow integration tests.
5. Check the public pages and each role dashboard at mobile and desktop widths.
6. Confirm no temporary QA accounts, bookings, reset tokens, or uploaded test assets remain.
7. Deploy over HTTPS, verify server logs, and record the backup and rollback procedure.
