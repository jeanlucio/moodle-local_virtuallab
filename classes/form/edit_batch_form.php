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
 * Form for editing an existing Lab Virtual batch.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Batch editing form: name, responsible teachers and lab prefix.
 */
class edit_batch_form extends \moodleform {
    #[\Override]
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'batchid');
        $mform->setType('batchid', PARAM_INT);

        $mform->addElement(
            'text',
            'name',
            get_string('batch_name', 'local_virtuallab'),
            ['size' => 60, 'maxlength' => 255]
        );
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $options = [
            'ajax'              => 'core_user/form_user_selector',
            'multiple'          => true,
            'noselectionstring' => get_string('search'),
        ];
        $mform->addElement(
            'autocomplete',
            'teacherids',
            get_string('batch_teacher', 'local_virtuallab'),
            $this->_customdata['teachers'] ?? [],
            $options
        );
        $mform->addRule('teacherids', null, 'required', null, 'client');

        $mform->addElement(
            'text',
            'nameprefix',
            get_string('batch_nameprefix', 'local_virtuallab'),
            ['size' => 40, 'maxlength' => 255]
        );
        $mform->setType('nameprefix', PARAM_TEXT);

        // The shared task list only makes sense when the teacher checklist block is installed.
        if (\local_virtuallab\local\checklist_integration::is_available()) {
            $mform->addElement(
                'textarea',
                'checklisttasks',
                get_string('batch_checklist_tasks', 'local_virtuallab'),
                ['rows' => 6, 'cols' => 60]
            );
            $mform->setType('checklisttasks', PARAM_TEXT);
            $mform->addHelpButton('checklisttasks', 'batch_checklist_tasks', 'local_virtuallab');
        }

        $actionstrmap = [
            0 => 'settings_lifecycle_action_none',
            1 => 'settings_lifecycle_action_reset',
            2 => 'settings_lifecycle_action_delete',
        ];
        $defaultmaxteachers = (int) (get_config('local_virtuallab', 'max_teachers_per_lab') ?: 3);
        $defaultlifecyclemonths = (int) get_config('local_virtuallab', 'lifecycle_months');
        $defaultlifecycleaction = (int) get_config('local_virtuallab', 'lifecycle_action');
        $defaultwarningdays = (int) (get_config('local_virtuallab', 'warning_days_before') ?: 7);
        $defaultactionlabel = get_string(
            $actionstrmap[$defaultlifecycleaction] ?? 'settings_lifecycle_action_none',
            'local_virtuallab'
        );

        $mform->addElement('header', 'overrides', get_string('batch_overrides', 'local_virtuallab'));
        $mform->addElement(
            'static',
            'overridesnote',
            '',
            get_string('batch_overrides_help', 'local_virtuallab')
        );

        $maxteachersgrp = [
            $mform->createElement('text', 'maxteachers', '', ['size' => 5]),
            $mform->createElement('static', '', '', \html_writer::span(
                get_string('batch_override_placeholder', 'local_virtuallab', $defaultmaxteachers),
                'text-muted small ms-2'
            )),
        ];
        $mform->addGroup(
            $maxteachersgrp,
            'maxteachers_grp',
            get_string('settings_max_teachers', 'local_virtuallab'),
            '',
            false
        );
        $mform->setType('maxteachers', PARAM_TEXT);

        $mform->addElement(
            'static',
            'lifecyclerecountnote',
            '',
            \html_writer::div(
                get_string('batch_lifecycle_recount_note', 'local_virtuallab'),
                'alert alert-info mb-2'
            )
        );

        $lifecyclemonthsgrp = [
            $mform->createElement('text', 'lifecyclemonths', '', ['size' => 5]),
            $mform->createElement('static', '', '', \html_writer::span(
                get_string('batch_override_placeholder', 'local_virtuallab', $defaultlifecyclemonths)
                . ' ' . get_string('batch_lifecycle_zero_hint', 'local_virtuallab'),
                'text-muted small ms-2'
            )),
        ];
        $mform->addGroup(
            $lifecyclemonthsgrp,
            'lifecyclemonths_grp',
            get_string('settings_lifecycle_months', 'local_virtuallab'),
            '',
            false
        );
        $mform->setType('lifecyclemonths', PARAM_TEXT);

        $mform->addElement('select', 'lifecycleaction', get_string('settings_lifecycle_action', 'local_virtuallab'), [
            ''  => get_string('batch_override_default_hint', 'local_virtuallab', $defaultactionlabel),
            '0' => get_string('settings_lifecycle_action_none', 'local_virtuallab'),
            '1' => get_string('settings_lifecycle_action_reset', 'local_virtuallab'),
            '2' => get_string('settings_lifecycle_action_delete', 'local_virtuallab'),
        ]);

        $warningdaysgrp = [
            $mform->createElement('text', 'warningdays', '', ['size' => 5]),
            $mform->createElement('static', '', '', \html_writer::span(
                get_string('batch_override_placeholder', 'local_virtuallab', $defaultwarningdays),
                'text-muted small ms-2'
            )),
        ];
        $mform->addGroup(
            $warningdaysgrp,
            'warningdays_grp',
            get_string('settings_warning_days', 'local_virtuallab'),
            '',
            false
        );
        $mform->setType('warningdays', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('save_batch', 'local_virtuallab'));
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $fieldgroups = [
            'maxteachers'     => 'maxteachers_grp',
            'lifecyclemonths' => 'lifecyclemonths_grp',
            'warningdays'     => 'warningdays_grp',
        ];
        foreach ($fieldgroups as $field => $groupname) {
            if ($data[$field] !== '' && !ctype_digit((string) $data[$field])) {
                $errors[$groupname] = get_string('error', 'moodle');
            }
        }

        return $errors;
    }
}
