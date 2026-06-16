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

$string['access_as_editor'] = 'Access {$a} as editor';
$string['access_as_visitor'] = 'Access {$a} as visitor';
$string['access_via_teacher'] = 'Access to this lab is provided by the responsible teacher(s): {$a}. Please contact them to be enrolled.';
$string['already_enrolled'] = 'Enrolled';
$string['batch_category'] = 'Destination category';
$string['batch_created'] = 'Batch created successfully.';
$string['batch_deleted'] = 'Batch and all its labs deleted successfully.';
$string['batch_name'] = 'Batch name';
$string['batch_nameprefix'] = 'Lab name prefix';
$string['batch_override_default'] = 'Use site default';
$string['batch_overrides'] = 'Per-batch overrides (optional)';
$string['batch_overrides_help'] = 'Leave a field empty to use the site default for this batch.';
$string['batch_teacher'] = 'Responsible teacher';
$string['batch_updated'] = 'Batch updated successfully';
$string['bulk_action'] = 'With selected';
$string['bulk_action_delete'] = 'Delete';
$string['bulk_action_reset'] = 'Reset';
$string['confirm_bulk_delete'] = 'Are you sure you want to delete {$a} selected lab(s)? All courses and their content will be permanently removed. This action cannot be undone.';
$string['confirm_bulk_reset'] = 'Are you sure you want to reset {$a} selected lab(s)? All user data and activities will be cleared.';
$string['confirm_delete_batch'] = 'Are you sure you want to delete the batch <strong>{$a}</strong> and ALL its labs? All courses and their content will be permanently removed. This action cannot be undone.';
$string['confirm_delete_lab'] = 'Are you sure you want to delete lab <strong>{$a}</strong>? The course and all its content will be permanently removed.';
$string['confirm_reset_lab'] = 'Are you sure you want to reset lab <strong>{$a}</strong>? All user data and activities will be cleared.';
$string['copy_panel_link'] = 'Copy link';
$string['create_batch'] = 'New batch';
$string['create_labs'] = 'Create labs';
$string['currenteditors'] = 'Current editors';
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
$string['email_warning_editor_body'] = 'The lab(s) you are editing below will be {$a->action} by {$a->date}. Please save anything you need before that date.';
$string['email_warning_subject'] = 'Virtual Lab — your labs will be {$a->action} in {$a->days} day(s)';
$string['enrol_join'] = 'Enrol and join';
$string['error_already_editor_in_batch'] = 'You are already enrolled as an editor in another lab in this batch.';
$string['error_batch_not_found'] = 'Batch not found or does not belong to this site.';
$string['error_course_not_managed'] = 'This course is not managed by Virtual Lab.';
$string['error_enrol_mismatch'] = 'Enrollment instance does not match the requested lab.';
$string['error_lab_full'] = 'This lab has reached the maximum number of editors.';
$string['error_no_labs_selected'] = 'No labs selected. Please select at least one lab.';
$string['error_too_many_labs'] = 'You can create at most {$a} labs at once.';
$string['eventbatchdeleted'] = 'Batch deleted';
$string['eventbatchdeleted_desc'] = 'The batch with id {$a->batchid} was deleted ({$a->labcount} lab(s) removed).';
$string['eventcoursedeleted'] = 'Lab course deleted';
$string['eventcoursedeleted_desc'] = 'The lab course {$a->courseid} was deleted from batch {$a->batchid}.';
$string['eventcoursereset'] = 'Lab course reset';
$string['eventcoursereset_desc'] = 'The lab course {$a->courseid} was reset in batch {$a->batchid}.';
$string['lab_available'] = 'Available';
$string['lab_count'] = 'Number of labs';
$string['lab_deleted'] = 'Lab deleted successfully.';
$string['lab_full'] = 'Full';
$string['lab_in_use'] = 'In use';
$string['lab_reset'] = 'Lab reset successfully.';
$string['labs_bulk_deleted'] = '{$a} lab(s) deleted successfully.';
$string['labs_bulk_reset'] = '{$a} lab(s) reset successfully.';
$string['labs_created'] = '{$a} lab(s) created successfully.';
$string['lastreset'] = 'Last reset';
$string['manage_batches'] = 'Manage Virtual Lab';
$string['manage_labs'] = 'Manage labs — {$a}';
$string['nobatches'] = 'No batches found. Click the button below to add a new batch.';
$string['nolabs'] = 'No labs in this batch yet.';
$string['panel_url'] = 'Student panel URL';
$string['panel_url_copied'] = 'URL copied to clipboard.';
$string['panel_url_help'] = 'Share this URL with students so they can access and choose their lab sandbox.';
$string['parentcategory'] = 'Virtual labs';
$string['pluginname'] = 'Virtual Lab';
$string['privacy:metadata'] = 'The Virtual Lab plugin does not store any personal data directly. Course and enrolment records are managed by Moodle core.';
$string['reset_lab'] = 'Reset';
$string['reset_lab_label'] = 'Reset lab: {$a}';
$string['role_batchmanager'] = 'Virtual Lab batch manager';
$string['role_batchmanager_desc'] = 'Gives a responsible teacher full management of the courses in their own batch category (edit content, grades and enrolments, without enrolling) plus the Virtual Lab batch tools.';
$string['save_batch'] = 'Save batch';
$string['settings_lifecycle'] = 'Lifecycle';
$string['settings_lifecycle_action'] = 'Automatic action';
$string['settings_lifecycle_action_delete'] = 'Delete';
$string['settings_lifecycle_action_desc'] = 'Action to perform on overdue labs. Set to "None" to disable automatic processing.';
$string['settings_lifecycle_action_none'] = 'None (disabled)';
$string['settings_lifecycle_action_reset'] = 'Reset';
$string['settings_lifecycle_months'] = 'Lifecycle (months)';
$string['settings_lifecycle_months_desc'] = 'Number of months before a lab is considered overdue for automatic action. The threshold is measured from the last reset date, or from the creation date if the lab has never been reset. Set to 0 to disable.';
$string['settings_max_teachers'] = 'Maximum editors per lab';
$string['settings_max_teachers_desc'] = 'Maximum number of users with the editingteacher role allowed per lab before it is marked as full. Default: 3.';
$string['settings_notify_admin'] = 'Send a copy to the administrator';
$string['settings_notify_admin_desc'] = 'When enabled, the site administrator also receives a consolidated copy of the lifecycle warning and summary emails.';
$string['settings_warning_days'] = 'Warning days before action';
$string['settings_warning_days_desc'] = 'Number of days before the lifecycle action when a warning email is sent to the responsible teacher. Set to 0 to disable warning emails.';
$string['task_maintenance'] = 'Virtual Lab — lifecycle maintenance';
$string['view_course'] = 'View course';
$string['view_course_label'] = 'View course: {$a}';
$string['view_panel'] = 'Student panel';
$string['virtuallab:manage'] = 'Manage Virtual Lab (create batches and labs, reset, delete)';
$string['virtuallab:view'] = 'View Virtual Lab student panel';
