<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/vgcuzvux
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, C};

type ShipItDiff = shape(
  'path' => string,
  'body' => string,
);

type ShipItChangesetData = shape(
  'id' => string,
  'timestamp' => int,
  'author' => string,
  'subject' => string,
  'message' => string,
  'diffs' => vec<ShipItDiff>,
);

/*
 * Repo agnostic representation of a patch/changeset
 */
final class ShipItChangeset {
  private string $id = "";
  private int $timestamp = 0;
  private string $author = "";
  private string $subject = "";
  private string $message = "";
  private vec<ShipItDiff> $diffs = vec[];
  private vec<string> $debugMessages = vec[];
  private bool $isTaggedEmptyCommit = false;
  private string $coAuthorLines = "";

  public function isValid(): bool {
    return !$this->isEmptyChange();
  }
  public function isEmptyChange(): bool {
    return C\is_empty($this->diffs);
  }

  public function getID(): string {
    return $this->id;
  }

  public function getShortID(): string {
    if ($this->getID() === '') {
      return '';
    }
    $short_id = Str\slice($this->getID(), 0, ShipItUtil::SHORT_REV_LENGTH);
    invariant(
      $short_id is string,
      'got %s, expected string',
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \gettype($short_id),
    );
    return $short_id;
  }

  public function withID(string $id): ShipItChangeset {
    $out = clone $this;
    $out->id = $id;
    return $out;
  }

  public function getTimestamp(): int {
    return $this->timestamp;
  }

  public function withTimestamp(int $timestamp): ShipItChangeset {
    $out = clone $this;
    $out->timestamp = $timestamp;
    return $out;
  }

  public function getAuthor(): string {
    return $this->author;
  }

  public function withAuthor(string $author): ShipItChangeset {
    $out = clone $this;
    $out->author = $author;
    return $out;
  }

  public function getSubject(): string {
    return $this->subject;
  }

  public function withSubject(string $subject): ShipItChangeset {
    $out = clone $this;
    $out->subject = $subject;
    return $out;
  }

  public function getMessage(): string {
    return $this->message;
  }

  public function withMessage(string $message): ShipItChangeset {
    $out = clone $this;
    $out->message = $message;
    return $out;
  }

  public function getDiffs(): vec<ShipItDiff> {
    return $this->diffs;
  }

  public function withDiffs(vec<ShipItDiff> $diffs): ShipItChangeset {
    $out = clone $this;
    $out->diffs = $diffs;
    return $out;
  }

  public function getDebugMessages(): vec<string> {
    return $this->debugMessages;
  }

  public function withDebugMessage(
    Str\SprintfFormatString $format_string,
    mixed ...$args
  ): ShipItChangeset {
    $messages = $this->getDebugMessages();
    /* HH_FIXME[4027]: cannot be a literal string */
    $messages[] = Str\format($format_string, ...$args);

    $out = clone $this;
    $out->debugMessages = $messages;
    return $out;
  }

  public function getIsTaggedEmptyCommit(): bool {
    return $this->isTaggedEmptyCommit;
  }

  public function withIsTaggedEmptyCommit(
    bool $is_tagged_empty_commit,
  ): ShipItChangeset {
    $out = clone $this;
    $out->isTaggedEmptyCommit = $is_tagged_empty_commit;
    return $out;
  }

  public function getCoAuthorLines(): string {
    return $this->coAuthorLines;
  }

  public function withCoAuthorLines(string $co_author_lines): ShipItChangeset {
    /* @lint-ignore HackLint5562 */
    $out = clone $this;
    $out->coAuthorLines = $co_author_lines;
    return $out;
  }

  public function dumpDebugMessages(): void {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \printf(
      "  DEBUG %s %s\n    Full ID: %s\n",
      $this->getShortID(),
      $this->getSubject(),
      $this->getID(),
    );
    foreach ($this->getDebugMessages() as $message) {
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \printf("    %s\n", $message);
    }
  }

  public function toData(): ShipItChangesetData {
    return shape(
      'id' => $this->getID(),
      'timestamp' => $this->getTimestamp(),
      'author' => $this->getAuthor(),
      'subject' => $this->getSubject(),
      'message' => $this->getMessage(),
      'diffs' => vec($this->getDiffs()),
    );
  }

  public static function fromData(ShipItChangesetData $shape): ShipItChangeset {
    return (new ShipItChangeset())
      ->withID($shape['id'])
      ->withTimestamp($shape['timestamp'])
      ->withAuthor($shape['author'])
      ->withSubject($shape['subject'])
      ->withMessage($shape['message'])
      ->withDiffs($shape['diffs']);
  }
}
