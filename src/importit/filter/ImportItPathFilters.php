<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ImportIt;

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
    ImmMap<string, string> $shipit_mapping,
    ImmVector<string> $skip_patterns = ImmVector {},
  ): \Facebook\ShipIt\ShipItChangeset {
    $mapping = self::invertShipIt($shipit_mapping);
    return \Facebook\ShipIt\ShipItPathFilters::moveDirectories(
      $changeset,
      $mapping,
      $skip_patterns,
    );
  }

  /**
   * Invert this ShipIt map, throwing on any keys that would be duplicated.
   *
   * @param $shipit_mapping the mapping to invert
   */
  public static function invertShipIt(
    ImmMap<string, string> $shipit_mapping,
  ): ImmMap<string, string> {
    $reverse_mapping = Map {};
    foreach ($shipit_mapping as $dest_path => $src_path) {
      invariant(
        !$reverse_mapping->containsKey($src_path),
        'Mulitiple paths map from "%s" ("%s" and "%s")!',
        $src_path,
        $dest_path,
        $reverse_mapping[$src_path],
      );
      $reverse_mapping[$src_path] = $dest_path;
    }
    // Sort the mapping in reverse order.  The purpose of this is to make sure
    // that if two src path entries exist such that one of them is a prefix of
    // the other, the prefix always appears last.  This ensures that mappings
    // for subdirectories always take precedence over less-specific mappings.
    \krsort(&$reverse_mapping);

    return $reverse_mapping->toImmMap();
  }
}
