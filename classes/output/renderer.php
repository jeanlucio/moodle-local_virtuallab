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
 * Output renderer for local_labvirtual.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual\output;

use plugin_renderer_base;

/**
 * Renders Mustache templates for the plugin.
 */
class renderer extends plugin_renderer_base {
    /**
     * Renders the student panel for a batch.
     *
     * @param \stdClass $batch     Batch record with teacher name pre-populated.
     * @param array     $labs      Enriched lab data from panel_repository.
     * @param int       $batchid   Batch ID (for form actions).
     * @return string Rendered HTML.
     */
    public function render_labs_panel(\stdClass $batch, array $labs, int $batchid): string {
        $firstlab = !empty($labs) ? $labs[array_key_first($labs)] : null;
        $showkeys = $firstlab && !empty($firstlab['showkeys']);

        $labrows = [];
        foreach ($labs as $lab) {
            $lab['courseurl'] = (new \moodle_url('/course/view.php', ['id' => $lab['courseid']]))->out(false);
            $labrows[] = $lab;
        }

        $context = [
            'batchname'       => format_string($batch->name),
            'teachername'     => format_string(fullname($batch)),
            'batchid'         => $batchid,
            'sesskey'         => sesskey(),
            'viewurl'         => (new \moodle_url('/local/labvirtual/view.php', ['batchid' => $batchid]))->out(false),
            'showkeys'        => $showkeys,
            'batchteacherkey' => $firstlab ? $firstlab['teacherkey'] : '',
            'batchstudentkey' => $firstlab ? $firstlab['studentkey'] : '',
            'haslabs'         => !empty($labs),
            'labs'            => $labrows,
        ];

        return $this->render_from_template('local_labvirtual/labs_panel', $context);
    }

    /**
     * Renders the admin batches list.
     *
     * @param array  $batches      List of batch records from batch_manager::list_batches().
     * @param string $createurl    URL for the "New batch" action.
     * @return string Rendered HTML.
     */
    public function render_batches_list(array $batches, string $createurl): string {
        $rows = [];

        foreach ($batches as $batch) {
            $rows[] = [
                'id'           => $batch->id,
                'name'         => format_string($batch->name),
                'teachername'  => format_string(fullname($batch)),
                'categoryname' => format_string($batch->categoryname),
                'labcount'     => (int) $batch->labcount,
                'manageurl'    => (new \moodle_url(
                    '/local/labvirtual/manage.php',
                    ['batchid' => $batch->id]
                ))->out(false),
                'deleteurl'    => (new \moodle_url('/local/labvirtual/manage.php', [
                    'action'        => 'deletebatch',
                    'targetbatchid' => $batch->id,
                ]))->out(false),
                'deletelabel'  => get_string('delete_batch_label', 'local_labvirtual', format_string($batch->name)),
            ];
        }

        $context = [
            'batches'    => $rows,
            'hasbatches' => !empty($rows),
            'createurl'  => $createurl,
        ];

        return $this->render_from_template('local_labvirtual/manage_batches', $context);
    }

    /**
     * Renders the admin labs list for a batch, including reset/delete actions and bulk form.
     *
     * @param \stdClass $batch     Batch record.
     * @param array     $labs      Lab records from course_registry::list_labs().
     * @param string    $panelurl  Absolute URL to the student panel for this batch.
     * @param string    $createurl URL for the "Create labs" action.
     * @return string Rendered HTML.
     */
    public function render_labs_list(
        \stdClass $batch,
        array $labs,
        string $panelurl,
        string $createurl
    ): string {
        $rows = [];

        foreach ($labs as $lab) {
            $rows[] = [
                'id'          => $lab->id,
                'coursename'  => format_string($lab->coursename),
                'shortname'   => s($lab->shortname),
                'courseurl'   => (new \moodle_url('/course/view.php', ['id' => $lab->courseid]))->out(false),
                'lastreset'   => $lab->lastreset > 0
                    ? userdate($lab->lastreset, get_string('strftimedatetime', 'langconfig'))
                    : '—',
                'reseturl'    => (new \moodle_url('/local/labvirtual/manage.php', [
                    'batchid' => $batch->id,
                    'action'  => 'resetlab',
                    'labid'   => $lab->id,
                ]))->out(false),
                'deleteurl'   => (new \moodle_url('/local/labvirtual/manage.php', [
                    'batchid' => $batch->id,
                    'action'  => 'deletelab',
                    'labid'   => $lab->id,
                ]))->out(false),
                'resetlabel'  => get_string('reset_lab_label', 'local_labvirtual', format_string($lab->coursename)),
                'deletelabel' => get_string('delete_lab_label', 'local_labvirtual', format_string($lab->coursename)),
            ];
        }

        $context = [
            'batchname'    => format_string($batch->name),
            'batchid'      => $batch->id,
            'manageurl'    => (new \moodle_url('/local/labvirtual/manage.php'))->out(false),
            'labs'         => $rows,
            'haslabs'      => !empty($rows),
            'panelurl'     => $panelurl,
            'createurl'    => $createurl,
            'sesskey'      => sesskey(),
            'strpanelhelp' => get_string('panel_url_help', 'local_labvirtual'),
        ];

        return $this->render_from_template('local_labvirtual/manage_labs', $context);
    }
}
