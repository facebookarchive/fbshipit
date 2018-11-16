<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItSubmoduleFilter {
  <<TestsBypassVisibility>>
  private static function makeSubmoduleDiff(
    string $path,
    ?string $old_rev,
    ?string $new_rev,
  ): string {
    if ($old_rev === null && $new_rev !== null) {
      \fprintf(
        \STDERR,
        "  Adding submodule at '%s'.\n",
        $path,
      );
      return \sprintf(
        'new file mode 16000
index 0000000..%s 160000
--- /dev/null
+++ b/%s
@@ -0,0 +1 @@
+Subproject commit %s
',
        $new_rev,
        $path,
        $new_rev,
      );
    } else if ($new_rev === null && $old_rev !== null) {
      \fprintf(
        \STDERR,
        "  Removing submodule at '%s'.\n",
        $path,
      );
      return \sprintf(
        'deleted file mode 160000
index %s..0000000
--- a/%s
+++ /dev/null
@@ -1 +0,0 @@
-Subproject commit %s
',
        $old_rev,
        $path,
        $old_rev,
      );
    } else {
      return \sprintf(
        'index %s..%s 160000
--- a/%s
+++ b/%s
@@ -1 +1 @@
-Subproject commit %s
+Subproject commit %s
',
        $old_rev,
        $new_rev,
        $path,
        $path,
        $old_rev,
        $new_rev,
      );
    }
  }

  /**
   * Convert a text file like:
   *   Subproject commit deadbeef
   * ...to an actual subproject commit.
   *
   * For example, hphp/facebook/third-party-rev.txt contains this, and becomes
   * the 'third-party/' submodule on github.com/facebook/hhvm/
   */
  public static function useSubmoduleCommitFromTextFile(
    ShipItChangeset $changeset,
    string $text_file_with_rev,
    string $submodule_path,
  ): ShipItChangeset {
    $diffs = Vector { };
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];
      $body = $diff['body'];

      if ($path !== $text_file_with_rev) {
        $diffs[] = $diff;
        continue;
      }

      $old_rev = $new_rev = null;
      foreach(\explode("\n", $body) as $line) {
        if (!\strncmp('-Subproject commit ', $line, 19)) {
          $old_rev = \trim(\substr($line, 19));
        } else if (!\strncmp('+Subproject commit ', $line, 19)) {
          $new_rev = \trim(\substr($line, 19));
        }
      }

      $diffs[] = shape(
        'path' => $submodule_path,
        'body' => self::makeSubmoduleDiff(
          $submodule_path,
          $old_rev,
          $new_rev,
        ),
      );
    }

    return $changeset->withDiffs($diffs->toImmVector());
  }
}
