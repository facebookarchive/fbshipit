<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

class ShipItUserFilters {
  /** Rewrite authors that match a certain pattern.
   *
   * @param $pattern a regular expression defining a 'user' named capture
   */
  public static function rewriteAuthorWithUserPattern(
    ShipItChangeset $changeset,
    classname<ShipItUserInfo> $user_info,
    string $pattern,
  ): ShipItChangeset {
    $matches = [];
    if (
      \preg_match(
        $pattern,
        $changeset->getAuthor(),
        &$matches,
      )
      && \array_key_exists('user', $matches)
    ) {
      $author = \HH\Asio\join(
        $user_info::getDestinationAuthorFromLocalUser(
          $matches['user']
        )
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
  public static function rewriteSVNAuthor(
    ShipItChangeset $changeset,
    classname<ShipItUserInfo> $user_info,
  ): ShipItChangeset {
    return self::rewriteAuthorWithUserPattern(
      $changeset,
      $user_info,
      '/^(?<user>.*)@[a-f0-9-]{36}$/',
    );
  }

  public static function rewriteMentions(
    ShipItChangeset $changeset,
    classname<ShipItUserInfo> $user_info,
  ): ShipItChangeset {
    return ShipItMentions::rewriteMentions(
      $changeset,
      function(string $mention): string use ($user_info) {
        $mention = \substr($mention, 1); // chop off leading @
        $new = \HH\Asio\join(
          $user_info::getDestinationUserFromLocalUser($mention)
        );
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
    string $pattern,
  ): ShipItChangeset {
    $matches = [];
    if (\preg_match($pattern, $changeset->getMessage(), &$matches)) {
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
      '/(^|\n)GitHub Author:\s*(?<author>.*?)(\n|$)/si',
    );
  }
}
