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
 * Form for editing an existing Lab Virtual batch (turma).
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
        $mform->addRule('nameprefix', null, 'required', null, 'client');

        $mform->addElement('header', 'overrides', get_string('batch_overrides', 'local_virtuallab'));
        $mform->addElement(
            'static',
            'overridesnote',
            '',
            get_string('batch_overrides_help', 'local_virtuallab')
        );

        $mform->addElement('text', 'maxteachers', get_string('settings_max_teachers', 'local_virtuallab'), ['size' => 5]);
        $mform->setType('maxteachers', PARAM_TEXT);

        $mform->addElement(
            'text',
            'lifecyclemonths',
            get_string('settings_lifecycle_months', 'local_virtuallab'),
            ['size' => 5]
        );
        $mform->setType('lifecyclemonths', PARAM_TEXT);

        $mform->addElement('select', 'lifecycleaction', get_string('settings_lifecycle_action', 'local_virtuallab'), [
            ''  => get_string('batch_override_default', 'local_virtuallab'),
            '0' => get_string('settings_lifecycle_action_none', 'local_virtuallab'),
            '1' => get_string('settings_lifecycle_action_reset', 'local_virtuallab'),
            '2' => get_string('settings_lifecycle_action_delete', 'local_virtuallab'),
        ]);

        $mform->addElement('text', 'warningdays', get_string('settings_warning_days', 'local_virtuallab'), ['size' => 5]);
        $mform->setType('warningdays', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('save_batch', 'local_virtuallab'));
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        foreach (['maxteachers', 'lifecyclemonths', 'warningdays'] as $field) {
            if ($data[$field] !== '' && !ctype_digit((string) $data[$field])) {
                $errors[$field] = get_string('error', 'moodle');
            }
        }

        return $errors;
    }
}
