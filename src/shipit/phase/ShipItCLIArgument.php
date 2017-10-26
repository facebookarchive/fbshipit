<?hh // strict
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
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
