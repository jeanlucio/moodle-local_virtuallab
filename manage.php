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
 * Admin management page for Lab Virtual batches and labs.
 *
 * Level 1 (no batchid): list of batches + create/delete batch.
 * Level 2 (batchid set): labs of a batch + create/reset/delete labs + bulk actions.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_labvirtual\form\create_batch_form;
use local_labvirtual\form\create_labs_form;
use local_labvirtual\local\batch_manager;
use local_labvirtual\local\course_factory;
use local_labvirtual\local\course_registry;
use local_labvirtual\local\maintenance_service;

$batchid    = optional_param('batchid', 0, PARAM_INT);
$action     = optional_param('action', '', PARAM_ALPHAEXT);
$labid      = optional_param('labid', 0, PARAM_INT);
$confirm    = optional_param('confirm', 0, PARAM_INT);
$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$labids     = optional_param_array('labids', [], PARAM_INT);

require_login();
$context = context_system::instance();
require_capability('local/labvirtual:manage', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

$manageurl = new moodle_url('/local/labvirtual/manage.php');

// Level 2: labs within a batch.
if ($batchid) {
    $batchmgr  = new batch_manager();
    $batch     = $batchmgr->get_batch($batchid);
    $level2url = new moodle_url('/local/labvirtual/manage.php', ['batchid' => $batchid]);
    $createurl = new moodle_url(
        '/local/labvirtual/manage.php',
        ['batchid' => $batchid, 'action' => 'createlabs']
    );

    $PAGE->set_url($level2url);
    $PAGE->set_title(get_string('manage_labs', 'local_labvirtual', format_string($batch->name)));
    $PAGE->set_heading(get_string('manage_labs', 'local_labvirtual', format_string($batch->name)));
    $PAGE->navbar->add(get_string('manage_batches', 'local_labvirtual'), $manageurl);
    $PAGE->navbar->add(format_string($batch->name));

    // Create labs.
    if ($action === 'createlabs') {
        $form = new create_labs_form($createurl->out(false));
        $form->set_data(['batchid' => $batchid]);

        if ($form->is_cancelled()) {
            redirect($level2url);
        }

        if ($data = $form->get_data()) {
            require_sesskey();
            $factory = new course_factory();
            $created = $factory->create_labs(
                (int) $data->batchid,
                (int) $data->labcount,
                $data->teacherkey,
                $data->studentkey
            );
            redirect(
                $level2url,
                get_string('labs_created', 'local_labvirtual', count($created)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        echo $OUTPUT->header();
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    // Reset single lab.
    if ($action === 'resetlab' && $labid) {
        $registry = new course_registry();
        $lab      = $registry->get_lab_for_batch($labid, $batchid);

        if (!$confirm) {
            $confirmurl = new moodle_url('/local/labvirtual/manage.php', [
                'batchid' => $batchid,
                'action'  => 'resetlab',
                'labid'   => $labid,
                'confirm' => 1,
                'sesskey' => sesskey(),
            ]);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirm_reset_lab', 'local_labvirtual', format_string($lab->coursename)),
                $confirmurl,
                $level2url
            );
            echo $OUTPUT->footer();
            exit;
        }

        confirm_sesskey();
        $service = new maintenance_service();
        $service->reset_lab($labid, $batchid);
        redirect(
            $level2url,
            get_string('lab_reset', 'local_labvirtual'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Delete single lab.
    if ($action === 'deletelab' && $labid) {
        $registry = new course_registry();
        $lab      = $registry->get_lab_for_batch($labid, $batchid);

        if (!$confirm) {
            $confirmurl = new moodle_url('/local/labvirtual/manage.php', [
                'batchid' => $batchid,
                'action'  => 'deletelab',
                'labid'   => $labid,
                'confirm' => 1,
                'sesskey' => sesskey(),
            ]);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirm_delete_lab', 'local_labvirtual', format_string($lab->coursename)),
                $confirmurl,
                $level2url
            );
            echo $OUTPUT->footer();
            exit;
        }

        confirm_sesskey();
        $service = new maintenance_service();
        $service->delete_lab($labid, $batchid);
        redirect(
            $level2url,
            get_string('lab_deleted', 'local_labvirtual'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Bulk actions (POST from the labs table form).
    if ($bulkaction) {
        require_sesskey();

        $labids = array_filter(array_map('intval', $labids));
        if (empty($labids)) {
            redirect(
                $level2url,
                get_string('error_no_labs_selected', 'local_labvirtual'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        // Validate all selected labs belong to this batch in one query.
        $registry  = new course_registry();
        $validlabs = $registry->get_labs_for_batch_bulk($labids, $batchid);

        if (count($validlabs) !== count($labids)) {
            throw new \moodle_exception('error_course_not_managed', 'local_labvirtual');
        }

        if (!$confirm) {
            $count         = count($validlabs);
            $confirmstring = $bulkaction === 'reset'
                ? get_string('confirm_bulk_reset', 'local_labvirtual', $count)
                : get_string('confirm_bulk_delete', 'local_labvirtual', $count);

            $renderer = $PAGE->get_renderer('local_labvirtual');

            echo $OUTPUT->header();
            echo $renderer->render_bulk_confirm(
                $confirmstring,
                $level2url->out(false),
                $batchid,
                $bulkaction,
                array_keys($validlabs),
                $level2url->out(false)
            );
            echo $OUTPUT->footer();
            exit;
        }

        $service = new maintenance_service();

        if ($bulkaction === 'reset') {
            foreach ($labids as $lid) {
                $service->reset_lab($lid, $batchid);
            }
            redirect(
                $level2url,
                get_string('labs_bulk_reset', 'local_labvirtual', count($labids)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        if ($bulkaction === 'delete') {
            foreach ($labids as $lid) {
                $service->delete_lab($lid, $batchid);
            }
            redirect(
                $level2url,
                get_string('labs_bulk_deleted', 'local_labvirtual', count($labids)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }

    // Default: labs listing.
    $registry = new course_registry();
    $labs     = $registry->list_labs($batchid);
    $panelurl = (new moodle_url(
        '/local/labvirtual/view.php',
        ['batchid' => $batchid]
    ))->out(false);

    $renderer = $PAGE->get_renderer('local_labvirtual');

    echo $OUTPUT->header();
    echo $renderer->render_labs_list($batch, $labs, $panelurl, $createurl->out(false));
    echo $OUTPUT->footer();
    exit;
}

// Level 1: list of batches.

$PAGE->set_url($manageurl);
$PAGE->set_title(get_string('manage_batches', 'local_labvirtual'));
$PAGE->set_heading(get_string('manage_batches', 'local_labvirtual'));

$createurl = new moodle_url('/local/labvirtual/manage.php', ['action' => 'createbatch']);

// Create batch.
if ($action === 'createbatch') {
    $form = new create_batch_form($createurl->out(false));

    if ($form->is_cancelled()) {
        redirect($manageurl);
    }

    if ($data = $form->get_data()) {
        require_sesskey();
        $teacherid = is_array($data->teacherid) ? (int) reset($data->teacherid) : (int) $data->teacherid;

        $batchmgr = new batch_manager();
        $batchmgr->create_batch(
            $data->name,
            $teacherid,
            (int) $data->categoryid,
            $data->nameprefix
        );
        redirect(
            $manageurl,
            get_string('batch_created', 'local_labvirtual'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

// Delete batch.
if ($action === 'deletebatch') {
    $targetbatchid = optional_param('targetbatchid', 0, PARAM_INT);
    if (!$targetbatchid) {
        redirect($manageurl);
    }

    $batchmgr    = new batch_manager();
    $targetbatch = $batchmgr->get_batch($targetbatchid);

    if (!$confirm) {
        $confirmurl = new moodle_url('/local/labvirtual/manage.php', [
            'action'        => 'deletebatch',
            'targetbatchid' => $targetbatchid,
            'confirm'       => 1,
            'sesskey'       => sesskey(),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirm_delete_batch', 'local_labvirtual', format_string($targetbatch->name)),
            $confirmurl,
            $manageurl
        );
        echo $OUTPUT->footer();
        exit;
    }

    confirm_sesskey();
    $service = new maintenance_service();
    $service->delete_batch($targetbatchid);
    redirect(
        $manageurl,
        get_string('batch_deleted', 'local_labvirtual'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Default: batches listing.
$batchmgr = new batch_manager();
$batches  = $batchmgr->list_batches();
$renderer = $PAGE->get_renderer('local_labvirtual');

echo $OUTPUT->header();
echo $renderer->render_batches_list($batches, $createurl->out(false));
echo $OUTPUT->footer();
