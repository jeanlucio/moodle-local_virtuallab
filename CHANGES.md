# Changelog — Virtual Lab

## [v1.0.0] — 2026-06-16

Initial public release.

- Batch management: admins create batches linked to a dedicated Moodle category; each batch can have one or more responsible teachers
- Lab generation: generate up to 50 lab courses per batch from a configurable name prefix
- Student self-service panel: shows lab status (available / in use / full) and lets students enrol themselves as editor or visitor with one click; shareable URL per batch
- One-editor-anywhere rule: a student cannot hold editor slots in more than one lab per batch simultaneously
- Delegated teacher management: responsible teachers can rename their batch, manage co-teachers, set the lab prefix and override lifecycle settings, scoped to their own subcategory
- Lifecycle maintenance task: automatically resets or deletes overdue labs on a nightly schedule, with configurable thresholds per site and per batch
- Warning emails: responsible teachers are notified a configurable number of days before a lifecycle action runs
- Summary emails: after each maintenance run, each affected teacher receives a summary of what happened to their labs
- Visitor notice: a logged-in user who opens a managed lab without access sees the responsible teacher(s) to contact
- Audit events: lab reset, lab deleted, batch deleted
- PHPUnit integration test suite covering all core classes and the scheduled task
