<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/cgcrhd9r
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C, Regex, Str}; // @oss-enable

abstract final class ShipItUserFilters {
  /** Rewrite authors that match a certain pattern.
   *
   * @param $pattern a regular expression defining a 'user' named capture
   */
  public static async function genRewriteAuthorWithUserPattern(
    ShipItChangeset $changeset,
    classname<ShipItUserInfo> $user_info,
    Regex\Pattern<shape('user' => string, ...)> $pattern,
  ): Awaitable<ShipItChangeset> {
    $matches = Regex\first_match($changeset->getAuthor(), $pattern);
    if ($matches is nonnull) {
      $author = await $user_info::genDestinationAuthorFromLocalUser(
        $matches['user'],
      );
      if ($author !== null) {
        return $changeset->withAuthor($author);
      }
    }
    return $changeset;
  }

  /** Rewrite author fields created by git-svn or HgSubversion.
   *
   * Original author: foobar@uuid
   * New author: Foo Bar <foobar@example.com>
   */
  public static async function genRewriteSVNAuthor(
    ShipItChangeset $changeset,
    classname<ShipItUserInfo> $user_info,
  ): Awaitable<ShipItChangeset> {
    return await self::genRewriteAuthorWithUserPattern(
      $changeset,
      $user_info,
      re'/^(?<user>.*)@[a-f0-9-]{36}$/',
    );
  }

  public static async function genRewriteMentions(
    ShipItChangeset $changeset,
    classname<ShipItUserInfo> $user_info,
  ): Awaitable<ShipItChangeset> {
    return await ShipItMentions::genRewriteMentions(
      $changeset,
      async function(string $mention): Awaitable<string> use ($user_info) {
        $mention = Str\slice($mention, 1); // chop off leading @
        $new = await $user_info::genDestinationUserFromLocalUser($mention);
        return '@'.($new ?? $mention);
      },
    );
  }

  /** Replace the author with a specially-formatted part of the commit
   * message.
   *
   * Useful for dealing with pull requests if there are restrictions on who
   * is a valid author for your internal repository.
   *
   * @param $pattern regexp pattern defining an 'author' capture group
   */
  public static function rewriteAuthorFromMessagePattern(
    ShipItChangeset $changeset,
    Regex\Pattern<shape('author' => string, ...)> $pattern,
  ): ShipItChangeset {
    $matches = Regex\first_match($changeset->getMessage(), $pattern);
    if ($matches is nonnull) {
      return $changeset->withAuthor($matches['author']);
    }
    return $changeset;
  }

  /** Convenience wrapper for the above for 'GitHub Author: ' lines */
  public static function rewriteAuthorFromGitHubAuthorLine(
    ShipItChangeset $changeset,
  ): ShipItChangeset {
    return self::rewriteAuthorFromMessagePattern(
      $changeset,
      re'/(^|\n)GitHub Author:\s*(?<author>.*?)(\n|$)/si',
    );
  }
}
