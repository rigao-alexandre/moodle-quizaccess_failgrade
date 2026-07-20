# Moodle - Fail Grade Quiz Access Rule (quizaccess_failgrade)

## Description

Restricts further quiz attempts once a user reaches the quiz's "grade to pass", using whichever
grading method the quiz is already configured with (highest grade, average, first attempt, last
attempt). Works out of the box: no extra settings to configure, no separate grade field - it reuses
the "Grade to pass" the quiz already has.

**Compatibility note:** if your site resets courses periodically for retraining (e.g. Moodle's own
"Reset course", or a tool like [local_recompletion](https://github.com/danmarsden/moodle-local_recompletion))
without clearing old quiz grades, this plugin may keep blocking reattempts after a reset - see
[Known limitations](https://github.com/rigao-alexandre/moodle-quizaccess_failgrade#known-limitations)
for the current workaround and status.

## Credits

Originally based on:

- [Reattempt Checker - a quiz access rule](https://github.com/terrycampbell/moodle-quizaccess_reattemptchecker)
  https://moodle.org/plugins/quizaccess_reattemptchecker
- [Pass grade quiz access rule](https://github.com/catalyst/moodle-quizaccess_passgrade)
  https://moodle.org/plugins/quizaccess_passgrade

## Installation

Please refer to the official documentation: [Installing Plugins](https://docs.moodle.org/en/Installing_plugins)

## Requirements

- Moodle 3.9 (2020060900) through Moodle 5.2, tested via CI against every stable branch in that
  range (see `.github/workflows/main.yml`).

## Known limitations

### Course/user resets ("Reset course", recompletion, etc.)

This plugin decides whether to block a new attempt using two things: the user's previous attempts
on the quiz, and their current grade in the gradebook. It has no notion of "training cycles" - so if
another tool resets a user's completion/attempts for retraining purposes but leaves their old
quiz attempts and/or gradebook grade in place, this plugin can keep blocking new attempts, thinking
the user is reattempting a quiz they already passed.

This has been reported in practice with [local_recompletion](https://github.com/danmarsden/moodle-local_recompletion):
by default it preserves attempt/grade history (sensible for compliance/audit trails), and its
per-course "Delete grade data" option is off unless explicitly enabled. If your site resets courses
for periodic/annual retraining and you don't want a passed quiz to permanently block reattempts
after a reset:

- **Moodle's native "Reset course":** already safe with the default options. "Remove all quiz
  attempts" is checked by default, and when it runs, `mod_quiz` also clears the quiz's gradebook
  grade as part of the same step - regardless of whether the general "Remove all course grades"
  option was selected. The issue can only resurface here if "Remove all quiz attempts" is
  deliberately unchecked while resetting a course for some other reason.
- **`local_recompletion` workaround today:** in the course's recompletion settings, enable both
  "Quiz attempts: Delete" and "Delete grade data" - unlike the native reset above, these are fully
  independent here, and skipping "Delete grade data" leaves the old grade in place. Note this
  removes the old grade from the live gradebook (it's still recoverable via Moodle's "Grade
  history" report, just not visible in the normal grade views) - not ideal if you need the old
  pass/fail visible as a compliance record.
- **In progress:** automatic detection of course resets (both Moodle's native "Reset course" and
  `local_recompletion`) is being worked on, so a reset can be recognised without needing to delete
  any grade/attempt history. Track progress via the GitHub issues.

## Status / Roadmap

- [x] Publish plugin on GitHub

- [x] Submit to [Moodle Plugins directory](https://moodle.org/plugins/)

- [x] GDPR

- [x] Unit tests

- [ ] Behat tests

- [ ] Translate to other languages

## Development

Please, use GitHub for issues.

## License

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html)
