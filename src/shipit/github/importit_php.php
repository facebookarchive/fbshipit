<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ImportIt\PHP;

// NOTE: These functions exist as a compatibility layer with FB internal infrastructure.
// As such, do not depend on these functions or their type signatures outside of ShipIt!

function file_put_contents(
  string $filename,
  mixed $data,
  int $flags = 0,
  mixed $context = null,
): dynamic {
  return \file_put_contents($filename, $data, $flags, $context);
}

function file_exists(string $filename): bool {
  return \file_exists($filename);
}

function mkdir(
  string $pathname,
  int $mode = 0777,
  bool $recursive = false,
  mixed $context = null,
): bool {
  return \mkdir($pathname, $mode, $recursive, $context);
}

function is_dir(string $filename): bool {
  return \is_dir($filename);
}
