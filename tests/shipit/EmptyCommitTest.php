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
 * https://fburl.com/gtoz3ypt
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str; // @oss-enable


<<\Oncalls('open_source')>>
final class EmptyCommitTest extends ShellTest {
  public async function testSourceGitDestGit(): Awaitable<void> {
    list($source_dir, $rev) = await $this->genSourceGitRepoAndRev();
    $source_repo = await ShipItRepo::genTypedOpen<ShipItSourceRepo>(
      new ShipItDummyLock(),
      $source_dir->getPath(),
      'master',
    );
    \expect(await $source_repo->genNativeHeaderFromID($rev))->toNotBePHPEqual(
      '',
      'Expecting a patch header for an empty commit.',
    );
    \expect(await $source_repo->genNativePatchFromID($rev))->toEqual(
      '',
      'Expecting no patch for an empty commit.',
    );
    $changeset = await $source_repo->genChangesetFromID($rev);
    invariant($changeset !== null, 'impossible');
    \expect(vec($changeset->getDiffs()))
      ->toBeEmpty('Expected zero diffs in source changeset.');

    $dest_path = new ShipItTempDir('destination-git-repo');
    await $this->genInitGitRepo($dest_path);
    $dest_repo = await ShipItRepo::genTypedOpen<ShipItDestinationRepo>(
      new ShipItDummyLock(),
      $dest_path->getPath(),
      'master',
    );
    $new_rev = await $dest_repo->genCommitPatch($changeset);
    invariant($dest_repo is ShipItSourceRepo, 'impossible');
    $new_changeset = await $dest_repo->genChangesetFromID($new_rev);
    invariant($new_changeset !== null, 'impossible');
    \expect(vec($new_changeset->getDiffs()))
      ->toBeEmpty('Expected zero diffs in source changeset.');
  }

  public async function testSourceGitDestHg(): Awaitable<void> {
    list($source_dir, $rev) = await $this->genSourceGitRepoAndRev();
    $source_repo = await ShipItRepo::genTypedOpen<ShipItSourceRepo>(
      new ShipItDummyLock(),
      $source_dir->getPath(),
      'master',
    );
    \expect(await $source_repo->genNativeHeaderFromID($rev))->toNotBePHPEqual(
      '',
      'Expecting a patch header for an empty commit.',
    );
    \expect(await $source_repo->genNativePatchFromID($rev))->toEqual(
      '',
      'Expecting no patch for an empty commit.',
    );
    $changeset = await $source_repo->genChangesetFromID($rev);
    invariant($changeset !== null, 'impossible');
    \expect(vec($changeset->getDiffs()))
      ->toBeEmpty('Expected zero diffs in source changeset.');

    $dest_path = new ShipItTempDir('destination-hg-repo');
    await $this->genInitMercurialRepo($dest_path);
    $dest_repo = await ShipItRepo::genTypedOpen<ShipItDestinationRepo>(
      new ShipItDummyLock(),
      $dest_path->getPath(),
      'master',
    );
    $new_rev = await $dest_repo->genCommitPatch($changeset);
    invariant($dest_repo is ShipItSourceRepo, 'impossible');
    $new_changeset = await $dest_repo->genChangesetFromID($new_rev);
    invariant($new_changeset !== null, 'impossible');
    \expect(vec($new_changeset->getDiffs()))
      ->toBeEmpty('Expected zero diffs in source changeset.');
  }

  public async function testSourceHgDestGit(): Awaitable<void> {
    list($source_dir, $rev) = await $this->genSourceHgRepoAndRev();
    $source_repo = await ShipItRepo::genTypedOpen<ShipItSourceRepo>(
      new ShipItDummyLock(),
      $source_dir->getPath(),
      'master',
    );
    \expect(await $source_repo->genNativeHeaderFromID($rev))->toNotBePHPEqual(
      '',
      'Expecting a patch header for an empty commit.',
    );
    \expect(await $source_repo->genNativePatchFromID($rev))->toEqual(
      '',
      'Expecting no patch for an empty commit.',
    );
    $changeset = await $source_repo->genChangesetFromID($rev);
    invariant($changeset !== null, 'impossible');
    \expect(vec($changeset->getDiffs()))
      ->toBeEmpty('Expected zero diffs in source changeset.');

    $dest_path = new ShipItTempDir('destination-git-repo');
    await $this->genInitGitRepo($dest_path);
    $dest_repo = await ShipItRepo::genTypedOpen<ShipItDestinationRepo>(
      new ShipItDummyLock(),
      $dest_path->getPath(),
      'master',
    );
    $new_rev = await $dest_repo->genCommitPatch($changeset);
    invariant($dest_repo is ShipItSourceRepo, 'impossible');
    $new_changeset = await $dest_repo->genChangesetFromID($new_rev);
    invariant($new_changeset !== null, 'impossible');
    \expect(vec($new_changeset->getDiffs()))
      ->toBeEmpty('Expected zero diffs in source changeset.');
  }

