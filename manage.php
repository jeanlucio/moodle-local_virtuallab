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
 * Admin management page for Virtual Lab batches and labs.
 *
 * Level 1 (no batchid): list of batches + create/delete batch.
 * Level 2 (batchid set): labs of a batch + create/reset/delete labs + bulk actions.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_virtuallab\form\create_batch_form;
use local_virtuallab\form\create_labs_form;
use local_virtuallab\form\edit_batch_form;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_factory;
use local_virtuallab\local\course_registry;
use local_virtuallab\local\maintenance_service;

$batchid    = optional_param('batchid', 0, PARAM_INT);
$action     = optional_param('action', '', PARAM_ALPHAEXT);
$labid      = optional_param('labid', 0, PARAM_INT);
$confirm    = optional_param('confirm', 0, PARAM_INT);
$bulkaction = optional_param('bulkaction', '', PARAM_ALPHA);
$labids     = optional_param_array('labids', [], PARAM_INT);

require_login();
$systemcontext = context_system::instance();

// System-level managers (admins) manage every batch; delegated teachers manage only the
// batches whose subcategory grants them :manage. Per-action checks enforce this below.
$canmanageall = has_capability('local/virtuallab:manage', $systemcontext);

$PAGE->set_context($systemcontext);
$PAGE->set_pagelayout('admin');

$manageurl = new moodle_url('/local/virtuallab/manage.php');

