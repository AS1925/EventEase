# EventEase Progress Checklist

## Phase 0 - Audit and inspection

- [x] Review project folder structure and core files.
- [x] Inspect database connection and SQL scripts.
- [x] Review authentication, passwords, sessions, and access control.
- [x] Review public event browsing and registration flow.
- [x] Review user dashboard, profile, service requests, and cancellation flow.
- [x] Review admin dashboard, reports, and moderation flows.
- [x] Identify working features, missing features, and security concerns.
- [x] Capture audit summary in [EVENTEASE_AUDIT.md](EVENTEASE_AUDIT.md).
- [x] Confirm that no database or application code was changed during audit.
- [ ] Export full live MySQL schema from phpMyAdmin for confirmation before any migration.
- [x] Receive approval to move into Phase 1 implementation.

## Phase 1 - Fix existing bugs, navigation, sessions, database access, and security issues

- [x] Confirm live schema columns, indexes, foreign keys, and table engines read-only.
- [x] Audit remaining SQL injection risks; prepared statements are used for variable inputs.
- [x] Review session security and navigation consistency.
- [x] Add an accessible shared Back control to all 50 rendered PHP pages; return to same-origin history when available and use a role-appropriate fallback for direct visits; verify public routes and both navigation paths in the browser.
- [x] Harden authentication and role handling while preserving existing data.
- [x] Test login, dashboards, registration confirmation, and duplicate-registration rejection; successful insertion remains untested to preserve live database contents.
- [x] Validate public/admin navigation does not unexpectedly log users out.
- [x] Protect logout and state-changing POST forms with session CSRF tokens.
- [x] Validate event image uploads by file content and enforce a 5 MB limit.

## Phase 2 - Organizer accounts, organizer profiles, and event ownership

- [x] Add customer, organizer, vendor, and admin roles; migrate legacy user accounts to customer.
- [x] Add organizer profile registration and editing with pending/verified/rejected status.
- [x] Add an admin organizer verification workflow.
- [x] Add organizer ownership and event category, start/end time, address, coordinates, registration window, and publish status fields; reuse the existing venue column.
- [x] Preserve existing event fields/data while backfilling category/start time and keeping legacy event columns synchronized.
- [x] Add an organizer dashboard and filter event listings to the signed-in organizer.
- [x] Enforce owner checks on event edit and delete requests.
- [x] Write and run the CLI-only migration against `sakthi`; rerun it successfully to verify idempotence and unchanged user/event/registration row counts.
- [x] Add an organizer-owned registration list with event filtering and registration-number search.
- [x] Add an administrator-only, CSRF-protected manual owner assignment for unassigned events; restrict targets to verified organizers and preserve existing assignments.
- [x] Fix organizer event creation: default new listings to published, bind coordinates numerically, permit events without an image, scope duplicate checks to their owner, report database save failures safely, and send successful saves to the owner-scoped event list.
- [x] Obtain approval, back up `sakthi`, add nullable profile location and logo-path columns with the idempotent CLI migration, and verify the second run changes nothing.
- [x] Add validated location and optional JPG/PNG/WebP logo editing to organizer profiles; store generated filenames only and disable directory execution/listing.
- [x] Browser-test profile editing and logo upload, organizer verification, owner event creation/editing, cross-organizer edit and registration denial, admin visibility/manual assignment, and customer registration-number confirmation/cancellation using temporary records.
- [x] Remove QA users, profiles, events, registration, and uploaded images; restore row counts to 11 users, 1 organizer profile, 7 events (0 assigned), 3 registrations, and 3 service requests.
- [x] **Approval checkpoint before Phase 3**

## Phase 3 - Registration upgrades

