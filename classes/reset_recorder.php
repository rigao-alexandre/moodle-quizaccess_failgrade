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
 * Shared helper for recording quizaccess_failgrade_reset rows.
 *
 * @package quizaccess_failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_failgrade;

defined('MOODLE_INTERNAL') || die();

/**
 * Records that a reset happened (automatic, via observer.php, or manual, via override.php),
 * so rule.php::reset_since() can ignore any attempt/grade from before it. Both callers write
 * to the same table through this one place, so there is a single source of truth for what
 * counts as a reset.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reset_recorder
{
    /**
     * Record that a reset happened.
     * @param int $courseid
     * @param int|null $userid null means the reset applies to every user in the course.
     */
    public static function record($courseid, $userid)
    {
        global $DB;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->userid = $userid;
        $record->timereset = time();

        $DB->insert_record('quizaccess_failgrade_reset', $record);
    }
}
