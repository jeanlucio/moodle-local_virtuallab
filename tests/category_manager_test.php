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
 * PHPUnit tests for category_manager.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab;

use advanced_testcase;
use local_virtuallab\local\category_manager;

/**
 * Tests the batch category structure and its safe deletion guard.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class category_manager_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * An empty batch subcategory under the plugin parent is deleted.
     */
    public function test_delete_category_removes_empty_batch_subcategory(): void {
        global $DB;

        $categoryid = category_manager::create_batch_category('Batch X');
        category_manager::delete_category($categoryid);

        $this->assertFalse($DB->record_exists('course_categories', ['id' => $categoryid]));
    }

    /**
     * A category that is not a child of the plugin parent is never deleted.
     */
    public function test_delete_category_keeps_category_outside_parent(): void {
        global $DB;

        $other = $this->getDataGenerator()->create_category();
        category_manager::delete_category((int) $other->id);

        $this->assertTrue($DB->record_exists('course_categories', ['id' => $other->id]));
    }

    /**
     * A batch subcategory that still holds a course is not deleted.
     */
    public function test_delete_category_keeps_non_empty_subcategory(): void {
        global $DB;

        $categoryid = category_manager::create_batch_category('Batch Y');
        $this->getDataGenerator()->create_course(['category' => $categoryid]);

        category_manager::delete_category($categoryid);

        $this->assertTrue($DB->record_exists('course_categories', ['id' => $categoryid]));
    }
}
