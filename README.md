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

## Status / Roadmap

- [x] Publish plugin on GitHub

- [x] Submit to [Moodle Plugins directory](https://moodle.org/plugins/)

- [x] GDPR

- [x] Unit tests

- [ ] Behat tests

- [ ] Translate to other languages

## Development

Please, use GitHub for issues.

### Contributing code or translations

If you'd like to contribute a fix, a new feature, or a translation, a **pull request is strongly
preferred over attaching a file to an issue** (e.g. a zip with translated strings). A PR shows
exactly what changed, runs through CI automatically, and is much easier to review and merge than a
file someone has to download and apply by hand. If you're not comfortable with git/GitHub, opening
an issue with the file attached is still welcome - it just takes longer to get merged.

### Reporting a bug

To help diagnose the issue, please include:

- **Moodle version** (Site administration → General → Version)
- **PHP version** and database (MySQL/MariaDB/Postgres) and its version
- **Plugin version** (see `version.php`'s `release`, e.g. `v1.3.1`, or the exact commit if
  installed from git)
- **Quiz settings** relevant to the issue: grading method, "grade to pass", number of attempts
  allowed
- **Steps to reproduce**, and what you expected to happen vs. what actually happened
- Any relevant error message, or entry from the Moodle/PHP error log

Reports without this context are usually much harder to act on, so including it up front saves a
round trip.

### Packaging a release for moodle.org

The Moodle plugins directory takes a zip upload rather than linking directly to this repo, so a
release needs to be packaged first. `.gitattributes` marks the files that don't belong in that zip
(CI config, this README's own dev-only docs) via `export-ignore`, so `git archive` produces a clean
package on its own - no manual exclude flags to keep in sync:

```bash
ref=$(git describe --tags --exact-match 2>/dev/null || git rev-parse --short HEAD)
git archive --format=zip --prefix=failgrade/ "$ref" -o "quizaccess_failgrade-${ref}.zip"
```

Run this after checking out (or tagging) the exact commit to release. It picks up the tag pointing
at the current commit automatically (falling back to the short commit hash if there isn't one yet)
and uses it both as the archive ref and in the output filename, so there's nothing to edit by hand.
`--prefix=failgrade/` wraps the contents in a single top-level folder named after the plugin's
install path (`mod/quiz/accessrule/failgrade`), matching what moodle.org expects. `tests/` is intentionally
still included - useful for anyone installing from the zip who wants to run the suite locally.

## License

Licensed under the [GNU GPL License](http://www.gnu.org/copyleft/gpl.html)
