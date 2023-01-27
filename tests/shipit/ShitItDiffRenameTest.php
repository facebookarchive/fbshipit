<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

use namespace HH\Lib\C; // @oss-enable

<<\Oncalls('open_source')>>
final class ShitItDiffRenameTest extends ShellTest {
  public async function testShipItDiffRenamePathParse(): Awaitable<void> {
    $diff = ShipItRepoGIT::parseDiffHunk(
      "diff --git a/a.txt b/a_rename.txt \n similarity index 100% \n rename from a.txt \n rename to a_rename.txt",
    );
    \expect($diff)->toNotBeNull();
    \expect($diff as nonnull['path'])->toEqual('a.txt');
    \expect(Shapes::idx($diff, 'new_path'))->toNotBeNull();
    \expect(Shapes::idx($diff, 'new_path') as nonnull)->toEqual('a_rename.txt');
  }

  public async function testShipItDiffRenamePathMapping(): Awaitable<void> {
    $changeset = (new ShipItChangeset())
      ->withDiffs(
        vec[shape(
          'path' => 'monica-test.md',
          'body' => 'junk',
          'operation' => ShipItDiffOperation::RENAME,
          'new_path' => 'edward-test.md',
        )],
      );
    $map = dict[
      "cpp/" => "fbcode/opensource/phabtest_fbsource/cpp/",
      ".github/workflows/TagIt.yml" =>
        "fbcode/opensource/github_actions/tagit/TagIt.yml",
      "" => "fbcode/opensource/phabtest_fbsource/",
    ];
    $changeset = ShipItPathFilters::moveDirectories($changeset, $map);
    \expect(C\count($changeset->getDiffs()))->toEqual(1);
    $diff = $changeset->getDiffs()[0];
    \expect($diff['path'])->toEqual(
      'fbcode/opensource/phabtest_fbsource/monica-test.md',
    );
    \expect(Shapes::idx($diff, 'new_path'))->toNotBeNull();
    \expect(Shapes::idx($diff, 'new_path') as nonnull)->toEqual(
      'fbcode/opensource/phabtest_fbsource/edward-test.md',
    );
  }
}
