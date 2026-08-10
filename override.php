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
 * Lets a teacher/admin manually grant a user one more attempt at a quiz that this plugin
 * would otherwise keep blocking - for exceptions that automatic reset detection (see
 * classes/observer.php) does not cover. See ROADMAP.md for the background.
 *
 * @package quizaccess_failgrade
 * @copyright 2020 Alexandre Paes Rigão <rigao.com.br>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/rule.php');
// For the QUIZ_GRADEAVERAGE/QUIZ_ATTEMPTLAST constants used below - not guaranteed to already
// be loaded here, since this page doesn't go through mod_quiz's normal view/report dispatch.
require_once($CFG->dirroot . '/mod/quiz/lib.php');

$cmid = required_param('cmid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('quizaccess/failgrade:overrideattempt', $context);

$pageurl = new moodle_url('/mod/quiz/accessrule/failgrade/override.php', ['cmid' => $cmid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($context);
$PAGE->set_title(get_string('manageoverrides', 'quizaccess_failgrade'));
$PAGE->set_heading($course->fullname);

$quizobj = quizaccess_failgrade_quiz::create($cm->instance);
$quiz = $quizobj->get_quiz();

// Step 2: the actual write, reached only via the POST button on the confirmation screen below.
if ($action === 'override' && $userid) {
    require_sesskey();

    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    \quizaccess_failgrade\reset_recorder::record($course->id, $userid);

    redirect(
        $pageurl,
        get_string('overridegranted', 'quizaccess_failgrade', fullname($user)),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Step 1: confirmation screen for a specific user, reached from the table below.
if ($action === 'confirm' && $userid) {
    $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $continue = new moodle_url($pageurl, ['action' => 'override', 'userid' => $userid, 'sesskey' => sesskey()]);

    $message = get_string('confirmoverride', 'quizaccess_failgrade', fullname($user));

    // With these two grading methods, a low score on the new attempt can overwrite the
    // student's already-passing recorded grade (see ROADMAP.md) - worth a heads-up before
    // the teacher/admin confirms, since it's easy to assume "one more attempt" is risk-free.
    if ($quiz->grademethod == QUIZ_ATTEMPTLAST) {
        $message .= ' ' . get_string('warningoverridelastattempt', 'quizaccess_failgrade', fullname($user));
    } else if ($quiz->grademethod == QUIZ_GRADEAVERAGE) {
        $message .= ' ' . get_string('warningoverrideaverage', 'quizaccess_failgrade', fullname($user));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        $message,
        new single_button($continue, get_string('grantoneattempt', 'quizaccess_failgrade'), 'post'),
        $pageurl
    );
    echo $OUTPUT->footer();
    exit;
}

// Default: list every user this rule is currently blocking on this quiz.
$blocked = quizaccess_failgrade::get_blocked_users($quizobj);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($quiz->name));

if (empty($blocked)) {
    echo $OUTPUT->notification(get_string('nooverridesneeded', 'quizaccess_failgrade'), 'info');
} else {
    $users = $DB->get_records_list('user', 'id', array_keys($blocked));

    $table = new html_table();
    $table->head = [get_string('fullnameuser'), get_string('lastattempt', 'quizaccess_failgrade'), ''];

    foreach ($blocked as $blockeduserid => $attempt) {
        if (empty($users[$blockeduserid])) {
            // The user may have been deleted/suspended since the attempt was made.
            continue;
        }

        $confirmurl = new moodle_url($pageurl, ['action' => 'confirm', 'userid' => $blockeduserid]);

        $table->data[] = [
            html_writer::link(
                new moodle_url('/user/view.php', ['id' => $blockeduserid, 'course' => $course->id]),
                fullname($users[$blockeduserid])
            ),
            userdate($attempt->timefinish),
            html_writer::link($confirmurl, get_string('grantoneattempt', 'quizaccess_failgrade'), ['class' => 'btn btn-secondary btn-sm']),
        ];
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
