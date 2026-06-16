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
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_virtuallab\local\batch_manager;
use local_virtuallab\local\batch_settings;
use local_virtuallab\local\course_registry;
use local_virtuallab\local\panel_repository;

$batchid = required_param('batchid', PARAM_INT);

$viewurl = new moodle_url('/local/virtuallab/view.php', ['batchid' => $batchid]);

require_login();

// The panel requires a real account to self-enrol into a lab; send guests to log in.
if (isguestuser()) {
    $SESSION->wantsurl = $viewurl->out(false);
    redirect(new moodle_url('/login/index.php'));
}

$context = context_system::instance();
require_capability('local/virtuallab:view', $context);

$batchmgr = new batch_manager();
$batch    = $batchmgr->get_batch($batchid);

$PAGE->set_context($context);
$PAGE->set_url($viewurl);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(format_string($batch->name));
$PAGE->set_heading(format_string($batch->name));

// Enrolment action.
$action   = optional_param('action', '', PARAM_ALPHA);
$role     = optional_param('role', '', PARAM_ALPHA);
$courseid = optional_param('courseid', 0, PARAM_INT);

if ($action === 'enrol' && ($role === 'editor' || $role === 'visitor') && $courseid) {
    require_sesskey();

    $registry = new course_registry();
    $lab      = $registry->get_lab_for_enrol($courseid, $batchid);

    $isteacher = ($role === 'editor');
    $roleid    = $DB->get_field(
        'role',
        'id',
        ['shortname' => $isteacher ? 'editingteacher' : 'student'],
        MUST_EXIST
    );

    if ($isteacher) {
        $maxteachers   = batch_settings::effective($batch)->maxteachers;
        $coursecontext = context_course::instance($courseid);
        $editors       = get_enrolled_users($coursecontext, 'moodle/course:update');

        if (count($editors) >= $maxteachers) {
            redirect(
                $viewurl,
                get_string('error_lab_full', 'local_virtuallab'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        $editorsql = "SELECT ra.id
                        FROM {local_virtuallab_courses} lc
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
            'roleid'        => $roleid,
            'batchid'       => $batchid,
            'currentcourse' => $courseid,
        ]);

        if ($iseditorelsewhere) {
            redirect(
                $viewurl,
                get_string('error_already_editor_in_batch', 'local_virtuallab'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    }

    require_once($CFG->libdir . '/enrollib.php');
    $enrolinstance = $DB->get_record('enrol', ['id' => $lab->enrolid, 'courseid' => $courseid], '*', MUST_EXIST);
    $enrolplugin   = enrol_get_plugin('manual');
    $enrolplugin->enrol_user($enrolinstance, $USER->id, $roleid, time(), 0);

    $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
    redirect($courseurl);
}

// Render panel.
$teachers     = $batchmgr->get_teachers($batchid);
$teachernames = implode(', ', array_map(fn($teacher) => fullname($teacher), $teachers));

$repository = new panel_repository();
$labs       = $repository->get_panel_data($batchid, $USER->id);

// Only batch managers (admin or the responsible teachers) get the "view course" link;
// they can open the lab courses without enrolling.
$canmanage = has_capability(
    'local/virtuallab:manage',
    context_coursecat::instance($batch->categoryid)
);

$renderer = $PAGE->get_renderer('local_virtuallab');

echo $OUTPUT->header();
echo $renderer->render_labs_panel($batch, $teachernames, $labs, $batchid, $canmanage);
echo $OUTPUT->footer();
