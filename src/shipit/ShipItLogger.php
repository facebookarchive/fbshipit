<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/kljtdfab
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str; // @oss-enable

type ShipItLoggerOutputFunc = (function(
  Str\SprintfFormatString,
  mixed...
): void);

abstract final class ShipItLogger {
  private static ?ShipItLoggerOutputFunc $outFunc = null;
  private static ?ShipItLoggerOutputFunc $errFunc = null;

  public static function redirectOutput(
    ShipItLoggerOutputFunc $out_func,
    ?ShipItLoggerOutputFunc $err_func = null,
  ): void {
    self::$outFunc = $out_func;
    self::$errFunc = $err_func ?? $out_func;
  }

  public static function out(Str\SprintfFormatString $f, mixed ...$args): void {
    $out_func = self::$outFunc;
    if ($out_func is nonnull) {
      /* HH_IGNORE_ERROR[4027] Passing in a format string */
      $out_func($f, ...$args);
      return;
    }
    if (!\defined('\STDOUT')) {
      // No place to log to.
      return;
    }
    PHP\fprintf(\STDOUT, (string) $f, ...$args);
  }

  public static function err(Str\SprintfFormatString $f, mixed ...$args): void {
    $err_func = self::$errFunc;
    if ($err_func is nonnull) {
      /* HH_IGNORE_ERROR[4027] Passing in a format string */
      $err_func($f, ...$args);
      return;
    }
    if (!\defined('\STDERR')) {
      // No place to log to.
      return;
    }
    PHP\fprintf(\STDERR, (string) $f, ...$args);
  }
}
