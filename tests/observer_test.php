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
 * PHPUnit tests for the course_deleted observer.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\batch_manager;
use local_virtuallab\local\course_factory;

/**
 * Tests for the local_virtuallab course_deleted observer.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class observer_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Creates a batch with one lab and returns its local_virtuallab_courses row.
     *
     * @return \stdClass The lab tracking record.
     */
    private function create_lab(): \stdClass {
        global $DB;

        $user    = $this->getDataGenerator()->create_user();
        $mgr     = new batch_manager();
        $batchid = $mgr->create_batch('Test Batch', [$user->id], 'Lab');
        $factory = new course_factory();
        $factory->create_labs($batchid, 1);

        return $DB->get_record('local_virtuallab_courses', ['batchid' => $batchid], '*', MUST_EXIST);
    }

    /**
     * Deleting a lab course through the standard Moodle course deletion flow
     * (bypassing maintenance_service entirely) must still remove its tracking
     * row, so the lifecycle cron never targets a course that no longer exists.
     */
    public function test_course_deletion_outside_maintenance_service_removes_tracking_row(): void {
        global $DB;

        $lab = $this->create_lab();

        delete_course($lab->courseid, false);

        $this->assertFalse($DB->record_exists('local_virtuallab_courses', ['id' => $lab->id]));
    }

    /**
     * Deleting one lab's course must not affect another lab's tracking row.
     */
    public function test_course_deletion_leaves_other_labs_untouched(): void {
        global $DB;

        $lab = $this->create_lab();
        $otherlab = $this->create_lab();

        delete_course($lab->courseid, false);

        $this->assertFalse($DB->record_exists('local_virtuallab_courses', ['id' => $lab->id]));
        $this->assertTrue($DB->record_exists('local_virtuallab_courses', ['id' => $otherlab->id]));
    }
}
