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
 * Report repository — queries for the consolidated lab usage report.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_virtuallab\local;

/**
 * Fetches enrolment, role and log data for the usage report.
 */
class report_repository {
    /** @var int Rows per page on the batch overview. */
    public const PER_PAGE = 30;

    /** @var int Role ID for editingteacher, cached to avoid repeated queries. */
    private int $teacherroleid;

    /**
     * Constructor resolves the editingteacher role ID once.
     */
    public function __construct() {
        global $DB;
        $this->teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher'], MUST_EXIST);
    }

    /**
     * Returns the editingteacher role ID, resolved once in the constructor.
     *
     * @return int
     */
    public function get_teacher_role_id(): int {
        return $this->teacherroleid;
    }

    /**
     * Total number of student enrolment rows for a batch (for pagination).
     *
     * @param int $batchid Batch ID.
     * @return int
     */
    public function count_batch_enrolments(int $batchid): int {
        global $DB;

        $sql = "SELECT COUNT(ue.id)
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {local_virtuallab_courses} lc ON lc.enrolid = e.id
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                 WHERE lc.batchid = :batchid";

        return (int) $DB->count_records_sql($sql, ['batchid' => $batchid]);
    }

    /**
     * Paginated enrolment rows for the batch overview table.
     *
     * Each row contains userid, labid, courseid, coursename, enrolledat and user name fields.
     * Ordered by lab name then student surname.
     *
     * @param int $batchid Batch ID.
     * @param int $page    Zero-indexed page number.
     * @return \stdClass[]
     */
    public function get_batch_enrolments(int $batchid, int $page): array {
        global $DB;

        $records = $DB->get_records_sql(
            $this->batch_enrolments_sql(),
            ['batchid' => $batchid],
            $page * self::PER_PAGE,
            self::PER_PAGE
        );

        return array_values($records);
    }

    /**
     * All enrolment rows for the batch overview, unpaginated (used for full exports).
     *
     * @param int $batchid Batch ID.
     * @return \stdClass[]
     */
    public function get_all_batch_enrolments(int $batchid): array {
        global $DB;

        return array_values($DB->get_records_sql($this->batch_enrolments_sql(), ['batchid' => $batchid]));
    }

    /**
     * Shared SQL for the batch overview enrolment rows.
     *
     * @return string
     */
    private function batch_enrolments_sql(): string {
        return "SELECT ue.id, ue.userid, ue.timecreated AS enrolledat,
                        lc.id AS labid, lc.courseid,
                        c.fullname AS coursename,
                        u.firstname, u.lastname, u.firstnamephonetic,
                        u.lastnamephonetic, u.middlename, u.alternatename
                   FROM {user_enrolments} ue
                   JOIN {enrol} e ON e.id = ue.enrolid
                   JOIN {local_virtuallab_courses} lc ON lc.enrolid = e.id
                   JOIN {course} c ON c.id = lc.courseid
                   JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
                  WHERE lc.batchid = :batchid
               ORDER BY c.fullname ASC, u.lastname ASC, u.firstname ASC";
    }

    /**
     * Log summary (last access + event count) keyed by [userid][courseid].
     *
     * Queries logstore_standard_log for the given (userid, courseid) pairs only.
     * Both arrays must be non-empty — callers must guard against empty pages.
     *
     * @param int[] $courseids Moodle course IDs.
     * @param int[] $userids   User IDs.
     * @return array<int, array<int, array{lastactivity: int, eventcount: int}>>
     */
    public function get_log_summary(array $courseids, array $userids): array {
        global $DB;

        if (empty($courseids) || empty($userids)) {
            return [];
        }

        [$insqlc, $paramsc] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        [$insqlu, $paramsu] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        $sql = "SELECT l.userid, l.courseid,
                       MAX(l.timecreated) AS lastactivity,
                       COUNT(*) AS eventcount
                  FROM {logstore_standard_log} l
                 WHERE l.courseid $insqlc
                   AND l.userid $insqlu
              GROUP BY l.userid, l.courseid";

        $recordset = $DB->get_recordset_sql($sql, array_merge($paramsc, $paramsu));
        $result = [];
        foreach ($recordset as $row) {
            $result[(int) $row->userid][(int) $row->courseid] = [
                'lastactivity' => (int) $row->lastactivity,
                'eventcount'   => (int) $row->eventcount,
            ];
        }
        $recordset->close();

        return $result;
    }

