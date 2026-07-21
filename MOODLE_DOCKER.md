# Testing this plugin with moodle-docker

How to spin up a real, clickable Moodle site to manually test this plugin against a specific
Moodle version/branch, using [moodle-docker](https://github.com/moodlehq/moodle-docker) (the
official moodlehq Docker environment - the same one `moodle-plugin-ci` uses under the hood).

## Why WSL2, not a Windows drive

On Windows, if Docker Desktop uses the WSL2 backend (check with `docker info | grep WSL`), **do
not** put the Moodle checkout on a Windows drive path (`C:\`, `R:\`, etc.) and bind-mount it into
the container. That path gets mounted cross-OS (Windows ↔ WSL2), which is very slow for the kind
of many-small-file I/O Moodle's installer does - a fresh install that takes ~1 minute on a native
Linux filesystem can take over an hour this way, without actually failing (it just crawls).

Instead, do everything inside the WSL2 distro's own native filesystem (e.g. `~` inside
`wsl -d <distro>`), not under `/mnt/c/...` or `/mnt/r/...` (those are the same Windows drives,
just seen from the Linux side - still slow for the same reason).

All commands below assume you're running them from Windows via:
```
wsl -d <your-distro-name> -- bash -lc "<command>"
```
(list your distros with `wsl --list --verbose`; substitute your actual distro name.) If you're
already inside a WSL2 shell, just drop the `wsl -d <distro> -- bash -lc "..."` wrapper.

## One-time setup

```bash
# Everything lives under ~/moodle5-docker-test (native WSL2 filesystem).
mkdir -p ~/moodle5-docker-test && cd ~/moodle5-docker-test

# 1. The moodle-docker tooling itself (compose files + helper scripts).
git clone https://github.com/moodlehq/moodle-docker.git .

# 2. Moodle core, at whichever branch you want to test against.
git clone --depth 1 -b MOODLE_501_STABLE https://github.com/moodle/moodle.git moodle

# 3. Docker config for Moodle.
cp config.docker-template.php moodle/config.php

# 4. This plugin, checked out at the branch you want to test, in its subplugin path.
#    Moodle 5.0+ moved the docroot under public/ - for 4.x branches, use "moodle/mod/..." instead.
git clone -b moodle5-support \
  https://github.com/rigao-alexandre/moodle-quizaccess_failgrade.git \
  moodle/public/mod/quiz/accessrule/failgrade
```

## Start containers and install the site

```bash
cd ~/moodle5-docker-test
export MOODLE_DOCKER_WWWROOT=/home/<you>/moodle5-docker-test/moodle   # absolute path, no $(pwd) - see note below
export MOODLE_DOCKER_DB=mariadb
export MOODLE_DOCKER_PHP_VERSION=8.2    # match whatever the target Moodle branch needs

bin/moodle-docker-compose up -d
bin/moodle-docker-wait-for-db

bin/moodle-docker-compose exec webserver php admin/cli/install_database.php \
  --agree-license --fullname="Failgrade test" --shortname="fgtest" \
  --summary="quizaccess_failgrade test site" \
  --adminpass="Test1234!" --adminemail="admin@example.com"
```

> **Note:** if you're driving this from a single non-interactive command line (e.g. scripting it),
> use a literal absolute path for `MOODLE_DOCKER_WWWROOT` rather than `$(pwd)/moodle` - if that
> command line itself gets wrapped/quoted by something else (like `wsl -- bash -lc "..."`), the
> `$(pwd)` can get evaluated in the wrong shell and silently produce an empty/wrong path.

Site is then at **http://localhost:8000/** (Docker Desktop exposes this to Windows automatically,
even though everything runs inside WSL2) - log in as `admin` / `Test1234!`.

## Run cron

Needed for scheduled/adhoc tasks (e.g. you may see a notice about `mod_qbank` transfer tasks not
being complete on a fresh install - that's just cron not having run yet, unrelated to this plugin):

```bash
bin/moodle-docker-compose exec webserver php admin/cli/cron.php
```

## Set up a test scenario (student + course + quiz + questions)

**Testing as `admin` is not enough** - admins normally use "Preview quiz", which skips every access
rule (including this plugin's), so you'd never see the actual blocking behaviour.

This single script creates everything needed in one go: a Student-role user (reused if it already
exists, so the script is safe to re-run), a course, an enrolment, a quiz with "Block extra attempts
if passing grade" already enabled and "grade to pass" set to 6/10, and two numerical questions
added to it.

Write it to `moodle/public/setup_test_scenario.php`, run it, then delete it. If you're on Windows
and hit garbled `$` variables writing this through several layers of shell quoting
(`wsl -- bash -lc "..."` wrapping a heredoc), write the file directly via the WSL UNC path from
Windows instead of through a shell at all:
`\\wsl.localhost\<distro>\home\<you>\moodle5-docker-test\moodle\public\setup_test_scenario.php`.

```php
<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/testing/generator/component_generator_base.php');
require_once($CFG->libdir . '/testing/generator/module_generator.php');
require_once($CFG->libdir . '/testing/generator/data_generator.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/question/engine/bank.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

// ---- 1. Student user (reused if it already exists). ----
$student = $DB->get_record('user', ['username' => 'student1']);
if (!$student) {
    $newuser = new stdClass();
    $newuser->username = 'student1';
    $newuser->password = 'Student1234!';
    $newuser->firstname = 'Student';
    $newuser->lastname = 'One';
    $newuser->email = 'student1@example.com';
    $newuser->auth = 'manual';
    $newuser->confirmed = 1;
    $newuser->mnethostid = $CFG->mnet_localhost_id;
    $studentid = user_create_user($newuser, true, true);
    $student = $DB->get_record('user', ['id' => $studentid], '*', MUST_EXIST);
    echo "Created user student1 (id {$student->id})\n";
} else {
    echo "Reusing existing user student1 (id {$student->id})\n";
}

$generator = new testing_data_generator();

// ---- 2. Course. ----
$shortname = 'fgtest-' . time();
$course = $generator->create_course([
    'fullname' => 'Fail Grade test course',
    'shortname' => $shortname,
]);
echo "Course id: {$course->id} (shortname {$shortname})\n";

// ---- 3. Enrol the student. ----
$generator->enrol_user($student->id, $course->id, 'student');
echo "Enrolled student1 in the course\n";

// ---- 4. Quiz, with the plugin's rule already enabled. ----
$quizgenerator = $generator->get_plugin_generator('mod_quiz');
$quiz = $quizgenerator->create_instance([
    'course' => $course->id,
    'name' => 'Fail Grade test quiz',
    'grade' => 10.0,
    'sumgrades' => 2,
    'attempts' => 5,
    'grademethod' => QUIZ_GRADEHIGHEST,
    'failgradeenabled' => 1,
]);
echo "Quiz id: {$quiz->id}\n";

// ---- 5. Grade to pass. ----
$item = grade_item::fetch([
    'courseid' => $course->id,
    'itemtype' => 'mod',
    'itemmodule' => 'quiz',
    'iteminstance' => $quiz->id,
    'outcomeid' => null,
]);
$item->gradepass = 6;
$item->update();
echo "Grade to pass set to 6 (out of 10)\n";

// ---- 6. Questions, via the same production API the question bank form uses - NOT
//         $generator->get_plugin_generator('core_question')->create_question(), which fails
//         outside PHPUnit with "Class PHPUnit\Framework\TestCase not found": many qtype "test
//         helpers" (e.g. numerical's default question fixture) are themselves defined inside
//         real PHPUnit test files, which a normal site install doesn't have available. ----
$questiongenerator = $generator->get_plugin_generator('core_question');
$cat = $questiongenerator->create_question_category();

function make_numerical_pi_form(int $categoryid, string $name): stdClass {
    $form = new stdClass();
    $form->category = $categoryid; // Plain category id is fine - save_question() only
                                    // explode(',')s it, which is a no-op without a comma.
    $form->name = $name;
    $form->questiontext = ['format' => FORMAT_HTML, 'text' => 'What is pi to two d.p.?'];
    $form->defaultmark = 1;
    $form->generalfeedback = ['format' => FORMAT_HTML, 'text' => '3.14 is the right answer.'];

    $form->noanswers = 2;
    $form->answer = ['3.14', '*'];
    $form->tolerance = [0, 0];
    $form->fraction = ['1.0', '0.0'];
    $form->feedback = [
        ['format' => FORMAT_HTML, 'text' => 'Correct.'],
        ['format' => FORMAT_HTML, 'text' => 'Incorrect.'],
    ];

    $form->unitrole = '3';
    $form->unitpenalty = 0.1;
    $form->unitgradingtypes = '1';
    $form->unitsleft = '0';
    $form->nounits = 0;
    $form->multiplier = [];

    $form->penalty = 0.3333333;
    $form->numhints = 0;
    $form->hint = [];

    $form->qtype = 'numerical';
    $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

    return $form;
}

for ($i = 1; $i <= 2; $i++) {
    $question = new stdClass();
    $question->qtype = 'numerical';
    $question->createdby = 2; // admin.
    $question->idnumber = null;
    $question->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;

    $form = make_numerical_pi_form($cat->id, "Pi to two d.p. ({$i})");
    $saved = question_bank::get_qtype('numerical')->save_question($question, $form);
    quiz_add_quiz_question($saved->id, $quiz);
}
echo "Added 2 numerical questions (correct answer: 3.14)\n";

// ---- Summary. ----
$cm = get_coursemodule_from_instance('quiz', $quiz->id);
echo "\nDone.\n";
echo "Course: {$CFG->wwwroot}/course/view.php?id={$course->id}\n";
echo "Quiz:   {$CFG->wwwroot}/mod/quiz/view.php?id={$cm->id}\n";
echo "Log in as student1 / Student1234! to attempt the quiz.\n";
```

```bash
bin/moodle-docker-compose exec webserver php public/setup_test_scenario.php
# then, from the WSL side (not through docker exec):
rm ~/moodle5-docker-test/moodle/public/setup_test_scenario.php
```

Re-running it creates a fresh course/quiz each time (the shortname includes a timestamp) while
reusing the same `student1` user - handy for testing a scenario again after checking out a
different branch.

If you'd rather not script the questions, adding 1-2 through the UI (Question bank → Create a new
question → Numerical) takes well under a minute and exercises the real UI anyway.

## What to actually test

- **Site administration → Plugins → Activity modules → Quiz → Quiz access rules**
  (`admin/category.php?category=modsettingsquizcat`) - loads clean, no `class_alias` warning. This
  is the exact page from the original bug report (#13), and this environment already has several
  other core accessrule plugins installed alongside it (seb, numattempts, timelimit, etc.), so it's
  a realistic collision test.
- Create a quiz with "Block extra attempts if passing grade" enabled and a "grade to pass" set. As
  the student user: fail an attempt (should allow retry), then pass (should block further
  attempts).
- To also test the `recompletion-compat` branch's course-reset detection: after passing, use
  "Reset course" (Participants → Reset, or the course-level reset link) and confirm a new attempt
  is allowed again afterwards.

## Stop / tear down

```bash
cd ~/moodle5-docker-test
export MOODLE_DOCKER_WWWROOT=/home/<you>/moodle5-docker-test/moodle
export MOODLE_DOCKER_DB=mariadb
export MOODLE_DOCKER_PHP_VERSION=8.2
bin/moodle-docker-compose down -v
```

## Testing a different branch (plugin or Moodle core)

To switch what's being tested:

```bash
# Plugin: check out a different branch in place.
cd ~/moodle5-docker-test/moodle/public/mod/quiz/accessrule/failgrade
git fetch origin && git checkout <branch-name>

# Moodle core: easiest is a fresh clone at the new branch/PHP version, since MOODLE_DOCKER_WWWROOT,
# the docker containers, and the installed database are all tied together for a given version.
```
