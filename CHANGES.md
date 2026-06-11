# Changelog — Lab Virtual

## 0.5.0-alpha (2026-06-11)

- Lifecycle warning emails: the responsible teacher is notified a configurable number of days before a lab is reset or deleted (new "Warning days before action" setting, default 7; 0 disables)
- Post-action summary emails sent to each responsible teacher and to the site administrator after the maintenance task runs
- Lifecycle emails include a direct link: the batch panel for teachers and the management page for the administrator
- Fixed capability language string keys (`labvirtual:view` / `labvirtual:manage`) so failed permission checks render the standard message instead of a fatal error
- The student panel now sends guests to the login page (returning to the panel afterwards) instead of failing a capability check
- Removed enrolment key display from the student panel: enrolment is automatic via the panel buttons, so the keys are no longer shown to anyone (they remain set on the enrol instances to prevent direct self-enrolment). The "Show enrolment keys in student panel" setting was removed.
- Removed enrolment keys entirely: lab courses are now created with self-enrolment disabled (newenrols = 0) and no enrolment key, so the "Create labs" form no longer asks for editor/visitor keys. The panel enrols users programmatically. An upgrade step normalises existing labs.
- A batch can now have more than one responsible teacher. The batch form accepts multiple teachers, the panel shows all of them, and lifecycle emails are sent to every responsible teacher. A new join table replaces the single teacher column (existing batches are migrated automatically).
- Lifecycle warning and summary emails are now also sent to the editors of each affected course (one email per editor, listing their own labs). The administrator copy is now optional via the new "Send a copy to the administrator" setting (off by default).
- Labs now enrol users through the course manual enrolment instance instead of two self-enrolment instances. The panel chooses the role (editor or visitor) at enrolment time, and the standard course "enrolment options" page no longer shows the confusing disabled key blocks. The single teacher_enrolid/student_enrolid columns become one enrolid (existing labs migrated). The self-enrolment observer was removed as it is no longer reachable.
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
