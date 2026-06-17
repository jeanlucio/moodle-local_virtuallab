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
 * Form for creating a new Lab Virtual batch.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Batch creation form.
 */
class create_batch_form extends \moodleform {
    #[\Override]
    public function definition(): void {
        $mform = $this->_form;

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
            [],
            $options
        );
        $mform->addRule('teacherids', null, 'required', null, 'client');

        $this->add_action_buttons(false, get_string('create_batch', 'local_virtuallab'));
    }
}
