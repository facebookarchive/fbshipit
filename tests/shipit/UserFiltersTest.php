<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/b8165lq5
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str;
use type Facebook\HackTest\DataProvider;// @oss-enable
// @oss-disable: use type DataProvider;

final class UserInfoTestImplementation extends ShipItUserInfo {
  <<__Override>>
  public static async function getDestinationAuthorFromLocalUser(
    string $local_user,
  ): Awaitable<string> {
    $user = await self::getDestinationUserFromLocalUser($local_user);
    return 'Example User <'.$user.'@example.com>';
  }

  <<__Override>>
  public static async function getDestinationUserFromLocalUser(
    string $local_user,
  ): Awaitable<string> {
    return $local_user.'-public';
  }
}

<<\Oncalls('open_source')>>
final class UserFiltersTest extends BaseTest {
  public static function examplesForGetMentions(
  ): vec<(string, keyset<string>)> {
    return vec[
      tuple('@foo', keyset['@foo']),
      tuple('@foo @bar', keyset['@foo', '@bar']),
      tuple('@foo foo@example.com', keyset['@foo']),
      tuple("\n@foo\n", keyset['@foo']),
    ];
  }

  <<DataProvider('examplesForGetMentions')>>
  public function testGetMentions(
    string $message,
    keyset<string> $expected,
  ): void {
    $changeset = (new ShipItChangeset())->withMessage($message);
    \expect(ShipItMentions::getMentions($changeset))->toBePHPEqual($expected);
  }

  public static function rewriteMentionsExamples(
  ): vec<(string, (function(string): string), string)> {
    return vec[
      tuple(
        '@foo @bar @baz',
        $mention ==> $mention === '@foo' ? '@herp' : $mention,
        '@herp @bar @baz',
      ),
      tuple(
        '@foo @bar @baz',
        $mention ==> $mention === '@bar' ? '@herp' : $mention,
        '@foo @herp @baz',
      ),
      tuple(
        '@foo @bar @baz',
        $mention ==> $mention === '@bar' ? '' : $mention,
        '@foo  @baz',
      ),
      tuple(
        '@foo @bar @baz',
        $mention ==> Str\slice($mention, 1),
        'foo bar baz',
      ),
    ];
  }

  <<DataProvider('rewriteMentionsExamples')>>
  public function testRewriteMentions(
    string $message,
    (function(string): string) $callback,
    string $expected_message,
  ): void {
    $changeset = (new ShipItChangeset())->withMessage($message);
    \expect(
      ShipItMentions::rewriteMentions($changeset, $callback)->getMessage(),
    )->toEqual($expected_message);
  }

  public function testContainsMention(): void {
    $changeset = (new ShipItChangeset())->withMessage('@foo @bar');
    \expect(ShipItMentions::containsMention($changeset, '@foo'))->toBeTrue();
    \expect(ShipItMentions::containsMention($changeset, '@bar'))->toBeTrue();
    \expect(ShipItMentions::containsMention($changeset, '@baz'))->toBeFalse();
  }

  public static function examplesForSVNUserMapping(): vec<(string, string)> {
    $fake_uuid = Str\repeat('a', 36);
    return vec[
      tuple('Foo <foo@example.com>', 'Foo <foo@example.com>'),
      tuple('foo@'.$fake_uuid, 'Example User <foo-public@example.com>'),
    ];
  }

  <<DataProvider('examplesForSVNUserMapping')>>
  public function testSVNUserMapping(string $in, string $expected): void {
    $changeset = (new ShipItChangeset())->withAuthor($in)
      |> ShipItUserFilters::rewriteSVNAuthor(
        $$,
        UserInfoTestImplementation::class,
      );
    \expect($changeset->getAuthor())->toEqual($expected);
  }
}
