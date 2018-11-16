<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class EmptyCommitTest extends BaseTest {
  public function testSourceGitDestGit(): void {
    list($source_dir, $rev) = $this->getSourceGitRepoAndRev();
    $source_repo = ShipItRepo::typedOpen(
      ShipItSourceRepo::class,
      $source_dir->getPath(),
      'master',
    );
    $this->assertNotEmpty(
      $source_repo->getNativeHeaderFromID($rev),
      'Expecting a patch header for an empty commit.',
    );
    $this->assertEmpty(
      $source_repo->getNativePatchFromID($rev),
      'Expecting no patch for an empty commit.',
    );
    $changeset = $source_repo->getChangesetFromID($rev);
    invariant($changeset !== null, 'impossible');
    $this->assertCount(
      0,
      $changeset->getDiffs()->toArray(),
      'Expected zero diffs in source changeset.',
    );

    $dest_path = new ShipItTempDir('destination-git-repo');
    $this->initGitRepo($dest_path);
    $dest_repo = ShipItRepo::typedOpen(
      ShipItDestinationRepo::class,
      $dest_path->getPath(),
      'master',
    );
    $new_rev = $dest_repo->commitPatch($changeset);
    invariant($dest_repo instanceof ShipItSourceRepo, 'impossible');
    $new_changeset = $dest_repo->getChangesetFromID($new_rev);
    invariant($new_changeset !== null, 'impossible');
    $this->assertCount(
      0,
      $new_changeset->getDiffs()->toArray(),
      'Expected zero diffs in source changeset.',
    );
  }

  public function testSourceGitDestHg(): void {
    list($source_dir, $rev) = $this->getSourceGitRepoAndRev();
    $source_repo = ShipItRepo::typedOpen(
      ShipItSourceRepo::class,
      $source_dir->getPath(),
      'master',
    );
    $this->assertNotEmpty(
      $source_repo->getNativeHeaderFromID($rev),
      'Expecting a patch header for an empty commit.',
    );
    $this->assertEmpty(
      $source_repo->getNativePatchFromID($rev),
      'Expecting no patch for an empty commit.',
    );
    $changeset = $source_repo->getChangesetFromID($rev);
    invariant($changeset !== null, 'impossible');
    $this->assertCount(
      0,
      $changeset->getDiffs()->toArray(),
      'Expected zero diffs in source changeset.',
    );

    $dest_path = new ShipItTempDir('destination-hg-repo');
    $this->initMercurialRepo($dest_path);
    $dest_repo = ShipItRepo::typedOpen(
      ShipItDestinationRepo::class,
      $dest_path->getPath(),
      'master',
    );
    $new_rev = $dest_repo->commitPatch($changeset);
    invariant($dest_repo instanceof ShipItSourceRepo, 'impossible');
    $new_changeset = $dest_repo->getChangesetFromID($new_rev);
    invariant($new_changeset !== null, 'impossible');
    $this->assertCount(
      0,
      $new_changeset->getDiffs()->toArray(),
      'Expected zero diffs in source changeset.',
    );
  }

  public function testSourceHgDestGit(): void {
    list($source_dir, $rev) = $this->getSourceHgRepoAndRev();
    $source_repo = ShipItRepo::typedOpen(
      ShipItSourceRepo::class,
      $source_dir->getPath(),
      'master',
    );
    $this->assertNotEmpty(
      $source_repo->getNativeHeaderFromID($rev),
      'Expecting a patch header for an empty commit.',
    );
    $this->assertEmpty(
      $source_repo->getNativePatchFromID($rev),
      'Expecting no patch for an empty commit.',
    );
    $changeset = $source_repo->getChangesetFromID($rev);
    invariant($changeset !== null, 'impossible');
    $this->assertCount(
      0,
      $changeset->getDiffs()->toArray(),
      'Expected zero diffs in source changeset.',
    );

    $dest_path = new ShipItTempDir('destination-git-repo');
    $this->initGitRepo($dest_path);
    $dest_repo = ShipItRepo::typedOpen(
      ShipItDestinationRepo::class,
      $dest_path->getPath(),
      'master',
    );
    $new_rev = $dest_repo->commitPatch($changeset);
    invariant($dest_repo instanceof ShipItSourceRepo, 'impossible');
    $new_changeset = $dest_repo->getChangesetFromID($new_rev);
    invariant($new_changeset !== null, 'impossible');
    $this->assertCount(
      0,
      $new_changeset->getDiffs()->toArray(),
      'Expected zero diffs in source changeset.',
    );
  }

  public function testSourceHgDestHg(): void {
    list($source_dir, $rev) = $this->getSourceHgRepoAndRev();
    $source_repo = ShipItRepo::typedOpen(
      ShipItSourceRepo::class,
      $source_dir->getPath(),
      'master',
    );
    $this->assertNotEmpty(
      $source_repo->getNativeHeaderFromID($rev),
      'Expecting a patch header for an empty commit.',
    );
    $this->assertEmpty(
      $source_repo->getNativePatchFromID($rev),
      'Expecting no patch for an empty commit.',
    );
    $changeset = $source_repo->getChangesetFromID($rev);
    invariant($changeset !== null, 'impossible');
    $this->assertCount(
      0,
      $changeset->getDiffs()->toArray(),
      'Expected zero diffs in source changeset.',
    );

    $dest_path = new ShipItTempDir('destination-hg-repo');
    $this->initMercurialRepo($dest_path);
    $dest_repo = ShipItRepo::typedOpen(
      ShipItDestinationRepo::class,
      $dest_path->getPath(),
      'master',
    );
    $new_rev = $dest_repo->commitPatch($changeset);
    invariant($dest_repo instanceof ShipItSourceRepo, 'impossible');
    $new_changeset = $dest_repo->getChangesetFromID($new_rev);
    invariant($new_changeset !== null, 'impossible');
    $this->assertCount(
      0,
      $new_changeset->getDiffs()->toArray(),
      'Expected zero diffs in source changeset.',
    );
  }

  private function getSourceGitRepoAndRev(): (ShipItTempDir, string) {
    $dir = new ShipItTempDir('source-git-repo');
    $this->initGitRepo($dir);
    (new ShipItShellCommand(
      $dir->getPath(),
      'git',
      'commit',
      '--allow-empty',
      '-m', 'This is an empty commit.',
    ))
      ->runSynchronously();
    return tuple(
      $dir,
      \trim((new ShipItShellCommand($dir->getPath(), 'git', 'rev-parse', 'HEAD'))
        ->runSynchronously()
        ->getStdOut()),
    );
  }

  private function getSourceHgRepoAndRev(): (ShipItTempDir, string) {
    $dir = new ShipItTempDir('source-hg-repo');
    $this->initMercurialRepo($dir);
    (new ShipItShellCommand(
      $dir->getPath(),
      'hg',
      '--config', 'ui.allowemptycommit=True',
      'commit',
      '-m', 'This is an empty commit.',
    ))
      ->runSynchronously();
    return tuple(
      $dir,
      \trim((new ShipItShellCommand($dir->getPath(), 'hg', 'id', '--id'))
        ->runSynchronously()
        ->getStdOut()),
    );
  }

  private function initGitRepo(ShipItTempDir $tempdir): void {
    $path = $tempdir->getPath();
    (new ShipItShellCommand($path, 'git', 'init'))->runSynchronously();
    (new ShipItShellCommand(
      $path,
      'git', 'config', 'user.name', 'FBShipIt Unit Test',
    ))->runSynchronously();
    (new ShipItShellCommand(
      $path,
      'git', 'config', 'user.email', 'fbshipit@example.com',
    ))->runSynchronously();
    (new ShipItShellCommand(
      $path,
      'git', 'commit', '--allow-empty', '-m', 'initial commit',
    ))->runSynchronously();
  }

  private function initMercurialRepo(ShipItTempDir $tempdir): void {
    $path = $tempdir->getPath();
    (new ShipItShellCommand($path, 'hg', 'init'))->runSynchronously();
    $this->configureHg($tempdir);
    (new ShipItShellCommand(
      $path,
      'hg',
      '--config', 'ui.allowemptycommit=True',
      'commit',
      '-m', 'initial commit',
    ))
      ->runSynchronously();
  }
}
