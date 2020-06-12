<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/k0eh6x88
 */
namespace Facebook\ShipIt;


<<\Oncalls('open_source')>>
final class SyncTrackingTest extends ShellTest {

  private ?ShipItTempDir $tempDir;

  <<__Override>>
  public async function beforeEachTestAsync(): Awaitable<void> {
    $this->tempDir = new ShipItTempDir('git-sync-test');
    $path = $this->tempDir->getPath();

    // Prepare an empty repo
    (new ShipItShellCommand($path, 'git', 'init'))->runSynchronously();
    (
      new ShipItShellCommand(
        $path,
        'git',
        'config',
        'user.name',
        'FBShipIt Unit Test',
      )
    )->runSynchronously();
    (
      new ShipItShellCommand(
        $path,
        'git',
        'config',
        'user.email',
        'fbshipit@example.com',
      )
    )->runSynchronously();
  }

  <<__Override>>
  public async function afterEachTestAsync(): Awaitable<void> {
    $this->tempDir?->remove();
  }

  private function getBaseConfig(): ShipItBaseConfig {
    return (new ShipItBaseConfig('/var/tmp/fbshipit', '', '', keyset[]))
      ->withCommitMarkerPrefix(true);
  }

  private function getGITRepoWithCommit(string $message): ShipItRepoGIT {
    // Add a tracked commit
    $path = \expect($this->tempDir?->getPath())->toNotBeNull();
    (
      new ShipItShellCommand(
        $path,
        'git',
        'commit',
        '--cleanup=verbatim',
        '--allow-empty',
        '-m',
        $message,
      )
    )->runSynchronously();
    return new ShipItRepoGIT(new ShipItDummyLock(), $path, 'master');
  }

  public function testLastSourceCommitWithGit(): void {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $fake_commit_id = \bin2hex(\random_bytes(16));
    $message = ShipItSync::addTrackingData(
      $this->getBaseConfig(),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    \expect($message)->toContainSubstring('fbshipit');
    $repo = $this->getGITRepoWithCommit($message);
    \expect($repo->findLastSourceCommit(keyset[]))->toEqual($fake_commit_id);
  }

  public function testLastSourceCommitWithMercurial(): void {
    $tempdir = new ShipItTempDir('hg-sync-test');
    $path = $tempdir->getPath();

    // Prepare an empty repo
    (new ShipItShellCommand($path, 'hg', 'init'))->runSynchronously();
    $this->configureHg($tempdir);

    // Add a tracked commit
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $fake_commit_id = \bin2hex(\random_bytes(16));
    $message = ShipItSync::addTrackingData(
      $this->getBaseConfig(),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    (new ShipItShellCommand($path, 'touch', 'testfile'))->runSynchronously();
    (
      new ShipItShellCommand($path, 'hg', 'commit', '-A', '-m', $message)
    )->runSynchronously();

    $repo = new ShipItRepoHG(new ShipItDummyLock(), $path, 'master');
    \expect($repo->findLastSourceCommit(keyset[]))->toEqual($fake_commit_id);
  }

  public function testLastSourceCommitMultipleMarkers(): void {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $fake_commit_id_1 = \bin2hex(\random_bytes(16));
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $fake_commit_id_2 = \bin2hex(\random_bytes(16));
    $message_1 = ShipItSync::addTrackingData(
      $this->getBaseConfig(),
      (new ShipItChangeset())->withID($fake_commit_id_1),
    )->getMessage();
    $message_2 = ShipItSync::addTrackingData(
      $this->getBaseConfig(),
      (new ShipItChangeset())->withID($fake_commit_id_2),
    )->getMessage();
    $repo = $this->getGITRepoWithCommit($message_1."\n\n".$message_2);
    \expect($repo->findLastSourceCommit(keyset[]))->toEqual($fake_commit_id_2);
  }

  public function testLastSourceCommitWithWhitespace(): void {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $fake_commit_id = \bin2hex(\random_bytes(16));
    $message = ShipItSync::addTrackingData(
      $this->getBaseConfig(),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    $repo = $this->getGITRepoWithCommit($message." ");
    \expect($repo->findLastSourceCommit(keyset[]))->toEqual($fake_commit_id);
  }

  public function testLastSourceCommitMissingWhitespace(): void {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $fake_commit_id = \bin2hex(\random_bytes(16));
    $message = "fbshipit-source-id:".$fake_commit_id;
    $repo = $this->getGITRepoWithCommit($message);
    \expect($repo->findLastSourceCommit(keyset[]))->toEqual($fake_commit_id);
  }

  public function testLastSourceCommitWithoutPrefix(): void {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    $fake_commit_id = \bin2hex(\random_bytes(16));
    $message = ShipItSync::addTrackingData(
      $this->getBaseConfig()->withCommitMarkerPrefix(false),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    \expect($message)->toNotContainSubstring('fbshipit');
    $repo = $this->getGITRepoWithCommit($message);
    \expect($repo->findLastSourceCommit(keyset[]))->toEqual($fake_commit_id);
  }

  public function testCoAuthorLines(): void {
    $in = (new ShipItChangeset())
      ->withCoAuthorLines("Co-authored-by: Jon Janzen <jonjanzen@fb.com>");
    $out = ShipItSync::addTrackingData(
      $this->getBaseConfig()->withCommitMarkerPrefix(true),
      $in,
      "TEST",
    );
    \expect($out->getMessage())->toBePHPEqual(
      "fbshipit-source-id: TEST\n\nCo-authored-by: Jon Janzen <jonjanzen@fb.com>",
    );
  }
}
