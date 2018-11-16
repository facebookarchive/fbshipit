<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ConditionalLinesFilterTest extends BaseTest {
  const string COMMENT_LINES_NO_COMMENT_END =
    'comment-lines-no-comment-end';
  const string COMMENT_LINES_COMMENT_END =
    'comment-lines-comment-end';

  private static function getChangeset(string $name): ShipItChangeset {
    $header = \file_get_contents(__DIR__.'/git-diffs/'.$name.'.header');
    $patch = \file_get_contents(__DIR__.'/git-diffs/'.$name.'.patch');
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      $header,
      $patch,
    );
    assert($changeset !== null);
    return $changeset;
  }

  public function testCommentingLinesWithNoCommentEnd(): void {
    $changeset = self::getChangeset(self::COMMENT_LINES_NO_COMMENT_END);
    $changeset = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      '@oss-disable',
      '//',
    );
    $diffs = $changeset->getDiffs();
    $this->assertEquals(1, $diffs->count());
    $diff = $diffs->at(0)['body'];

    $this->assertRegExp(
      '/^'.\preg_quote('+// @oss-disable: baz', '/').'$/m',
      $diff,
    );
    $this->assertRegExp(
      '/^'.\preg_quote('-  // @oss-disable: derp', '/').'$/m',
      $diff,
    );
    $this->assertNotRegExp('/ @oss-disable$/', $diff);
  }

  public function testCommentingLinesWithCommentEnd(): void {
    $changeset = self::getChangeset(self::COMMENT_LINES_COMMENT_END);
    $changeset = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      '@oss-disable',
      '/*',
      '*/',
    );
    $diffs = $changeset->getDiffs();
    $this->assertEquals(1, $diffs->count());
    $diff = $diffs->at(0)['body'];

    $this->assertRegExp(
      '/^'.\preg_quote('+/* @oss-disable: baz */', '/').'$/m',
      $diff,
    );
    $this->assertRegExp(
      '/^'.\preg_quote('-  /* @oss-disable: derp */', '/').'$/m',
      $diff,
    );
    $this->assertNotRegExp('/ @oss-disable \*\/$/', $diff);
  }

  public function testFilesProvider(): array<(string, string, ?string)> {
    return [
      tuple(self::COMMENT_LINES_NO_COMMENT_END, '//', null),
      tuple(self::COMMENT_LINES_COMMENT_END, '/*', '*/'),
    ];
  }

  /**
   * @dataProvider testFilesProvider
   */
  public function testUncommentLines(
    string $name,
    string $comment_start,
    ?string $comment_end,
  ): void {
    $changeset = self::getChangeset($name);
    $commented = ShipItConditionalLinesFilter::commentLines(
      $changeset,
      '@oss-disable',
      $comment_start,
      $comment_end,
    );
    $uncommented = ShipItConditionalLinesFilter::uncommentLines(
      $commented,
      '@oss-disable',
      $comment_start,
      $comment_end,
    );
    $this->assertNotSame(
      $changeset->getDiffs()->at(0)['body'],
      $commented->getDiffs()->at(0)['body'],
    );
    $this->assertSame(
      $changeset->getDiffs()->at(0)['body'],
      $uncommented->getDiffs()->at(0)['body'],
    );
  }
}
