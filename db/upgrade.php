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
 * Database upgrade steps for local_labvirtual.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Applies incremental schema changes for the plugin.
 *
 * @param int $oldversion The currently installed plugin version.
 * @return bool
 */
function xmldb_local_labvirtual_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026061100) {
        $table = new xmldb_table('local_labvirtual_courses');
        $field = new xmldb_field(
            'lastwarn',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'lastreset'
        );

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026061100, 'local', 'labvirtual');
    }

    if ($oldversion < 2026061103) {
        // Normalise existing lab enrol instances: disable self-enrolment (newenrols = 0)
        // and clear the now-unused enrolment keys. The panel enrols programmatically.
        $enrolids = $DB->get_fieldset_sql(
            "SELECT teacher_enrolid FROM {local_labvirtual_courses}
             UNION
             SELECT student_enrolid FROM {local_labvirtual_courses}"
        );

        if ($enrolids) {
            [$insql, $params] = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED);
            $DB->set_field_select('enrol', 'customint6', 0, "id $insql", $params);
            $DB->set_field_select('enrol', 'password', '', "id $insql", $params);
        }

        upgrade_plugin_savepoint(true, 2026061103, 'local', 'labvirtual');
    }

    if ($oldversion < 2026061110) {
        // New join table for multiple responsible teachers per batch.
        $table = new xmldb_table('local_labvirtual_batch_teachers');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('batchid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('batchid-userid', XMLDB_INDEX_UNIQUE, ['batchid', 'userid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Migrate the single teacherid into the new table.
        $batchtable = new xmldb_table('local_labvirtual_batches');
        $teacherfield = new xmldb_field('teacherid');

        if ($dbman->field_exists($batchtable, $teacherfield)) {
            $batches = $DB->get_records('local_labvirtual_batches', null, '', 'id, teacherid');
            $rows = [];
            foreach ($batches as $batch) {
                if ((int) $batch->teacherid > 0) {
                    $rows[] = (object) ['batchid' => $batch->id, 'userid' => $batch->teacherid];
                }
            }
            if ($rows) {
                $DB->insert_records('local_labvirtual_batch_teachers', $rows);
            }

            $teacherindex = new xmldb_index('teacherid', XMLDB_INDEX_NOTUNIQUE, ['teacherid']);
            if ($dbman->index_exists($batchtable, $teacherindex)) {
                $dbman->drop_index($batchtable, $teacherindex);
            }
            $dbman->drop_field($batchtable, $teacherfield);
        }

        upgrade_plugin_savepoint(true, 2026061110, 'local', 'labvirtual');
    }

    if ($oldversion < 2026061120) {
        $table = new xmldb_table('local_labvirtual_courses');

        // Single manual enrolment instance replaces the two enrol_self instances.
        $enrolfield = new xmldb_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'courseid');
        if (!$dbman->field_exists($table, $enrolfield)) {
            $dbman->add_field($table, $enrolfield);
        }

        // Populate enrolid with each course's manual enrolment instance.
        $labs = $DB->get_records('local_labvirtual_courses');
        if ($labs) {
            $courseids = array_map(fn($lab) => $lab->courseid, $labs);
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $params['enrol'] = 'manual';
            $manuals = $DB->get_records_select('enrol', "courseid $insql AND enrol = :enrol", $params, '', 'id, courseid');

            $bycourse = [];
            foreach ($manuals as $manual) {
                $bycourse[$manual->courseid] = $manual->id;
            }

            $manualplugin = enrol_get_plugin('manual');
            foreach ($labs as $lab) {
                $enrolid = $bycourse[$lab->courseid] ?? 0;
                if (!$enrolid) {
                    $course = $DB->get_record('course', ['id' => $lab->courseid]);
                    if ($course) {
                        $enrolid = (int) $manualplugin->add_instance($course);
                    }
                }
                $DB->set_field('local_labvirtual_courses', 'enrolid', $enrolid, ['id' => $lab->id]);
            }
        }

        $enrolindex = new xmldb_index('enrolid', XMLDB_INDEX_NOTUNIQUE, ['enrolid']);
        if (!$dbman->index_exists($table, $enrolindex)) {
            $dbman->add_index($table, $enrolindex);
        }

        // Drop the obsolete enrol_self instance columns.
        foreach (['teacher_enrolid', 'student_enrolid'] as $fieldname) {
            $oldindex = new xmldb_index($fieldname, XMLDB_INDEX_NOTUNIQUE, [$fieldname]);
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }
            $oldfield = new xmldb_field($fieldname);
            if ($dbman->field_exists($table, $oldfield)) {
                $dbman->drop_field($table, $oldfield);
            }
        }

        upgrade_plugin_savepoint(true, 2026061120, 'local', 'labvirtual');
    }

    if ($oldversion < 2026061121) {
        // Remove the obsolete self-enrolment instances from managed courses so the standard
        // enrolment page no longer shows the disabled key blocks. Any remaining self
        // enrolments are moved to the manual instance first so access is preserved. The
        // per-course loop is acceptable here as this is a one-off migration.
        $labs = $DB->get_records('local_labvirtual_courses', null, '', 'id, courseid, enrolid');
        if ($labs) {
            $manualplugin = enrol_get_plugin('manual');
            $selfplugin   = enrol_get_plugin('self');

            foreach ($labs as $lab) {
                $manualinstance = $lab->enrolid ? $DB->get_record('enrol', ['id' => $lab->enrolid]) : null;
                $selfs = $DB->get_records('enrol', ['courseid' => $lab->courseid, 'enrol' => 'self']);

                foreach ($selfs as $self) {
                    if ($manualinstance) {
                        foreach ($DB->get_records('user_enrolments', ['enrolid' => $self->id]) as $ue) {
                            $manualplugin->enrol_user($manualinstance, $ue->userid, (int) $self->roleid);
                        }
                    }
                    $selfplugin->delete_instance($self);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026061121, 'local', 'labvirtual');
    }

    if ($oldversion < 2026061130) {
        // Provision the delegated batch-manager role for existing installs.
        \local_labvirtual\local\role_provisioner::ensure_role();

        upgrade_plugin_savepoint(true, 2026061130, 'local', 'labvirtual');
    }

    if ($oldversion < 2026061140) {
        $table = new xmldb_table('local_labvirtual_batches');

        $fields = [
            new xmldb_field('maxteachers', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'timecreated'),
            new xmldb_field('lifecyclemonths', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'maxteachers'),
            new xmldb_field('lifecycleaction', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'lifecyclemonths'),
            new xmldb_field('warningdays', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'lifecycleaction'),
        ];
        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026061140, 'local', 'labvirtual');
    }

    if ($oldversion < 2026061150) {
        // Re-provision the role so responsible teachers gain full manager capabilities.
        \local_labvirtual\local\role_provisioner::ensure_role();

        upgrade_plugin_savepoint(true, 2026061150, 'local', 'labvirtual');
    }

    if ($oldversion < 2026061151) {
        // Re-provision the role: editingteacher-based set (no category-level visibility).
        \local_labvirtual\local\role_provisioner::ensure_role();

        upgrade_plugin_savepoint(true, 2026061151, 'local', 'labvirtual');
    }

    return true;
}
