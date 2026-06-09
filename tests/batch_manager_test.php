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
 * PHPUnit tests for batch_manager.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_labvirtual;

use advanced_testcase;
use local_labvirtual\local\batch_manager;

/**
 * Tests for batch creation, retrieval and listing.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class batch_manager_test extends advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Creating a batch persists all fields and can be retrieved by ID.
     */
    public function test_create_and_get_batch(): void {
        $user     = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();

        $mgr = new batch_manager();
        $id  = $mgr->create_batch('Projeto de Interface 2026/1', $user->id, $category->id, 'Lab EAD');

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        $batch = $mgr->get_batch($id);
        $this->assertSame('Projeto de Interface 2026/1', $batch->name);
        $this->assertEquals($user->id, (int) $batch->teacherid);
        $this->assertEquals($category->id, (int) $batch->categoryid);
        $this->assertSame('Lab EAD', $batch->nameprefix);
        $this->assertGreaterThan(0, (int) $batch->timecreated);
    }

    /**
     * list_batches returns teacher name and category name via JOIN, with correct lab count.
     */
    public function test_list_batches_returns_joined_data(): void {
        $user     = $this->getDataGenerator()->create_user();
        $category = $this->getDataGenerator()->create_category();

        $mgr = new batch_manager();
        $id  = $mgr->create_batch('Turma Teste', $user->id, $category->id, 'Lab');

        $batches = $mgr->list_batches();
        $this->assertArrayHasKey($id, $batches);

        $batch = $batches[$id];
        $this->assertSame($user->firstname, $batch->firstname);
        $this->assertSame($user->lastname, $batch->lastname);
        $this->assertSame($category->name, $batch->categoryname);
        $this->assertEquals(0, (int) $batch->labcount);
    }

    /**
     * get_batch throws when the given ID does not exist.
     */
    public function test_get_batch_not_found_throws(): void {
        $mgr = new batch_manager();
        $this->expectException(\dml_exception::class);
        $mgr->get_batch(99999);
    }
}
