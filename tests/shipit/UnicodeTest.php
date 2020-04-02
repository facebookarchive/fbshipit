<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/pt2dnpjf
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str;
use type Facebook\HackTest\DataProvider;

<<\Oncalls('open_source')>>
final class UnicodeTest extends ShellTest {
  const string CONTENT_SHA256 =
    '7b61b2a5bc81a5ef79267f11b5464a006824cb07b47da8773c8c5230c5c803e9';
  const string CONTENT_FILE = __DIR__.'/files/unicode.txt';
  private ?string $ctype;

  <<__Override>>
  public async function setUp(): Awaitable<void> {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    $ctype = \getenv('LC_CTYPE');
    if ($ctype !== false) {
      $this->ctype = $ctype;
    }
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \putenv('LC_CTYPE=US-ASCII');
  }

  <<__Override>>
  public async function tearDownAsync(): Awaitable<void> {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \putenv('LC_CTYPE='.$this->ctype);
  }

  <<__Memoize>>
  private function getExpectedContent(): string {
    $content = \file_get_contents(self::CONTENT_FILE);
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \expect(\hash('sha256', $content, /* raw output = */ false))
      ->toEqual(self::CONTENT_SHA256);
    return $content;
  }

  public static function getSourceRepoImplementations(
  ): vec<(classname<ShipItSourceRepo>, string, string)> {
    return vec[
      tuple(
        ShipItRepoGIT::class,
        __DIR__.'/git-diffs/unicode.header',
        __DIR__.'/git-diffs/unicode.patch',
      ),
      tuple(
        ShipItRepoHG::class,
        __DIR__.'/hg-diffs/unicode.header',
        __DIR__.'/hg-diffs/unicode.patch',
      ),
    ];
  }

  <<DataProvider('getSourceRepoImplementations')>>
  public function testCommitMessage(
    classname<ShipItSourceRepo> $impl,
    string $header_file,
    string $patch_file,
  ): void {
    $changeset = $impl::getChangesetFromExportedPatch(
      \file_get_contents($header_file),
      \file_get_contents($patch_file),
    );
    $changeset = \expect($changeset)->toNotBeNull();
    \expect($changeset->getMessage())->toEqual(
      Str\trim($this->getExpectedContent()),
    );
  }

  public function testCreatedFileWithGit(): void {
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      \file_get_contents(__DIR__.'/git-diffs/unicode.header'),
      \file_get_contents(__DIR__.'/git-diffs/unicode.patch'),
    );
    $changeset = \expect($changeset)->toNotBeNull();

    $tempdir = new ShipItTempDir('unicode-test-git');
    $this->initGitRepo($tempdir);

    $repo = new ShipItRepoGIT($tempdir->getPath(), 'master');
    $repo->commitPatch($changeset);

    \expect(\file_get_contents($tempdir->getPath().'/unicode-example.txt'))
      ->toEqual($this->getExpectedContent());
  }

  public function testCreatedFileWithMercurial(): void {
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      \file_get_contents(__DIR__.'/git-diffs/unicode.header'),
      \file_get_contents(__DIR__.'/git-diffs/unicode.patch'),
    );
    $changeset = \expect($changeset)->toNotBeNull();

    $tempdir = new ShipItTempDir('unicode-test-hg');
    $this->initMercurialRepo($tempdir);

    $repo = new ShipItRepoHG($tempdir->getPath(), 'master');
    $repo->commitPatch($changeset);

    \expect(\file_get_contents($tempdir->getPath().'/unicode-example.txt'))
      ->toEqual($this->getExpectedContent());
  }

  public function testCreatingCommitWithGit(): void {
    $tempdir = new ShipItTempDir('unicode-test');
    $path = $tempdir->getPath();
    $this->initGitRepo($tempdir);

    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \file_put_contents($tempdir->getPath().'/foo', 'bar');

    (new ShipItShellCommand($path, 'git', 'add', 'foo'))->runSynchronously();
    (
      new ShipItShellCommand(
        $path,
        'git',
        'commit',
        '-m',
        "Subject\n\n".$this->getExpectedContent(),
      )
    )->setEnvironmentVariables(dict[
      'LC_ALL' => 'en_US.UTF-8',
    ])
      ->runSynchronously();

    $repo = new ShipItRepoGIT($tempdir->getPath(), 'master');
    $changeset = $repo->getChangesetFromID('HEAD');
    \expect($changeset->getMessage())
      ->toEqual(Str\trim($this->getExpectedContent()));
  }

  public function testCreatingCommitWithHG(): void {
    $tempdir = new ShipItTempDir('unicode-test');
    $path = $tempdir->getPath();
    $this->initMercurialRepo($tempdir);

    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \file_put_contents($tempdir->getPath().'/foo', 'bar');

    (
      new ShipItShellCommand(
        $path,
        'hg',
        'commit',
        '-Am',
        "Subject\n\n".$this->getExpectedContent(),
      )
    )->setEnvironmentVariables(dict[
      'LC_ALL' => 'en_US.UTF-8',
    ])
      ->runSynchronously();

    $repo = new ShipItRepoHG($tempdir->getPath(), 'master');
    $changeset = $repo->getChangesetFromID('.');
    \expect($changeset?->getMessage())->toEqual(
      Str\trim($this->getExpectedContent()),
    );
  }

  private function initGitRepo(ShipItTempDir $tempdir): void {
    $path = $tempdir->getPath();
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
    (
      new ShipItShellCommand(
        $path,
        'git',
        'commit',
        '--allow-empty',
        '-m',
        'initial commit',
      )
    )->runSynchronously();
  }

  private function initMercurialRepo(ShipItTempDir $tempdir): void {
    $path = $tempdir->getPath();
    (new ShipItShellCommand($path, 'hg', 'init'))->runSynchronously();
    $this->configureHg($tempdir);
  }
}
