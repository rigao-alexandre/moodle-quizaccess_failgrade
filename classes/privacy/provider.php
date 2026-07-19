<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for quizaccess_failgrade.
 *
 * @package quizaccess_failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_failgrade\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for quizaccess_failgrade.
 *
 * quizaccess_failgrade itself stores no personal data, but the
 * quizaccess_failgrade_reset table (used to detect course/user resets from tools like
 * local_recompletion, see classes/observer.php) records a userid for per-user resets.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider
{
    /**
     * Describe the personal data stored by this plugin.
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection
    {
        $collection->add_database_table(
            'quizaccess_failgrade_reset',
            [
                'courseid' => 'privacy:metadata:quizaccess_failgrade_reset:courseid',
                'userid' => 'privacy:metadata:quizaccess_failgrade_reset:userid',
                'timereset' => 'privacy:metadata:quizaccess_failgrade_reset:timereset',
            ],
            'privacy:metadata:quizaccess_failgrade_reset'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain personal data for the given user.
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist
    {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {quizaccess_failgrade_reset} r
                  JOIN {context} ctx ON ctx.instanceid = r.courseid AND ctx.contextlevel = :contextcourse
                 WHERE r.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextcourse' => CONTEXT_COURSE,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users who have personal data within the given context.
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void
    {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {quizaccess_failgrade_reset} WHERE courseid = :courseid AND userid IS NOT NULL',
            ['courseid' => $context->instanceid]
        );
    }

    /**
     * Export personal data for the approved contexts of one user.
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void
    {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $records = $DB->get_records('quizaccess_failgrade_reset', [
                'courseid' => $context->instanceid,
                'userid' => $user->id,
            ]);

            if (empty($records)) {
                continue;
            }

            $data = (object) [
                'resets' => array_values(array_map(function ($record) {
                    return (object) [
                        'timereset' => \core_privacy\local\request\transform::datetime($record->timereset),
                    ];
                }, $records)),
            ];

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'quizaccess_failgrade')],
                $data
            );
        }
    }

    /**
     * Delete all personal data for all users in the given context.
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void
    {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $DB->delete_records('quizaccess_failgrade_reset', ['courseid' => $context->instanceid]);
    }

    /**
     * Delete personal data for one user, in each of their approved contexts.
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void
    {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $DB->delete_records('quizaccess_failgrade_reset', [
                'courseid' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Delete personal data for a set of users within a single context.
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void
    {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $inparams['courseid'] = $context->instanceid;

        $DB->delete_records_select(
            'quizaccess_failgrade_reset',
            "courseid = :courseid AND userid $insql",
            $inparams
        );
    }
}
