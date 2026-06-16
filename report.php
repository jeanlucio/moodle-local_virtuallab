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
 * Consolidated usage report for a Lab Virtual batch.
 *
 * Level 1 (no labid): paginated enrolment table for all labs in the batch.
 * Level 2 (labid set): per-student event summary for a specific lab.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_registry;
use local_virtuallab\local\report_repository;

$batchid = required_param('batchid', PARAM_INT);
$labid   = optional_param('labid', 0, PARAM_INT);
$page    = optional_param('page', 0, PARAM_INT);

require_login();

$batchmgr = new batch_manager();
$batch    = $batchmgr->get_batch($batchid);

require_capability('local/virtuallab:manage', context_coursecat::instance($batch->categoryid));

$manageurl = new moodle_url('/local/virtuallab/manage.php', ['batchid' => $batchid]);
$reporturl = new moodle_url('/local/virtuallab/report.php', ['batchid' => $batchid]);

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_url($reporturl);

$renderer   = $PAGE->get_renderer('local_virtuallab');
$repository = new report_repository();

// Level 2: detail for a single lab.
if ($labid) {
    $registry = new course_registry();
    $lab      = $registry->get_lab_for_batch($labid, $batchid);

    $detailurl = new moodle_url('/local/virtuallab/report.php', ['batchid' => $batchid, 'labid' => $labid]);

    $PAGE->set_url($detailurl);
    $PAGE->set_title(get_string('report_lab_detail_heading', 'local_virtuallab', format_string($lab->coursename)));
    $PAGE->set_heading(get_string('report_lab_detail_heading', 'local_virtuallab', format_string($lab->coursename)));
    $PAGE->navbar->add(get_string('manage_batches', 'local_virtuallab'), new moodle_url('/local/virtuallab/manage.php'));
    $PAGE->navbar->add(format_string($batch->name), $manageurl);
    $PAGE->navbar->add(get_string('report', 'local_virtuallab'), $reporturl);
    $PAGE->navbar->add(format_string($lab->coursename));

    $enrolments = $repository->get_lab_enrolments($labid, $batchid);

    $userids  = array_map(fn($r) => (int) $r->userid, $enrolments);
    $courseid = (int) $lab->courseid;

    $rolemap      = $repository->get_role_map([$courseid], $userids);
    $breakdown    = $repository->get_lab_event_breakdown($courseid, $userids);

    echo $OUTPUT->header();
    echo $renderer->render_report_lab_detail($batch, $lab, $enrolments, $rolemap, $breakdown);
    echo $OUTPUT->footer();
    exit;
}

// Level 1: paginated batch overview.
$heading = get_string('report_heading', 'local_virtuallab', format_string($batch->name));

$PAGE->set_title($heading);
$PAGE->set_heading($heading);
$PAGE->navbar->add(get_string('manage_batches', 'local_virtuallab'), new moodle_url('/local/virtuallab/manage.php'));
$PAGE->navbar->add(format_string($batch->name), $manageurl);
$PAGE->navbar->add(get_string('report', 'local_virtuallab'));

$total      = $repository->count_batch_enrolments($batchid);
$enrolments = $repository->get_batch_enrolments($batchid, $page);

$courseids = array_unique(array_map(fn($r) => (int) $r->courseid, $enrolments));
$userids   = array_unique(array_map(fn($r) => (int) $r->userid, $enrolments));

$logsummary = $repository->get_log_summary($courseids, $userids);
$rolemap    = $repository->get_role_map($courseids, $userids);

echo $OUTPUT->header();
echo $renderer->render_report_batch($batch, $enrolments, $rolemap, $logsummary, $batchid);
echo $OUTPUT->paging_bar($total, $page, report_repository::PER_PAGE, $reporturl);
echo $OUTPUT->footer();
