The options are frequently useful for debugging - see `--help` for a full list:

 - `--skip-push`: don't push to the destination repository - allows manual inspection afterwards
 - `--skip-destination-pull --skip-destination-init`: does not update the destination repository before syncing - allows you to run FBShipIt with the destination repository in a curated state

# Why are files/commits being skipped?

Run with `--verbose` for much more information - for example:

```
$ hhvm shipit_wdt.php --skip-destination-init --skip-destination-pull --skip-push --verbose
Starting phase: Initialize source fbsource repository
Finished phase: Initialize source fbsource repository
Starting phase: Pull source repository
Finished phase: Pull source repository
Skipping phase: Create a new git repo with an initial commit
Skipping phase: Initialize destination GitHub repository
Skipping phase: Pull destination repository
Starting phase: Synchronize commits
  DEBUG 674521d make WDT use new API, no longer special case
    Full ID: 674521d0868fc6ba6dd925fc3fdbf33614c217a2
    STRIP FILE: "fbcode/wdt/fbonly/..." matches pattern "@(^|/)fb-?only(/|$)@"
    STRIP FILE: "fbcode/opensource/shipit/bin/shipit_wdt.php" matches pattern "@^(?!fbcode/wdt/)@"
    SKIPPED COMMIT: no matching files
  SKIP 674521d make WDT use new API, no longer special case
  DEBUG 50fbe13 wdt version bump
    Full ID: 50fbe1334a46ed989e29c0253ae52d45567ed9ed
  OK 50fbe13 wdt version bump
  DEBUG 7c2a1e4 codemod: LOG -> WLOG (and VLOG and _IF variants) with common prefix for easier extraction in prod logs of svcs
    Full ID: 7c2a1e4c0fc7c10973ef1f07a7039fae5d1ac0dc
    STRIP FILE: "fbcode/wdt/fbonly/......" matches pattern "@(^|/)fb-?only(/|$)@"
  OK 7c2a1e4 codemod: LOG -> WLOG (and VLOG and _IF variants) with common prefix for easier extraction in prod logs of svcs
  DEBUG 24d5ea2 wdt version bump
    Full ID: 24d5ea24f1e6d8485cca6327365f9f2da849dca6
  OK 24d5ea2 wdt version bump
Finished phase: Synchronize commits
Skipping phase: Push destination repository
```

# `git-am: patch does not apply` or `already exists in index`

This means that git is unable to use the patch that FBShipIt created.

## General Investigation

Use `--save-patches-to=/some/directory` to save the patches that FBShipIt
creates:

```
$ hhvm shipit_wdt.php --skip-destination-init --skip-destination-pull --skip-push --save-patches-to=/tmp/wdt-github-patches
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
$ ls /tmp/wdt-github-patches/
24d5ea24f1e6d8485cca6327365f9f2da849dca6.patch
50fbe1334a46ed989e29c0253ae52d45567ed9ed.patch
674521d0868fc6ba6dd925fc3fdbf33614c217a2.patch
7c2a1e4c0fc7c10973ef1f07a7039fae5d1ac0dc.patch
```

The patches are named after the commit ID in the source repository. You should
be able to reproduce the `git-am` error by applying these
patches by hand, then debug like any other bad-patch issue.

## Common Causes

### File Does Not Exist On GitHub

If the file is meant to be public, commit the file directly to GitHub, with the content that it had before the diff that is blocking the push.

If the file is not meant to be public, modify the `stripPaths` part of your filter so that FBShipIt does not try to apply that part of the patch, and extend your unit test to cover it.

### File On GitHub Does Not Match Internal Copy

If the GitHub version is strictly better, create an internal commit copying the
changes internally, and put `@already-on-github` in the commit message.
One cause of this is people using the 'merge pull request' button on GitHub
instead of merging pull requests to your internal repository.

If the internal version is strictly better, commit an update directly to the GitHub repository.

If both contain changes you want, you will need to merge the two files by hand, using a combination of both approaches.

You can disable the 'merge pull request' button for your project  by sending a
fake unit test result (eg 'no-direct-merges') via the GitHub API, then marking
that unit test as required for merges to master.

# Stalls/lockups when using Mercurial repositories

We use `flock()` for added safety when interacting with Mercurial; to find out
if this is causing problems, try setting the `FBSHIPIT_DEBUG_FLOCK`
environment variable:

```
[fredemmott@devbig349.prn1 ~/fbcode/opensource/shipit/bin] hhvm shipit_folly.php --skip-push --skip-project-specific
--- Starting Facebook\ShipIt\ShipItFolly
Starting phase: Sanity-check commit filter
Finished phase: Sanity-check commit filter
Starting phase: Initialize source fbsource repository
Finished phase: Initialize source fbsource repository
Starting phase: Pull source repository
Finished phase: Pull source repository
Skipping phase: Create a new git repo with an initial commit
Starting phase: Initialize destination GitHub repository
Finished phase: Initialize destination GitHub repository
Starting phase: Pull destination repository
Finished phase: Pull destination repository
Starting phase: Synchronize commits
  No new commits to sync.
Finished phase: Synchronize commits
Skipping phase: Verify that destination repository is sync
Skipping phase: Push destination repository
[fredemmott@devbig349.prn1 ~/fbcode/opensource/shipit/bin] FBSHIPIT_DEBUG_FLOCK=yes hhvm shipit_folly.php --skip-push --skip-project-specific
--- Starting Facebook\ShipIt\ShipItFolly
Starting phase: Sanity-check commit filter
Finished phase: Sanity-check commit filter
Starting phase: Initialize source fbsource repository
Finished phase: Initialize source fbsource repository
Starting phase: Pull source repository
  [flock] Acquiring shared lock...
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] ...lock acquired.
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] Acquiring exclusive lock...
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] ...lock acquired.
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] Downgrading to shared lock...
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] ...lock downgraded.
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] Releasing lock...
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] ...lock released.
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
Finished phase: Pull source repository
Skipping phase: Create a new git repo with an initial commit
Starting phase: Initialize destination GitHub repository
Finished phase: Initialize destination GitHub repository
Starting phase: Pull destination repository
Finished phase: Pull destination repository
Starting phase: Synchronize commits
  [flock] Acquiring shared lock...
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] ...lock acquired.
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  No new commits to sync.
  [flock] Releasing lock...
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
  [flock] ...lock released.
    /var/tmp/oss_sync_and_push/fbsource/.hg/fbshipit.lock
Finished phase: Synchronize commits
Skipping phase: Verify that destination repository is sync
Skipping phase: Push destination repository
```
