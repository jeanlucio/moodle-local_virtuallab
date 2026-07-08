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
 * Privacy provider.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_virtuallab.
 *
 * The only personal data stored by this plugin is the responsible-teacher assignment
 * per batch. Everything else (courses, enrolments, categories) is managed by Moodle core
 * and covered by core's own privacy providers.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about the data stored by this plugin.
     *
     * @param collection $collection The collection object.
     * @return collection The collection with added metadata.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_virtuallab_batch_teachers', [
            'batchid' => 'privacy:metadata:batch_teachers:batchid',
            'userid' => 'privacy:metadata:batch_teachers:userid',
        ], 'privacy:metadata');

        return $collection;
    }

    /**
     * Get the list of contexts where a user has data.
     *
     * @param int $userid The user ID.
     * @return contextlist The list of contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {local_virtuallab_batch_teachers} bt
                  JOIN {local_virtuallab_batches} b ON b.id = bt.batchid
                  JOIN {context} ctx ON ctx.instanceid = b.categoryid AND ctx.contextlevel = :coursecatlevel
                 WHERE bt.userid = :userid";

        $contextlist->add_from_sql($sql, ['userid' => $userid, 'coursecatlevel' => CONTEXT_COURSECAT]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a specific context.
     *
     * @param userlist $userlist The userlist object.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_coursecat) {
            return;
        }

        $sql = "SELECT bt.userid
                  FROM {local_virtuallab_batch_teachers} bt
                  JOIN {local_virtuallab_batches} b ON b.id = bt.batchid
                 WHERE b.categoryid = :categoryid";

        $userlist->add_from_sql('userid', $sql, ['categoryid' => $context->instanceid]);
    }

    /**
     * Export all user data for the specified approved contextlist.
     *
     * @param approved_contextlist $contextlist The approved contextlist.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_coursecat) {
                continue;
            }

            $batch = $DB->get_record_sql(
                "SELECT b.id, b.name
                   FROM {local_virtuallab_batches} b
                   JOIN {local_virtuallab_batch_teachers} bt ON bt.batchid = b.id
                  WHERE b.categoryid = :categoryid AND bt.userid = :userid",
                ['categoryid' => $context->instanceid, 'userid' => $userid]
            );

            if (!$batch) {
                continue;
            }

            writer::with_context($context)->export_data(
                [
                    get_string('pluginname', 'local_virtuallab'),
                    get_string('privacy:export:batchteachers', 'local_virtuallab'),
                ],
                (object) ['batchname' => $batch->name]
            );
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_coursecat) {
            return;
        }

        $batchid = $DB->get_field('local_virtuallab_batches', 'id', ['categoryid' => $context->instanceid], IGNORE_MISSING);

        if ($batchid) {
            $DB->delete_records('local_virtuallab_batch_teachers', ['batchid' => $batchid]);
        }
    }

    /**
     * Delete all user data for the specified user in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contextlist.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_coursecat) {
                continue;
            }

            $batchid = $DB->get_field(
                'local_virtuallab_batches',
                'id',
                ['categoryid' => $context->instanceid],
                IGNORE_MISSING
            );

            if ($batchid) {
                $DB->delete_records('local_virtuallab_batch_teachers', ['batchid' => $batchid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved userlist.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_coursecat) {
            return;
        }

        $batchid = $DB->get_field('local_virtuallab_batches', 'id', ['categoryid' => $context->instanceid], IGNORE_MISSING);

        if (!$batchid) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = array_merge(['batchid' => $batchid], $inparams);

        $DB->delete_records_select('local_virtuallab_batch_teachers', "batchid = :batchid AND userid $insql", $params);
    }
}
