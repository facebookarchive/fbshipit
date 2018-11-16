<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;


final class MessageSectionsTest extends BaseTest {
  public function examplesForGetSections(
  ): array<(string, ?ImmSet<string>, ImmMap<string, string>)> {
    return [
      tuple(
        "Summary: Foo\nFor example: bar",
        ImmSet { 'summary' },
        ImmMap {
          'summary' => "Foo\nFor example: bar",
        },
      ),
      tuple(
        "Summary: Foo\nTest plan: bar",
        ImmSet { 'summary', 'test plan' },
        ImmMap {
          'summary' => 'Foo',
          'test plan' => 'bar',
        },
      ),
      tuple(
        'Foo: bar',
        null,
        ImmMap { 'foo' => 'bar' },
      ),
      tuple(
        'Foo: Bar: baz',
        ImmSet { 'foo' },
        ImmMap { 'foo' => 'Bar: baz' },
      ),
      tuple(
        'Foo: Bar: baz',
        ImmSet { 'bar' },
        ImmMap { '' => 'Foo: Bar: baz' },
      ),
      tuple(
        'Foo: Bar: baz',
        ImmSet { 'foo', 'bar' },
        ImmMap { 'bar' => 'baz' },
      ),
    ];
  }

  /**
   * @dataProvider examplesForGetSections
   */
  public function testGetSections(
    string $message,
    ?ImmSet<string> $valid,
    ImmMap<string, string> $expected,
  ): void {
    $in = (new ShipItChangeset())->withMessage($message);
    $out = ShipItMessageSections::getSections($in, $valid);
    $this->assertEquals($expected, $out->toImmMap());
  }

  public function examplesForBuildMessage(
  ): array<(ImmMap<string, string>, string)> {
    return [
      tuple(
        ImmMap { 'foo' => 'bar' },
        'Foo: bar',
      ),
      tuple(
        ImmMap { 'foo' => "bar\nbaz" },
        "Foo:\nbar\nbaz",
      ),
      tuple(
        ImmMap { 'foo bar' => 'herp derp' },
        'Foo Bar: herp derp',
      ),
      tuple(
        ImmMap { 'foo' => '' },
        '',
      ),
      tuple(
        ImmMap { 'foo' => 'bar', 'herp' => 'derp' },
        "Foo: bar\n\nHerp: derp",
      ),
      tuple(
        ImmMap { 'foo' => '', 'herp' => 'derp' },
        "Herp: derp",
      ),
    ];
  }

  /**
   * @dataProvider examplesForBuildMessage
   */
  public function testBuildMessage(
    ImmMap<string, string> $sections,
    string $expected,
  ): void {
    $this->assertSame(
      $expected,
      ShipItMessageSections::buildMessage($sections),
    );
  }

  public function getExamplesForWhitespaceEndToEnd(
  ): array<(string, string)> {
    return [
      tuple("Summary: foo", 'Summary: foo'),
      tuple("Summary:\nfoo", 'Summary: foo'),
      tuple("Summary: foo\nbar", "Summary:\nfoo\nbar"),
      tuple("Summary:\nfoo\nbar", "Summary:\nfoo\nbar"),
    ];
  }

  /**
   * @dataProvider getExamplesForWhitespaceEndToEnd
   */
  public function testWhitespaceEndToEnd(
    string $in,
    string $expected,
  ): void {
    $message = (new ShipItChangeset())
      ->withMessage($in)
      |> ShipItMessageSections::getSections($$, ImmSet { 'summary' })
      |> ShipItMessageSections::buildMessage($$->toImmMap());
    $this->assertSame(
      $expected,
      $message,
    );
  }
}
