<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class UserInfoTestImplementation extends ShipItUserInfo {
  public static async function getDestinationAuthorFromLocalUser(
    string $local_user,
  ): Awaitable<string> {
    $user = await self::getDestinationUserFromLocalUser($local_user);
    return 'Example User <'.$user.'@example.com>';
  }

  public static async function getDestinationUserFromLocalUser(
    string $local_user,
  ): Awaitable<string> {
    return $local_user.'-public';
  }
}

final class UserFiltersTest extends BaseTest {
  public function examplesForGetMentions(
  ): array<(string, ImmSet<string>)> {
    return [
      tuple(
        '@foo',
        ImmSet { '@foo' },
      ),
      tuple(
        '@foo @bar',
        ImmSet { '@foo', '@bar' },
      ),
      tuple(
        '@foo foo@example.com',
        ImmSet { '@foo' },
      ),
      tuple(
        "\n@foo\n",
        ImmSet { '@foo' },
      ),
    ];
  }

  /**
   * @dataProvider examplesForGetMentions
   */
  public function testGetMentions(
    string $message,
    ImmSet<string> $expected,
  ): void {
    $changeset = (new ShipItChangeset())->withMessage($message);
    $this->assertEquals(
      $expected,
      ShipItMentions::getMentions($changeset),
    );
  }

  public function rewriteMentionsExamples(
  ): array<(string, (function(string):string), string)> {
    return [
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
        $mention ==> \substr($mention, 1),
        'foo bar baz',
      ),
    ];
  }

  /**
   * @dataProvider rewriteMentionsExamples
   */
  public function testRewriteMentions(
    string $message,
    (function(string): string) $callback,
    string $expected_message,
  ): void {
    $changeset = (new ShipItChangeset())->withMessage($message);
    $this->assertSame(
      $expected_message,
      ShipItMentions::rewriteMentions($changeset, $callback)->getMessage(),
    );
  }

  public function testContainsMention(): void {
    $changeset = (new ShipItChangeset())->withMessage('@foo @bar');
    $this->assertTrue(
      ShipItMentions::containsMention($changeset, '@foo')
    );
    $this->assertTrue(
      ShipItMentions::containsMention($changeset, '@bar')
    );
    $this->assertFalse(
      ShipItMentions::containsMention($changeset, '@baz')
    );
  }

  public function examplesForSVNUserMapping(
  ): array<(string, string)> {
    $fake_uuid = \str_repeat('a', 36);
    return [
      tuple('Foo <foo@example.com>', 'Foo <foo@example.com>'),
      tuple('foo@'.$fake_uuid, 'Example User <foo-public@example.com>'),
    ];
  }

  /**
   * @dataProvider examplesForSVNUserMapping
   */
  public function testSVNUserMapping(
    string $in,
    string $expected,
  ): void {
    $changeset = (new ShipItChangeset())->withAuthor($in)
      |> ShipItUserFilters::rewriteSVNAuthor(
          $$,
          UserInfoTestImplementation::class,
        );
    $this->assertSame(
      $expected,
      $changeset->getAuthor(),
    );
  }
}
