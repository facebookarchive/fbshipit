<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/mxrtk0pl
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, C, Regex};

class ShipItRepoException extends \Exception {
  public function __construct(?ShipItRepo $repo, string $message) {
    if ($repo !== null) {
      $message = \get_class($repo).": $message";
    }
    parent::__construct($message);
  }
}

/**
 * Repo handler interface
 * For agnostic communication with git, hg, etc...
 */
abstract class ShipItRepo {
  /**
   * @param $path the path to the repository
   */
  public function __construct(
    private IShipItLock $lock,
    protected string $path,
    string $branch,
  ) {
    $this->setBranch($branch);
  }

  /**
   * Get the ShipItChangeset of the HEAD revision in the current branch.
   */
  public abstract function getHeadChangeset(): ?ShipItChangeset;

  protected function getSharedLock(): IShipItLock {
    return $this->lock;
  }

  const VERBOSE_FETCH = 1;
  const VERBOSE_SHELL = 2;
  const VERBOSE_SHELL_OUTPUT = 4;
  const VERBOSE_SHELL_INPUT = 8;

  // Level of verbosity for -v option
  const VERBOSE_STANDARD = 3;

  static public int $verbose = 0;

  const TYPE_GIT = 'git';
  const TYPE_HG = 'hg';

  public function getPath(): string {
    return $this->path;
  }

  /**
   * Implement to allow changing branches
   */
  protected abstract function setBranch(string $branch): bool;

  public abstract function updateBranchTo(string $base_rev): void;

  /**
   * Cleans our checkout.
   */
  public abstract function clean(): void;

  /**
   * Updates our checkout
   */
  public abstract function pull(): void;

  /**
   * push lfs support
   */
  public abstract function pushLfs(
    string $lfs_pull_endpoint,
    string $lfs_push_endpoint,
  ): void;

  /**
   * Get the origin of the checkout.
   */
  public abstract function getOrigin(): string;

  public static function typedOpen<Trepo as ShipItRepo>(
    classname<Trepo> $interface,
    IShipItLock $lock,
    string $path,
    string $branch,
  ): Trepo {
    $repo = ShipItRepo::open($lock, $path, $branch);
    invariant(
      \is_a($repo, $interface),
      '%s is a %s, needed a %s',
      $path,
      \get_class($repo),
      $interface,
    );
    /* HH_FIXME[4110] */
    return $repo;
  }

  /**
   * Factory
   */
  public static function open(
    IShipItLock $lock,
    string $path,
    string $branch,
  ): ShipItRepo {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    if (\file_exists($path.'/.git')) {
      return new ShipItRepoGIT($lock, $path, $branch);
    }
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    if (\file_exists($path.'/.hg')) {
      return new ShipItRepoHG($lock, $path, $branch);
    }
    throw new ShipItRepoException(
      null,
      "Can't determine type of repo at ".$path,
    );
  }

  /**
   * Convert a hunk to a ShipItDiff shape
   */
  public static function parseDiffHunk(string $hunk): ?ShipItDiff {
    list($header, $body) = Str\split($hunk, "\n", 2);
    $matches = varray[];
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \preg_match_with_matches(
      '@^diff --git ("?)[ab]/(.*?)"? "?[ab]/(.*?)"?$@',
      Str\trim($header),
      inout $matches,
    );
    if (C\is_empty($matches)) {
      return null;
    }
    $path = $matches[2];
    $new_path = C\count($matches) > 3 ? $matches[3] : null;
    if ($new_path !== null && $path !== $new_path) {
      $operation = ShipItDiffOperation::RENAME;
    } else {
      $operation = ShipItDiffOperation::CHANGE;
    }
    if ($matches[1] === '"') {
      // Quoted paths may contain escaped characters.
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      $path = \stripslashes($path);
    }
    return shape(
      'path' => $path,
      'body' => $body,
      'operation' => $operation,
      'new_path' => $new_path,
    );
  }

  public abstract static function getDiffsFromPatch(
    string $patch,
  ): vec<ShipItDiff>;

  final public static function getCommitMessage(
    ShipItChangeset $changeset,
  ): string {
    return $changeset->getSubject()."\n\n".$changeset->getMessage();
  }

  /*
   * Generator yielding patch sections of the diff blocks (individually)
   * and finally the footer.
   */
  public static function parsePatch(string $patch): Iterator<string> {
    $contents = '';
    $matches = darray[];

    $minus_lines = 0;
    $plus_lines = 0;
    $seen_range_header = false;

    foreach (Str\split($patch, "\n") as $line) {
      $line = Regex\replace($line, re"/(\r\n|\n)/", "\n");

      if (
        Regex\matches(
          Str\trim_right($line),
          re"@^diff --git \"?[ab]/(.*?)\"? \"?[ab]/(.*?)\"?$@",
        )
      ) {
        if ($contents !== '') {
          yield $contents;
        }
        $seen_range_header = false;
        $contents = $line."\n";
        continue;
      }
      $matches = Regex\first_match(
        $line,
        re"/^@@ -\d+(,(?<minus_lines>\d+))? \+\d+(,(?<plus_lines>\d+))? @@/",
      );
      if ($matches !== null) {
        $minus_lines = $matches['minus_lines'] ?? '';
        $minus_lines = $minus_lines === '' ? 1 : (int)$minus_lines;
        $plus_lines = $matches['plus_lines'] ?? '';
        $plus_lines = $plus_lines === '' ? 1 : (int)$plus_lines;

        $contents .= $line."\n";
        $seen_range_header = true;
        continue;
      }

      if (!$seen_range_header) {
        $contents .= $line."\n";
        continue;
      }

      $leftmost = Str\slice($line, 0, 1);
      if ($leftmost === "\\") {
        $contents .= $line."\n";
        // Doesn't count as a + or - line whatever happens; if NL at EOF
        // changes, there is a + and - for the last line of content
        continue;
      }

      if ($minus_lines <= 0 && $plus_lines <= 0) {
        continue;
      }

      $leftmost = Str\slice($line, 0, 1);
      if ($leftmost === '+') {
        --$plus_lines;
      } else if ($leftmost === '-') {
        --$minus_lines;
      } else if ($leftmost === ' ') {
        // Context goes from both.
        --$plus_lines;
        --$minus_lines;
      } else {
        invariant_violation("Can't parse hunk line: %s", $line);
      }
      $contents .= $line."\n";
    }

    if ($contents !== '') {
      // If we got the patch from git-diff, there won't be the signature line
      // from format-patch
      yield $contents;
    }
  }
}
