<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/hd7psl8h
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C, Dict, Keyset, Regex, Str, Vec}; // @oss-enable

abstract final class ShipItPathFilters {
  // Skip debug messages on very large changesets, as that can cause
  // excessive delays in runtime.
  const int LARGE_CHANGESET = 3000;
  const int SINGLE_FILE_MINIMUM_DEPTH = 3;

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

      $match = self::matchesAnyPattern($path, $strip_exception_patterns);

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

      $match = self::matchesAnyPattern($path, $strip_patterns);
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
  ): ShipItChangeset {
    return self::rewritePaths(
      $changeset,
      $path ==> {
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
      $old_a_path = $diff['path'];
      $old_b_path = Shapes::idx($diff, 'new_path');
      $new_a_path = $path_rewrite_callback($old_a_path);
      if ($old_b_path is nonnull) {
        $new_b_path = $path_rewrite_callback($old_b_path);
      } else {
        $new_b_path = null;
      }
      if ($old_a_path === $new_a_path && $old_b_path === $new_b_path) {
        $diffs[] = $diff;
        continue;
      }

      $old_a_path = PHP\preg_quote($old_a_path, '@');

      $body = $diff['body'];
      $body = PHP\preg_replace(
        '@^--- (a/'.$old_a_path.'|"a/.*?"$)@m',
        '--- a/'.$new_a_path,
        $body,
      );

      if ($old_b_path is nonnull) {
        $body = PHP\preg_replace(
          '@^rename from '.$old_a_path.'$@m',
          'rename from '.$new_a_path,
          $body,
        );
        $body = PHP\preg_replace(
          '@^copy from '.$old_a_path.'$@m',
          'copy from '.$new_a_path,
          $body,
        );

        $old_b_path = PHP\preg_quote($old_b_path, '@');

        if ($new_b_path is nonnull) {
          $body = PHP\preg_replace(
            '@^rename to '.$old_b_path.'$@m',
            'rename to '.$new_b_path,
            $body,
          );
          $body = PHP\preg_replace(
            '@^copy to '.$old_b_path.'$@m',
            'copy to '.$new_b_path,
            $body,
          );
        }
      }

      $body = PHP\preg_replace(
        '@^\+\+\+ (b/'.($old_b_path ?? $old_a_path).'|"b/.*?"$)@m',
        '+++ b/'.($new_b_path ?? $new_a_path),
        $body,
      ) as string;

      if ($new_b_path is null) {
        $diffs[] = shape(
          'path' => $new_a_path,
          'body' => $body,
        );
      } else {
        $diffs[] = shape(
          'path' => $new_a_path,
          'body' => $body,
          'new_path' => $new_b_path,
        );
      }
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

  public static function getSourceRoots(
    dict<string, string> $path_mappings,
  ): keyset<string> {
    $paths = Keyset\keys($path_mappings);

    if (C\contains($paths, '')) {
      // All paths are included.
      return keyset[];
    }
    // Sort least specific to most specific.
    $paths = Vec\sort($paths);
    $roots = keyset[];
    foreach ($paths as $path) {
      // If this is a single file, use its parent,
      // but only if it's deep enough to mitigate risk of over-coverage.
      if (!Str\ends_with($path, '/')) {
        $parts = Str\split($path, '/');
        if (C\count($parts) >= self::SINGLE_FILE_MINIMUM_DEPTH) {
          $path = Vec\take($parts, C\count($parts) - 1)
            |> Str\join($$, '/')
            |> $$.'/';
        }
      }
      // If this path starts with any elements in the set of roots we have
      // already computed, we do not need to add this as it would be redundant.
      $root_match = false;
      foreach ($roots as $root) {
        $root_match = $root_match || Str\starts_with($path, $root);
      }
      if ($root_match) {
        continue;
      }
      $roots[] = $path;
    }
    return $roots;
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
   * Rewrite C/C++ #include and Thrift include directives using path mappings.
   *
   * E.g.
   * 1. `#include "deep/project/name.h"` exports to `#include "src/name.h"`
   * 2. `include "fbcode/glean/service.thrift"` exports to `include "glean/service.thrift"`
   */
  public static function rewriteIncludeDirectivePaths(
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
        // "#" is optional because Thrift include statements do not have a
        // leading "#" but CXX includes do.
        re"/#?include [<\"](.*)[>\"]/",
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

  public static function matchesAnyPattern(
    string $path,
    Container<string> $patterns,
  ): ?string {
    foreach ($patterns as $pattern) {
      $matches = vec[];
      if (PHP\preg_match($pattern, $path, inout $matches)) {
        return $pattern;
      }
    }
    return null;
  }
}
