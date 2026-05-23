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
 * Level 1 (no batchid): list of batches + create batch form.
 * Level 2 (batchid set): labs of a batch + create labs form + panel URL.
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

$batchid = optional_param('batchid', 0, PARAM_INT);
$action  = optional_param('action', '', PARAM_ALPHA);

require_login();
$context = context_system::instance();
require_capability('local/labvirtual:manage', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

// Level 2: labs within a batch.
if ($batchid) {
    $batchmgr = new batch_manager();
    $batch    = $batchmgr->get_batch($batchid);

    $manageurl  = new moodle_url('/local/labvirtual/manage.php');
    $level2url  = new moodle_url('/local/labvirtual/manage.php', ['batchid' => $batchid]);
    $createurl  = new moodle_url(
        '/local/labvirtual/manage.php',
        ['batchid' => $batchid, 'action' => 'createlabs']
    );

    $PAGE->set_url($level2url);
    $PAGE->set_title(get_string('manage_labs', 'local_labvirtual', format_string($batch->name)));
    $PAGE->set_heading(get_string('manage_labs', 'local_labvirtual', format_string($batch->name)));
    $PAGE->navbar->add(get_string('manage_batches', 'local_labvirtual'), $manageurl);
    $PAGE->navbar->add(format_string($batch->name));

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

    $registry = new course_registry();
    $labs     = $registry->list_labs($batchid);
    $panelurl = (new moodle_url(
        '/local/labvirtual/view.php',
        ['batchid' => $batchid]
    ))->out(false);

    $renderer = $PAGE->get_renderer('local_labvirtual');

    $PAGE->requires->js_call_amd('local_labvirtual/copy_panel_link', 'init');

    echo $OUTPUT->header();
    echo $renderer->render_labs_list($batch, $labs, $panelurl, $createurl->out(false));
    echo $OUTPUT->footer();
    exit;
}

// Level 1: list of batches.
$manageurl = new moodle_url('/local/labvirtual/manage.php');
$createurl = new moodle_url('/local/labvirtual/manage.php', ['action' => 'createbatch']);

$PAGE->set_url($manageurl);
$PAGE->set_title(get_string('manage_batches', 'local_labvirtual'));
$PAGE->set_heading(get_string('manage_batches', 'local_labvirtual'));

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

$batchmgr = new batch_manager();
$batches  = $batchmgr->list_batches();
$renderer = $PAGE->get_renderer('local_labvirtual');

echo $OUTPUT->header();
echo $renderer->render_batches_list($batches, $createurl->out(false));
echo $OUTPUT->footer();
