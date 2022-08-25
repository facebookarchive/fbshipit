<?hh
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 * @format
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

    \expect($old_pos)->toEqual(6);
    \expect($new_pos)->toEqual(48);
  }

  public async function testCommitPatchWithSubmodule(): Awaitable<void> {
    // First create a repo that we'll use as our submodule.
    $submodule_dir = new ShipItTempDir('submodule');
    await (new ShipItShellCommand($submodule_dir->getPath(), 'git', 'init'))
      ->genRun();
    await self::genConfigureGit($submodule_dir);
    PHP\file_put_contents($submodule_dir->getPath().'/somefile', '');
    await (
      new ShipItShellCommand(
        $submodule_dir->getPath(),
        'git',
        'add',
        'somefile',
      )
    )
      ->genRun();
    await (
      new ShipItShellCommand(
        $submodule_dir->getPath(),
        'git',
        'commit',
        '-m',
        'only commit to submodule repo',
      )
    )
      ->genRun();
    $repo = await ShipItRepo::genOpen(
      new ShipItDummyLock(),
      $submodule_dir->getPath(),
      'master',
    );
    $submodule_id = (
      await $repo
        ->genHeadChangeset()
    )
      ?->getID();
    invariant($submodule_id !== null, 'impossible');

    // Setup the source repo.
    $source_dir = new ShipItTempDir('source-repo');
    await (new ShipItShellCommand($source_dir->getPath(), 'git', 'init'))
      ->genRun();
    await self::genConfigureGit($source_dir);
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
    await (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'add',
        'rev.txt',
        '.gitmodules',
      )
    )
      ->genRun();
    await (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'commit',
        '-m',
        'add new submodule',
      )
    )
      ->genRun();
    $temp_repo = await ShipItRepo::genOpen(
      new ShipItDummyLock(),
      $source_dir->getPath(),
      'master',
    );
    $changeset = await $temp_repo
      ->genHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );

    // Setup the destination repo, and apply the changeset.
    $dest_dir = new ShipItTempDir('dest-repo');
    await (new ShipItShellCommand($dest_dir->getPath(), 'git', 'init'))
      ->genRun();
    await self::genConfigureGit($dest_dir);
    await (
      new ShipItShellCommand(
        $dest_dir->getPath(),
        'git',
        'commit',
        '--allow-empty',
        '-m',
        'initial commit',
      )
    )
      ->genRun();
    $repo = await ShipItRepoGIT::genTypedOpen<ShipItRepoGIT>(
      new ShipItDummyLock(),
      $dest_dir->getPath(),
      'master',
    );
    await $repo->genCommitPatch($changeset);

    // Now we can finally check stuff!
    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test'))
      ->toBeTrue('Subrepo should be a directory.');
    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test/somefile'))
      ->toBeTrue('Subrepo should be checked out at the correct revision.');

    // Make an update to the submodule, and ensure that that works.
    await (
      new ShipItShellCommand(
        $submodule_dir->getPath(),
        'git',
        'mv',
        'somefile',
        'otherfile',
      )
    )
      ->genRun();
    await (
      new ShipItShellCommand(
        $submodule_dir->getPath(),
        'git',
        'commit',
        '-m',
        'move file in submodule repo',
      )
    )
      ->genRun();
    $temp_repo = await ShipItRepo::genOpen(
      new ShipItDummyLock(),
      $submodule_dir->getPath(),
      'master',
    );
    $submodule_id = (await $temp_repo->genHeadChangeset())
      ?->getID();
    invariant($submodule_id !== null, 'impossible');
    PHP\file_put_contents(
      $source_dir->getPath().'/rev.txt',
      'Subproject commit '.$submodule_id,
    );
    await (
      new ShipItShellCommand($source_dir->getPath(), 'git', 'add', 'rev.txt')
    )
      ->genRun();
    await (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'commit',
        '-m',
        'update submodule',
      )
    )
      ->genRun();
    $temp_repo = await ShipItRepo::genOpen(
      new ShipItDummyLock(),
      $source_dir->getPath(),
      'master',
    );
    $changeset = await $temp_repo->genHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );
    await $repo->genCommitPatch($changeset);

    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test'))
      ->toBeTrue('Subrepo should be a directory.');
    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test/somefile'))
      ->toBeFalse('Subrepo should be checked out at the correct revision.');
    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test/otherfile'))
      ->toBeTrue('Subrepo should be checked out at the correct revision.');

    // Now ensure that removing the submodule works correctly.
    await (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'rm',
        '.gitmodules',
        'rev.txt',
      )
    )
      ->genRun();
    await (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'commit',
        '-m',
        'remove submodule',
      )
    )
      ->genRun();
    $temp_repo = await ShipItRepo::genOpen(
      new ShipItDummyLock(),
      $source_dir->getPath(),
      'master',
    );
    $changeset = await $temp_repo->genHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'rev.txt',
      'submodule-test',
    );
    await $repo->genCommitPatch($changeset);

    \expect(PHP\file_exists($dest_dir->getPath().'/submodule-test'))
      ->toBeFalse('Subrepo should no longer exist.');
  }
}