- [x] Add unique registration-number generation and show it on confirmation and My Events.
- [x] Block duplicate active registrations and use a transaction for the capacity check.
- [x] Separate registration lifecycle from attendance status and restore seats on cancellation.
- [x] Scope registrations to their participant and support registration-number search in admin registration management.
- [x] Write and apply the idempotent migration against `sakthi`; preserve existing registration rows.
- [x] Re-inspect the live registration schema and existing records: all three registrations already have distinct, non-empty numbers protected by a `NOT NULL` unique index; no migration or backfill was necessary.
- [x] Generate shorter ambiguity-resistant random numbers for new registrations while retaining existing numbers; log database failures and roll back failed registration transactions.
- [x] Count only active registrations for public seat availability; protect duplicate checks, capacity checks, and admin reactivation with event-row transactions.
- [x] Show event time, registration date/status, and copyable registration numbers on participant registration views; enforce ownership and cancellation eligibility server-side while retaining cancelled rows.
- [x] Add admin registration-number search and event/status filters; preserve separate registration and attendance statuses.
- [x] Lint changed PHP/JavaScript files; run read-only checks against `sakthi` including 250 generated number-format/uniqueness checks and updated admin/My Events queries; smoke-test public routes (200) and protected-route login redirects (302).
- [ ] End-to-end registration, duplicate/full-capacity, concurrent-capacity, cancellation, ownership-denial, and admin-update tests using temporary database records. Not run because the approved scope was code-only; no database rows were inserted or changed.
- [x] **Approval checkpoint before Phase 4**

## Phase 4 - Attendance tracking

- [x] Add the opt-in attendance setting to event create/edit forms; require venue coordinates when enabled.
- [x] Add an idempotent CLI migration for `events.attendance_enabled` and the unique-per-registration `event_attendance` table in `sakthi`.
- [x] Add the participant check-in flow with registration ownership, event setting, schedule window, venue-radius, and duplicate checks enforced server-side.
- [x] Require explicit location consent; retain check-in time, consent time, and distance only, not the participant's coordinates.
- [x] Hide check-in for events without tracking and add organizer-owned attendance viewing/search by registration number.
- [x] Add organizer/admin attendance reporting with event ownership enforcement, event/registration/status filters, checked-in timestamps, eligible counts, attendance rates, and cancelled/no-show distinctions.
- [x] End-to-end test customer login and event registration, GPS check-in form/consent, out-of-radius rejection, in-radius acceptance, duplicate prevention, and organizer attendance report using temporary `sakthi` records; remove all fixtures and confirm original row counts. GPS coordinates were simulated in the HTTP request; physical-device/browser geolocation permission was not exercised.
- [x] **Approval checkpoint before Phase 5**

## Phase 5 - Questions, replies, and reviews

- [x] Add the approved `event_questions` table to `sakthi` for participant questions, organizer replies, and pending/answered/closed status; migration is CLI-only and idempotent.
- [x] Add participant question submission and private question/reply history for their active event registrations.
- [x] Add organizer replies and closure for their own events only, plus admin question moderation by closure.
- [x] Enforce reviews for checked-in registrations when tracking is on and active registered participants when it is off.
- [x] Preserve the existing unique `(event_id, user_id)` review constraint and provide organizers a view of reviews for their own events.
- [ ] Verify question, reply, moderation, and review workflows end to end.
- [x] **Approval checkpoint before Phase 6**

## Phase 6 - Admin dashboards and reports

- [x] Expand the participant dashboard with live registration numbers/statuses, questions, and service-request data.
- [x] Expand the organizer dashboard with owned-event registration counts, seats left, tracked attendance rates, reviews, and pending questions.
- [x] Expand the admin dashboard with live user, organizer verification, vendor, event, active-registration, request, review, and question counts.
- [x] Use active registrations and actual attendance records for event-wise reports; aggregate published reviews without join-multiplication.
- [x] Scope participant, organizer, and admin dashboard/report queries to the signed-in role and the intended records.
- [x] Verify participant, organizer, and admin dashboards plus event-wise reports in the browser using temporary role-specific dummy records; confirm owner scoping and active/attendance metrics, then remove every fixture and restore baseline row counts.
- [x] **Approval checkpoint before Phase 7**

## Phase 7 - Vendor marketplace

