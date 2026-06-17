<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for local_virtuallab.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['access_as_editor'] = 'Access {$a} as slot holder';
$string['access_as_visitor'] = 'Access {$a} as visitor';
$string['access_via_teacher'] = 'Access to this lab is provided by the responsible teacher(s): {$a}. Please contact them to be enrolled.';
$string['already_enrolled'] = 'Enrolled';
$string['apply_checklist_existing'] = 'Apply task list to existing labs';
$string['batch_category'] = 'Destination category';
$string['batch_checklist_tasks'] = 'Lab task list';
$string['batch_checklist_tasks_help'] = 'Tasks entered here, one per line, are added to the Teacher Checklist block of every lab created afterwards. Use "Apply task list to existing labs" to push changes to labs that already exist. Leave empty to disable.';
$string['batch_created'] = 'Batch created successfully.';
$string['batch_deleted'] = 'Batch and all its labs deleted successfully.';
$string['batch_lifecycle_recount_note'] = 'Changing the lifecycle policy below restarts the countdown from today for every lab in this batch, so existing labs are never reset or deleted without notice.';
$string['batch_lifecycle_zero_hint'] = '(0 = disabled)';
$string['batch_name'] = 'Batch name';
$string['batch_nameprefix'] = 'Lab name prefix';
$string['batch_override_default'] = 'Use site default';
$string['batch_override_default_hint'] = 'Use site default ({$a})';
$string['batch_override_placeholder'] = 'Default: {$a}';
$string['batch_overrides'] = 'Per-batch overrides (optional)';
$string['batch_overrides_help'] = 'Leave a field empty to use the site default for this batch.';
$string['batch_teacher'] = 'Responsible teacher';
$string['batch_updated'] = 'Batch updated successfully';
$string['bulk_action'] = 'With selected';
$string['bulk_action_delete'] = 'Delete';
$string['bulk_action_reset'] = 'Reset';
$string['cannot_editor_elsewhere'] = 'You are already an editor in another lab in this batch.';
$string['checklist_synced'] = 'Task list applied to {$a} lab(s).';
$string['column_environment'] = 'Environment';
$string['column_lab'] = 'Lab';
$string['column_labs'] = 'Labs';
$string['column_shortname'] = 'Shortname';
$string['column_status'] = 'Status';
$string['confirm_become_editor'] = 'Become the editor of "{$a}"? You will not be able to edit another lab in this batch until you leave this one.';
$string['confirm_bulk_delete'] = 'Are you sure you want to delete {$a} selected lab(s)? All courses and their content will be permanently removed. This action cannot be undone.';
$string['confirm_bulk_reset'] = 'Are you sure you want to reset {$a} selected lab(s)? All user data and activities will be cleared.';
$string['confirm_delete_batch'] = 'Are you sure you want to delete the batch <strong>{$a}</strong> and ALL its labs? All courses and their content will be permanently removed. This action cannot be undone.';
$string['confirm_delete_lab'] = 'Are you sure you want to delete lab <strong>{$a}</strong>? The course and all its content will be permanently removed.';
$string['confirm_leave_lab'] = 'Leave "{$a}"? You will lose your access to this lab; if you were its editor, the slot is freed for another student.';
$string['confirm_reset_lab'] = 'Are you sure you want to reset lab <strong>{$a}</strong>? All user data and activities will be cleared.';
$string['copy_panel_link'] = 'Copy link';
$string['create_batch'] = 'New batch';
$string['create_labs'] = 'Create labs';
$string['currenteditors'] = 'Current slot holders';
$string['delete_batch'] = 'Delete batch';
$string['delete_batch_label'] = 'Delete batch: {$a}';
$string['delete_lab'] = 'Delete';
$string['delete_lab_label'] = 'Delete lab: {$a}';
$string['edit_batch'] = 'Edit batch';
$string['email_action_delete'] = 'deleted';
$string['email_action_reset'] = 'reset';
$string['email_manage_link'] = 'Open Virtual Lab management';
$string['email_panel_link'] = 'Open the lab panel';
$string['email_summary_body'] = 'The automatic lifecycle maintenance has just run. The labs below were processed:';
$string['email_summary_editor_body'] = 'The automatic lifecycle maintenance has just run. The lab(s) you were editing were processed:';
$string['email_summary_failed'] = 'failed';
$string['email_summary_ok'] = 'done';
$string['email_summary_subject'] = 'Virtual Lab — maintenance summary';
$string['email_warning_body'] = 'The labs below will be {$a->action} by {$a->date} under the automatic lifecycle policy. Access or reset them before that date if you still need their content.';
$string['email_warning_body_multi'] = 'The labs below will be {$a->action} automatically under the lifecycle policy, each on the date shown. Access or reset them before then if you still need their content.';
$string['email_warning_editor_body'] = 'The lab(s) you are editing below will be {$a->action} by {$a->date}. Please save anything you need before that date.';
$string['email_warning_editor_body_multi'] = 'The lab(s) you are editing below will be {$a->action} automatically, each on the date shown. Please save anything you need before then.';
$string['email_warning_subject'] = 'Virtual Lab — your labs will be {$a->action} in {$a->days} day(s)';
$string['enrol_join'] = 'Enrol and join';
$string['error_already_editor_in_batch'] = 'You are already enrolled as a slot holder in another lab in this batch.';
$string['error_batch_not_found'] = 'Batch not found or does not belong to this site.';
$string['error_course_not_managed'] = 'This course is not managed by Virtual Lab.';
$string['error_enrol_busy'] = 'This lab is being updated by another request. Please try again.';
$string['error_enrol_mismatch'] = 'Enrollment instance does not match the requested lab.';
$string['error_lab_full'] = 'This lab has reached the maximum number of slot holders.';
$string['error_no_labs_selected'] = 'No labs selected. Please select at least one lab.';
$string['error_too_many_labs'] = 'You can create at most {$a} labs at once.';
$string['eventbatchdeleted'] = 'Batch deleted';
$string['eventbatchdeleted_desc'] = 'The batch with id {$a->batchid} was deleted ({$a->labcount} lab(s) removed).';
$string['eventcoursedeleted'] = 'Lab course deleted';
$string['eventcoursedeleted_desc'] = 'The lab course {$a->courseid} was deleted from batch {$a->batchid}.';
$string['eventcoursereset'] = 'Lab course reset';
$string['eventcoursereset_desc'] = 'The lab course {$a->courseid} was reset in batch {$a->batchid}.';
$string['export_csv'] = 'Export CSV';
$string['export_excel'] = 'Export Excel';
$string['lab_available'] = 'Available';
$string['lab_count'] = 'Number of labs';
$string['lab_deleted'] = 'Lab deleted successfully.';
$string['lab_full'] = 'Full';
$string['lab_in_use'] = 'In use';
$string['lab_reset'] = 'Lab reset successfully.';
$string['lab_slots_left'] = '{$a} spots left';
$string['lab_slots_left_one'] = '1 spot left';
$string['labs_bulk_deleted'] = '{$a} lab(s) deleted successfully.';
$string['labs_bulk_reset'] = '{$a} lab(s) reset successfully.';
$string['labs_created'] = '{$a} lab(s) created successfully.';
$string['lastreset'] = 'Last reset';
$string['leave_lab'] = 'Leave';
$string['leave_lab_label'] = 'Leave lab: {$a}';
$string['left_lab'] = 'You have left the lab.';
$string['manage_batches'] = 'Manage Virtual Lab';
$string['manage_labs'] = 'Manage labs — {$a}';
$string['message_batch_assigned_body'] = 'You have been assigned as responsible teacher for the batch "{$a}". You can now manage its labs using the link below.';
$string['message_batch_assigned_small'] = 'You have been assigned as responsible teacher for a Virtual Lab batch.';
$string['message_batch_assigned_subject'] = 'Virtual Lab: you have been assigned to batch "{$a}"';
$string['messageprovider:batch_assigned'] = 'Assigned as Virtual Lab batch teacher';
$string['next_action'] = 'Next action';
$string['next_action_delete'] = 'Delete on {$a}';
$string['next_action_reset'] = 'Reset on {$a}';
$string['nobatches'] = 'No batches found. Click the button below to add a new batch.';
$string['nolabs'] = 'No labs in this batch yet.';
$string['panel_url'] = 'Student panel URL';
$string['panel_url_copied'] = 'URL copied to clipboard.';
$string['panel_url_help'] = 'Share this URL with students so they can access and choose their lab sandbox.';
$string['panel_url_qrcode'] = 'Student panel QR code';
$string['parentcategory'] = 'Virtual labs';
$string['pluginname'] = 'Virtual Lab';
$string['privacy:metadata'] = 'The Virtual Lab plugin does not store any personal data directly. Course and enrolment records are managed by Moodle core.';
$string['report'] = 'Report';
$string['report_action_attempted'] = 'attempted';
$string['report_action_created'] = 'created';
$string['report_action_deleted'] = 'deleted';
$string['report_action_downloaded'] = 'downloaded';
$string['report_action_failed'] = 'failed';
$string['report_action_finished'] = 'finished';
$string['report_action_graded'] = 'graded';
$string['report_action_started'] = 'started';
$string['report_action_submitted'] = 'submitted';
$string['report_action_updated'] = 'updated';
$string['report_action_uploaded'] = 'uploaded';
$string['report_action_viewed'] = 'viewed';
$string['report_back_to_batch'] = 'Back to batch report';
$string['report_col_action'] = 'Action';
$string['report_col_component'] = 'Module';
$string['report_col_count'] = 'Count';
$string['report_col_enrolled_at'] = 'Enrolled at';
$string['report_col_events'] = 'Events';
$string['report_col_lab'] = 'Lab';
$string['report_col_last_access'] = 'Last access';
$string['report_col_last_time'] = 'Last time';
$string['report_col_role'] = 'Role';
$string['report_col_student'] = 'Student';
$string['report_component_core'] = 'Course';
$string['report_heading'] = 'Lab usage report: {$a}';
$string['report_lab_detail_heading'] = 'Lab detail: {$a}';
$string['report_never'] = 'Never';
$string['report_no_activity'] = 'No activity recorded';
$string['report_no_enrolments'] = 'No students enrolled in any lab yet.';
$string['report_role_editor'] = 'Slot holder';
$string['report_role_visitor'] = 'Visitor';
$string['report_view_report'] = 'Report';
$string['report_view_report_label'] = 'View usage report: {$a}';
$string['reset_lab'] = 'Reset';
$string['reset_lab_label'] = 'Reset lab: {$a}';
$string['role_batchmanager'] = 'Virtual Lab batch manager';
$string['role_batchmanager_desc'] = 'Gives a responsible teacher full management of the courses in their own batch category (edit content, grades and enrolments, without enrolling) plus the Virtual Lab batch tools.';
$string['save_batch'] = 'Save batch';
$string['scheduled_action_delete'] = 'Scheduled: delete on {$a}';
$string['scheduled_action_reset'] = 'Scheduled: reset on {$a}';
$string['settings_lifecycle'] = 'Lifecycle';
$string['settings_lifecycle_action'] = 'Automatic action';
$string['settings_lifecycle_action_delete'] = 'Delete';
$string['settings_lifecycle_action_desc'] = 'Action to perform on overdue labs. Set to "None" to disable automatic processing.';
$string['settings_lifecycle_action_none'] = 'None (disabled)';
$string['settings_lifecycle_action_reset'] = 'Reset';
$string['settings_lifecycle_months'] = 'Lifecycle (months)';
$string['settings_lifecycle_months_desc'] = 'Number of months before a lab is considered overdue for automatic action. The threshold is measured from the last reset date, or from the creation date if the lab has never been reset. Set to 0 to disable.';
$string['settings_max_teachers'] = 'Maximum slot holders per lab';
$string['settings_max_teachers_desc'] = 'Maximum number of slot holders allowed per lab before it is marked as full. Default: 3.';
$string['settings_notify_admin'] = 'Send a copy to the administrator';
$string['settings_notify_admin_desc'] = 'When enabled, the site administrator also receives a consolidated copy of the lifecycle warning and summary emails.';
$string['settings_warning_days'] = 'Warning days before action';
$string['settings_warning_days_desc'] = 'Number of days before the lifecycle action when a warning email is sent to the responsible teacher. Set to 0 to disable warning emails.';
$string['show_qrcode'] = 'Show QR code';
$string['task_maintenance'] = 'Virtual Lab — lifecycle maintenance';
$string['view_course'] = 'View course';
$string['view_course_label'] = 'View course: {$a}';
$string['view_panel'] = 'Student panel';
$string['virtuallab:manage'] = 'Manage Virtual Lab (create batches and labs, reset, delete)';
$string['virtuallab:view'] = 'View Virtual Lab student panel';
