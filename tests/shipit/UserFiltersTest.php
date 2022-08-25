<?hh
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 * @format
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/b8165lq5
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str; // @oss-enable
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;

final class UserInfoTestImplementation extends ShipItUserInfo {
  <<__Override>>
  public static async function genDestinationAuthorFromLocalUser(
    string $local_user,
  ): Awaitable<string> {
    $user = await self::genDestinationUserFromLocalUser($local_user);
    return 'Example User <'.$user.'@example.com>';
  }

  <<__Override>>
  public static async function genDestinationUserFromLocalUser(
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
    \expect(ShipItMentions::getMentions($changeset))->toEqual($expected);
  }

  public static function rewriteMentionsExamples(
  ): vec<(string, (function(string): Awaitable<string>), string)> {
    return vec[
      tuple(
        '@foo @bar @baz',
        async $mention ==> $mention === '@foo' ? '@herp' : $mention,
        '@herp @bar @baz',
      ),
      tuple(
        '@foo @bar @baz',
        async $mention ==> $mention === '@bar' ? '@herp' : $mention,
        '@foo @herp @baz',
      ),
      tuple(
        '@foo @bar @baz',
        async $mention ==> $mention === '@bar' ? '' : $mention,
        '@foo  @baz',
      ),
      tuple(
        '@foo @bar @baz',
        async $mention ==> Str\slice($mention, 1),
        'foo bar baz',
      ),
    ];
  }

  <<DataProvider('rewriteMentionsExamples')>>
  public async function testRewriteMentions(
    string $message,
    (function(string): Awaitable<string>) $callback,
    string $expected_message,
  ): Awaitable<void> {
    $changeset = (new ShipItChangeset())->withMessage($message);
    \expect(
      (
        await ShipItMentions::genRewriteMentions($changeset, $callback)
      )->getMessage(),
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
  public async function testSVNUserMapping(
    string $in,
    string $expected,
  ): Awaitable<void> {
    $changeset = await (
      (new ShipItChangeset())->withAuthor($in)
      |> ShipItUserFilters::genRewriteSVNAuthor(
        $$,
        UserInfoTestImplementation::class,
      )
    );
    \expect($changeset->getAuthor())->toEqual($expected);
  }
}
