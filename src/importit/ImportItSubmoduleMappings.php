<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/s1x24ew6
 */
namespace Facebook\ImportIt;

/**
 * An interface a ShipIt CLI class would want to implement if it uses ImportIt
 * to pull changes into an internal repository and uses submodules.
 */
interface ImportItSubmoduleMappings {
  /**
   * A map from revision text file to the location a submodule should be.  This
   * is passed to ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile.
   */
  public static function getSubmoduleMappings(): dict<string, string>;
}
