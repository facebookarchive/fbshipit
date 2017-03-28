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

final class UnicodeTest extends BaseTest {
  const string CONTENT_SHA256 =
    '7b61b2a5bc81a5ef79267f11b5464a006824cb07b47da8773c8c5230c5c803e9';
  const string CONTENT_FILE = __DIR__.'/files/unicode.txt';
  private ?string $ctype;

  public function setUp(): void {
    $ctype = getenv('LC_CTYPE');
    if ($ctype !== false) {
      $this->ctype = $ctype;
    }
    putenv('LC_CTYPE=US-ASCII');
  }

  public function tearDown(): void {
    putenv('LC_CTYPE='.$this->ctype);
  }

  <<__Memoize>>
  private function getExpectedContent(): string {
    $content = file_get_contents(self::CONTENT_FILE);
    $this->assertSame(
      self::CONTENT_SHA256,
      hash('sha256', $content, /* raw output = */ false),
    );
    return $content;
  }

  public function getSourceRepoImplementations(
  ): array<(classname<ShipItSourceRepo>, string, string)> {
    return [
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

  /**
   * @dataProvider getSourceRepoImplementations
   */
  public function testCommitMessage(
    classname<ShipItSourceRepo> $impl,
    string $header_file,
    string $patch_file,
  ): void {
    $changeset = $impl::getChangesetFromExportedPatch(
      file_get_contents($header_file),
      file_get_contents($patch_file),
    );
    assert($changeset !== null);
    $this->assertSame(
      trim($this->getExpectedContent()),
      $changeset->getMessage(),
    );
  }

  public function testCreatedFileWithGit(): void {
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      file_get_contents(__DIR__.'/git-diffs/unicode.header'),
      file_get_contents(__DIR__.'/git-diffs/unicode.patch'),
    );
    assert($changeset !== null);

    $tempdir = new ShipItTempDir('unicode-test-git');
    $this->initGitRepo($tempdir);

    $repo = new ShipItRepoGIT($tempdir->getPath(), 'master');
    $repo->commitPatch($changeset);

    $this->assertSame(
      $this->getExpectedContent(),
      file_get_contents($tempdir->getPath().'/unicode-example.txt'),
    );
  }

  public function testCreatedFileWithMercurial(): void {
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      file_get_contents(__DIR__.'/git-diffs/unicode.header'),
      file_get_contents(__DIR__.'/git-diffs/unicode.patch'),
    );
    assert($changeset !== null);

    $tempdir = new ShipItTempDir('unicode-test-hg');
    $this->initMercurialRepo($tempdir);

    $repo = new ShipItRepoHG($tempdir->getPath(), 'master');
    $repo->commitPatch($changeset);

    $this->assertSame(
      $this->getExpectedContent(),
      file_get_contents($tempdir->getPath().'/unicode-example.txt'),
    );
  }

  public function testCreatingCommitWithGit(): void {
    $tempdir = new ShipItTempDir('unicode-test');
    $path = $tempdir->getPath();
    $this->initGitRepo($tempdir);

    file_put_contents($tempdir->getPath().'/foo', 'bar');

    (new ShipItShellCommand(
      $path,
      'git', 'add', 'foo',
    ))->runSynchronously();
    (new ShipItShellCommand(
      $path,
      'git', 'commit', '-m', "Subject\n\n".$this->getExpectedContent(),
    ))->setEnvironmentVariables(ImmMap {
        'LC_ALL' => 'en_US.UTF-8',
    })->runSynchronously();

    $repo = new ShipItRepoGIT($tempdir->getPath(), 'master');
    $changeset = $repo->getChangesetFromID('HEAD');
    $this->assertSame(
      trim($this->getExpectedContent()),
      $changeset?->getMessage(),
    );
  }

  public function testCreatingCommitWithHG(): void {
    $tempdir = new ShipItTempDir('unicode-test');
    $path = $tempdir->getPath();
    $this->initMercurialRepo($tempdir);

    file_put_contents($tempdir->getPath().'/foo', 'bar');

    (new ShipItShellCommand(
      $path,
      'hg', 'commit', '-Am', "Subject\n\n".$this->getExpectedContent(),
    ))->setEnvironmentVariables(ImmMap {
      'LC_ALL' => 'en_US.UTF-8',
    })->runSynchronously();

    $repo = new ShipItRepoHG($tempdir->getPath(), 'master');
    $changeset = $repo->getChangesetFromID('.');
    $this->assertSame(
      trim($this->getExpectedContent()),
      $changeset?->getMessage(),
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
  }
}
