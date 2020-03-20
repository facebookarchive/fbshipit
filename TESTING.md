# Running The Automatic tests

 - run `hh_client` to make sure there are no Hack errors.
 - run `vendor/bin/hacktest tests` from FBShipIt's directory to run FBShipIt's own tests.

# Automated Tests For Your Project

 - run `hh_client` to make sure there are no Hack errors.
   Add an empty `.hhconfig` file to your project root if necessary.
 - look at [fb-examples/tests/](https://github.com/facebook/fbshipit/tree/master/fb-examples/tests) for examples of automated tests with [HackTest](https://github.com/hhvm/hacktest).

# Manual Testing

The easiest way to manually test is to re-run FBShipIt over commits that have already been copied.

 - Clone your destination repository
 - Rewind it (eg `git reset --hard origin/master^^^`) so there are revisions that are not in the branch
 - Run with `--skip-push --source-repo-dir=/path/to/your/new/clone --skip-destination-pull'

For example:

```
$ cd /var/tmp/oss_sync_and_push/wdt
$ git reset --hard origin/master^^^
$ cd ~/fbcode/opensource/shipit/bin
$ hhvm shipit_wdt.php --skip-destination-init --skip-destination-pull --skip-push
Starting phase: Initialize source fbsource repository
Finished phase: Initialize source fbsource repository
Starting phase: Pull source repository
Finished phase: Pull source repository
Skipping phase: Create a new git repo with an initial commit
Skipping phase: Initialize destination GitHub repository
Skipping phase: Pull destination repository
Starting phase: Synchronize commits
  SKIP 674521d make WDT use new API, no longer special case
  OK 50fbe13 wdt version bump
  OK 7c2a1e4 codemod: LOG -> WLOG (and VLOG and _IF variants) with common prefix for easier extraction in prod logs of svcs
  OK 24d5ea2 wdt version bump
Finished phase: Synchronize commits
Skipping phase: Push destination repository
$ cd /var/tmp/oss_sync_and_push/wdt
$ # Were any commits applied?
$ git log --oneline origin/master..HEAD
0e8d4de wdt version bump
a592375 codemod: LOG -> WLOG (and VLOG and _IF variants) with common prefix for easier extraction in prod logs of svcs
3d9d953 wdt version bump
$ # Yep, same ones as FBShipIt said.
$ # Did the new code create the same result as the old code?
$ git diff origin/master..HEAD
$ # Yes, no difference :)
```
