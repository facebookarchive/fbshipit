<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

abstract final class ShipItPathFilters {
  public static function stripPaths(
    ShipItChangeset $changeset,
    ImmVector<string> $strip_patterns,
    ImmVector<string> $strip_exception_patterns = ImmVector { },
  ): ShipItChangeset {
    if ($strip_patterns->count() == 0) {
      return $changeset;
    }
    $diffs = Vector { };
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];

      $match = ShipItUtil::matchesAnyPattern(
        $path,
        $strip_exception_patterns,
      );

      if ($match !== null) {
        $diffs[] = $diff;
        $changeset = $changeset->withDebugMessage(
          'STRIP FILE EXCEPTION: "%s" matches pattern "%s"',
          $path,
          $match,
        );
        continue;
      }

      $match = ShipItUtil::matchesAnyPattern(
        $path,
        $strip_patterns,
      );
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

    return $changeset->withDiffs($diffs->toImmVector());
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
    ImmMap<string, string> $mapping,
    ImmVector<string> $skip_patterns = ImmVector {},
  ): ShipItChangeset {
    return self::rewritePaths(
      $changeset,
      $path ==> {
        $match = ShipItUtil::matchesAnyPattern(
          $path,
          $skip_patterns,
        );
        if ($match !== null) {
          return $path;
        }
        foreach ($mapping as $src => $dest) {
          if (\strncmp($path, $src, \strlen($src)) !== 0) {
            continue;
          }
          return $dest.\substr($path, \strlen($src));
        }
        return $path;
      },
    );
  }

  public static function rewritePaths(
    ShipItChangeset $changeset,
    (function(string):string) $path_rewrite_callback,
  ): ShipItChangeset {
    $diffs = Vector { };
    foreach ($changeset->getDiffs() as $diff) {
      $old_path = $diff['path'];
      $new_path = $path_rewrite_callback($old_path);
      if ($old_path === $new_path) {
        $diffs[] = $diff;
        continue;
      }

      $old_path = \preg_quote($old_path, '@');

      $body = $diff['body'];
      $body = \preg_replace(
        '@^--- a/'.$old_path.'@m',
        '--- a/'.$new_path,
        $body,
      );
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
    return $changeset->withDiffs($diffs->toImmVector());
  }

  public static function stripExceptDirectories(
    ShipItChangeset $changeset,
    ImmSet<string> $roots,
  ): ShipItChangeset {
    $roots = $roots->map(
      $root ==> \substr($root, -1) === '/' ? $root : $root.'/'
    );
    $diffs = Vector { };
    foreach ($changeset->getDiffs() as $diff) {
      $path = $diff['path'];
      $match = false;
      foreach ($roots as $root) {
        if (\substr($path, 0, \strlen($root)) === $root) {
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
        \implode(', ', $roots),
      );
    }
    return $changeset->withDiffs($diffs->toImmVector());
  }
}
