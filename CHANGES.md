# Changelog — Lab Virtual

## 0.5.0-alpha (2026-06-11)

- Lifecycle warning emails: the responsible teacher is notified a configurable number of days before a lab is reset or deleted (new "Warning days before action" setting, default 7; 0 disables)
- Post-action summary emails sent to each responsible teacher and to the site administrator after the maintenance task runs
- Lifecycle emails include a direct link: the batch panel for teachers and the management page for the administrator
- Fixed capability language string keys (`labvirtual:view` / `labvirtual:manage`) so failed permission checks render the standard message instead of a fatal error
- The student panel now sends guests to the login page (returning to the panel afterwards) instead of failing a capability check
- New `lastwarn` field on managed labs tracks the warning sent per lifecycle cycle; it is cleared on reset so a fresh warning is issued next cycle

## 0.4.0-alpha (2026-06-09)

- Initial release
- Batch creation: generates N lab courses with dual self-enrolment instances (editor + visitor key)
- Admin management panel: create batches and labs, reset and delete individually or in bulk
- Self-service student panel with lab status (available / in use / full) and one-click enrolment
- One-editor-anywhere rule: prevents a student from holding multiple editor slots in the same batch
- Shareable student panel URL per batch
- Nightly lifecycle maintenance task: configurable reset or delete of overdue labs
- Ownership guard on all write operations
- Audit events: course reset, course deleted, batch deleted
- PHPUnit integration test suite (26 tests) covering all core classes and the scheduled task
