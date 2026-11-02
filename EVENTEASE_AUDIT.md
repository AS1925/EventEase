# EventEase Audit Report

## Scope and method

This audit was performed by inspecting the existing project files in the workspace and the SQL scripts under the database folder, without altering application code or the database. We did not create, rename, drop, or migrate tables during this phase.

Relevant evidence inspected:
- [includes/db.php](includes/db.php)
- [database/admin_features.sql](database/admin_features.sql)
- [database/demo_seed.sql](database/demo_seed.sql)
- [login.php](login.php)
- [register.php](register.php)
- [register_event.php](register_event.php)
- [events.php](events.php)
- [user/my_events.php](user/my_events.php)
- [user/event_review.php](user/event_review.php)
- [admin/dashboard.php](admin/dashboard.php)
- [admin/reports.php](admin/reports.php)
- [includes/navbar.php](includes/navbar.php)
- [includes/access_control.php](includes/access_control.php)

> Important: the actual live MySQL schema was not fully verifiable from this environment because the PHP CLI/MySQL client was not available on the PATH during validation. The project does include SQL script files and application queries, but the complete live database schema should still be exported from phpMyAdmin before any migration work is approved.

---

## 1) Current project structure

Observed project structure:

- Public pages: [index.php](index.php), [about.php](about.php), [contact.php](contact.php), [events.php](events.php), [login.php](login.php), [register.php](register.php), [forgot_password.php](forgot_password.php)
- Auth/session utilities: [logout.php](logout.php), [includes/db.php](includes/db.php), [includes/access_control.php](includes/access_control.php), [includes/navbar.php](includes/navbar.php)
- User area: [user/dashboard.php](user/dashboard.php), [user/profile.php](user/profile.php), [user/my_events.php](user/my_events.php), [user/event_review.php](user/event_review.php), [user/my_requests.php](user/my_requests.php), [user/service_request.php](user/service_request.php), [user/cancel_registration.php](user/cancel_registration.php)
- Admin area: [admin/dashboard.php](admin/dashboard.php), [admin/add_event.php](admin/add_event.php), [admin/edit_event.php](admin/edit_event.php), [admin/delete_event.php](admin/delete_event.php), [admin/view_events.php](admin/view_events.php), [admin/view_registrations.php](admin/view_registrations.php), [admin/view_service_requests.php](admin/view_service_requests.php), [admin/enquiries.php](admin/enquiries.php), [admin/manage_reviews.php](admin/manage_reviews.php), [admin/manage_content.php](admin/manage_content.php), [admin/reports.php](admin/reports.php), [admin/update_request_status.php](admin/update_request_status.php)
- Shared includes: [includes/header.html](includes/header.html), [includes/footer.html](includes/footer.html), [includes/footer.php](includes/footer.php), [includes/admin_sidebar.php](includes/admin_sidebar.php), [includes/user_sidebar.php](includes/user_sidebar.php)
- Static assets: [assets/css/style.css](assets/css/style.css), [assets/js/script.js](assets/js/script.js)
- Event images/media: [events](events)
- Database scripts: [database/admin_features.sql](database/admin_features.sql), [database/demo_seed.sql](database/demo_seed.sql)

Observed architecture:
- PHP procedural style with shared MySQLi connection.
- Session-based auth with roles stored in `$_SESSION['role']`.
- Most pages include the navbar and footer.
- Admin and user navigation is split into sidebars.

---

## 2) Existing features and status

### Working or partially working features observed

1. Public event listing and search
   - Observed in [events.php](events.php).
   - Supports search by event name or type and capacity display.
   - Status: partially working; capacity is calculated by counting registrations, but no event-level attendance toggle or registration number logic is present.

2. User registration and login
   - Observed in [register.php](register.php) and [login.php](login.php).
   - Passwords are hashed with `password_hash()` and verified with `password_verify()`.
   - Status: usable base flow, but it does not cover role hierarchy beyond `admin` and `user`.

