<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/vkyyb5iv
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C, Keyset, Str}; // @oss-enable

final class ShipItMentions {
  // Ignore things like email addresses, let them pass cleanly through
  const string MENTIONS_PATTERN = '/(?<![a-zA-Z0-9\.=\+-])(@:?[a-zA-Z0-9-]+)/';

  public static function rewriteMentions(
    ShipItChangeset $changeset,
    (function(string): string) $callback,
  ): ShipItChangeset {
    $message = PHP\preg_replace_callback(
      self::MENTIONS_PATTERN,
      $matches ==> $callback($matches[1]),
      $changeset->getMessage(),
      -1,
    );

    return $changeset->withMessage(Str\trim($message));
  }

  /** Turn '@foo' into 'foo.
   *
   * Handy for github, otherwise everyone gets notified whenever a fork
   * rebases.
   */
  public static function rewriteMentionsWithoutAt(
    ShipItChangeset $changeset,
    keyset<string> $exceptions = keyset[],
  ): ShipItChangeset {
    return self::rewriteMentions(
      $changeset,
      $it ==> (C\contains($exceptions, $it) || Str\slice($it, 0, 1) !== '@')
        ? $it
        : Str\slice($it, 1),
    );
  }

  public static function getMentions(
    ShipItChangeset $changeset,
  ): keyset<string> {
    $matches = vec[];
    PHP\preg_match_all(
      self::MENTIONS_PATTERN,
      $changeset->getMessage(),
      inout $matches,
      \PREG_SET_ORDER,
    );
    /* HH_FIXME[4110] */
    return Keyset\map($matches as Container<_>, (KeyedContainer<int, string> $match) ==> $match[1]);
  }

  public static function containsMention(
    ShipItChangeset $changeset,
    string $mention,
  ): bool {
    return C\contains(self::getMentions($changeset), $mention);
  }
}
