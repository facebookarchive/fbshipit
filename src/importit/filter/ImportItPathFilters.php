<?hh
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 * @format
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/d1j5axht
 */
namespace Facebook\ImportIt;

use namespace HH\Lib\{C, Dict, Str}; // @oss-enable

abstract final class ImportItPathFilters {
  /**
   * Change directory paths in a diff using a mapping.  This is a convenience
   * method that takes ShipIt path mappings and converts them into something
   * useable for ImportIt.
   *
   * @param $mapping a map from directory paths in the destination repository to
   *   paths in the source repository. The last matching mapping is used.
   * @param $skip_patterns a set of patterns of paths that shouldn't be touched.
   */
  public static function moveDirectories(
    \Facebook\ShipIt\ShipItChangeset $changeset,
    dict<string, string> $shipit_mapping,
    vec<string> $skip_patterns = vec[],
  ): \Facebook\ShipIt\ShipItChangeset {
    $mapping = self::invertShipIt($shipit_mapping);
    return \Facebook\ShipIt\ShipItPathFilters::moveDirectories(
      $changeset,
      $mapping,
      $skip_patterns,
    );
  }

  /**
   * Rewrite C/C++ #include directives using path mappings.
   *
   * E.g. `#include "src/header.h"` imports to `#include "deep/project/header.h"`.
   */
  public static function rewriteIncludeDirectivePaths(
    \Facebook\ShipIt\ShipItChangeset $changeset,
    dict<string, string> $path_mappings,
  ): \Facebook\ShipIt\ShipItChangeset {
    $path_mappings = self::invertShipIt($path_mappings);
    return \Facebook\ShipIt\ShipItPathFilters::rewriteIncludeDirectivePaths(
      $changeset,
      $path_mappings,
    );
  }

  /**
   * Invert this ShipIt map, throwing on any keys that would be duplicated.
   *
   * @param $shipit_mapping the mapping to invert
   */
  public static function invertShipIt(
    dict<string, string> $shipit_mapping,
  ): dict<string, string> {
    $reverse_mapping = dict[];
    foreach ($shipit_mapping as $dest_path => $src_path) {
      if (C\contains_key($reverse_mapping, $src_path)) {
        throw new \Facebook\ShipIt\ShipItMultipleImportPathsException(
          Str\format(
            'Multiple paths map from "%s" ("%s" and "%s")!',
            $src_path,
            $dest_path,
            $reverse_mapping[$src_path],
          ),
        );
      }
      $reverse_mapping[$src_path] = $dest_path;
    }
    // Sort the mapping in reverse order.  The purpose of this is to make sure
    // that if two src path entries exist such that one of them is a prefix of
    // the other, the prefix always appears last.  This ensures that mappings
    // for subdirectories always take precedence over less-specific mappings.
    return Dict\sort_by_key($reverse_mapping)
      |> Dict\reverse($$);
  }
}
