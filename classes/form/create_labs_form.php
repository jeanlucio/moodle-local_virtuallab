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
 * Form for creating labs within an existing batch.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\form;

use local_virtuallab\local\course_factory;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Labs creation form (quantity only).
 */
class create_labs_form extends \moodleform {
    #[\Override]
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'batchid');
        $mform->setType('batchid', PARAM_INT);

        $mform->addElement(
            'text',
            'nameprefix',
            get_string('batch_nameprefix', 'local_virtuallab'),
            ['size' => 40, 'maxlength' => 255]
        );
        $mform->setType('nameprefix', PARAM_TEXT);
        $mform->addRule('nameprefix', null, 'required', null, 'client');

        $mform->addElement(
            'text',
            'labcount',
            get_string('lab_count', 'local_virtuallab'),
            ['size' => 5, 'maxlength' => 4]
        );
        $mform->setType('labcount', PARAM_INT);
        $mform->addRule('labcount', null, 'required', null, 'client');
        $mform->addRule('labcount', null, 'numeric', null, 'client');
        $mform->setDefault('labcount', 5);

        $this->add_action_buttons(true, get_string('create_labs', 'local_virtuallab'));
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $labcount = isset($data['labcount']) ? (int) $data['labcount'] : 0;
        if ($labcount < 1) {
            $errors['labcount'] = get_string('error', 'moodle');
        } else if ($labcount > course_factory::MAX_LABS) {
            $errors['labcount'] = get_string('error_too_many_labs', 'local_virtuallab', course_factory::MAX_LABS);
        }

        return $errors;
    }
}
