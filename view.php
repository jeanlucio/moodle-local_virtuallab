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
        $maxteachers   = (int) get_config('local_labvirtual', 'max_teachers_per_lab') ?: 3;
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

        $editorsql = "SELECT ra.id
                        FROM {local_labvirtual_courses} lc
                        JOIN {context} ctx ON ctx.instanceid  = lc.courseid
                                         AND ctx.contextlevel = :ctxlevel
                        JOIN {role_assignments} ra ON ra.contextid = ctx.id
                                                  AND ra.userid    = :userid
                                                  AND ra.roleid    = :roleid
                       WHERE lc.batchid   = :batchid
                         AND lc.courseid != :currentcourse";

        $iseditorelsewhere = $DB->record_exists_sql($editorsql, [
            'ctxlevel'      => CONTEXT_COURSE,
            'userid'        => $USER->id,
            'roleid'        => $teacherroleid,
            'batchid'       => $batchid,
            'currentcourse' => $courseid,
        ]);

        if ($iseditorelsewhere) {
            redirect(
                $viewurl,
                get_string('error_already_editor_in_batch', 'local_labvirtual'),
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
$userfields  = 'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename';
$teacher     = $DB->get_record('user', ['id' => $batch->teacherid], $userfields, MUST_EXIST);
$batchforrender = clone $batch;
$batchforrender->firstname         = $teacher->firstname;
$batchforrender->lastname          = $teacher->lastname;
$batchforrender->firstnamephonetic = $teacher->firstnamephonetic;
$batchforrender->lastnamephonetic  = $teacher->lastnamephonetic;
$batchforrender->middlename        = $teacher->middlename;
$batchforrender->alternatename     = $teacher->alternatename;

$repository = new panel_repository();
$labs       = $repository->get_panel_data($batchid, $USER->id);

$renderer = $PAGE->get_renderer('local_labvirtual');

echo $OUTPUT->header();
echo $renderer->render_labs_panel($batchforrender, $labs, $batchid);
echo $OUTPUT->footer();
