<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 * @format
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
  }

  <<__Override>>
  public async function afterEachTestAsync(): Awaitable<void> {
    await $this->tempDir?->genRemove();
  }

  private function getManifest(): ShipItManifest {
    return (new ShipItManifest('/var/tmp/fbshipit', '', '', keyset[]));
  }

  private async function genGITRepoWithCommit(
    string $message,
  ): Awaitable<ShipItRepoGIT> {
    // Add a tracked commit
    $path = \expect($this->tempDir?->getPath())->toNotBeNull();
    await (
      new ShipItShellCommand(
        $path,
        'git',
        'commit',
        '--cleanup=verbatim',
        '--allow-empty',
        '-m',
        $message,
      )
    )->genRun();
    $repo = new ShipItRepoGIT(new ShipItDummyLock(), $path);
    await $repo->genSetBranch('master');
    return $repo;
  }

  public async function testLastSourceCommitWithGit(): Awaitable<void> {
    $fake_commit_id = ShipItTempDir::randomHex(16);
    $message = ShipItSync::addTrackingData(
      $this->getManifest(),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    \expect($message)->toContainSubstring('fbshipit');
    $repo = await $this->genGITRepoWithCommit($message);
    \expect(
      await $repo->genFindLastSourceCommit(keyset[], 'fbshipit-source-id'),
    )->toEqual($fake_commit_id);
  }

  public async function testLastSourceCommitWithMercurial(): Awaitable<void> {
    $tempdir = new ShipItTempDir('hg-sync-test');
    $path = $tempdir->getPath();

    // Prepare an empty repo
    await (new ShipItShellCommand($path, 'hg', 'init'))->genRun();
    self::configureHg($tempdir);

    // Add a tracked commit
    $fake_commit_id = ShipItTempDir::randomHex(16);
    $message = ShipItSync::addTrackingData(
      $this->getManifest(),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    await (new ShipItShellCommand($path, 'touch', 'testfile'))->genRun();
    await (
      new ShipItShellCommand($path, 'hg', 'commit', '-A', '-m', $message)
    )->genRun();

    $repo = new ShipItRepoHG(new ShipItDummyLock(), $path);
    await $repo->genSetBranch('master');
    \expect(
      await $repo->genFindLastSourceCommit(keyset[], 'fbshipit-source-id'),
    )->toEqual($fake_commit_id);
  }

  public async function testLastSourceCommitMultipleMarkers(): Awaitable<void> {
    $fake_commit_id_1 = ShipItTempDir::randomHex(16);
    $fake_commit_id_2 = ShipItTempDir::randomHex(16);
    $message_1 = ShipItSync::addTrackingData(
      $this->getManifest(),
      (new ShipItChangeset())->withID($fake_commit_id_1),
    )->getMessage();
    $message_2 = ShipItSync::addTrackingData(
      $this->getManifest(),
      (new ShipItChangeset())->withID($fake_commit_id_2),
    )->getMessage();
    $repo = await $this->genGITRepoWithCommit($message_1."\n\n".$message_2);
    \expect(
      await $repo->genFindLastSourceCommit(keyset[], 'fbshipit-source-id'),
    )->toEqual($fake_commit_id_2);
  }

  public async function testLastSourceCommitWithWhitespace(): Awaitable<void> {
    $fake_commit_id = ShipItTempDir::randomHex(16);
    $message = ShipItSync::addTrackingData(
      $this->getManifest(),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    $repo = await $this->genGITRepoWithCommit($message." ");
    \expect(
      await $repo->genFindLastSourceCommit(keyset[], 'fbshipit-source-id'),
    )->toEqual($fake_commit_id);
  }

  public async function testLastSourceCommitMissingWhitespace(
  ): Awaitable<void> {
    $fake_commit_id = ShipItTempDir::randomHex(16);
    $message = "fbshipit-source-id:".$fake_commit_id;
    $repo = await $this->genGITRepoWithCommit($message);
    \expect(
      await $repo->genFindLastSourceCommit(keyset[], 'fbshipit-source-id'),
    )->toEqual($fake_commit_id);
  }

  public async function testLastSourceCommitWithoutPrefix(): Awaitable<void> {
    $fake_commit_id = ShipItTempDir::randomHex(16);
    $message = ShipItSync::addTrackingData(
      $this->getManifest()->withCommitMarker('shipit-source-id'),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    \expect($message)->toNotContainSubstring('fbshipit');
    $repo = await $this->genGITRepoWithCommit($message);
    \expect(await $repo->genFindLastSourceCommit(keyset[], 'shipit-source-id'))
      ->toEqual($fake_commit_id);
    \expect(
      await $repo->genFindLastSourceCommit(keyset[], 'fbshipit-source-id'),
    )->toEqual(null);
  }

  public async function testLastSourceCommitWithCustomCommitMarker(
  ): Awaitable<void> {
    $fake_commit_id = ShipItTempDir::randomHex(16);
    $message = ShipItSync::addTrackingData(
      $this->getManifest()->withCommitMarker('jonjanzens-cool-commit-marker'),
      (new ShipItChangeset())->withID($fake_commit_id),
    )->getMessage();
    \expect($message)->toNotContainSubstring('fbshipit');
    $repo = await $this->genGITRepoWithCommit($message);
    \expect(await $repo->genFindLastSourceCommit(
      keyset[],
      'jonjanzens-cool-commit-marker',
    ))
      ->toEqual($fake_commit_id);
    \expect(
      await $repo->genFindLastSourceCommit(keyset[], 'fbshipit-source-id'),
    )->toEqual(null);
  }

  public function testCoAuthorLines(): void {
    $in = (new ShipItChangeset())
      ->withCoAuthorLines("Co-authored-by: Jon Janzen <jonjanzen@fb.com>");
    $out = ShipItSync::addTrackingData($this->getManifest(), $in, "TEST");
    \expect($out->getMessage())->toBePHPEqual(
      "fbshipit-source-id: TEST\n\nCo-authored-by: Jon Janzen <jonjanzen@fb.com>",
    );
  }
}
