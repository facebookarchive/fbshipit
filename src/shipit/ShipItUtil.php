<?hh // strict
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

type ShipItAffectedFile = string;
type ShipItDiffAsString = string;

abstract class ShipItUtil {
  const SHORT_REV_LENGTH = 7;
  // flags for shellExec, no flag equal to 1
  // for compatibility with ShipItRepo verbose flags
  const DONT_VERBOSE = 0;
  const VERBOSE_SHELL = 2;
  const VERBOSE_SHELL_INPUT = 4;
  const VERBOSE_SHELL_OUTPUT = 8;
  const NO_THROW = 16;
  const RETURN_STDERR = 32;

  /*
   * Generator yielding patch sections of the diff blocks (individually)
   * and finally the footer.
   */
  public static function parsePatch(string $patch): Iterator<string> {
    $contents = '';
    $matches = [];

    $minus_lines = 0;
    $plus_lines = 0;
    $seen_range_header = false;

    foreach (\explode("\n", $patch) as $line) {
      $line = \preg_replace('/(\r\n|\n)/', "\n", $line);

      if (\preg_match('@^diff --git [ab]/(.*?) [ab]/\1$@', \rtrim($line))) {
        if ($contents !== '') {
          yield $contents;
        }
        $seen_range_header = false;
        $contents = $line."\n";
        continue;
      }
      if (
        \preg_match(
          '/^@@ -\d+(,(?<minus_lines>\d+))? \+\d+(,(?<plus_lines>\d+))? @@/',
          $line,
          &$matches,
        )
      ) {
        $minus_lines = $matches['minus_lines'] ?? '';
        $minus_lines = $minus_lines === '' ? 1 : (int) $minus_lines;
        $plus_lines = $matches['plus_lines'] ?? '';
        $plus_lines = $plus_lines === '' ? 1 : (int) $plus_lines;

        $contents .= $line."\n";
        $seen_range_header = true;
        continue;
      }

      if (!$seen_range_header) {
        $contents .= $line ."\n";
        continue;
      }

      $leftmost = \substr($line, 0, 1);
      if ($leftmost === "\\") {
        $contents .= $line."\n";
        // Doesn't count as a + or - line whatever happens; if NL at EOF
        // changes, there is a + and - for the last line of content
        continue;
      }

      if ($minus_lines <= 0 && $plus_lines <= 0) {
        continue;
      }

      $leftmost = \substr($line, 0, 1);
      if ($leftmost === '+') {
        --$plus_lines;
      } else if ($leftmost === '-') {
        --$minus_lines;
      } else if ($leftmost === ' ') {
        // Context goes from both.
        --$plus_lines;
        --$minus_lines;
      } else {
        invariant_violation(
          "Can't parse hunk line: %s",
          $line,
        );
      }
      $contents .= $line."\n";
    }

    if ($contents !== '') {
      // If we got the patch from git-diff, there won't be the signature line
      // from format-patch
      yield $contents;
    }
  }

  public static function isNewFile(string $body): bool {
    return (bool) \preg_match('@^new file@m', $body);
  }

  public static function isFileRemoval(string $body): bool {
    return (bool) \preg_match('@^deleted file@m', $body);
  }

  // 0 is runtime log rate - typechecker is sufficient.
  <<__Deprecated('Use ShipItShellCommand instead in new code', 0)>>
  public static function shellExec(
    string $path,
    ?string $stdin,
    int $flags,
    string ...$args
  ): string {
    $command = new ShipItShellCommand($path, ...$args);

    if ($flags & self::VERBOSE_SHELL) {
      $cmd = \implode(' ', $args);
      \fwrite(\STDERR, "\$ ".$cmd."\n");
    }


    if ($stdin !== null) {
      if ($flags & self::VERBOSE_SHELL_INPUT) {
        \fwrite(\STDERR, "--STDIN--\n".$stdin."\n");
      }
      $command->setStdIn($stdin);
    }

    if ($flags & self::VERBOSE_SHELL_OUTPUT) {
      $command->setOutputToScreen();
    }

    if ($flags && self::NO_THROW) {
      $command->setNoExceptions();
    }

    $result = $command->runSynchronously();

    $output = $result->getStdOut();
    if ($flags & self::RETURN_STDERR) {
      $output .= "\n".$result->getStdErr();
    }
    return $output;
  }

  public static function matchesAnyPattern(
    string $path,
    ImmVector<string> $patterns,
  ): ?string {
    foreach ($patterns as $pattern) {
      if (\preg_match($pattern, $path)) {
        return $pattern;
      }
    }
    return null;
  }
}
