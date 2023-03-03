<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/q80cg5rd
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, C, Vec}; // @oss-enable

<<\Oncalls('open_source')>>
final class SubmoduleTest extends ShellTest {
  public function testSubmoduleCommitFile(): void {
    $changeset = ShipItRepoHG::getChangesetFromExportedPatch(
      \file_get_contents(__DIR__.'/hg-diffs/submodule-hhvm-third-party.header'),
      \file_get_contents(__DIR__.'/hg-diffs/submodule-hhvm-third-party.patch'),
    );
    $changeset = \expect($changeset)->toNotBeNull();
    \expect($changeset->isValid())->toBeTrue();

    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'fbcode/hphp/facebook/third-party-rev.txt',
      'third-party',
    );

    \expect($changeset->getDiffs() |> Vec\keys($$) |> C\count($$))->toEqual(1);
    $diff = $changeset->getDiffs()
      |> Vec\filter($$, $diff ==> $diff['path'] === 'third-party')
      |> C\nfirst($$);
    $diff = \expect($diff)->toNotBeNull();
    $change = $diff['body'];
    \expect($change)->toNotBePHPEqual('');
    \expect($change)->toContainSubstring('--- a/third-party');
    \expect($change)->toContainSubstring('+++ b/third-party');

    $old_pos = Str\search($change, '6d9dffd0233c53bb83e4daf5475067073df9cdca');
    $new_pos = Str\search($change, 'ae031dcc9594163f5b0c35e7026563f1c8372595');

    \expect($old_pos)->toEqual(6);
    \expect($new_pos)->toEqual(48);
  }
}
