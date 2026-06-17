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
 * PHPUnit tests for enrolment_service.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_factory;
use local_virtuallab\local\enrolment_service;

/**
 * Tests the editor enrolment cap, the one-editor rule and the per-course lock.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class enrolment_service_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a batch with labs and returns the batch record plus its lab rows.
     *
     * @param int $labcount Number of labs.
     * @param int $maxteachers Per-batch editor cap.
     * @return array{batch: \stdClass, labs: \stdClass[]}
     */
    private function make_batch(int $labcount, int $maxteachers): array {
        global $DB;

        $user    = $this->getDataGenerator()->create_user();
        $mgr     = new batch_manager();
        $batchid = $mgr->create_batch('Batch', [$user->id], 'Lab');
        (new course_factory())->create_labs($batchid, $labcount);

        $DB->set_field('local_virtuallab_batches', 'maxteachers', $maxteachers, ['id' => $batchid]);

        return [
            'batch' => $DB->get_record('local_virtuallab_batches', ['id' => $batchid], '*', MUST_EXIST),
            'labs'  => array_values($DB->get_records('local_virtuallab_courses', ['batchid' => $batchid], 'id ASC')),
        ];
    }

    /**
     * A user enrols as editor into an available lab.
     */
    public function test_enrol_editor_success(): void {
        ['batch' => $batch, 'labs' => $labs] = $this->make_batch(1, 2);
        $student = $this->getDataGenerator()->create_user();

        $result = (new enrolment_service())->enrol_editor($labs[0], $batch, (int) $student->id);

        $this->assertSame('', $result);
        $context = \context_course::instance($labs[0]->courseid);
        $this->assertTrue(is_enrolled($context, $student));
    }

    /**
     * The cap is enforced: a second editor cannot join a lab that is already full.
     */
    public function test_enrol_editor_full(): void {
        ['batch' => $batch, 'labs' => $labs] = $this->make_batch(1, 1);
        $service = new enrolment_service();

        $first = $this->getDataGenerator()->create_user();
        $this->assertSame('', $service->enrol_editor($labs[0], $batch, (int) $first->id));

        $second = $this->getDataGenerator()->create_user();
        $this->assertSame('full', $service->enrol_editor($labs[0], $batch, (int) $second->id));

        $context = \context_course::instance($labs[0]->courseid);
        $this->assertFalse(is_enrolled($context, $second));
    }

    /**
     * The one-editor-anywhere rule blocks taking an editor slot in a second lab.
     */
    public function test_enrol_editor_elsewhere(): void {
        ['batch' => $batch, 'labs' => $labs] = $this->make_batch(2, 3);
        $service = new enrolment_service();
        $student = $this->getDataGenerator()->create_user();

        $this->assertSame('', $service->enrol_editor($labs[0], $batch, (int) $student->id));
        $this->assertSame('elsewhere', $service->enrol_editor($labs[1], $batch, (int) $student->id));
    }

    /**
     * error_message maps each result key to a non-empty localised string.
     */
    public function test_error_message_covers_all_reasons(): void {
        $service = new enrolment_service();

        $this->assertNotEmpty($service->error_message('full'));
        $this->assertNotEmpty($service->error_message('elsewhere'));
        $this->assertNotEmpty($service->error_message('busy'));
    }
}
