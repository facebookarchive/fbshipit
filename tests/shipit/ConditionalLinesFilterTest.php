<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/6sdynvfg
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C};
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;

<<\Oncalls('open_source')>>
final class ConditionalLinesFilterTest extends BaseTest {
  const string COMMENT_LINES_NO_COMMENT_END = 'comment-lines-no-comment-end';
  const string COMMENT_LINES_COMMENT_END = 'comment-lines-comment-end';

  private static function getChangeset(string $name): ShipItChangeset {
    $header = \file_get_contents(__DIR__.'/git-diffs/'.$name.'.header');
    $patch = \file_get_contents(__DIR__.'/git-diffs/'.$name.'.patch');
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch($header, $patch);
    $changeset = \expect($changeset)->toNotBeNull();
    return $changeset;
  }

  public function testCommentingLinesWithNoCommentEnd(): void {
    $changeset = self::getChangeset(self::COMMENT_LINES_NO_COMMENT_END);
    $changeset = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      '@x-oss-disable',
      '//',
    );
    $diffs = $changeset->getDiffs();
    \expect(C\count($diffs))->toBePHPEqual(1);
    $diff = $diffs[0]['body'];

    \expect($diff)->toMatchRegex(re"/^\+\/\/ @x-oss\-disable\: baz$/m");
    \expect($diff)->toMatchRegex(re"/^\-  \/\/ @x-oss\-disable\: derp$/m");
    \expect($diff)->toNotMatchRegex(re"/ @x-oss-disable$/");
  }

  public function testCommentingLinesWithCommentEnd(): void {
    $changeset = self::getChangeset(self::COMMENT_LINES_COMMENT_END);
    $changeset = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      '@x-oss-disable',
      '/*',
      '*/',
    );
    $diffs = $changeset->getDiffs();
    \expect(C\count($diffs))->toBePHPEqual(1);
    $diff = $diffs[0]['body'];

    \expect($diff)->toMatchRegex(re"/^\+\/\* @x-oss\-disable\: baz \*\/$/m");
    \expect($diff)->toMatchRegex(re"/^\-  \/\* @x-oss\-disable\: derp \*\/$/m");
    \expect($diff)->toNotMatchRegex(re"/ @x-oss-disable \*\/$/");
  }

  public static function testFilesProvider(): vec<(string, string, ?string)> {
    return vec[
      tuple(self::COMMENT_LINES_NO_COMMENT_END, '//', null),
      tuple(self::COMMENT_LINES_COMMENT_END, '/*', '*/'),
    ];
  }

  <<DataProvider('testFilesProvider')>>
  public function testUncommentLines(
    string $name,
    string $comment_start,
    ?string $comment_end,
  ): void {
    $changeset = self::getChangeset($name);
    $commented = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      '@x-oss-disable',
      $comment_start,
      $comment_end,
    );
    $uncommented = ShipItConditionalLinesFilter::uncommentLines(
      $commented,
      '@x-oss-disable',
      $comment_start,
      $comment_end,
    );
    \expect($commented->getDiffs()[0]['body'])->toNotEqual(
      $changeset->getDiffs()[0]['body'],
    );
    \expect($uncommented->getDiffs()[0]['body'])->toEqual(
      $changeset->getDiffs()[0]['body'],
    );
  }
}
