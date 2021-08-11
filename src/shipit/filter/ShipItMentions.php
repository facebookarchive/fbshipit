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

use namespace HH\Lib\{C, Dict, Keyset, Regex, Str}; // @oss-enable

abstract final class ShipItMentions {
  public static async function genRewriteMentions(
    ShipItChangeset $changeset,
    (function(string): Awaitable<string>) $callback,
  ): Awaitable<ShipItChangeset> {
    // Ignore things like email addresses, let them pass cleanly through
    $pattern = re'/(?<![a-zA-Z0-9\.=\+-])(@:?[a-zA-Z0-9-]+)/';

    $mentions = await (
      Regex\every_match($changeset->getMessage(), $pattern)
      |> Dict\from_values($$, $match ==> $match[1])
      |> Dict\map_async($$, async $match ==> await $callback($match[1]))
    );

    $message = Regex\replace_with(
      $changeset->getMessage(),
      $pattern,
      $matches ==> $mentions[$matches[1]],
    );

    return $changeset->withMessage(Str\trim($message));
  }

  /** Turn '@foo' into 'foo.
   *
   * Handy for github, otherwise everyone gets notified whenever a fork
   * rebases.
   */
  public static async function genRewriteMentionsWithoutAt(
    ShipItChangeset $changeset,
    keyset<string> $exceptions = keyset[],
  ): Awaitable<ShipItChangeset> {
    return await self::genRewriteMentions(
      $changeset,
      async $it ==> (
        C\contains($exceptions, $it) || Str\slice($it, 0, 1) !== '@'
      )
        ? $it
        : Str\slice($it, 1),
    );
  }

  public static function getMentions(
    ShipItChangeset $changeset,
  ): keyset<string> {
    // Ignore things like email addresses, let them pass cleanly through
    $pattern = re'/(?<![a-zA-Z0-9\.=\+-])(@:?[a-zA-Z0-9-]+)/';

    return Regex\every_match($changeset->getMessage(), $pattern)
      |> Keyset\map($$, ($match) ==> $match[1]);
  }

  public static function containsMention(
    ShipItChangeset $changeset,
    string $mention,
  ): bool {
    return C\contains(self::getMentions($changeset), $mention);
  }
}
