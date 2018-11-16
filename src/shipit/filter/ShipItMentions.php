<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItMentions {
  // Ignore things like email addresses, let them pass cleanly through
  const string MENTIONS_PATTERN =
    '/(?<![a-zA-Z0-9\.=\+-])(@:?[a-zA-Z0-9-]+)/';

  public static function rewriteMentions(
    ShipItChangeset $changeset,
    (function(string):string) $callback,
  ): ShipItChangeset {
    $message = \preg_replace_callback(
      self::MENTIONS_PATTERN,
      $matches ==> $callback($matches[1]),
      $changeset->getMessage(),
    );

    return $changeset->withMessage(\trim($message));
  }

  /** Turn '@foo' into 'foo.
   *
   * Handy for github, otherwise everyone gets notified whenever a fork
   * rebases.
   */
  public static function rewriteMentionsWithoutAt(
    ShipItChangeset $changeset,
    ImmSet<string> $exceptions = ImmSet { },
  ): ShipItChangeset {
    return self::rewriteMentions(
      $changeset,
      $it ==> ($exceptions->contains($it) || \substr($it, 0, 1) !== '@')
        ? $it
        : \substr($it, 1),
    );
  }

  public static function getMentions(
    ShipItChangeset $changeset,
  ): ImmSet<string> {
    $matches = [];
    \preg_match_all(
      self::MENTIONS_PATTERN,
      $changeset->getMessage(),
      &$matches,
      \PREG_SET_ORDER,
    );
    return (new ImmSet(\array_map($match ==> $match[1], $matches)));
  }

  public static function containsMention(
    ShipItChangeset $changeset,
    string $mention,
  ): bool {
    return self::getMentions($changeset)->contains($mention);
  }
}
