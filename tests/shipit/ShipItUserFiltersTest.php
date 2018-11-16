<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItUserFiltersTest extends BaseTest {
  public function testRewriteAuthorFromGitHubAuthorLine(): void {
    $changeset = (new ShipItChangeset())
      ->withAuthor('original author')
      ->withMessage(
        "Summary: text\nGitHub Author: github author\nTest Plan: none",
      );
    $changeset =
      ShipItUserFilters::rewriteAuthorFromGitHubAuthorLine($changeset);
    $this->assertEquals('github author', $changeset->getAuthor());
  }

  public function testRewriteAuthorFromGitHubAuthorLineNoMatch(): void {
    $changeset = (new ShipItChangeset())
      ->withAuthor('original author')
      ->withMessage(
        "Summary: text\nGitHup Author: github author\nTest Plan: none",
      );
    $changeset =
      ShipItUserFilters::rewriteAuthorFromGitHubAuthorLine($changeset);
    $this->assertEquals('original author', $changeset->getAuthor());
  }

  public function testRewriteAuthorFromGitHubAuthorLineMultiline(): void {
    $changeset = (new ShipItChangeset())
      ->withAuthor('original author')
      ->withMessage(
        "Summary:\ntext\nGitHub Author:\ngithub author\nTest Plan:\nnone",
      );
    $changeset =
      ShipItUserFilters::rewriteAuthorFromGitHubAuthorLine($changeset);
    $this->assertEquals('github author', $changeset->getAuthor());
  }
}
