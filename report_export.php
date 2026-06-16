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
 * Data export entry point for the consolidated lab usage report.
 *
 * Level 1 (no labid): full batch enrolment overview.
 * Level 2 (labid set): per-student event breakdown for a specific lab.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_registry;
use local_virtuallab\local\report_exporter;

$batchid = required_param('batchid', PARAM_INT);
$labid   = optional_param('labid', 0, PARAM_INT);
$format  = optional_param('format', 'csv', PARAM_ALPHANUMEXT);

require_login();
$PAGE->set_context(context_system::instance());

$batchmgr = new batch_manager();
$batch    = $batchmgr->get_batch($batchid);

require_capability('local/virtuallab:manage', context_coursecat::instance($batch->categoryid));

// Note: require_sesskey() is intentionally omitted here because data export
// is a read-only GET request and does not modify database state.

$exporter = new report_exporter();

if ($labid) {
    $registry = new course_registry();
    $lab      = $registry->get_lab_for_batch($labid, $batchid);
    $exporter->export_lab_detail($batch, $lab, $batchid, $format);
} else {
    $exporter->export_batch($batch, $batchid, $format);
}
