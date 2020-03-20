<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/yz6eqiy0
 */
namespace Facebook\ImportIt;

use namespace HH\Lib\Str;
use type Facebook\ShipIt\{ShipItChangeset};

final class ImportItSubmoduleFilter {

  /**
   * Like ShipItSubmoduleFilter, but produces a plain file instead of a
   * real submodule.
   */
  public static function makeSubmoduleDiff(
    string $path,
    ?string $old_rev,
    ?string $new_rev,
  ): string {
    if ($old_rev === null && $new_rev !== null) {
      return Str\format(
        'new file mode 100644
--- /dev/null
+++ b/%s
@@ -0,0 +1 @@
+Subproject commit %s
',
        $path,
        $new_rev,
      );
    } else if ($new_rev === null && $old_rev !== null) {
      return Str\format(
        'deleted file mode 100644
--- a/%s
+++ /dev/null
@@ -1 +0,0 @@
-Subproject commit %s
',
        $path,
        $old_rev,
      );
    } else {
      return Str\format(
        '--- a/%s
+++ b/%s
@@ -1 +1 @@
-Subproject commit %s
+Subproject commit %s
',
        $path,
        $path,
        $old_rev ?? '',
        $new_rev ?? '',
      );
    }
  }

  /**
   * Convert a subproject commit to a text file like:
   *   Subproject commit deadbeef
   *
   * This is the inverse to the ShipIt filter
   * ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile.
   */
  public static function moveSubmoduleCommitToTextFile(
    ShipItChangeset $changeset,
    string $submodule_path,
    string $text_file_with_rev,
  ): ShipItChangeset {
    $diffs = vec[];
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];
      $body = $diff['body'];

      if ($path !== $submodule_path) {
        $diffs[] = $diff;
        continue;
      }

      $new_rev = null;
      $old_rev = null;
      foreach (Str\split($body, "\n") as $line) {
        if (Str\starts_with($line, '-Subproject commit ')) {
          $old_rev = Str\trim(Str\slice($line, 19));
        } else if (Str\starts_with($line, '+Subproject commit ')) {
          $new_rev = Str\trim(Str\slice($line, 19));
        }
      }

      $changeset = $changeset
        ->withDebugMessage(
          'Updating submodule at %s (external path %s) to %s (from %s)',
          $text_file_with_rev,
          $submodule_path,
          $new_rev ?? 'null',
          $old_rev ?? 'null',
        );

      $diffs[] = shape(
        'path' => $text_file_with_rev,
        'body' =>
          self::makeSubmoduleDiff($text_file_with_rev, $old_rev, $new_rev),
      );
    }

    return $changeset->withDiffs($diffs);
  }
}
