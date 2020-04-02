<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/7xivqq4l
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, C, Vec};
use type Facebook\HackTest\DataProvider;

<<\Oncalls('open_source')>>
final class UnusualContentTest extends BaseTest {
  public static function examplesForRemovingFile(
  ): vec<(string, string, string, string, string)> {
    return vec[
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
   */
  <<DataProvider('examplesForRemovingFile')>>
  public function testRemovingFile(
    string $header_file,
    string $patch_file,
    string $pre,
    string $special,
    string $post,
  ): void {
    $header = \file_get_contents($header_file);
    $patch = \file_get_contents($patch_file);
    $lines = Str\split(Str\trim($patch), "\n");
    $git_version = Str\trim($lines[C\count($lines) - 1]);

    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch($header, $patch);
    $changeset = \expect($changeset)->toNotBeNull();
    \expect(C\count($changeset->getDiffs()))->toEqual(1);
    $hunk = $changeset->getDiffs()[0]['body'];
    \expect($hunk)->toContainSubstring($pre);
    \expect($hunk)->toContainSubstring($special);
    \expect($hunk)->toContainSubstring($post);
    \expect($hunk)->toNotContainSubstring($git_version);
  }

  public function testNoNewlineAtEOF(): void {
    $header = \file_get_contents(__DIR__.'/git-diffs/no-newline-at-eof.header');
    $patch = \file_get_contents(__DIR__.'/git-diffs/no-newline-at-eof.patch');
    $lines = Str\split(Str\trim($patch), "\n");
    $git_version = Str\trim($lines[C\count($lines) - 1]);

    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch($header, $patch);
    $changeset = \expect($changeset)->toNotBeNull();

    $hunk = $changeset->getDiffs()[0]['body'];
    \expect($hunk)->toContainSubstring('foo');
    \expect($hunk)->toContainSubstring("\n\\ No newline at");
    \expect($hunk)->toNotContainSubstring($git_version);
  }

  public function testAddingNewlineAtEOF(): void {
    $header = \file_get_contents(
      __DIR__.'/git-diffs/add-newline-at-eof.header',
    );
    $patch = \file_get_contents(__DIR__.'/git-diffs/add-newline-at-eof.patch');
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch($header, $patch);
    $changeset = \expect($changeset)->toNotBeNull();

    $hunk = $changeset->getDiffs()[0]['body'];
    \expect($hunk)->toContainSubstring(' foo');
    \expect($hunk)->toContainSubstring('-bar');
    \expect($hunk)->toContainSubstring('+bar');
    \expect($hunk)->toContainSubstring('\ No newline at end of file');
  }

  public function testStripFileListFromShortCommit(): void {
    $header = \file_get_contents(
      __DIR__.'/git-diffs/no-summary-in-message.header',
    );
    $patch = \file_get_contents(
      __DIR__.'/git-diffs/no-summary-in-message.patch',
    );
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch($header, $patch);
    $changeset = \expect($changeset)->toNotBeNull();

    $message = $changeset->getMessage();
    \expect($message)->toBePHPEqual("");
  }

  public function testStripFileListFromLongCommit(): void {
    $header = \file_get_contents(
      __DIR__.'/git-diffs/has-summary-in-message.header',
    );
    $patch = \file_get_contents(
      __DIR__.'/git-diffs/has-summary-in-message.patch',
    );
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch($header, $patch);
    $changeset = \expect($changeset)->toNotBeNull();

    $message = $changeset->getMessage();
    \expect($changeset->getSubject())->toContainSubstring(
      'This is a long commit message.',
    );
    \expect($message)->toBePHPEqual(
      "This is a really long commit message.\n\n".
      "And it also has a \"---\" block in it.\n\n".
      "---\n\n".
      "More stuff!!",
    );
  }

  public function testDiffInMessage(): void {
    $header = \file_get_contents(
      __DIR__.'/hg-diffs/has-diff-in-message.header',
    );
    $patch = \file_get_contents(__DIR__.'/hg-diffs/has-diff-in-message.patch');
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch($header, $patch);
    $changeset = \expect($changeset)->toNotBeNull();
    \expect($changeset->getMessage())->toContainSubstring('diff --git a/');
    \expect($changeset->getMessage())->toContainSubstring('--- a/');
    \expect($changeset->getMessage())->toContainSubstring('+++ b/');
  }

  public function testPoundSignInSummaryAndMessage(): void {
    $header = \file_get_contents(
      __DIR__.'/hg-diffs/has-pound-sign-in-subject-and-message.header',
    );
    $patch = \file_get_contents(
      __DIR__.'/hg-diffs/has-pound-sign-in-subject-and-message.patch',
    );
    $changeset = ShipItRepoHG::getChangesetFromExportedPatch($header, $patch);
    \expect($changeset->getSubject())->toBePHPEqual('# Testing pound signs');
    \expect($changeset->getMessage())->toBePHPEqual(
      "```\n# This is a code comment\n```",
    );
  }

  public function testQuotedFilenames(): void {
    $header = \file_get_contents(
      __DIR__.'/git-diffs/quoted-filenames.header',
    );
    $patch = \file_get_contents(
      __DIR__.'/git-diffs/quoted-filenames.patch',
    );
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch($header, $patch);
    $changeset = \expect($changeset)->toNotBeNull();
    // Verify quoted paths were correctly parsed.
    $expected_paths = vec[
      'fbcode/hphp/test/slow/bad-this-closure-2.php.expectf',
      'fbcode/hphp/test/slow/bad\\directory/i_am_a_very_bad\\file.php',
      'fbcode/hphp/test/slow/bad\\directory/i_am_a_very_bad\\file.php.expect',
    ];
    \expect(Vec\map($changeset->getDiffs(), $diff ==> $diff['path']))
      ->toEqual($expected_paths);
    // Verify paths can be correctly transformed.
    $changeset = ShipItPathFilters::rewritePaths(
      $changeset,
      $path ==> Str\strip_prefix($path, 'fbcode/'),
    );
    foreach ($changeset->getDiffs() as $i => $diff) {
      $expected = Str\strip_prefix($expected_paths[$i], 'fbcode/');
      \expect($diff['path'])->toEqual($expected);
      \expect($diff['body'])->toContainSubstring("\n+++ b/$expected\n");
    }
  }
}
