<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

/** Utility class for commit messages with sections preceded by "Header: ".
 *
 * For example, Phabricator creates messages like:
 * Summary:
 *   Foo bar
 * Test Plan:
 *   Baz
 */
final class ShipItMessageSections {
  /** Get a Map { $header => $content} of sections.
   *
   * @param $valid_sections what sections are real sections; if specified, and
   *   something that looks like a section header is seen that isn't in this
   *   list, it will be considered content. If unspecified, every line like
   *   /^[a-zA-Z ]+:/ will be considered a header. All headers should be
   *   lowercase.
   */
  public static function getSections(
    ShipItChangeset $changeset,
    ?ImmSet<string> $valid_sections = null,
  ): Map<string, string> {
    $sections = Map { '' => '' };
    $newpara = true;
    $section = '';
    foreach(\explode("\n", $changeset->getMessage()) as $line) {
      $line = \rtrim($line);
      if (\preg_match('/^[a-zA-Z ]+:/', $line)) {
        $h = \strtolower(\substr($line, 0, \strpos($line, ':')));
        if ($valid_sections === null || $valid_sections->contains($h)) {
          $section = $h;
          $value = \trim(\substr($line, \strlen($section) + 1));

          // Treat "Summary: FBOnly: bar" as "FBOnly: bar" - handy if using
          // Phabricator
          if (
            \preg_match('/^[a-zA-Z ]+:/', $value)
            && $valid_sections !== null
          ) {
            $h = \strtolower(\substr($value, 0, \strpos($value, ':')));
            if ($valid_sections->contains($h)) {
              $section = $h;
              $value = \trim(\substr($value, \strlen($section) + 1));
            }
          }
          $sections[$section] = $value;
          continue;
        }
      }
      $sections[$section] .= "\n{$line}";
      $newpara = ($line === '');
    }
    if ($sections[""] === '') {
      $sections->removeKey('');
    }

    return $sections->map($x ==> \trim($x));
  }

  /** Convert a section map back to a commit message */
  public static function buildMessage(
    ImmMap<string, string> $sections,
  ): string {
    $out = '';
    foreach ($sections as $section => $text) {
      if (\ctype_space($text) || \strlen($text) === 0) {
        continue;
      }
      $section_head = \ucwords($section).":";
      $text = \trim($text);
      if (!self::hasMoreThanOneNonEmptyLine($text)) {
        $section_head .= ' ';
      } else {
        $section_head .= "\n";
      }
      $out .= $section_head."$text\n\n";
    }
    return \rtrim($out);
  }

  private static function hasMoreThanOneNonEmptyLine(string $str): bool {
    $lines = \explode("\n", $str);
    $cn = 0;
    foreach ($lines as $line) {
      if (!(\ctype_space($line) || \strlen($line) === 0)) {
        ++$cn;
      }
    }
    return $cn > 1;
  }
}
