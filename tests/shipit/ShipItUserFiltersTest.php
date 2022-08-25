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
 * https://fburl.com/nni9suge
 */
namespace Facebook\ShipIt;


<<\Oncalls('open_source')>>
final class ShipItUserFiltersTest extends BaseTest {
  public function testRewriteAuthorFromGitHubAuthorLine(): void {
    $changeset = (new ShipItChangeset())
      ->withAuthor('original author')
      ->withMessage(
        "Summary: text\nGitHub Author: github author\nTest Plan: none",
      );
    $changeset =
      ShipItUserFilters::rewriteAuthorFromGitHubAuthorLine($changeset);
    \expect($changeset->getAuthor())->toEqual('github author');
  }

  public function testRewriteAuthorFromGitHubAuthorLineNoMatch(): void {
    $changeset = (new ShipItChangeset())
      ->withAuthor('original author')
      ->withMessage(
        "Summary: text\nGitHup Author: github author\nTest Plan: none",
      );
    $changeset =
      ShipItUserFilters::rewriteAuthorFromGitHubAuthorLine($changeset);
    \expect($changeset->getAuthor())->toEqual('original author');
  }

  public function testRewriteAuthorFromGitHubAuthorLineMultiline(): void {
    $changeset = (new ShipItChangeset())
      ->withAuthor('original author')
      ->withMessage(
        "Summary:\ntext\nGitHub Author:\ngithub author\nTest Plan:\nnone",
      );
    $changeset =
      ShipItUserFilters::rewriteAuthorFromGitHubAuthorLine($changeset);
    \expect($changeset->getAuthor())->toEqual('github author');
  }
}
