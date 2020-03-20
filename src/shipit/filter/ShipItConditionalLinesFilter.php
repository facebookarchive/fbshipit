<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/5oj8j0ki
 */

namespace Facebook\ShipIt;

use namespace HH\Lib\Str;

/**
 * Comments or uncomments specially marked lines.
 *
 * Eg if:
 *  - comment start is '//'
 *  - comment end is null
 *  - marker is '@x-oss-disable'
 *
 * commentLines():
 *  - foo() // @x-oss-disable
 *  + // @x-oss-disable: foo()
 * uncommentLines():
 *  - // @x-oss-disable: foo()
 *  + foo() // @x-oss-disable
 */
final class ShipItConditionalLinesFilter {
  public static function commentLines(
    ShipItChangeset $changeset,
    string $marker,
    string $comment_start,
    ?string $comment_end = null,
    bool $remove_content = false,
  ): ShipItChangeset {
    $pattern = '/^([-+ ]\s*)(\S.*) '.
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \preg_quote($comment_start, '/').
      ' '.
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \preg_quote($marker, '/').
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      ($comment_end === null ? '' : (' '.\preg_quote($comment_end, '/'))).
      '$/';

    $replacement = '\\1'.$comment_start.' '.$marker;
    if (!$remove_content) {
      $replacement .= ': \\2';
    }
    if ($comment_end !== null) {
      $replacement .= ' '.$comment_end;
    }

    return self::process($changeset, $pattern, $replacement);
  }

  public static function uncommentLines(
    ShipItChangeset $changeset,
    string $marker,
    string $comment_start,
    ?string $comment_end = null,
  ): ShipItChangeset {
    $pattern = '/^([-+ ]\s*)'.
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \preg_quote($comment_start, '/').
      ' '.
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \preg_quote($marker, '/').
      ': (.+)'.
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      ($comment_end === null ? '' : (' '.\preg_quote($comment_end, '/'))).
      '$/';
    $replacement = '\\1\\2 '.$comment_start.' '.$marker;
    if ($comment_end !== null) {
      $replacement .= ' '.$comment_end;
    }

    return self::process($changeset, $pattern, $replacement);
  }

  private static function process(
    ShipItChangeset $changeset,
    string $pattern,
    string $replacement,
  ): ShipItChangeset {
    $diffs = vec[];
    foreach ($changeset->getDiffs() as $diff) {
      $diff['body'] = (new ImmVector(Str\split($diff['body'], "\n")))
        ->map(
          /* HH_IGNORE_ERROR[2049] __PHPStdLib */
          /* HH_IGNORE_ERROR[4107] __PHPStdLib */
          $line ==> \preg_replace($pattern, $replacement, $line, /* limit */ 1),
        )
        |> Str\join($$, "\n");
      $diffs[] = $diff;
    }
    return $changeset->withDiffs($diffs);
  }
}