  public async function testSourceHgDestHg(): Awaitable<void> {
    list($source_dir, $rev) = await $this->genSourceHgRepoAndRev();
    $source_repo = await ShipItRepo::genTypedOpen<ShipItSourceRepo>(
      new ShipItDummyLock(),
      $source_dir->getPath(),
      'master',
    );
    \expect(await $source_repo->genNativeHeaderFromID($rev))->toNotBePHPEqual(
      '',
      'Expecting a patch header for an empty commit.',
    );
    \expect(await $source_repo->genNativePatchFromID($rev))->toEqual(
      '',
      'Expecting no patch for an empty commit.',
    );
    $changeset = await $source_repo->genChangesetFromID($rev);
    invariant($changeset !== null, 'impossible');
    \expect(vec($changeset->getDiffs()))
      ->toBeEmpty('Expected zero diffs in source changeset.');

    $dest_path = new ShipItTempDir('destination-hg-repo');
    await $this->genInitMercurialRepo($dest_path);
    $dest_repo = await ShipItRepo::genTypedOpen<ShipItDestinationRepo>(
      new ShipItDummyLock(),
      $dest_path->getPath(),
      'master',
    );
    $new_rev = await $dest_repo->genCommitPatch($changeset);
    invariant($dest_repo is ShipItSourceRepo, 'impossible');
    $new_changeset = await $dest_repo->genChangesetFromID($new_rev);
    invariant($new_changeset !== null, 'impossible');
    \expect(vec($new_changeset->getDiffs()))
      ->toBeEmpty('Expected zero diffs in source changeset.');
  }

  private async function genSourceGitRepoAndRev(
  ): Awaitable<(ShipItTempDir, string)> {
    $dir = new ShipItTempDir('source-git-repo');
    await $this->genInitGitRepo($dir);
    await (
      new ShipItShellCommand(
        $dir->getPath(),
        'git',
        'commit',
        '--allow-empty',
        '-m',
        'This is an empty commit.',
      )
    )
      ->genRun();
    return tuple(
      $dir,
      Str\trim(
        (
          await (
            new ShipItShellCommand($dir->getPath(), 'git', 'rev-parse', 'HEAD')
          )
            ->genRun()
        )
          ->getStdOut(),
      ),
    );
  }

  private async function genSourceHgRepoAndRev(
  ): Awaitable<(ShipItTempDir, string)> {
    $dir = new ShipItTempDir('source-hg-repo');
    await $this->genInitMercurialRepo($dir);
    await (
      new ShipItShellCommand(
        $dir->getPath(),
        'hg',
        '--config',
        'ui.allowemptycommit=True',
        'commit',
        '-m',
        'This is an empty commit.',
      )
    )
      ->genRun();
    return tuple(
      $dir,
      Str\trim(
        (
          await (new ShipItShellCommand($dir->getPath(), 'hg', 'id', '--id'))
            ->genRun()
        )
          ->getStdOut(),
      ),
    );
  }

  private async function genInitGitRepo(
    ShipItTempDir $tempdir,
  ): Awaitable<void> {
    $path = $tempdir->getPath();
    await (new ShipItShellCommand($path, 'git', 'init'))->genRun();
    await (
      new ShipItShellCommand(
        $path,
        'git',
        'config',
        'user.name',
        'FBShipIt Unit Test',
      )
    )->genRun();
    await (
      new ShipItShellCommand(
        $path,
        'git',
        'config',
        'user.email',
        'fbshipit@example.com',
      )
    )->genRun();
    await (
      new ShipItShellCommand(
        $path,
        'git',
        'commit',
        '--allow-empty',
        '-m',
        'initial commit',
      )
    )->genRun();
  }

  private async function genInitMercurialRepo(
    ShipItTempDir $tempdir,
  ): Awaitable<void> {
    $path = $tempdir->getPath();
    await (new ShipItShellCommand($path, 'hg', 'init'))->genRun();
    self::configureHg($tempdir);
    await (
      new ShipItShellCommand(
        $path,
        'hg',
        '--config',
        'ui.allowemptycommit=True',
        'commit',
        '-m',
        'initial commit',
      )
    )
      ->genRun();
  }
}
