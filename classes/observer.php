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
 * Event observers for the quizaccess_failgrade plugin.
 *
 * @package quizaccess_failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_failgrade;

/**
 * Records course/user reset events so rule.php can stop treating attempts and grades from
 * before the reset as still relevant.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer
{
    /**
     * Handle Moodle's own "Reset course" feature. This applies to every user in the course.
     * @param \core\event\course_reset_ended $event
     */
    public static function course_reset_ended(\core\event\course_reset_ended $event)
    {
        self::record_reset((int) $event->courseid, null);
    }

    /**
     * Handle a local_recompletion reset for a single user.
     * @param \local_recompletion\event\completion_reset $event
     */
    public static function recompletion_completion_reset(\local_recompletion\event\completion_reset $event)
    {
        self::record_reset((int) $event->courseid, (int) $event->relateduserid);
    }

    /**
     * Record that a reset happened, so is_finished() can ignore anything before it.
     * @param int $courseid
     * @param int|null $userid null means the reset applies to every user in the course.
     */
    protected static function record_reset($courseid, $userid)
    {
        global $DB;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->userid = $userid;
        $record->timereset = time();

        $DB->insert_record('quizaccess_failgrade_reset', $record);
    }
}
