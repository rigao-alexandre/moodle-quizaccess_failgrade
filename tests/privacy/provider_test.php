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
 * Privacy provider tests for the quizaccess_failgrade plugin.
 *
 * @package quizaccess_failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_failgrade\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider tests for the quizaccess_failgrade plugin.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider_test extends \core_privacy\tests\provider_testcase
{
    /**
     * Insert a reset row directly, mirroring what classes/observer.php does.
     */
    protected function insert_reset($courseid, $userid)
    {
        global $DB;

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->userid = $userid;
        $record->timereset = time();

        $DB->insert_record('quizaccess_failgrade_reset', $record);
    }

    public function test_get_contexts_for_userid()
    {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course();
        $course2 = $generator->create_course();
        $user = $generator->create_user();

        // A per-user reset in course1, a course-wide reset (no user) in course2.
        $this->insert_reset($course1->id, $user->id);
        $this->insert_reset($course2->id, null);

        $contextlist = provider::get_contexts_for_userid($user->id);

        $this->assertCount(1, $contextlist);
        $this->assertEquals(
            [\context_course::instance($course1->id)->id],
            $contextlist->get_contextids()
        );
    }

    public function test_export_user_data()
    {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_reset($course->id, $user->id);

        $approvedlist = new approved_contextlist($user, 'quizaccess_failgrade', [$context->id]);
        provider::export_user_data($approvedlist);

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'quizaccess_failgrade')]);
        $this->assertNotEmpty($data);
        $this->assertCount(1, $data->resets);
    }

    public function test_delete_data_for_user()
    {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_reset($course->id, $user1->id);
        $this->insert_reset($course->id, $user2->id);

        $approvedlist = new approved_contextlist($user1, 'quizaccess_failgrade', [$context->id]);
        provider::delete_data_for_user($approvedlist);

        $this->assertEquals(
            0,
            $DB->count_records('quizaccess_failgrade_reset', ['courseid' => $course->id, 'userid' => $user1->id])
        );
        $this->assertEquals(
            1,
            $DB->count_records('quizaccess_failgrade_reset', ['courseid' => $course->id, 'userid' => $user2->id])
        );
    }

    public function test_get_users_in_context_and_delete_data_for_users()
    {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user1 = $generator->create_user();
        $user2 = $generator->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_reset($course->id, $user1->id);
        $this->insert_reset($course->id, $user2->id);
        $this->insert_reset($course->id, null); // Course-wide reset, no user to report.

        $userlist = new userlist($context, 'quizaccess_failgrade');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user1->id, $user2->id], $userlist->get_userids());

        $approvedlist = new approved_userlist($context, 'quizaccess_failgrade', [$user1->id]);
        provider::delete_data_for_users($approvedlist);

        $this->assertEquals(
            0,
            $DB->count_records('quizaccess_failgrade_reset', ['courseid' => $course->id, 'userid' => $user1->id])
        );
        $this->assertEquals(
            1,
            $DB->count_records('quizaccess_failgrade_reset', ['courseid' => $course->id, 'userid' => $user2->id])
        );
    }

    public function test_delete_data_for_all_users_in_context()
    {
        global $DB;

        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $context = \context_course::instance($course->id);

        $this->insert_reset($course->id, $user->id);
        $this->insert_reset($course->id, null);

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('quizaccess_failgrade_reset', ['courseid' => $course->id]));
    }
}
