<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/hd7psl8h
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, Keyset, C};

abstract final class ShipItPathFilters {
  public static function stripPaths(
    ShipItChangeset $changeset,
    vec<string> $strip_patterns,
    vec<string> $strip_exception_patterns = vec[],
  ): ShipItChangeset {
    if (C\is_empty($strip_patterns)) {
      return $changeset;
    }
    $diffs = vec[];
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];

      $match = ShipItUtil::matchesAnyPattern($path, $strip_exception_patterns);

      if ($match !== null) {
        $diffs[] = $diff;
        $changeset = $changeset->withDebugMessage(
          'STRIP FILE EXCEPTION: "%s" matches pattern "%s"',
          $path,
          $match,
        );
        continue;
      }

      $match = ShipItUtil::matchesAnyPattern($path, $strip_patterns);
      if ($match !== null) {
        $changeset = $changeset->withDebugMessage(
          'STRIP FILE: "%s" matches pattern "%s"',
          $path,
          $match,
        );
        continue;
      }

      $diffs[] = $diff;
    }

    return $changeset->withDiffs($diffs);
  }

  /**
   * Change directory paths in a diff using a mapping.
   *
   * @param $mapping a map from directory paths in the source repository to
   *   paths in the destination repository. The first matching mapping is used.
   * @param $skip_patterns a set of patterns of paths that shouldn't be touched.
   */
  public static function moveDirectories(
    ShipItChangeset $changeset,
    dict<string, string> $mapping,
    vec<string> $skip_patterns = vec[],
  ): ShipItChangeset {
    return self::rewritePaths(
      $changeset,
      $path ==> {
        $match = ShipItUtil::matchesAnyPattern($path, $skip_patterns);
        if ($match !== null) {
          return $path;
        }
        foreach ($mapping as $src => $dest) {
          /* HH_IGNORE_ERROR[2049] __PHPStdLib */
          /* HH_IGNORE_ERROR[4107] __PHPStdLib */
          if (\strncmp($path, $src, Str\length($src)) !== 0) {
            continue;
          }
          return $dest.Str\slice($path, Str\length($src));
        }
        return $path;
      },
    );
  }

  public static function rewritePaths(
    ShipItChangeset $changeset,
    (function(string): string) $path_rewrite_callback,
  ): ShipItChangeset {
    $diffs = vec[];
    foreach ($changeset->getDiffs() as $diff) {
      $old_path = $diff['path'];
      $new_path = $path_rewrite_callback($old_path);
      if ($old_path === $new_path) {
        $diffs[] = $diff;
        continue;
      }

      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      $old_path = \preg_quote($old_path, '@');

      $body = $diff['body'];
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      $body = \preg_replace(
        '@^--- a/'.$old_path.'@m',
        '--- a/'.$new_path,
        $body,
      );
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      $body = \preg_replace(
        '@^\+\+\+ b/'.$old_path.'@m',
        '+++ b/'.$new_path,
        $body,
      );
      $diffs[] = shape(
        'path' => $new_path,
        'body' => $body,
      );
    }
    return $changeset->withDiffs($diffs);
  }

  public static function stripExceptDirectories(
    ShipItChangeset $changeset,
    keyset<string> $roots,
  ): ShipItChangeset {
    $roots = Keyset\map(
      $roots,
      $root ==> Str\slice($root, -1) === '/' ? $root : $root.'/',
    );
    $diffs = vec[];
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];
      $match = false;
      foreach ($roots as $root) {
        if (Str\slice($path, 0, Str\length($root)) === $root) {
          $match = true;
          break;
        }
      }
      if ($match) {
        $diffs[] = $diff;
        continue;
      }

      $changeset = $changeset->withDebugMessage(
        'STRIP FILE: "%s" is not in a listed root (%s)',
        $path,
        Str\join($roots, ', '),
      );
    }
    return $changeset->withDiffs($diffs);
  }
}
