<?hh
// (c) Facebook, Inc. and its affiliates. Confidential and proprietary.

namespace Facebook\ShipIt;

use namespace HH\Lib\Dict;

final abstract class ShipItEnv {
  private static dict<string, string> $extraEnv = dict[];

  public static function addEnv(string $key, string $value): void {
    self::$extraEnv[$key] = $value;
  }

  public static function getEnv(): dict<string, string> {
    /* HH_FIXME[2050] undefined $_ENV */
    if ($_ENV is nonnull) {
      return Dict\merge($_ENV, self::$extraEnv);
    }
    return self::$extraEnv;
  }
}
