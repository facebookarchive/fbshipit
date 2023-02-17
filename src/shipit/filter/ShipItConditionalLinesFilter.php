<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/5oj8j0ki
 */

namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, Vec}; // @oss-enable

/**
 * Comments or uncomments specially marked lines.
 *
 * Eg if:
 *  - comment start is '//'
 *  - marker is '@x-oss-disable'
 *
 * commentLines():
 *  - foo() // @x-oss-disable
 *  + // @x-oss-disable: foo()
 * uncommentLines():
 *  - // @x-oss-disable: foo()
 *  + foo() // @x-oss-disable
 */
final abstract class ShipItConditionalLinesFilter {
  public static function commentLines(
    ShipItChangeset $changeset,
    ?string $path_regex,
    string $marker,
    string $comment_start,
    bool $remove_content = false,
  ): ShipItChangeset {
    $pattern = '/^([-+ ]\s*)(\S.*) '.
      PHP\preg_quote($comment_start, '/').
      ' '.
      PHP\preg_quote($marker, '/').
      '$/';

    $replacement = '\\1'.$comment_start.' '.$marker;
    if (!$remove_content) {
      $replacement .= ': \\2';
    }

    return self::process($changeset, $path_regex, $pattern, $replacement);
  }

  public static function uncommentLines(
    ShipItChangeset $changeset,
    ?string $path_regex,
    string $marker,
    string $comment_start,
  ): ShipItChangeset {
    $pattern = '/^([-+ ]\s*)'.
      PHP\preg_quote($comment_start, '/').
      ' '.
      PHP\preg_quote($marker, '/').
      ': (.+)'.
      '$/';
    $replacement = '\\1\\2 '.$comment_start.' '.$marker;

    return self::process($changeset, $path_regex, $pattern, $replacement);
  }

  private static function process(
    ShipItChangeset $changeset,
    ?string $path_regex,
    string $pattern,
    string $replacement,
  ): ShipItChangeset {
    $diffs = vec[];
    foreach ($changeset->getDiffs() as $diff) {
      $_matches = vec[];
      if (
        $path_regex is nonnull &&
        !PHP\preg_match($path_regex, $diff['path'], inout $_matches)
      ) {
        $diffs[] = $diff;
        continue;
      }
      $diff['body'] = Str\split($diff['body'], "\n")
        |> Vec\map(
          $$,
          $line ==> PHP\preg_replace(
            $pattern,
            $replacement,
            $line, /* limit */
            1,
          ) as ?string ??
            $line, // if input length === 0, then `null` is returned
        )
        |> Str\join($$, "\n");
      $diffs[] = $diff;
    }
    return $changeset->withDiffs($diffs);
  }
}
