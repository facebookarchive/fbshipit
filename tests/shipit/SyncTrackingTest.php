<?hh // strict
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

final class SyncTrackingTest extends BaseTest {
  public function testLastSourceCommitWithGit(): void {
    $tempdir = new ShipItTempDir('git-sync-test');
    $path = $tempdir->getPath();

    // Prepare an empty repo
    (new ShipItShellCommand($path, 'git', 'init'))->runSynchronously();
    (new ShipItShellCommand(
      $path,
      'git', 'config', 'user.name', 'FBShipIt Unit Test',
    ))->runSynchronously();
    (new ShipItShellCommand(
      $path,
      'git', 'config', 'user.email', 'fbshipit@example.com',
    ))->runSynchronously();

    // Add a tracked commit
    $fake_commit_id = bin2hex(random_bytes(16));
    $message = ShipItSync::addTrackingData(
      (new ShipItChangeset())->withID($fake_commit_id)
    )->getMessage();
    (new ShipItShellCommand(
      $path,
      'git', 'commit', '--allow-empty', '-m', $message,
    ))->runSynchronously();

    $repo = new ShipItRepoGIT($path, 'master');
    $this->assertSame($fake_commit_id, $repo->findLastSourceCommit(ImmSet { }));
  }

  public function testLastSourceCommitWithMercurial(): void {
    $tempdir = new ShipItTempDir('hg-sync-test');
    $path = $tempdir->getPath();

    // Prepare an empty repo
    (new ShipItShellCommand($path, 'hg', 'init'))->runSynchronously();
    $this->configureHg($tempdir);

    // Add a tracked commit
    $fake_commit_id = bin2hex(random_bytes(16));
    $message = ShipItSync::addTrackingData(
      (new ShipItChangeset())->withID($fake_commit_id)
    )->getMessage();
    (new ShipItShellCommand($path, 'touch', 'testfile',))->runSynchronously();
    (new ShipItShellCommand(
      $path,
      'hg', 'commit', '-A', '-m', $message,
    ))->runSynchronously();

    $repo = new ShipItRepoHG($path, 'master');
    $this->assertSame($fake_commit_id, $repo->findLastSourceCommit(ImmSet { }));
  }
}
