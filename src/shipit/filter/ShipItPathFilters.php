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

use namespace HH\Lib\{Str, Keyset, C, Dict, Regex};

abstract final class ShipItPathFilters {
  // Skip debug messages on very large changesets, as that can cause
  // excessive delays in runtime.
  const int LARGE_CHANGESET = 3000;

  public static function stripPaths(
    ShipItChangeset $changeset,
    vec<string> $strip_patterns,
    vec<string> $strip_exception_patterns = vec[],
  ): ShipItChangeset {
    if (C\is_empty($strip_patterns)) {
      return $changeset;
    }
    $diffs = vec[];
    $use_debug = C\count($changeset->getDiffs()) < self::LARGE_CHANGESET;
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];

      $match = ShipItUtil::matchesAnyPattern($path, $strip_exception_patterns);

      if ($match !== null) {
        $diffs[] = $diff;
        if ($use_debug) {
          $changeset = $changeset->withDebugMessage(
            'STRIP FILE EXCEPTION: "%s" matches pattern "%s"',
            $path,
            $match,
          );
        }
        continue;
      }

      $match = ShipItUtil::matchesAnyPattern($path, $strip_patterns);
      if ($match !== null) {
        if ($use_debug) {
          $changeset = $changeset->withDebugMessage(
            'STRIP FILE: "%s" matches pattern "%s"',
            $path,
            $match,
          );
        }
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
          if (!Str\starts_with($path, $src)) {
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

      /* HH_FIXME[2049] __PHPStdLib */
      /* HH_FIXME[4107] __PHPStdLib */
      $old_path = \preg_quote($old_path, '@');

      $body = $diff['body'];
      /* HH_FIXME[2049] __PHPStdLib */
      /* HH_FIXME[4107] __PHPStdLib */
      $body = \preg_replace(
        '@^--- (a/'.$old_path.'|"a/.*?"$)@m',
        '--- a/'.$new_path,
        $body,
      );
      /* HH_FIXME[2049] __PHPStdLib */
      /* HH_FIXME[4107] __PHPStdLib */
      $body = \preg_replace(
        '@^\+\+\+ (b/'.$old_path.'|"b/.*?"$)@m',
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
    $use_debug = C\count($changeset->getDiffs()) < self::LARGE_CHANGESET;
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

      if ($use_debug) {
        $changeset = $changeset->withDebugMessage(
          'STRIP FILE: "%s" is not in a listed root (%s)',
          $path,
          Str\join($roots, ', '),
        );
      }
    }
    return $changeset->withDiffs($diffs);
  }

  public static function stripExceptSourceRoots(
    ShipItChangeset $changeset,
    keyset<string> $source_roots,
  ): ShipItChangeset {
    $roots = Keyset\filter($source_roots, $root ==> $root !== '');
    if (C\is_empty($roots)) {
      return $changeset;
    }

    return self::stripExceptDirectories($changeset, $roots);
  }

  /**
   * Rewrite C/C++ #include directives using path mappings.
   *
   * E.g. `#include "deep/project/name.h"` exports to `#include "src/name.h"`
   */
  public static function rewriteCppIncludeDirectivePaths(
    ShipItChangeset $changeset,
    dict<string, string> $path_mappings,
  ): ShipItChangeset {
    // This explicitly does not support mapping #includes to the root of the
    // destination repo. This causes problems when importing files as it's not
    // possible to determine whether to map paths for the #include or not.
    $path_mappings = Dict\filter_with_key(
      $path_mappings,
      ($src, $dest) ==> !Str\is_empty($src) && !Str\is_empty($dest),
    );
    $diffs = vec[];
    foreach ($changeset->getDiffs() as $diff) {
      $diff['body'] = Regex\replace_with(
        $diff['body'],
        re"/#include [<\"](.*)[>\"]/",
        ($match) ==> {
          $full_match = $match[0];
          $path = $match[1];
          foreach ($path_mappings as $src => $dest) {
            if (!Str\starts_with($path, $src)) {
              continue;
            }
            return Str\replace(
              $full_match,
              $path,
              $dest.Str\slice($path, Str\length($src)),
            );
          }
          return $full_match;
        },
      );
      $diffs[] = $diff;
    }
    return $changeset->withDiffs($diffs);
  }
}
