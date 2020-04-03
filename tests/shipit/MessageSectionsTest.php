<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/h7ixv5su
 */
namespace Facebook\ShipIt;

use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;

<<\Oncalls('open_source')>>
final class MessageSectionsTest extends BaseTest {
  public static function examplesForGetSections(
  ): vec<(string, ?keyset<string>, dict<string, string>)> {
    return vec[
      tuple(
        "Summary: Foo\nFor example: bar",
        keyset['summary'],
        dict[
          'summary' => "Foo\nFor example: bar",
        ],
      ),
      tuple(
        "Summary: Foo\nTest plan: bar",
        keyset['summary', 'test plan'],
        dict[
          'summary' => 'Foo',
          'test plan' => 'bar',
        ],
      ),
      tuple('Foo: bar', null, dict['foo' => 'bar']),
      tuple('Foo: Bar: baz', keyset['foo'], dict['foo' => 'Bar: baz']),
      tuple('Foo: Bar: baz', keyset['bar'], dict['' => 'Foo: Bar: baz']),
      tuple('Foo: Bar: baz', keyset['foo', 'bar'], dict['bar' => 'baz']),
    ];
  }

  <<DataProvider('examplesForGetSections')>>
  public function testGetSections(
    string $message,
    ?keyset<string> $valid,
    dict<string, string> $expected,
  ): void {
    $in = (new ShipItChangeset())->withMessage($message);
    $out = ShipItMessageSections::getSections($in, $valid);
    \expect($out)->toBePHPEqual($expected);
  }

  public static function examplesForBuildMessage(
  ): vec<(dict<string, string>, string)> {
    return vec[
      tuple(dict['foo' => 'bar'], 'Foo: bar'),
      tuple(dict['foo' => "bar\nbaz"], "Foo:\nbar\nbaz"),
      tuple(dict['foo bar' => 'herp derp'], 'Foo Bar: herp derp'),
      tuple(dict['foo' => ''], ''),
      tuple(
        dict['foo' => 'bar', 'herp' => 'derp'],
        "Foo: bar\n\nHerp: derp",
      ),
      tuple(dict['foo' => '', 'herp' => 'derp'], "Herp: derp"),
    ];
  }

  <<DataProvider('examplesForBuildMessage')>>
  public function testBuildMessage(
    dict<string, string> $sections,
    string $expected,
  ): void {
    \expect(ShipItMessageSections::buildMessage($sections))->toEqual($expected);
  }

  public static function getExamplesForWhitespaceEndToEnd(
  ): vec<(string, string)> {
    return vec[
      tuple("Summary: foo", 'Summary: foo'),
      tuple("Summary:\nfoo", 'Summary: foo'),
      tuple("Summary: foo\nbar", "Summary:\nfoo\nbar"),
      tuple("Summary:\nfoo\nbar", "Summary:\nfoo\nbar"),
    ];
  }

  <<DataProvider('getExamplesForWhitespaceEndToEnd')>>
  public function testWhitespaceEndToEnd(string $in, string $expected): void {
    $message = (new ShipItChangeset())
      ->withMessage($in)
      |> ShipItMessageSections::getSections($$, keyset['summary'])
      |> ShipItMessageSections::buildMessage($$);
    \expect($message)->toEqual($expected);
  }
}