// Level 2: labs within a batch.
if ($batchid) {
    $batchmgr  = new batch_manager();
    $batch     = $batchmgr->get_batch($batchid);
    require_capability('local/virtuallab:manage', context_coursecat::instance($batch->categoryid));
    $level2url = new moodle_url('/local/virtuallab/manage.php', ['batchid' => $batchid]);
    $createurl = new moodle_url(
        '/local/virtuallab/manage.php',
        ['batchid' => $batchid, 'action' => 'createlabs']
    );

    $PAGE->set_url($level2url);
    $PAGE->set_title(get_string('manage_labs', 'local_virtuallab', format_string($batch->name)));
    $PAGE->set_heading(get_string('manage_labs', 'local_virtuallab', format_string($batch->name)));
    $PAGE->navbar->add(get_string('manage_batches', 'local_virtuallab'), $manageurl);
    $PAGE->navbar->add(format_string($batch->name));

    // Create labs.
    if ($action === 'createlabs') {
        $form = new create_labs_form($createurl->out(false));
        $form->set_data(['batchid' => $batchid, 'nameprefix' => $batch->nameprefix]);

        if ($form->is_cancelled()) {
            redirect($level2url);
        }

        if ($data = $form->get_data()) {
            require_sesskey();
            $batchmgr->set_prefix((int) $data->batchid, trim($data->nameprefix));
            $factory = new course_factory();
            $created = $factory->create_labs(
                (int) $data->batchid,
                (int) $data->labcount
            );
            redirect(
                $level2url,
                get_string('labs_created', 'local_virtuallab', count($created)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        echo $OUTPUT->header();
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }

    // Edit batch.
    if ($action === 'editbatch') {
        $editurl = new moodle_url(
            '/local/virtuallab/manage.php',
            ['batchid' => $batchid, 'action' => 'editbatch']
        );
        $teachers = $batchmgr->get_teachers($batchid);
        $teacheroptions = [];
        foreach ($teachers as $teacher) {
            $teacheroptions[$teacher->id] = fullname($teacher);
        }
        $form = new edit_batch_form(
            $editurl->out(false),
            ['teachers' => $teacheroptions]
        );

        if ($form->is_cancelled()) {
            redirect($level2url);
        }

        if ($data = $form->get_data()) {
            require_sesskey();
            $teacherids = array_map('intval', (array) $data->teacherids);
            $batchmgr->update_batch($batchid, $data->name, $teacherids, trim($data->nameprefix), [
                'maxteachers'     => $data->maxteachers,
                'lifecyclemonths' => $data->lifecyclemonths,
                'lifecycleaction' => $data->lifecycleaction,
                'warningdays'     => $data->warningdays,
            ]);
            if (isset($data->checklisttasks)) {
                (new \local_virtuallab\local\task_manager())
                    ->set_tasks_from_text($batchid, $data->checklisttasks);
            }
            redirect(
                $level2url,
                get_string('batch_updated', 'local_virtuallab'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $form->set_data([
            'batchid'         => $batchid,
            'name'            => $batch->name,
            'teacherids'      => array_keys($teachers),
            'nameprefix'      => $batch->nameprefix,
            'checklisttasks'  => (new \local_virtuallab\local\task_manager())->get_tasks_text($batchid),
            'maxteachers'     => $batch->maxteachers,
            'lifecyclemonths' => $batch->lifecyclemonths,
            'lifecycleaction' => $batch->lifecycleaction === null ? '' : (string) $batch->lifecycleaction,
            'warningdays'     => $batch->warningdays,
        ]);

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
            $confirmurl = new moodle_url('/local/virtuallab/manage.php', [
                'batchid' => $batchid,
                'action'  => 'resetlab',
                'labid'   => $labid,
                'confirm' => 1,
                'sesskey' => sesskey(),
            ]);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirm_reset_lab', 'local_virtuallab', format_string($lab->coursename)),
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
            get_string('lab_reset', 'local_virtuallab'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Delete single lab.
    if ($action === 'deletelab' && $labid) {
        $registry = new course_registry();
        $lab      = $registry->get_lab_for_batch($labid, $batchid);

        if (!$confirm) {
            $confirmurl = new moodle_url('/local/virtuallab/manage.php', [
                'batchid' => $batchid,
                'action'  => 'deletelab',
                'labid'   => $labid,
                'confirm' => 1,
                'sesskey' => sesskey(),
            ]);
            echo $OUTPUT->header();
            echo $OUTPUT->confirm(
                get_string('confirm_delete_lab', 'local_virtuallab', format_string($lab->coursename)),
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
            get_string('lab_deleted', 'local_virtuallab'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    // Apply the batch task list to every existing lab.
    if ($action === 'syncchecklist') {
        require_sesskey();
        $synced = (new \local_virtuallab\local\checklist_integration())->sync_batch($batchid);
        redirect(
            $level2url,
            get_string('checklist_synced', 'local_virtuallab', $synced),
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
                get_string('error_no_labs_selected', 'local_virtuallab'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }

        // Validate all selected labs belong to this batch in one query.
        $registry  = new course_registry();
        $validlabs = $registry->get_labs_for_batch_bulk($labids, $batchid);

        if (count($validlabs) !== count($labids)) {
            throw new \moodle_exception('error_course_not_managed', 'local_virtuallab');
        }

        if (!$confirm) {
            $count         = count($validlabs);
            $confirmstring = $bulkaction === 'reset'
                ? get_string('confirm_bulk_reset', 'local_virtuallab', $count)
                : get_string('confirm_bulk_delete', 'local_virtuallab', $count);

            $renderer = $PAGE->get_renderer('local_virtuallab');

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
                get_string('labs_bulk_reset', 'local_virtuallab', count($labids)),
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
                get_string('labs_bulk_deleted', 'local_virtuallab', count($labids)),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    }

    // Default: labs listing.
    $registry = new course_registry();
    $labs     = $registry->list_labs($batchid);
    $panelurl = (new moodle_url(
        '/local/virtuallab/view.php',
        ['batchid' => $batchid]
    ))->out(false);

    $editurl = (new moodle_url(
        '/local/virtuallab/manage.php',
        ['batchid' => $batchid, 'action' => 'editbatch']
    ))->out(false);

    // Offer the retroactive sync only when the checklist plugin is present and the batch
    // actually has tasks to push into the already-created labs.
    $syncurl = '';
    $hassynctasks = \local_virtuallab\local\checklist_integration::is_available()
        && (new \local_virtuallab\local\task_manager())->get_tasks($batchid);
    if ($hassynctasks) {
        $syncurl = (new moodle_url('/local/virtuallab/manage.php', [
            'batchid' => $batchid,
            'action'  => 'syncchecklist',
            'sesskey' => sesskey(),
        ]))->out(false);
    }

    $renderer = $PAGE->get_renderer('local_virtuallab');

    echo $OUTPUT->header();
    echo $renderer->render_labs_list($batch, $labs, $panelurl, $createurl->out(false), $editurl, $syncurl);
    echo $OUTPUT->footer();
    exit;
}

// Level 1: list of batches.

$PAGE->set_url($manageurl);
$PAGE->set_title(get_string('manage_batches', 'local_virtuallab'));
$PAGE->set_heading(get_string('manage_batches', 'local_virtuallab'));

$createurl = new moodle_url('/local/virtuallab/manage.php', ['action' => 'createbatch']);

// Create batch (system-level managers only).
if ($action === 'createbatch') {
    require_capability('local/virtuallab:manage', $systemcontext);

    $form = new create_batch_form($createurl->out(false));

    if ($form->is_cancelled()) {
        redirect($manageurl);
    }

    if ($data = $form->get_data()) {
        require_sesskey();
        $teacherids = array_map('intval', (array) $data->teacherids);

        $batchmgr = new batch_manager();
        $batchmgr->create_batch($data->name, $teacherids);
        redirect(
            $manageurl,
            get_string('batch_created', 'local_virtuallab'),
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
    require_capability('local/virtuallab:manage', context_coursecat::instance($targetbatch->categoryid));

    if (!$confirm) {
        $confirmurl = new moodle_url('/local/virtuallab/manage.php', [
            'action'        => 'deletebatch',
            'targetbatchid' => $targetbatchid,
            'confirm'       => 1,
            'sesskey'       => sesskey(),
        ]);
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('confirm_delete_batch', 'local_virtuallab', format_string($targetbatch->name)),
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
        get_string('batch_deleted', 'local_virtuallab'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Default: batches listing.
$batchmgr = new batch_manager();
$batches  = $batchmgr->list_batches();

// A delegated teacher who manages no batch has no business on this page.
if (!$canmanageall && empty($batches)) {
    throw new required_capability_exception($systemcontext, 'local/virtuallab:manage', 'nopermissions', '');
}

$renderer = $PAGE->get_renderer('local_virtuallab');

echo $OUTPUT->header();
echo $renderer->render_batches_list($batches, $createurl->out(false), $canmanageall);
echo $OUTPUT->footer();
