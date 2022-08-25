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
 * https://fburl.com/u0w82645
 */
namespace Facebook\ImportIt;

use namespace HH\Lib\{Str, C}; // @oss-enable


use type Facebook\ShipIt\{
  ShipItRepo,
  ShipItRepoGIT,
  ShipItShellCommand,
  ShipItSubmoduleFilter,
  ShipItTempDir,
  ShipItDummyLock,
};

<<\Oncalls('open_source')>>
final class SubmoduleTest extends \Facebook\ShipIt\ShellTest {
  public function testSubmoduleCommitFile(): void {
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      \file_get_contents(
        __DIR__.'/git-diffs/submodule-hhvm-third-party.header',
      ),
      \file_get_contents(__DIR__.'/git-diffs/submodule-hhvm-third-party.patch'),
    );
    $changeset = \expect($changeset)->toNotBeNull();
    \expect($changeset->isValid())->toBeTrue();

    $changeset = ImportItSubmoduleFilter::moveSubmoduleCommitToTextFile(
      $changeset,
      'third-party',
      'fbcode/hphp/facebook/third-party-rev.txt',
    );

    \expect(C\count($changeset->getDiffs()))->toEqual(1);
    $change = C\nfirst($changeset->getDiffs());
    $change = \expect($change)->toNotBeNull();
    $change = $change['body'];
    \expect($change)->toNotBePHPEqual('');
    \expect($change)
      ->toContainSubstring('--- a/fbcode/hphp/facebook/third-party-rev.txt');
    \expect($change)
      ->toContainSubstring('+++ b/fbcode/hphp/facebook/third-party-rev.txt');

    $old_pos = Str\search($change, '6d9dffd0233c53bb83e4daf5475067073df9cdca');
    $new_pos = Str\search($change, 'ae031dcc9594163f5b0c35e7026563f1c8372595');

    \expect($old_pos)->toEqual(125);
    \expect($new_pos)->toEqual(185);
  }

  public async function testImportCommitPatchWithSubmodule(): Awaitable<void> {
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
    $submodule_first_id = (
      await $repo
        ->genHeadChangeset()
    )
      ?->getID();
    invariant($submodule_first_id !== null, 'impossible');
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
    $repo = await ShipItRepo::genOpen(
      new ShipItDummyLock(),
      $submodule_dir->getPath(),
      'master',
    );
    $submodule_second_id = (await $repo->genHeadChangeset())
      ?->getID();
    invariant($submodule_second_id !== null, 'impossible');

    // Setup the destination repo (what we import to).
    $dest_dir = new ShipItTempDir('dest-repo');
    await (new ShipItShellCommand($dest_dir->getPath(), 'git', 'init'))
      ->genRun();
    await self::genConfigureGit($dest_dir);
    PHP\file_put_contents(
      $dest_dir->getPath().'/rev.txt',
      'Subproject commit '.$submodule_first_id."\n",
    );
    PHP\file_put_contents(
      $dest_dir->getPath().'/.gitmodules',
      '[submodule "test"]
         path=submodule-test
         url='.
      $submodule_dir->getPath(),
    );
    await (
      new ShipItShellCommand(
        $dest_dir->getPath(),
        'git',
        'add',
        'rev.txt',
        '.gitmodules',
      )
    )
      ->genRun();
    await (
      new ShipItShellCommand(
        $dest_dir->getPath(),
        'git',
        'commit',
        '-m',
        'add new submodule',
      )
    )
      ->genRun();

    // Setup the source repo (what we import from).
    $source_dir = new ShipItTempDir('source-dir');
    await (new ShipItShellCommand($source_dir->getPath(), 'git', 'init'))
      ->genRun();
    await self::genConfigureGit($source_dir);
    $source_dir =
      await \Facebook\ShipIt\ShipItCreateNewRepoPhase::genCreateNewGitRepo(
        (
          new \Facebook\ShipIt\ShipItManifest(
            '',
            $dest_dir->getPath(),
            //$source_dir->getPath(),
            (new ShipItTempDir('source-dir'))->getPath(),
            keyset[],
          )
        )
          ->withDestinationBranch('master')
          ->withSourceBranch('master'),
        async $c ==> ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
          $c,
          'rev.txt',
          'submodule-test',
        ),
        shape(
          'name' => 'Test User',
          'email' => 'someone@example.com',
        ),
      );
    await (
      new ShipItShellCommand($source_dir->getPath(), 'git', 'submodule', 'init')
    )
      ->genRun();
    await (
      new ShipItShellCommand(
        $source_dir->getPath().'/submodule-test',
        'git',
        'checkout',
        $submodule_second_id,
      )
    )
      ->genRun();
    await (
      new ShipItShellCommand(
        $source_dir->getPath(),
        'git',
        'commit',
        '--all',
        '-m',
        'update submodule',
      )
    )
      ->genRun();
    $repo = await ShipItRepo::genOpen(
      new ShipItDummyLock(),
      $source_dir->getPath(),
      'master',
    );
    $changeset = await $repo->genHeadChangeset();
    invariant($changeset !== null, 'impossible');
    $repo = await ShipItRepoGIT::genTypedOpen<ShipItRepoGIT>(
      new ShipItDummyLock(),
      $dest_dir->getPath(),
      'master',
    );
    await $repo->genCommitPatch(
      ImportItSubmoduleFilter::moveSubmoduleCommitToTextFile(
        $changeset,
        'submodule-test',
        'rev.txt',
      ),
    );

    // Now we can finally check stuff!
    \expect(\file_get_contents($dest_dir->getPath().'/rev.txt'))->toEqual(
      'Subproject commit '.$submodule_second_id."\n",
      'File should be updated with new hash.',
    );
  }
}
