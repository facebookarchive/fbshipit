<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/tfdj3zb0
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str;

final class ShipItSubmoduleFilter {
  public static function makeSubmoduleDiff(
    string $path,
    ?string $old_rev,
    ?string $new_rev,
  ): string {
    if ($old_rev === null && $new_rev !== null) {
      ShipItLogger::err("  Adding submodule at '%s'.\n", $path);
      return Str\format(
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
      ShipItLogger::err("  Removing submodule at '%s'.\n", $path);
      return Str\format(
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
      return Str\format(
        'index %s..%s 160000
--- a/%s
+++ b/%s
@@ -1 +1 @@
-Subproject commit %s
+Subproject commit %s
',
        $old_rev ?? '',
        $new_rev ?? '',
        $path,
        $path,
        $old_rev ?? '',
        $new_rev ?? '',
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
    $diffs = vec[];
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];
      $body = $diff['body'];

      if ($path !== $text_file_with_rev) {
        $diffs[] = $diff;
        continue;
      }

      $new_rev = null;
      $old_rev = null;
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      foreach (\explode("\n", $body) as $line) {
        /* HH_IGNORE_ERROR[2049] __PHPStdLib */
        /* HH_IGNORE_ERROR[4107] __PHPStdLib */
        if (!\strncmp('-Subproject commit ', $line, 19)) {
          $old_rev = Str\trim(Str\slice($line, 19));
          /* HH_IGNORE_ERROR[2049] __PHPStdLib */
          /* HH_IGNORE_ERROR[4107] __PHPStdLib */
        } else if (!\strncmp('+Subproject commit ', $line, 19)) {
          $new_rev = Str\trim(Str\slice($line, 19));
        }
      }

      $diffs[] = shape(
        'path' => $submodule_path,
        'body' => self::makeSubmoduleDiff($submodule_path, $old_rev, $new_rev),
      );
    }

    return $changeset->withDiffs($diffs);
  }

  /**
   * Is this a diff on a submodule file?
   * Pattern:
   * new file mode 160000
   * index 0000000..6a3dcef
   * --- /dev/null
   * +++ b/third_party/project
   * @@ -0,0 +1 @@
   * +Subproject commit deadbeef123
   */
  public static function isSubmoduleDiff(ShipItDiff $diff): bool {
    $subproject_line = '[-+]Subproject commit [0-9a-fA-F]+';
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    return (bool)\preg_match(
      '@'.
      // Submodule file mode
      '(new file mode 160\d+ *\n)?'.
      '(deleted file mode 160\d+ *\n)?'.
      // Header lines
      'index \w+..\w+ *\n'.
      '[^\n]+\n[^\n]+\n[^\n]+\n'.
      // One or two submodule lines
      Str\format('%s( *\n%s)?', $subproject_line, $subproject_line).
      // Nothing else
      '\s*$@',
      $diff['body'],
    );
  }

  /**
   * Is this a diff on a .submodule.txt file?
   * Pattern:
   * new file mode 100644
   * --- /dev/null
   * +++ b/path/to/file.submodule.txt
   * @@ -0,0 +1,1 @@
   * +Subproject commit deadbeef123
   */
  public static function isSubmoduleTXTDiff(ShipItDiff $diff): bool {
    if (!Str\ends_with($diff['path'], '.submodule.txt')) {
      return false;
    }
    $subproject_line = '[-+]Subproject commit [0-9a-fA-F]+';
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    return (bool)\preg_match(
      '@'.
      // Regular file mode
      '(new file mode 100\d+ *\n)?'.
      '(deleted file mode 100\d+ *\n)?'.
      // Header lines
      '[^\n]+\n[^\n]+\n[^\n]+\n'.
      // One or two submodule lines
      Str\format('%s( *\n%s)?', $subproject_line, $subproject_line).
      // Nothing else
      '\s*$@',
      $diff['body'],
    );
  }
}