3. Event registration with capacity guard
   - Observed in [register_event.php](register_event.php).
   - Uses a transaction and checks for duplicates/capacity before insert.
   - Status: better than a simple form, but still missing registration-number generation, attendance gating, and a robust data model for status/eligibility.

4. User dashboard and event tracking
   - Observed in [user/dashboard.php](user/dashboard.php) and [user/my_events.php](user/my_events.php).
   - Status: functional as a basic dashboard with registration and cancellation features.

5. User cancellation flow
   - Observed in [user/cancel_registration.php](user/cancel_registration.php).
   - Status: functional but direct delete without policy checks or refund/attendance constraints.

6. Event reviews workflow
   - Observed in [user/event_review.php](user/event_review.php) and [admin/manage_reviews.php](admin/manage_reviews.php).
   - Uses `event_reviews` with pending/published/rejected workflow.
   - Status: present and partially usable, but no event policy enforcement for attendance or eligibility is visible.

7. Contact enquiry workflow
   - Observed in [contact.php](contact.php) and [admin/enquiries.php](admin/enquiries.php).
   - Status: implemented and appears operational.

8. Service request workflow
   - Observed in [user/service_request.php](user/service_request.php), [user/my_requests.php](user/my_requests.php), [admin/view_service_requests.php](admin/view_service_requests.php), [admin/update_request_status.php](admin/update_request_status.php).
   - Status: present and probably reusable as the private-function request path.

9. Editable homepage content and admin review moderation
   - Observed in [database/admin_features.sql](database/admin_features.sql) and [admin/manage_content.php](admin/manage_content.php).
   - Uses the `page_content` table and `event_reviews` moderation.
   - Status: present and reusable.

### Features that are missing or incomplete relative to the EventEase requirements

- No separate role model for Customer / Participant, Event Organizer, Service Provider / Vendor, Platform Administrator.
- No organizer profile management or organizer-owned events.
- No event ownership checks for organizer editing.
- No event-level attendance toggle (`Enable attendance tracking: Yes / No`) in the schema or forms.
- No registration number generation or attendance verification logic.
- No geolocation validation or browser location permission flow.
- No unique registration-number tracking or attendance status table/columns observed.
- No event questions module with organizer replies and statuses.
- No vendor marketplace / vendor profiles / quotations / availability / package listings.
- No admin authorization matrix beyond a simple `admin` role check.
- No explicit CSRF tokens observed.
- No file-safe upload validation or admin-mediated file storage logic seen.
- No robust session security settings (timeout, regeneration, cookie flags) observed.
- No migration plan or schema versioning created in the project yet.

---

## 3) Existing database tables and relationships

### Observed from the SQL scripts and application queries

Tables and patterns referenced in the project include:

1. `users`
   - Used in login, registration, profile updates, admin dashboard, event registration, and review data.
   - Observed columns from queries and scripts: `id`, `name`, `email`, `phone`, `password`, `role`.
   - Role values in the app appear to be `user` and `admin` in the current code.

2. `events`
   - Used widely for public events listing and registration.
   - Observed columns from queries: `id`, `event_name`, `event_type`, `event_date`, `event_time`, `venue`, `description`, `max_participants`, `image`.
   - No organizer ownership columns or event-level attendance toggle were visible in the inspected scripts.

3. `registrations`
   - Used for event registration records and status tracking.
   - Observed queries reference `id`, `user_id`, `event_id`, and `status`.
   - The demo seed script inserts `registrations (user_id, event_id, status)` with `status = 'Attended'` for a sample event, indicating a status field exists or is used in practice.
   - No registration number column or check-in timestamp/verification fields were visible in the inspected scripts.

4. `event_reviews`
   - Created by [database/admin_features.sql](database/admin_features.sql).
   - Observed schema: `id`, `event_id`, `user_id`, `rating`, `review_text`, `status`, `created_at`.
   - Unique key: `(event_id, user_id)`.
   - Status values: `Pending`, `Published`, `Rejected`.

5. `page_content`
   - Created by [database/admin_features.sql](database/admin_features.sql).
   - Observed schema: `content_key`, `content_value`, `updated_at`.
   - Used to drive home/about content.

