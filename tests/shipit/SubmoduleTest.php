<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/q80cg5rd
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, C, Vec}; // @oss-enable


<<\Oncalls('open_source')>>
final class SubmoduleTest extends ShellTest {
  public function testSubmoduleCommitFile(): void {
    $changeset = ShipItRepoHG::getChangesetFromExportedPatch(
      \file_get_contents(__DIR__.'/hg-diffs/submodule-hhvm-third-party.header'),
      \file_get_contents(__DIR__.'/hg-diffs/submodule-hhvm-third-party.patch'),
    );
    $changeset = \expect($changeset)->toNotBeNull();
    \expect($changeset->isValid())->toBeTrue();

    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'fbcode/hphp/facebook/third-party-rev.txt',
      'third-party',
    );

    \expect($changeset->getDiffs() |> Vec\keys($$) |> C\count($$))->toEqual(1);
    $diff = $changeset->getDiffs()
      |> Vec\filter($$, $diff ==> $diff['path'] === 'third-party')
      |> C\nfirst($$);
    $diff = \expect($diff)->toNotBeNull();
    $change = $diff['body'];
    \expect($change)->toNotBePHPEqual('');
    \expect($change)->toContainSubstring('--- a/third-party');
    \expect($change)->toContainSubstring('+++ b/third-party');

    $old_pos = Str\search($change, '6d9dffd0233c53bb83e4daf5475067073df9cdca');
    $new_pos = Str\search($change, 'ae031dcc9594163f5b0c35e7026563f1c8372595');

    \expect($old_pos)->toBePHPEqual(6);
    \expect($new_pos)->toBePHPEqual(48);
  }

  public function testCommitPatchWithSubmodule(): void {
    // First create a repo that we'll use as our submodule.
    $submodule_dir = new ShipItTempDir('submodule');
    (new ShipItShellCommand($submodule_dir->getPath(), 'git', 'init'))
      ->runSynchronously();
    self::configureGit($submodule_dir);
    PHP\file_put_contents($submodule_dir->getPath().'/somefile', '');
    (
      new ShipItShellCommand(
        $submodule_dir->getPath(),
        'git',
        'add',
        'somefile',
      )
    )
      ->runSynchronously();
    (
      new ShipItShellCommand(
        $submodule_dir->getPath(),
        'git',
        'commit',
        '-m',
        'only commit to submodule repo',
      )
    )
      ->runSynchronously();
    $submodule_id = ShipItRepo::open(
      new ShipItDummyLock(),
      $submodule_dir->getPath(),
      'master',
    )
      ->getHeadChangeset()
      ?->getID();
    invariant($submodule_id !== null, 'impossible');

    // Setup the source repo.
    $source_dir = new ShipItTempDir('source-repo');
    (new ShipItShellCommand($source_dir->getPath(), 'git', 'init'))
      ->runSynchronously();
    self::configureGit($source_dir);
    PHP\file_put_contents(
      $source_dir->getPath().'/rev.txt',
      'Subproject commit '.$submodule_id,
    );
    PHP\file_put_contents(
      $source_dir->getPath().'/.gitmodules',
      '[submodule "test"]
         path=submodule-test
         url='.
      $submodule_dir->getPath(),
    );
    (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'add',
        'rev.txt',
        '.gitmodules',
      )
    )
      ->runSynchronously();
    (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'commit',
        '-m',
        'add new submodule',
      )
    )
      ->runSynchronously();
    $changeset =
      ShipItRepo::open(new ShipItDummyLock(), $source_dir->getPath(), 'master')
        ->getHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );

    // Setup the destination repo, and apply the changeset.
    $dest_dir = new ShipItTempDir('dest-repo');
    (new ShipItShellCommand($dest_dir->getPath(), 'git', 'init'))
      ->runSynchronously();
    self::configureGit($dest_dir);
    (
      new ShipItShellCommand(
        $dest_dir->getPath(),
        'git',
        'commit',
        '--allow-empty',
        '-m',
        'initial commit',
      )
    )
      ->runSynchronously();
    $repo = ShipItRepoGIT::typedOpen(
      ShipItRepoGIT::class,
      new ShipItDummyLock(),
      $dest_dir->getPath(),
      'master',
    );
    $repo->commitPatch($changeset);

    // Now we can finally check stuff!
    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test'))
      ->toBeTrue('Subrepo should be a directory.');
    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test/somefile'))
      ->toBeTrue('Subrepo should be checked out at the correct revision.');

    // Make an update to the submodule, and ensure that that works.
    (
      new ShipItShellCommand(
        $submodule_dir->getPath(),
        'git',
        'mv',
        'somefile',
        'otherfile',
      )
    )
      ->runSynchronously();
    (
      new ShipItShellCommand(
        $submodule_dir->getPath(),
        'git',
        'commit',
        '-m',
        'move file in submodule repo',
      )
    )
      ->runSynchronously();
    $submodule_id = ShipItRepo::open(
      new ShipItDummyLock(),
      $submodule_dir->getPath(),
      'master',
    )
      ->getHeadChangeset()
      ?->getID();
    invariant($submodule_id !== null, 'impossible');
    PHP\file_put_contents(
      $source_dir->getPath().'/rev.txt',
      'Subproject commit '.$submodule_id,
    );
    (new ShipItShellCommand($source_dir->getPath(), 'git', 'add', 'rev.txt'))
      ->runSynchronously();
    (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'commit',
        '-m',
        'update submodule',
      )
    )
      ->runSynchronously();
    $changeset =
      ShipItRepo::open(new ShipItDummyLock(), $source_dir->getPath(), 'master')
        ->getHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );
    $repo->commitPatch($changeset);

    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test'))
      ->toBeTrue('Subrepo should be a directory.');
    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test/somefile'))
      ->toBeFalse('Subrepo should be checked out at the correct revision.');
    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test/otherfile'))
      ->toBeTrue('Subrepo should be checked out at the correct revision.');

    // Now ensure that removing the submodule works correctly.
    (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'rm',
        '.gitmodules',
        'rev.txt',
      )
    )
      ->runSynchronously();
    (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'commit',
        '-m',
        'remove submodule',
      )
    )
      ->runSynchronously();
    $changeset =
      ShipItRepo::open(new ShipItDummyLock(), $source_dir->getPath(), 'master')
        ->getHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );
    $repo->commitPatch($changeset);

    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test'))
      ->toBeFalse('Subrepo should no longer exist.');
  }
}
