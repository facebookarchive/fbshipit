<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class UnusualContentTest extends BaseTest {
  public function examplesForRemovingFile(
  ): array<(string, string, string, string, string)> {
    return [
      tuple(
        __DIR__.'/git-diffs/remove-file-with-hyphen-line.header',
        __DIR__.'/git-diffs/remove-file-with-hyphen-line.patch',
        'pre-hyphen',
        "\n--\n",
        'post-hyphen',
      ),
      tuple(
        __DIR__.'/git-diffs/remove-file-with-hyphen-space-line.header',
        __DIR__.'/git-diffs/remove-file-with-hyphen-space-line.patch',
        'pre hyphen space',
        "\n-- \n",
        'after hyphen space',
      ),
    ];
  }

  /** If a file contains '-', removing it creates a patch containing
   * just '--' as a line - this kind-of-looks-like (and has been incorrectly
   * interpreted as) a section separator instead of content, forming
   * an invalid patch.
   *
   * @dataProvider examplesForRemovingFile
   */
  public function testRemovingFile(
    string $header_file,
    string $patch_file,
    string $pre,
    string $special,
    string $post,
  ): void {
    $header = \file_get_contents($header_file);
    $patch = \file_get_contents($patch_file);
    $lines = \explode("\n", \trim($patch));
    $git_version = \trim($lines[\count($lines) - 1]);

    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      $header,
      $patch,
    );
    assert($changeset !== null);
    $this->assertSame(
      1,
      $changeset->getDiffs()->count(),
    );
    $hunk = $changeset->getDiffs()->at(0)['body'];
    $this->assertContains(
      $pre,
      $hunk,
    );
    $this->assertContains(
      $special,
      $hunk,
    );
    $this->assertContains(
      $post,
      $hunk,
    );
    $this->assertNotContains(
      $git_version,
      $hunk,
    );
  }

  public function testNoNewlineAtEOF(): void {
    $header = \file_get_contents(
      __DIR__.'/git-diffs/no-newline-at-eof.header',
    );
    $patch = \file_get_contents(__DIR__.'/git-diffs/no-newline-at-eof.patch');
    $lines = \explode("\n", \trim($patch));
    $git_version = \trim($lines[\count($lines) - 1]);

    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      $header,
      $patch,
    );
    assert($changeset !== null);

    $hunk = $changeset->getDiffs()->at(0)['body'];
    $this->assertContains(
      'foo',
      $hunk,
    );
    $this->assertContains(
      "\n\\ No newline at",
      $hunk,
    );
    $this->assertNotContains(
      $git_version,
      $hunk,
    );
  }

  public function testAddingNewlineAtEOF(): void {
    $header = \file_get_contents(
      __DIR__.'/git-diffs/add-newline-at-eof.header',
    );
    $patch = \file_get_contents(__DIR__.'/git-diffs/add-newline-at-eof.patch');
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      $header,
      $patch,
    );
    assert($changeset !== null);

    $hunk = $changeset->getDiffs()->at(0)['body'];
    $this->assertContains(' foo', $hunk);
    $this->assertContains('-bar', $hunk);
    $this->assertContains('+bar', $hunk);
    $this->assertContains('\ No newline at end of file', $hunk);
  }

  public function testStripFileListFromShortCommit(): void {
    $header = \file_get_contents(
      __DIR__.'/git-diffs/no-summary-in-message.header',
    );
    $patch = \file_get_contents(
      __DIR__.'/git-diffs/no-summary-in-message.patch',
    );
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      $header,
      $patch,
    );
    assert($changeset !== null);

    $message = $changeset->getMessage();
    $this->assertEquals("", $message);
  }

  public function testStripFileListFromLongCommit(): void {
    $header = \file_get_contents(
      __DIR__.'/git-diffs/has-summary-in-message.header',
    );
    $patch = \file_get_contents(
      __DIR__.'/git-diffs/has-summary-in-message.patch',
    );
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      $header,
      $patch,
    );
    assert($changeset !== null);

    $message = $changeset->getMessage();
    $this->assertContains(
      'This is a long commit message.',
      $changeset->getSubject(),
    );
    $this->assertEquals(
      "This is a really long commit message.\n\n".
      "And it also has a \"---\" block in it.\n\n".
      "---\n\n".
      "More stuff!!",
      $message
    );
  }

  public function testDiffInMessage(): void {
    $header = \file_get_contents(
      __DIR__.'/hg-diffs/has-diff-in-message.header',
    );
    $patch = \file_get_contents(
      __DIR__.'/hg-diffs/has-diff-in-message.patch',
    );
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      $header,
      $patch,
    );
    assert($changeset !== null);

    $this->assertContains(
      'diff --git a/',
      $changeset->getMessage(),
    );
    $this->assertContains(
      '--- a/',
      $changeset->getMessage(),
    );
    $this->assertContains(
      '+++ b/',
      $changeset->getMessage(),
    );
  }
}
