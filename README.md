# FBShipIt

[![Build Status](https://travis-ci.org/facebook/fbshipit.svg?branch=master)](https://travis-ci.org/facebook/fbshipit)

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
$ hhvm /path/to/composer require facebook/fbshipit
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
 - `ShipItFilterSanityCheckPhase`: make sure that the filter is consistent with the specified roots.
 - `ShipItGitHubInitPhase`: create and configure a github clone.
 - `ShipItPullPhase`: pull in any new changes to a repository.
 - `ShipItPushPhase`: push local changes to the destination repository.
 - `ShipItSyncPhase`: copy commits from the source repository to the destination repository.
 - `ShipItVerifyRepoPhase`: check that the destination repository matches the source repository and filter. Skipped unless `--verify` or `--create-fixup-patch` is passed.

## Using FBShipIt

You need to construct:
 - a `ShipItBaseConfig` object, defining your default working directory, and the directory names of your source and destination repositories
 - a list of phases you want to run
 - a pipeline of filters, assuming you are using the `ShipItSyncPhase`

Filters are provided for common operations - the most frequent are:
 - `ShipItPathFilters::moveDirectories(string $changeset, ImmMap<string, string> $mapping)`: apply patches to a different directory in the destination repository
 - `ShipItPathFilters::stripPaths(string $changeset, ImmVector<string> $patterns, ImmVector<string> $exception_patterns = ImmVector { })`: remove any modifications to paths matching `$patterns`, unless they match something in `$exception_patterns`.

## Example

```Hack
<?hh

namespace Facebook\ShipIt;

class ShipMyProject
  implements \Facebook\ImportIt\ImportItPathMappings {

  public static function getPathMappings(): ImmMap<string, string> {
    return ImmMap {
      'myproject/' => '',
    };
  }

  public static function filterChangeset(
    ShipItChangeset $changeset,
  ): ShipItChangeset {
    return $changeset
      |> ShipItPathFilters::stripPaths(
          $$,
          ImmVector {
            '@^(?!myproject/)@',
            '@^myproject(/[^/]+)*/MY_PRIVATE_STUFF$@',
            '@/non[-_]?public/@',
          },
        )
      |> ShipItPathFilters::moveDirectories(
          $$,
          self::getPathMappings(),
        );
  }

  public static function cliMain(): void {
    $config = new ShipItBaseConfig(
      /* default working dir = */ '/var/tmp/shipit',
      'source_dir_name',
      'dest_dir_name',
    );

    $phases = ImmVector {
      new MySourceRepoInitPhase(/* ... */ ),
      new ShipItPullPhase(ShipItRepoSide::SOURCE),
      new ShipItGitHubInitPhase(
        'my-github-org',
        'my-github-project',
        ShipItRepoSide::DESTINATION,
        ShipItTransport::HTTPS,
        MyGitHubUtils::class,
      ),
      new ShipItCreateNewRepoPhase(),
      new ShipItPullPhase(ShipItRepoSide::DESTINATION),
      new ShipItSyncPhase(
        ($config, $changeset) ==> self::filterChangeset($changeset),
        /* directories to run hg log/hg diff in: */ ImmSet { 'myproject/' },
      ),
      new ShipItPushPhase(),
    };

    (new ShipItPhaseRunner($config, $phases))->run();
  }
}

// Allow require() from unit test
if (isset($argv) && idx($argv, 0) === realpath(__FILE__)) {
  ShipMyProject::cliMain();
}
```

```Hack
<?hh

namespace Facebook\ImportIt;

use \Facebook\ShipIt\ {
  MyGitHubUtils,
  MySourceRepoInitPhase,
  ShipItBaseConfig,
  ShipItChangeset,
  ShipItCleanPhase,
  ShipItPullPhase,
  ShipItRepoSide,
  ShipItTransport
};

class ImportMyProject {

  public static function filterChangeset(
    ShipItChangeset $changeset,
  ): ShipItChangeset {
    return $changeset
      |> ImportItPathFilters::moveDirectories(
          $$,
          ShipMyProject::getPathMappings(),
        );
  }

  public static function cliMain(): void {
    // The repository state will be updated and modified, so we need to use a
    // consistent destination repository for all ImportIt phases.
    $source_repo_getter = (ShipItBaseConfig $c) ==> {
      return new ImportItRepoGIT(
        $c->getSourcePath(),
        $c->getSourceBranch(),
      );
    };

    (new ShipItPhaseRunner(
      new ShipItBaseConfig(
        /* default working dir = */ '/var/tmp/shipit',
        'source_dir_name',
        'dest_dir_name',
      ),
      ImmVector {
        new MySourceRepoInitPhase(/* ... */ ),
        new ShipItCleanPhase(ShipItRepoSide::DESTINATION),
        new ShipItPullPhase(ShipItRepoSide::DESTINATION),
        new ShipItGitHubInitPhase(
          'my-github-org',
          'my-github-project',
          ShipItRepoSide::SOURCE,
          ShipItTransport::HTTPS,
          MyGitHubUtils::class,
        ),
        new ShipItCleanPhase(ShipItRepoSide::SOURCE),
        new ShipItPullPhase(ShipItRepoSide::SOURCE),
        new ImportItCheckoutBaseRevisionPhase($source_repo_getter),
        new ImportItApplyPatchPhase(
          $source_repo_getter,
          ShipItRepoSide::SOURCE,
        ),
        new ImportItApplyPatchPhase(
          $source_repo_getter,
          ShipItRepoSide::DESTINATION,
          $changeset ==> self::filterChangeset($changeset),
        ),
      },
    ))
      ->run();
  }
}

// Allow require() from unit test
if (isset($argv) && idx($argv, 0) === realpath(__FILE__)) {
  ImportMyProject::cliMain();
}
```

This will require two additional classes:

 - `MySourceRepoInitPhase` - clone and checkout the source repository.
   See [`ShipItGitHubInitPhase`](https://github.com/facebook/fbshipit/blob/master/src/phase/ShipItGitHubInitPhase.php)) for an example.
 - `MyGitHubUtils` - subclass of [`ShipItGitHubUtils`](https://github.com/facebook/fbshipit/blob/master/src/ShipItGitHubUtils.php) which implements `getCredentialsForProject(string $organization, string $project)`

## Using With An Empty Destination Repository

Add `ShipItCreateNewRepoPhase` to your phase list (after source init and pull
phases), then run:

```
hhvm my_script.php --create-new-repo
```

This will give you the path to a git repository with a single commit; you can then push it to your destination.

## Using With An Existing Destination Repository

When there is at least one relevant commit in the source repostitory that is not in the destination repository, run:

```
hhvm my_script.php --first-commit=FIRST_UNSYNCED_COMMIT_ID_GOES_HERE
```

## Future Runs

Run your script with no arguments; FBShipIt adds tracking information to the
commits it creates, so will automatically sync any new commits.

## Reducing Common Code With Multiple Projects

We recommend splitting out common filters and phase setup to separate classes,
and for these to be re-used between your projects. For examples from Facebook's
usage, see:

 - [FBLuaLib's sync script](fb-examples/bin/shipit_fblualib.php-example)
 - [FBCommonFilters](fb-examples/lib/shipit/FBCommonFilters.php-example)
 - [FBShipItCLI](fb-examples/lib/shipit/FBShipItCLI.php-example)

## Further Examples

The code for all public Facebook projects that use FBShipit is available in
[fb-examples/](https://github.com/facebook/fbshipit/tree/master/fb-examples).

## License

FBShipIt is MIT-licensed.
