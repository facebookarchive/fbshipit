<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

/**
 * Comments or uncomments specially marked lines.
 *
 * Eg if:
 *  - comment start is '//'
 *  - comment end is null
 *  - marker is '@oss-disable'
 *
 * commentLines():
 *  - foo() // @oss-disable
 *  + // @oss-disable: foo()
 * uncommentLines():
 *  - // @oss-disable: foo()
 *  + foo() // @oss-disable
 */
final class ShipItConditionalLinesFilter {
  public static function commentLines(
    ShipItChangeset $changeset,
    string $marker,
    string $comment_start,
    ?string $comment_end = null,
    bool $remove_content = false,
  ): ShipItChangeset {
    $pattern =
      '/^([-+ ]\s*)(\S.*) '.
      \preg_quote($comment_start, '/').
      ' '.
      \preg_quote($marker, '/').
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
    $pattern =
      '/^([-+ ]\s*)'.
      \preg_quote($comment_start, '/').
      ' '.
      \preg_quote($marker, '/').
      ': (.+)'.
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
    $diffs = Vector {};
    foreach ($changeset->getDiffs() as $diff) {
      $diff['body'] = (new ImmVector(\explode("\n", $diff['body'])))
        ->map(
          $line ==> \preg_replace($pattern, $replacement, $line, /* limit */ 1),
        )
        |> \implode("\n", $$);
      $diffs[] = $diff;
    }
    return $changeset->withDiffs($diffs->toImmVector());
  }
}
