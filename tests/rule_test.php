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
 * Unit tests for the quizaccess_failgrade plugin.
 *
 * @package quizaccess
 * @subpackage failgrade
 * @category phpunit
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_failgrade;

use advanced_testcase;
use quizaccess_failgrade;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/quiz/accessrule/failgrade/rule.php');

// This work-around is required until Moodle 4.2 is the lowest version we support.
// Use plugin-specific alias names (not the generic 'quiz' / 'quiz_attempt') so this does not
// collide with other plugins' test bootstraps doing the same trick in the same PHPUnit run.
if (class_exists('\mod_quiz\local\access_rule_base')) {
    \class_alias('\mod_quiz\quiz_settings', 'quizaccess_failgrade_test_quiz');
    \class_alias('\mod_quiz\quiz_attempt', 'quizaccess_failgrade_test_quiz_attempt');
} else {
    \class_alias('quiz', 'quizaccess_failgrade_test_quiz');
    \class_alias('quiz_attempt', 'quizaccess_failgrade_test_quiz_attempt');
}

/**
 * Unit tests for the quizaccess_failgrade plugin.
 *
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_test extends advanced_testcase
{
    /**
     * Create a course with completion/groups enabled and a user enrolled in it.
     * @return array [$course, $user]
     */
    private function create_test_course_and_user()
    {
        global $CFG;

        $CFG->enablecompletion = true;
        $CFG->enableavailability = true;
        $generator = $this->getDataGenerator();

        $course = $generator->create_course(
            ['numsections' => 1, 'enablecompletion' => 1],
            ['createsections' => true]
        );

        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);
        $this->setUser($user);

        $group = $generator->create_group(['courseid' => $course->id]);
        groups_add_member($group, $user);

        return [$course, $user];
    }

    /**
     * Create a quiz with two numerical questions worth 1 mark each.
     * @return array [$quizobj, $quiz]
     */
    private function create_test_quiz($course, $user, $grademethod, $failgradeenabled, $attempts = 5, $quizgrade = 10.0)
    {
        $generator = $this->getDataGenerator();
        $quizgenerator = $generator->get_plugin_generator('mod_quiz');

        $quiz = $quizgenerator->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => $quizgrade,
            'sumgrades' => 2,
            'attempts' => $attempts,
            'name' => 'Quiz!',
            'grademethod' => $grademethod,
            'failgradeenabled' => $failgradeenabled,
        ]);

        $questiongenerator = $generator->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);
        $numq = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($numq->id, $quiz);

        $quizobj = \quizaccess_failgrade_test_quiz::create($quiz->id, $user->id);

        return [$quizobj, $quiz];
    }

    /**
     * Set the passing grade on a quiz's gradebook item.
     */
    private function set_grade_pass($course, $quiz, $gradepass)
    {
        $item = \grade_item::fetch([
            'courseid' => $course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'outcomeid' => null,
        ]);
        $item->gradepass = $gradepass;
        $item->update();
    }

    /**
     * Simulate a single finished quiz attempt and return the resulting attempt record.
     */
    private function do_attempt($quizobj, $user, $attemptnumber, array $answers)
    {
        $quba = \question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $timenow = time();
        $attempt = quiz_create_attempt($quizobj, $attemptnumber, false, $timenow, false, $user->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptnumber, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);
        $attemptobj = \quizaccess_failgrade_test_quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, $answers);

        // process_finish() is deprecated since Moodle 5.0 (MDL-68806, triggers a debugging()
        // call that --fail-on-warning treats as a test failure) in favour of calling these two
        // separately - but those two don't exist yet on the older branches this plugin still
        // supports, so detect which API is available instead of hard-coding a version number.
        if (method_exists($attemptobj, 'process_submit')) {
            $attemptobj->process_submit($timenow, false);
            $attemptobj->process_grade_submission($timenow);
        } else {
            $attemptobj->process_finish($timenow, false);
        }

        return $attempt;
    }

    public function test_setting()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();

        // The rule does not apply at all when failgradeenabled is off.
        [$quizobj] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 0);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);
        $this->assertNull($rule);

        // With failgradeenabled on, a quiz with no previous attempts never blocks.
        [$quizobj] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);
        $this->assertInstanceOf('quizaccess_failgrade', $rule);
        $this->assertFalse($rule->is_finished(0, null));
        $this->assertEmpty($rule->prevent_new_attempt(0, null));
    }

    public function test_grade_highest()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        // Fail.
        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        // Pass.
        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(2, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(2, $attempt));

        // Fail again: with "highest grade" the earlier pass still counts.
        $attempt = $this->do_attempt($quizobj, $user, 3, [1 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(3, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(3, $attempt));
    }

    public function test_grade_firstattempt()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();

        // First attempt fails: the grade stays "fail" for good, even if a later attempt passes.
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_ATTEMPTFIRST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(2, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(2, $attempt));

        // First attempt passes: the quiz is finished straight away.
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_ATTEMPTFIRST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(1, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(1, $attempt));
    }

    public function test_grade_lastattempt()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_ATTEMPTLAST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        // Fail.
        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        // Then pass: with "last attempt" only the most recent one counts.
        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(2, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(2, $attempt));
    }

    public function test_grade_average()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_GRADEAVERAGE, 1, 0);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        // Fail.
        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14']]);
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        // Pass: averaging in a full-marks attempt clears the pass grade.
        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(2, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(2, $attempt));
    }

    /**
     * A quiz with grading disabled ("grade" = 0) never gets a row in the gradebook's
     * grade_grades table (see quiz_update_grades() in mod/quiz/lib.php, which skips
     * pushing any grade value when $quiz->grade == 0). The grade_item itself still
     * exists though, so is_finished() must treat a missing per-user grade the same as
     * "not graded yet" instead of blocking - or worse, warning on the missing array key.
     */
    public function test_no_grade_item()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1, 5, 0.0);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);

        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));
    }

    /**
     * save_settings()/delete_settings() persist the failgradeenabled flag in the
     * quizaccess_failgrade table; neither was covered by the grading tests above.
     */
    public function test_save_and_delete_settings()
    {
        global $DB;

        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [, $quiz] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1);

        // Enabling the rule inserts exactly one record.
        $quiz->failgradeenabled = 1;
        quizaccess_failgrade::save_settings($quiz);
        $this->assertEquals(1, $DB->count_records('quizaccess_failgrade', ['quizid' => $quiz->id]));

        // Saving again while already enabled must not insert a duplicate row.
        quizaccess_failgrade::save_settings($quiz);
        $this->assertEquals(1, $DB->count_records('quizaccess_failgrade', ['quizid' => $quiz->id]));

        // Disabling deletes the record.
        $quiz->failgradeenabled = 0;
        quizaccess_failgrade::save_settings($quiz);
        $this->assertEquals(0, $DB->count_records('quizaccess_failgrade', ['quizid' => $quiz->id]));

        // delete_settings() removes any existing record regardless of the flag's value.
        $quiz->failgradeenabled = 1;
        quizaccess_failgrade::save_settings($quiz);
        quizaccess_failgrade::delete_settings($quiz);
        $this->assertEquals(0, $DB->count_records('quizaccess_failgrade', ['quizid' => $quiz->id]));
    }

    /**
     * Tools like local_recompletion or Moodle's own "Reset course" can reset a user's
     * completion/attempts for a new training cycle without clearing their old passing
     * grade, which would otherwise keep is_finished() blocking forever. Moodle's native
     * "Reset course" fires \core\event\course_reset_ended, which observer.php listens for;
     * this exercises that same path (record_reset()) that local_recompletion's own event
     * also delegates to - that second observer isn't covered here since local_recompletion
     * itself isn't part of this plugin's CI environment.
     */
    public function test_is_finished_ignores_attempts_before_a_course_reset()
    {
        global $DB;

        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        // Pass: blocked as usual, same as test_grade_highest().
        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(1, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(1, $attempt));

        // A course reset happens after that attempt (e.g. annual retraining). time() only has
        // second resolution, so instead of trusting that enough real time passes between the
        // statements in this test, force the recorded reset to a value derived from the first
        // attempt's own timestamp: unambiguously after it, regardless of wall-clock timing.
        \core\event\course_reset_ended::create([
            'context' => \context_course::instance($course->id),
            'other' => ['reset_options' => []],
        ])->trigger();
        $timereset = $attempt->timefinish + 1;
        $DB->set_field('quizaccess_failgrade_reset', 'timereset', $timereset, ['courseid' => $course->id]);

        // The same old attempt/grade must no longer block, even though numprevattempts is
        // still 1 (the reset tool may not have deleted the quiz_attempts row).
        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));

        // A fresh attempt made after the reset is evaluated normally again: derive its
        // timefinish from the recorded reset value for the same reason as above.
        $attempt = $this->do_attempt($quizobj, $user, 2, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $attempt->timefinish = $timereset + 1;
        $this->assertTrue($rule->is_finished(2, $attempt));
        $this->assertNotEmpty($rule->prevent_new_attempt(2, $attempt));
    }

    /**
     * get_blocked_users() drives override.php's list of who to show a "grant one more
     * attempt" button for - it must include users this rule is currently blocking, and
     * exclude everyone else (no attempts yet, or not yet passed).
     */
    public function test_get_blocked_users()
    {
        $this->resetAfterTest();

        [$course, $passeduser] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $passeduser, QUIZ_GRADEHIGHEST, 1);
        $this->set_grade_pass($course, $quiz, 6);

        $generator = $this->getDataGenerator();
        $faileduser = $generator->create_user();
        $generator->enrol_user($faileduser->id, $course->id);
        $untricduser = $generator->create_user();
        $generator->enrol_user($untricduser->id, $course->id);

        // Passes: should show up as blocked.
        $passedattempt = $this->do_attempt(
            $quizobj,
            $passeduser,
            1,
            [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]
        );

        // Fails: has an attempt, but not blocked.
        $this->do_attempt($quizobj, $faileduser, 1, [1 => ['answer' => '3.14']]);

        // $untricduser never attempts at all, and must not appear either.

        $blocked = quizaccess_failgrade::get_blocked_users($quizobj);

        $this->assertArrayHasKey($passeduser->id, $blocked);
        $this->assertEquals($passedattempt->id, $blocked[$passeduser->id]->id);
        $this->assertArrayNotHasKey($faileduser->id, $blocked);
        $this->assertArrayNotHasKey($untricduser->id, $blocked);
    }

    /**
     * A manual override (override.php, via reset_recorder::record()) must unblock a user
     * the same way an automatic reset does - it writes to the same table that
     * reset_since() reads from, see classes/reset_recorder.php.
     */
    public function test_manual_override_unblocks_user()
    {
        $this->resetAfterTest();

        [$course, $user] = $this->create_test_course_and_user();
        [$quizobj, $quiz] = $this->create_test_quiz($course, $user, QUIZ_GRADEHIGHEST, 1);
        $this->set_grade_pass($course, $quiz, 6);
        $rule = quizaccess_failgrade::make($quizobj, 0, false);

        $attempt = $this->do_attempt($quizobj, $user, 1, [1 => ['answer' => '3.14'], 2 => ['answer' => '3.14']]);
        $this->assertTrue($rule->is_finished(1, $attempt));

        \quizaccess_failgrade\reset_recorder::record($course->id, $user->id);

        $this->assertFalse($rule->is_finished(1, $attempt));
        $this->assertEmpty($rule->prevent_new_attempt(1, $attempt));
    }
}
