# FBShipIt
#An Amazing Project

[![CI](https://github.com/facebook/fbshipit/workflows/CI/badge.svg)](https://github.com/facebook/fbshipit/actions?query=branch%3Amaster)

FBShipIt is a library written in [Hack](http://hacklang.org) for
copying commits from one repository to another.

For example, Facebook uses it to:

 - copy commits from our monolithic Mercurial repository to
   project-specific GitHub repositories
 - populate Buck's gh-pages branch with the contents of
   [the docs folder](https://github.com/facebook/buck/tree/master/docs)
    in the master branch.

## Major Features

 - read from Git or Mercurial (hg) repositories
 - write to Git or Mercurial (hg) repositories
 - rewrite `user@uuid` authors from HgSubversion/git-svn
 - remove files or directories matching certain patterns
 - remove/rewrite sections of commit messages
 - modify commit authors (for example, if all internal commits are authored by
   employees, restore original authors for GitHub pull requests)

## Major Limitations

FBShipIt has been primarily designed for branches with linear histories; in
particular, it does not understand merge commits.

## Requirements

 - [HHVM (latest release)](https://docs.hhvm.com/hhvm/installation)
 - [Composer](https://getcomposer.org/doc/00-intro.md)

## Installing

```
$ composer require facebook/fbshipit
```

## How FBShipIt Works

There are three main concepts: phases, changesets, and filters.

`ShipItPhase` objects represent a high-level action, such as
'clone this repository', 'pull this repository',
'sync changesets', and 'push repository'.

Within the sync phase, a `ShipItChangeset` is an immutable
object representing a commit.

Filters are functions that take
a Changeset, and return a new, modified one.

### Provided Phases

 - `ShipItCreateNewRepoPhase`: creates a new Git repository with an 'initial commit'. Skipped unless `--create-new-repo` passed.
 - `ShipItAssertValidFilterPhase`: make sure that the filter is consistent with the specified roots.
 - `ShipItGitHubInitPhase`: create and configure a github clone.
 - `ShipItPullPhase`: pull in any new changes to a repository.
 - `ShipItPushPhase`: push local changes to the destination repository.
 - `ShipItSyncPhase`: copy commits from the source repository to the destination repository.
 - `ShipItVerifyRepoPhase`: check that the destination repository matches the source repository and filter. Skipped unless `--verify` or `--create-fixup-patch` is passed.

## Using FBShipIt

You need to construct:
 - a `ShipItManifest` object, defining your default working directory, and the directory names of your source and destination repositories
 - a list of phases you want to run
 - a pipeline of filters, assuming you are using the `ShipItSyncPhase`

Filters are provided for common operations - the most frequent are:
 - `ShipItPathFilters::moveDirectories(string $changeset, dict<string, string> $mapping)`: apply patches to a different directory in the destination repository
 - `ShipItPathFilters::stripPaths(string $changeset, vec<string> $patterns, vec<string> $exception_patterns = vec[])`: remove any modifications to paths matching `$patterns`, unless they match something in `$exception_patterns`.

## Example

See the [`demo/`](demo) directory for a simple example to start from.

## Using With An Empty Destination Repository

Add `ShipItCreateNewRepoPhase` to your phase list (after source init and pull
phases), then run:

```
hhvm my_script.hack --create-new-repo
```

This will give you the path to a git repository with a single commit; you can then push it to your destination.

## Using With An Existing Destination Repository

When there is at least one relevant commit in the source repository that is not in the destination repository, run:

```
hhvm my_script.hack --first-commit=FIRST_UNSYNCED_COMMIT_ID_GOES_HERE
```

## Future Runs

Run your script with no arguments; FBShipIt adds tracking information to the
commits it creates, so will automatically sync any new commits.

## Reducing Common Code With Multiple Projects

We recommend splitting out common filters and phase setup to separate classes,
and for these to be re-used between your projects. For an example from Facebook's
usage, see [FBCommonFilters](fb-examples/lib/shipit/FBCommonFilters.php-example)

## Further Examples

Some other code that might be useful for configuring FBShipIt can be found in
[fb-examples/](https://github.com/facebook/fbshipit/tree/master/fb-examples).

## License

FBShipIt is MIT-licensed.