6. `contact_messages`
   - Referenced by [contact.php](contact.php) and [admin/enquiries.php](admin/enquiries.php).
   - Observed columns from queries: `id`, `name`, `email`, `subject`, `message`, `submitted_at`.

7. `service_requests`
   - Used in user-private service workflow and admin updates.
   - Observed columns from queries and seed script: `id`, `user_id`, `event_type`, `event_date`, `venue`, `guests`, `decoration`, `photography`, `catering`, `dj`, `additional_requirements`, `status`.
   - This is the clearest foundation for private-function service requests.

### Relationships implied by the code

The application logic indicates these relationships are expected:
- `users.id` -> `registrations.user_id`
- `events.id` -> `registrations.event_id`
- `users.id` -> `event_reviews.user_id`
- `events.id` -> `event_reviews.event_id`
- `users.id` -> `service_requests.user_id`
- `users.id` -> `contact_messages` (not a formal FK necessarily, but a user could be associated via email/name in the current design)

### Important caveat

The project does not yet provide a complete exported live schema. We have not verified the actual database in phpMyAdmin or from a schema export. The table list above is therefore based on:
- SQL files in [database/admin_features.sql](database/admin_features.sql) and [database/demo_seed.sql](database/demo_seed.sql)
- application queries in PHP files

This is enough to identify likely schema patterns, but it is not a substitute for a real schema dump before migrations.

---

## 4) Identified bugs and security issues

### Bugs / behavior problems

1. Root database credentials are hard-coded
   - [includes/db.php](includes/db.php) uses `mysqli_connect("localhost","root","","sakthi")`.
   - This is a development pattern and is insecure for production.

2. Authentication and role handling are too narrow
   - [login.php](login.php) only treats `admin` and everything else as a generic `user`.
   - There is no organizer or vendor role structure and no granular permission model.

3. Access control is not enough for real authorization
   - [includes/access_control.php](includes/access_control.php) only checks one exact role.
   - It does not enforce ownership rules or record-level authorization for organizer/admin/vendor tasks.

4. Navigation may be fragile when session state is inconsistent
   - [includes/navbar.php](includes/navbar.php) sets `$dashboard_link` based on `$_SESSION['role']`; if roles are missing or invalid, it may redirect users incorrectly.
   - This is not a direct logout bug, but it is a session-navigation risk.

5. Inconsistent query style and SQL safety
   - Several files still concatenate user values directly into SQL strings, including [user/dashboard.php](user/dashboard.php) and [user/my_events.php](user/my_events.php).
   - Even though most other files use prepared statements, these examples are risky and should be normalized.

6. No CSRF protection visible
   - State-changing forms such as registration, cancellation, review submission, and admin status changes do not show CSRF tokens in the reviewed files.

7. No attendance or registration-number model
   - The application can count registrants but cannot distinguish attendance eligibility, unique registration IDs, or check-in verification.

8. Undefined or unverified server-side checks for private workflows
   - The service-request workflow is present, but the current project is not split between public-event attendance and private-function coordination as required.

9. The event review gate is not aligned with the requested policy model
   - [user/event_review.php](user/event_review.php) allows reviews when a user has an `Attended` status in the past, but there is no explicit event-level policy setting or alternative rule for disabled attendance.

10. Current code does not handle location verification or browser spoofing risk
   - This is explicitly missing and should be designed as a future feature with controlled assumptions.

### UI / UX issues observed

- Some pages use good Bootstrap styling, but the overall interface is not yet fully consistent across all modules.
- Some forms do not show clear validation state or server-side error handling beyond basic messages.
- Session flow should be tested for navigation from public pages to user/admin dashboards when session data is stale or missing.
- Some pages may still rely on mixed HTML conventions and include files with overlapping responsibilities.

### Security risks

