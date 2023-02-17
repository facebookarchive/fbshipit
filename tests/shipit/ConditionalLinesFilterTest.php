<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/6sdynvfg
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C}; // @oss-enable
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;

<<\Oncalls('open_source')>>
final class ConditionalLinesFilterTest extends BaseTest {
  const string COMMENT_LINES_NO_COMMENT_END = 'comment-lines-no-comment-end';

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
      null,
      '@x-oss-disable',
      '//',
    );
    $diffs = $changeset->getDiffs();
    \expect(C\count($diffs))->toEqual(1);
    $diff = $diffs[0]['body'];

    \expect($diff)->toMatchRegex(re"/^\+\/\/ @x-oss\-disable\: baz$/m");
    \expect($diff)->toMatchRegex(re"/^\-  \/\/ @x-oss\-disable\: derp$/m");
    \expect($diff)->toNotMatchRegex(re"/ @x-oss-disable$/");
  }

  public function testUncommentLines(): void {
    $changeset = self::getChangeset(self::COMMENT_LINES_NO_COMMENT_END);
    $commented = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      null,
      '@x-oss-disable',
      '//',
    );
    $uncommented = ShipItConditionalLinesFilter::uncommentLines(
      $commented,
      null,
      '@x-oss-disable',
      '//',
    );
    \expect($commented->getDiffs()[0]['body'])->toNotEqual(
      $changeset->getDiffs()[0]['body'],
    );
    \expect($uncommented->getDiffs()[0]['body'])->toEqual(
      $changeset->getDiffs()[0]['body'],
    );
  }

  public function testCommentingLinesWithPathRegex(): void {
    $changeset = self::getChangeset(self::COMMENT_LINES_NO_COMMENT_END);
    $good_regex_changeset = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      '@foo@',
      '@x-oss-disable',
      '//',
    );
    $good_regex_diffs = $good_regex_changeset->getDiffs();
    \expect(C\count($good_regex_diffs))->toEqual(1);
    $good_regex_diff = $good_regex_diffs[0]['body'];

    \expect($good_regex_diff)->toMatchRegex(
      re"/^\+\/\/ @x-oss\-disable\: baz$/m",
    );
    \expect($good_regex_diff)->toMatchRegex(
      re"/^\-  \/\/ @x-oss\-disable\: derp$/m",
    );
    \expect($good_regex_diff)->toNotMatchRegex(re"/ @x-oss-disable$/");

    $bad_regex_changeset = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      '@baz@',
      '@x-oss-disable',
      '//',
    );
    $bad_regex_diffs = $bad_regex_changeset->getDiffs();
    \expect(C\count($bad_regex_diffs))->toEqual(1);
    $bad_regex_diff = $bad_regex_diffs[0]['body'];

    \expect($bad_regex_diff)->toNotMatchRegex(
      re"/^\+\/\/ @x-oss\-disable\: baz$/m",
    );
    \expect($bad_regex_diff)->toNotMatchRegex(
      re"/^\-  \/\/ @x-oss\-disable\: derp$/m",
    );
    \expect($bad_regex_diff)->toMatchRegex(re"/ @x-oss-disable$/");
  }
}
