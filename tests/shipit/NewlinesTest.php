<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/wrfvw1gs
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str;

<<\Oncalls('open_source')>>
final class NewlinesTest extends ShellTest {
  const UNIX_TXT = "foo\nbar\nbaz\n";
  const WINDOWS_TXT = "foo\r\nbar\r\nbaz\r\n";

  public function testTestData(): void {
    \expect(Str\length(self::WINDOWS_TXT))->toEqual(
      Str\length(self::UNIX_TXT) + 3,
    );
  }

  public function testMercurialSource(): void {
    $temp_dir = new ShipItTempDir('mercurial-newline-test');

    $this->createTestFiles($temp_dir);
    $this->initMercurialRepo($temp_dir);

    self::execSteps(
      $temp_dir->getPath(),
      vec['hg', 'commit', '-Am', 'add files'],
    );

    $repo = new ShipItRepoHG(
      new ShipItDummyLock(),
      $temp_dir->getPath(),
      'master',
    );
    $changeset = $repo->getChangesetFromID('.');
    $changeset = \expect($changeset)->toNotBeNull();

    $this->assertContainsCorrectNewLines($changeset);
    $this->assertCreatesCorrectNewLines($changeset);
  }

  public function testGitSource(): void {
    $temp_dir = new ShipItTempDir('git-newline-test');

    $this->createTestFiles($temp_dir);
    $this->initGitRepo($temp_dir);

    self::execSteps(
      $temp_dir->getPath(),
      vec['git', 'add', '.'],
      vec['git', 'commit', '-m', 'add files'],
    );

    $repo = new ShipItRepoGIT(
      new ShipItDummyLock(),
      $temp_dir->getPath(),
      'master',
    );
    $changeset = $repo->getChangesetFromID('HEAD');
    $changeset = \expect($changeset)->toNotBeNull();

    $this->assertContainsCorrectNewLines($changeset);
    $this->assertCreatesCorrectNewLines($changeset);
  }

  private function createTestFiles(ShipItTempDir $temp_dir): void {
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    \file_put_contents($temp_dir->getPath().'/unix.txt', self::UNIX_TXT);
    /* HH_FIXME[2049] __PHPStdLib */
    /* HH_FIXME[4107] __PHPStdLib */
    \file_put_contents($temp_dir->getPath().'/windows.txt', self::WINDOWS_TXT);
  }

  private function assertContainsCorrectNewLines(
    ShipItChangeset $changeset,
  ): void {
    $map = dict[];
    foreach ($changeset->getDiffs() as $diff) {
      $map[$diff['path']] = $diff['body'];
    }
    \expect($map['unix.txt'])->toContainSubstring("\n");
    \expect($map['windows.txt'])->toContainSubstring("\r\n");
    \expect($map['unix.txt'])->toNotContainSubstring("\r\n");
  }

  private function initGitRepo(ShipItTempDir $temp_dir): void {
    self::execSteps($temp_dir->getPath(), vec['git', 'init']);
    self::configureGit($temp_dir);
  }

  private function initMercurialRepo(ShipItTempDir $temp_dir): void {
    self::execSteps($temp_dir->getPath(), vec['hg', 'init']);
    self::configureHg($temp_dir);
  }

  private function assertCreatesCorrectNewLines(
    ShipItChangeset $changeset,
  ): void {
    $git_dir = new ShipItTempDir('newline-output-test-git');
    $this->initGitRepo($git_dir);
    $hg_dir = new ShipItTempDir('newline-output-test-hg');
    $this->initMercurialRepo($hg_dir);
    $repos = vec[
      new ShipItRepoGIT(
        new ShipItDummyLock(),
        $git_dir->getPath(),
        '--orphan=master',
      ),
      new ShipItRepoHG(new ShipItDummyLock(), $hg_dir->getPath(), 'master'),
    ];

    foreach ($repos as $repo) {
      $repo->commitPatch($changeset);

      \expect(\file_get_contents($repo->getPath().'/unix.txt'))->toEqual(
        self::UNIX_TXT,
        'Unix test file',
      );
      \expect(\file_get_contents($repo->getPath().'/windows.txt'))->toEqual(
        self::WINDOWS_TXT,
        'Windows text file',
      );
    }
  }
}