- Hard-coded database credentials.
- Root user and a shared database name indicate a non-production setup.
- Potential SQL injection risk in unprepared queries.
- Session-based access is not robustly hardened.
- No visible CSRF tokens.
- No visible rate limiting on login attempts.
- No visible secure cookie configuration.
- No explicit authorization checks on record ownership for organizer or vendor actions.
- No visible server-side validation when handling arbitrary POST data.
- No safe file upload handling observed.

---

## 5) Features that can be reused

The following parts of the project are worth preserving and extending, rather than replacing:

- Authentication structure and users table: good base for role expansion.
- Event browsing/search flow: reusable for public-event discovery.
- Event registration transaction logic: reusable foundation for duplicate prevention and capacity checks.
- Reviews table and moderation flow: good base for event-specific feedback review.
- Service request flow: valuable for private events and vendor workflows.
- Content management table: good base for editing landing-page text and site configuration.
- Admin dashboards and reporting structure: can be upgraded rather than rebuilt.
- Shared navigation and UI shell: can be modernized without a full redesign.

---

## 6) Missing requirements relative to the requested EventEase roadmap

The project currently does not yet satisfy the full specification described in the project requirements. Missing major pieces include:

- Organizer role and organizer profile model.
- Distinction between public-event registration and private-function service requests.
- Per-event attendance setting and enforcement.
- Registration-number generation and verification.
- Browser geolocation check-in flow with vulnerability explanation and configurable radius.
- Questions/replies workflow for event-specific queries.
- Vendor profiles, service categories, package listings, quotes, and booking statuses.
- Role-based permission enforcement beyond a basic admin/user check.
- Platform-wide admin reporting for attendance and role management.
- Clear migration path for schema changes.

---

## 7) Recommended implementation order

Phase 1 (current audit stage and next step):
- Fix session/navigation and access-control consistency.
- Replace unsafe or direct SQL string interpolation.
- Harden DB connection and auth basics.
- Verify actual schema with a real export before migration.

Phase 2:
- Add organizer accounts and organizer ownership model.
- Add organizer profile editing and per-event ownership checks.

Phase 3:
- Upgrade registration records with unique registration numbers and correct capacity logic.

Phase 4:
- Implement optional attendance tracking and geolocation verification.

Phase 5:
- Add event questions, organizer replies, reviews, and compatibility rules.

Phase 6:
- Improve admin dashboards and reporting.

Phase 7:
- Extend the service request workflow into a vendor marketplace.

Phase 8:
- UI/UX cleanup, security testing, and deployment prep.

---

## 8) Proposed database migration plan

Before any schema change, a real schema export from phpMyAdmin is required. The current scripts do not provide a complete authoritative list of all live columns, constraints, or indexes.

Required migration planning before code changes:
1. Export the live `sakthi` schema and optionally its data.
2. Compare exported schema against the project queries and SQL scripts.
3. Decide whether to add new tables or alter existing ones only where necessary.
4. Use safe, reversible migration steps with backups.
5. Keep the existing public-event and private-service data flows separate.

Recommended migration categories to review after schema export:
- Add `organizer_id` to `events` if organizer-owned events are adopted.
- Add or normalize registration metadata such as registration number, registration status, and check-in fields.
- Add attendance table or attendance fields with event-level toggle support.
- Add event question and organizer reply tables.
- Add vendor profile/service package tables if the marketplace is implemented.
- Add strict indexing and foreign keys for ownership and relationships.

> Do not migrate the database until the live schema is exported and reviewed.

---

## 9) Verification status

Observed verification status:
- Project files were read and analyzed.
- Relevant SQL scripts were inspected.
- Runtime PHP validation is limited in this environment because the PHP CLI is not installed or not present on PATH. The command `php -v` failed with `php: not recognized`.
- This means the audit is based on source inspection, not full runtime execution for every workflow.

---

## 10) Bottom line

The project already contains a promising base for a small event management system, especially around event discovery, registration, reviews, and service requests. However, the current state is not yet aligned with the stricter EventEase requirements for organizer ownership, vendor workflows, attendance tracking, and secure authorization.

The safest next step is to export the live schema, confirm the actual database structure, and then proceed with Phase 1 fixes only after approval.
