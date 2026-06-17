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
 * Step definitions for local_virtuallab acceptance tests.
 *
 * @package    local_virtuallab
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_factory;

/**
 * Custom steps to set up Virtual Lab data without going through the admin forms.
 *
 * @package    local_virtuallab
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_virtuallab extends behat_base {
    /**
     * Maps batch names created during a scenario to their database IDs.
     *
     * @var int[]
     */
    protected $batchids = [];

    /**
     * Creates a Virtual Lab batch (and optionally its labs) owned by an existing user.
     *
     * The batch uses the fixed lab name prefix "Lab", so labs are named
     * "Lab 01", "Lab 02", etc. The referenced teacher must already exist.
     *
     * @Given a lab virtual batch :name exists with teacher :username and :count labs
     * @param string $name     Human-readable batch name.
     * @param string $username Username of the responsible teacher.
     * @param string $count    Number of labs to create.
     */
    public function a_lab_virtual_batch_exists(string $name, string $username, string $count): void {
        global $DB;

        $teacher  = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);

        $batchmgr = new batch_manager();
        $batchid  = $batchmgr->create_batch($name, [(int) $teacher->id], 'Lab');

        if ((int) $count > 0) {
            $factory = new course_factory();
            $factory->create_labs($batchid, (int) $count);
        }

        $this->batchids[$name] = $batchid;
    }

    /**
     * Enrols an existing user as editor (editingteacher) in the first lab of a batch.
     *
     * @Given the user :username is already enrolled as editor in a lab of batch :name
     * @param string $username Username of the user to enrol.
     * @param string $name     Batch name created earlier in the scenario.
     */
    public function the_user_is_already_enrolled_as_editor(string $username, string $name): void {
        global $CFG, $DB;

        require_once($CFG->libdir . '/enrollib.php');

        $user    = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $batchid = $this->get_batchid($name);

        $labs = $DB->get_records('local_virtuallab_courses', ['batchid' => $batchid], 'id ASC', '*', 0, 1);
        $lab  = reset($labs);

        if (!$lab) {
            throw new \Exception("Batch '{$name}' has no labs to enrol into.");
        }

        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
        $instance      = $DB->get_record('enrol', ['id' => $lab->enrolid], '*', MUST_EXIST);
        $plugin        = enrol_get_plugin('manual');
        $plugin->enrol_user($instance, (int) $user->id, (int) $teacherroleid);
    }

    /**
     * Navigates to the admin batches management page.
     *
     * @When I visit the lab virtual management page
     */
    public function i_visit_the_lab_virtual_management_page(): void {
        $this->execute('behat_general::i_visit', ['/local/virtuallab/manage.php']);
    }

    /**
     * Navigates to the labs page of a previously created batch.
     *
     * @When I visit the labs page for batch :name
     * @param string $name Batch name created earlier in the scenario.
     */
    public function i_visit_the_labs_page_for_batch(string $name): void {
        $url = new moodle_url('/local/virtuallab/manage.php', ['batchid' => $this->get_batchid($name)]);
        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Navigates to the student panel of a previously created batch.
     *
     * @When I visit the student panel for batch :name
     * @param string $name Batch name created earlier in the scenario.
     */
    public function i_visit_the_student_panel_for_batch(string $name): void {
        $url = new moodle_url('/local/virtuallab/view.php', ['batchid' => $this->get_batchid($name)]);
        $this->execute('behat_general::i_visit', [$url->out_as_local_url(false)]);
    }

    /**
     * Returns the database ID of a batch created earlier in the scenario.
     *
     * @param string $name Batch name.
     * @return int Batch ID.
     * @throws \Exception If the batch was not created in this scenario.
     */
    protected function get_batchid(string $name): int {
        if (!isset($this->batchids[$name])) {
            throw new \Exception("No Virtual Lab batch named '{$name}' was created in this scenario.");
        }

        return $this->batchids[$name];
    }
}
