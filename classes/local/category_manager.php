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
 * Manages the course category structure used by Lab Virtual batches.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

use core_course_category;

/**
 * Provides the single parent category and one subcategory per batch.
 *
 * Each batch lives in its own subcategory under a shared parent, so the delegated
 * batch-manager role can be assigned at the subcategory and isolate a teacher to the
 * labs of their own batch.
 */
class category_manager {
    /** @var string Config key holding the shared parent category ID. */
    private const PARENT_CONFIG = 'parentcategoryid';

    /**
     * Returns the shared parent category ID, creating it on first use.
     *
     * @return int The parent category ID.
     */
    public static function get_parent_category(): int {
        global $DB;

        $catid = (int) get_config('local_virtuallab', self::PARENT_CONFIG);
        if ($catid && $DB->record_exists('course_categories', ['id' => $catid])) {
            return $catid;
        }

        $category = core_course_category::create((object) [
            'name'        => get_string('parentcategory', 'local_virtuallab'),
            'parent'      => 0,
            'description' => '',
        ]);

        set_config(self::PARENT_CONFIG, $category->id, 'local_virtuallab');

        return (int) $category->id;
    }

    /**
     * Creates a subcategory for a batch under the shared parent and returns its ID.
     *
     * @param string $name Batch name (used as the subcategory name).
     * @return int The created subcategory ID.
     */
    public static function create_batch_category(string $name): int {
        $category = core_course_category::create((object) [
            'name'        => $name,
            'parent'      => self::get_parent_category(),
            'description' => '',
        ]);

        return (int) $category->id;
    }

    /**
     * Renames a batch subcategory, ignoring a missing category.
     *
     * @param int    $categoryid Subcategory ID.
     * @param string $name       New name.
     */
    public static function rename_category(int $categoryid, string $name): void {
        $category = core_course_category::get($categoryid, IGNORE_MISSING);
        if ($category) {
            $category->update((object) ['name' => $name]);
        }
    }

    /**
     * Deletes a batch subcategory, but only when it is safe to do so.
     *
     * As a safeguard against legacy or shared categories, the deletion only happens when
     * the category is a direct child of the plugin parent category and is already empty
     * (no courses and no subcategories). Otherwise it is left untouched.
     *
     * @param int $categoryid Subcategory ID.
     */
    public static function delete_category(int $categoryid): void {
        global $DB;

        $parentid = (int) get_config('local_virtuallab', self::PARENT_CONFIG);
        $category = $DB->get_record('course_categories', ['id' => $categoryid]);

        if (!$category || !$parentid || (int) $category->parent !== $parentid) {
            return;
        }

        $hascourses    = $DB->record_exists('course', ['category' => $categoryid]);
        $hassubcats    = $DB->record_exists('course_categories', ['parent' => $categoryid]);
        if ($hascourses || $hassubcats) {
            return;
        }

        $coursecat = core_course_category::get($categoryid, IGNORE_MISSING, true);
        if ($coursecat) {
            $coursecat->delete_full(false);
        }
    }
}
