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
use local_virtuallab\local\course_registry;
use local_virtuallab\local\enrolment_service;
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
$confirm  = optional_param('confirm', 0, PARAM_INT);

if ($action === 'enrol' && ($role === 'editor' || $role === 'visitor') && $courseid) {
    require_sesskey();

    $registry  = new course_registry();
    $lab       = $registry->get_lab_for_enrol($courseid, $batchid);
    $courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);

    if ($role === 'editor') {
        $service = new enrolment_service();

        // Fast pre-check so a full lab never reaches the confirmation page.
        $reason = $service->editor_block_reason($lab, $batch, $USER->id);
        if ($reason !== '') {
            redirect($viewurl, $service->error_message($reason), null, \core\output\notification::NOTIFY_ERROR);
        }

        // Becoming an editor commits the student to this lab (the one-editor rule blocks
        // the others), so confirm before the irreversible-by-self enrolment.
        if (!$confirm) {
            $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid], MUST_EXIST);
            $confirmurl = new moodle_url($viewurl, [
                'action'   => 'enrol',
                'role'     => 'editor',
                'courseid' => $courseid,
                'confirm'  => 1,
                'sesskey'  => sesskey(),
            ]);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirm_become_editor', 'local_virtuallab', format_string($coursename)),
                $confirmurl,
                $viewurl
            );
            echo $OUTPUT->footer();
            exit;
        }

        // Authoritative enrolment under a per-course lock; re-checks the cap inside it.
        $result = $service->enrol_editor($lab, $batch, $USER->id);
        if ($result !== '') {
            redirect($viewurl, $service->error_message($result), null, \core\output\notification::NOTIFY_ERROR);
        }

        redirect($courseurl);
    }

    // Visitor: no cap, straight enrolment through the manual instance.
    require_once($CFG->libdir . '/enrollib.php');
    $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
    $enrolinstance = $DB->get_record('enrol', ['id' => $lab->enrolid, 'courseid' => $courseid], '*', MUST_EXIST);
    enrol_get_plugin('manual')->enrol_user($enrolinstance, $USER->id, $studentroleid, time(), 0);

    redirect($courseurl);
}

if ($action === 'leave' && $courseid) {
    require_sesskey();

    $registry = new course_registry();
    $lab      = $registry->get_lab_for_enrol($courseid, $batchid);

    if (!$confirm) {
        $coursename = $DB->get_field('course', 'fullname', ['id' => $courseid], MUST_EXIST);
        $confirmurl = new moodle_url($viewurl, [
            'action'   => 'leave',
            'courseid' => $courseid,
            'confirm'  => 1,
            'sesskey'  => sesskey(),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirm_leave_lab', 'local_virtuallab', format_string($coursename)),
            $confirmurl,
            $viewurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    require_once($CFG->libdir . '/enrollib.php');
    $enrolinstance = $DB->get_record('enrol', ['id' => $lab->enrolid, 'courseid' => $courseid], '*', MUST_EXIST);
    $enrolplugin   = enrol_get_plugin('manual');
    $enrolplugin->unenrol_user($enrolinstance, $USER->id);

    redirect(
        $viewurl,
        get_string('left_lab', 'local_virtuallab'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
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
