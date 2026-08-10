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
 * Strings for the quizaccess_failgrade plugin.
 *
 * @package quizaccess
 * @subpackage failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Fail grade';

$string['failgradeenabled'] = 'Block extra attempts if passing grade';
$string['failgradeenabled_help'] = 'Prevent user from attempting the quiz again once they have received a passing grade.';

$string['failgradedescription'] = 'Attempts available until reaching passing grade.';
$string['preventmoreattempts'] = 'You have already passed this quiz, and may not make further attempts.';

$string['privacy:metadata:quizaccess_failgrade_reset'] = 'Records course or user reset events (for example, from local_recompletion or Moodle\'s "Reset course"), so that attempts and grades from before the reset are not used to block new quiz attempts.';
$string['privacy:metadata:quizaccess_failgrade_reset:courseid'] = 'The course the reset applies to.';
$string['privacy:metadata:quizaccess_failgrade_reset:userid'] = 'The user the reset applies to, if it was a per-user reset.';
$string['privacy:metadata:quizaccess_failgrade_reset:timereset'] = 'The time the reset happened.';

$string['failgrade:overrideattempt'] = 'Manually grant a user one more quiz attempt, bypassing this rule';

$string['manageoverrides'] = 'Manage manual overrides';
$string['lastattempt'] = 'Last attempt';
$string['grantoneattempt'] = 'Grant one more attempt';
$string['confirmoverride'] = 'Grant {$a} one more attempt at this quiz, even though they have already reached the passing grade?';
$string['overridegranted'] = '{$a} can now attempt this quiz again.';
$string['nooverridesneeded'] = 'No one is currently blocked from attempting this quiz.';
