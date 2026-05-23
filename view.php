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
 * Student self-service panel — choose and access a lab sandbox.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_labvirtual\local\batch_manager;
use local_labvirtual\local\course_registry;
use local_labvirtual\local\panel_repository;

$batchid = required_param('batchid', PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/labvirtual:view', $context);

$batchmgr = new batch_manager();
$batch    = $batchmgr->get_batch($batchid);

$viewurl = new moodle_url('/local/labvirtual/view.php', ['batchid' => $batchid]);
$PAGE->set_context($context);
$PAGE->set_url($viewurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($batch->name));
$PAGE->set_heading(format_string($batch->name));

// Enrolment action.
$action   = optional_param('action', '', PARAM_ALPHA);
$enrolid  = optional_param('enrolid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

if ($action === 'enrol' && $enrolid && $courseid) {
    require_sesskey();

    $registry = new course_registry();
    $lab      = $registry->validate_enrol_instance($enrolid, $courseid, $batchid);

    $isteacher = ($enrolid === (int) $lab->teacher_enrolid);

    if ($isteacher) {
        $maxteachers = (int) get_config('local_labvirtual', 'max_teachers_per_lab') ?: 3;
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $coursecontext = context_course::instance($courseid);
        $editors       = get_enrolled_users($coursecontext, 'moodle/course:update');

        if (count($editors) >= $maxteachers) {
            redirect(
                $viewurl,
                get_string('error_lab_full', 'local_labvirtual'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    require_once($CFG->libdir . '/enrollib.php');
    $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid, 'courseid' => $courseid], '*', MUST_EXIST);
    $enrolplugin   = enrol_get_plugin('self');
    $enrolplugin->enrol_user($enrolinstance, $USER->id, $enrolinstance->roleid, time(), 0);

    $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    redirect($courseurl);
}

// Render panel.
$teacher = $DB->get_record('user', ['id' => $batch->teacherid], 'id, firstname, lastname', MUST_EXIST);
$batchforrender = clone $batch;
$batchforrender->firstname = $teacher->firstname;
$batchforrender->lastname  = $teacher->lastname;

$repository = new panel_repository();
$labs       = $repository->get_panel_data($batchid);

$renderer = $PAGE->get_renderer('local_labvirtual');

echo $OUTPUT->header();
echo $renderer->render_labs_panel($batchforrender, $labs, $batchid);
echo $OUTPUT->footer();