- [x] Design and apply the approved, idempotent vendor profile, services, portfolio, availability, messages, and review schema in `sakthi`.
- [x] Add vendor registration, profile editing, services/packages, portfolio image management, and availability management.
- [x] Add a verified-vendor marketplace with category filtering, package details, upcoming availability, portfolio, and published review ratings.
- [x] Extend customer service requests into private vendor bookings without creating event registrations or attendance.
- [x] Add vendor quote/reject, customer accept/reject/cancel, and vendor completion workflows with booking status tracking.
- [x] Add customer/vendor booking messages and enforce booking-participant ownership.
- [x] Add completed-booking vendor reviews, admin moderation, and vendor review listings.
- [x] Add admin vendor verification and service-request oversight.
- [x] Add organizer dashboard/sidebar access to the verified-vendor marketplace and show current upcoming availability without changing booking permissions.
- [x] Let verified organizers request active vendor packages for their own upcoming events, manage incoming quotes and messages, and let vendors accept or reject requests using the existing booking workflow.
- [x] Test marketplace visibility, request submission, quote and acceptance, rejection, completion, two-party messaging, booking ownership denial, review submission/moderation, verification visibility, and admin oversight with temporary records; verify registration/attendance counts stay unchanged and remove all fixtures.
- [x] Rerun the Phase 7 migration successfully to verify idempotence and preservation of existing service requests.
- [x] **Approval checkpoint before Phase 8**

## Phase 8 - Final polish and deployment

- [x] Add a stable public `event.php?id=<event_id>` link for every published event, details pages with registration availability, and share/copy actions on public listings and published organizer/admin event rows; no database migration required.
- [x] Replace the page-shell `main` wrapper with a neutral container and correct shared footer asset paths for organizer/vendor pages.
- [x] Smoke-test public marketplace and vendor dashboard layouts at 375px; confirm no horizontal overflow and one main landmark.
- [x] Replace email-and-phone password changes with a one-time, 30-minute email reset link; store only token hashes, enforce CSRF, limit requests, invalidate used links, and avoid account-existence disclosure.
- [x] Obtain approval, create `password_reset_tokens` in `sakthi`, and verify the migration is idempotent.
- [x] Integration-test password reset, login with the new password, and rejection of a replayed reset token; remove the temporary account and token.
- [x] Add initial deployment and backup notes in [DEPLOYMENT.md](DEPLOYMENT.md).
- [x] Complete the defensive security review and remove both reported code-level findings.
- [ ] Configure `APP_BASE_URL`, `MAIL_FROM`, and PHP mail delivery; verify outbound reset email before deployment.
- [x] Restrict database access to the existing `sakthi` database; permit XAMPP root/blank-password access only for local Windows localhost and CLI development.
- [x] Centralize the local Windows XAMPP connection to the existing `sakthi` database in `includes/db.php`; remove the obsolete external private config.
- [x] Verify the `sakthi` database connection from Apache and the PHP CLI; the browser no longer shows the DB configuration error on this host.
- [x] Hide the business/organization-name field for customers; require it only for organizer/vendor signup, with server-side validation.
- [x] Verify signup in the browser and remove the temporary account; check desktop/mobile registration alignment and horizontal overflow.
- [x] Recover XAMPP's `mysql.db` from an intact archived Aria data file; rebuild/zerofill its index and verify a temporary grant/drop test.
- [x] Confirm all 31 XAMPP system tables and 19 `sakthi` application tables report `OK`; preserve baseline counts: 9 users, 3 registrations, 0 attendance rows, and 3 service requests.
- [x] Verify customer, organizer, vendor, and admin login redirects; exercise signup/profile creation for customer, organizer, and vendor.
- [x] Exercise customer/vendor booking from request through quote, acceptance, messaging, completion, review submission, and admin review moderation.
- [x] Smoke-test 30 public/protected routes without database configuration or connection errors; syntax-check all 58 project PHP files.
- [x] Remove temporary QA users, package, booking, messages, and reviews; verify baseline counts of 9 users, 3 registrations, 0 attendance rows, and 3 service requests.
- [ ] Configure a dedicated least-privilege account and environment settings on the deployment host.
- [ ] Replace the local-only XAMPP root connection with a dedicated least-privilege runtime account before production deployment, without storing production secrets in source control.
- [ ] Complete end-to-end attendance and event-question/reply workflows; only vendor-review submission/moderation was exercised in the latest browser pass.
- [ ] Complete cross-role integration coverage and production deployment checks after secure database and mail settings are configured.
