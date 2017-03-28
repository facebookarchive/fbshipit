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

type ShipItDiff = shape(
  'path' => string,
  'body' => string,
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
  private ImmVector<ShipItDiff> $diffs = ImmVector { };
  private ImmVector<string> $debugMessages = ImmVector { };

  public function isValid(): bool {
    return $this->diffs->count() > 0;
  }

  public function getID(): string {
    return $this->id;
  }

  public function getShortID(): string {
    if ($this->getID() === '') {
      return '';
    }
    $short_id = substr($this->getID(), 0, ShipItUtil::SHORT_REV_LENGTH);
    invariant(
      is_string($short_id),
      'got %s, expected string',
      gettype($short_id),
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

  public function getDiffs(): ImmVector<ShipItDiff> {
    return $this->diffs;
  }

  public function withDiffs(ImmVector<ShipItDiff> $diffs): ShipItChangeset {
    $out = clone $this;
    $out->diffs = $diffs;
    return $out;
  }

  public function getDebugMessages(): ImmVector<string> {
    return $this->debugMessages;
  }

  public function withDebugMessage(
    \HH\FormatString<\PlainSprintf> $format_string,
    mixed ...$args
  ): ShipItChangeset {
    $messages = $this->getDebugMessages()->toVector();
    /* HH_FIXME[4027]: cannot be a literal string */
    $messages[] = sprintf($format_string, ...$args);

    $out = clone $this;
    $out->debugMessages = $messages->toImmVector();
    return $out;
  }

  public function dumpDebugMessages(): void {
    printf(
      "  DEBUG %s %s\n    Full ID: %s\n",
      $this->getShortID(),
      $this->getSubject(),
      $this->getID(),
    );
    foreach ($this->getDebugMessages() as $message) {
      printf("    %s\n", $message);
    }
  }
}
