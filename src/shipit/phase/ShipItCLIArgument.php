<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

type ShipItCLIArgument = shape(
  ?'short_name' => string,
  'long_name' => string,
  // If null, the function is considered deprecated
  ?'description' => string,
  // Set non-null if deprecated with a replacement
  ?'replacement' => string,
  // Handler function for when the option is set
  ?'write' => (function(string):mixed),
  // Detect if a required option has been set; if this isn't provided, it will
  // be required on the command line. Specifying this function allows it to be
  // prefilled on the config object instead
  ?'isset' => (function():bool),
);