    /**
     * Role map keyed by [userid][courseid] → roleid.
     *
     * When a user has multiple roles in the same course, editingteacher takes priority.
     *
     * @param int[] $courseids Moodle course IDs.
     * @param int[] $userids   User IDs.
     * @return array<int, array<int, int>>
     */
    public function get_role_map(array $courseids, array $userids): array {
        global $DB;

        if (empty($courseids) || empty($userids)) {
            return [];
        }

        [$insqlc, $paramsc] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        [$insqlu, $paramsu] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $params = array_merge($paramsc, $paramsu, ['ctxlevel' => CONTEXT_COURSE]);

        $sql = "SELECT ra.id, ra.userid, ctx.instanceid AS courseid, ra.roleid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                                    AND ctx.contextlevel = :ctxlevel
                 WHERE ctx.instanceid $insqlc
                   AND ra.userid $insqlu";

        $recordset = $DB->get_recordset_sql($sql, $params);
        $result = [];
        foreach ($recordset as $row) {
            $uid = (int) $row->userid;
            $cid = (int) $row->courseid;
            if (!isset($result[$uid][$cid]) || (int) $row->roleid === $this->teacherroleid) {
                $result[$uid][$cid] = (int) $row->roleid;
            }
        }
        $recordset->close();

        return $result;
    }

    /**
     * All enrolled users for a specific lab, ordered by surname then first name.
     *
     * @param int $labid   Row ID in local_virtuallab_courses.
     * @param int $batchid Batch ownership guard.
     * @return \stdClass[]
     */
    public function get_lab_enrolments(int $labid, int $batchid): array {
        global $DB;

        $sql = "SELECT ue.userid, ue.timecreated AS enrolledat,
                       lc.courseid,
                       u.firstname, u.lastname, u.firstnamephonetic,
                       u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {local_virtuallab_courses} lc ON lc.enrolid = e.id
                                                    AND lc.id = :labid
                                                    AND lc.batchid = :batchid
                  JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
              ORDER BY u.lastname ASC, u.firstname ASC";

        return array_values($DB->get_records_sql($sql, ['labid' => $labid, 'batchid' => $batchid]));
    }

    /**
     * Event breakdown per user for a specific course, keyed by userid.
     *
     * Each entry is an array of rows: [component, action, cnt, lasttime].
     * Rows are ordered by count descending within each user.
     *
     * @param int   $courseid Moodle course ID of the lab.
     * @param int[] $userids  User IDs to filter.
     * @return array<int, array<int, array{component: string, action: string, cnt: int, lasttime: int}>>
     */
    public function get_lab_event_breakdown(int $courseid, array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        [$insqlu, $paramsu] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $paramsu['courseid'] = $courseid;

        $sql = "SELECT l.userid, l.component, l.action,
                       COUNT(*) AS cnt,
                       MAX(l.timecreated) AS lasttime
                  FROM {logstore_standard_log} l
                 WHERE l.courseid = :courseid
                   AND l.userid $insqlu
              GROUP BY l.userid, l.component, l.action
              ORDER BY l.userid ASC, COUNT(*) DESC";

        $recordset = $DB->get_recordset_sql($sql, $paramsu);
        $result = [];
        foreach ($recordset as $row) {
            $result[(int) $row->userid][] = [
                'component' => $row->component,
                'action'    => $row->action,
                'cnt'       => (int) $row->cnt,
                'lasttime'  => (int) $row->lasttime,
            ];
        }
        $recordset->close();

        return $result;
    }

    /**
     * Returns a human-readable label for a Moodle event component.
     *
     * Falls back to the raw component string for unknown values.
     *
     * @param string $component Raw component name (e.g. 'mod_assign').
     * @return string
     */
    public static function component_label(string $component): string {
        $map = [
            'core'         => 'Curso',
            'mod_assign'   => 'Tarefa',
            'mod_forum'    => 'Fórum',
            'mod_quiz'     => 'Questionário',
            'mod_resource' => 'Arquivo',
            'mod_page'     => 'Página',
            'mod_url'      => 'URL',
            'mod_folder'   => 'Pasta',
            'mod_wiki'     => 'Wiki',
            'mod_glossary' => 'Glossário',
            'mod_chat'     => 'Chat',
            'mod_book'     => 'Livro',
            'mod_lti'      => 'Ferramenta externa',
            'mod_scorm'    => 'SCORM',
            'mod_feedback' => 'Feedback',
            'mod_survey'   => 'Pesquisa',
            'mod_choice'   => 'Escolha',
            'mod_data'     => 'Base de dados',
            'mod_lesson'   => 'Lição',
        ];
        return $map[$component] ?? $component;
    }

    /**
     * Returns a human-readable label for a Moodle event action.
     *
     * Falls back to the raw action string for unknown values.
     *
     * @param string $action Raw action name (e.g. 'viewed').
     * @return string
     */
    public static function action_label(string $action): string {
        $map = [
            'viewed'    => 'visualizado',
            'submitted' => 'enviado',
            'created'   => 'criado',
            'updated'   => 'atualizado',
            'deleted'   => 'excluído',
            'graded'    => 'avaliado',
            'uploaded'  => 'enviado (arquivo)',
            'downloaded' => 'baixado',
            'started'   => 'iniciado',
            'attempted' => 'tentativa realizada',
            'finished'  => 'concluído',
            'failed'    => 'reprovado',
        ];
        return $map[$action] ?? $action;
    }
}
