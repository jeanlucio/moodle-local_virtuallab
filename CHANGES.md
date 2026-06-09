# Changelog — Lab Virtual

## 4.0.0 (2026-06-09)

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
