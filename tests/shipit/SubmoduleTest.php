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


final class SubmoduleTest extends BaseTest {
  public function testSubmoduleCommitFile(): void {
    $changeset = ShipItRepoHG::getChangesetFromExportedPatch(
      file_get_contents(
        __DIR__.'/hg-diffs/submodule-hhvm-third-party.header',
      ),
      file_get_contents(
        __DIR__.'/hg-diffs/submodule-hhvm-third-party.patch',
      ),
    );
    $this->assertNotNull($changeset);
    assert($changeset !== null); // for typechecker
    $this->assertTrue($changeset->isValid());

    $changeset = ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
      $changeset,
      'fbcode/hphp/facebook/third-party-rev.txt',
      'third-party',
    );

    $this->assertSame(1, $changeset->getDiffs()->keys()->count());
    $change = ($changeset->getDiffs()->filter(
      $diff ==> $diff['path'] === 'third-party'
    )->firstValue() ?? [])['body'];
    $this->assertNotEmpty($change);

    $this->assertContains('--- a/third-party', $change);
    $this->assertContains('+++ b/third-party', $change);

    $old_pos = strpos($change, '6d9dffd0233c53bb83e4daf5475067073df9cdca');
    $new_pos = strpos($change, 'ae031dcc9594163f5b0c35e7026563f1c8372595');

    $this->assertNotFalse($old_pos);
    $this->assertNotFalse($new_pos);
    $this->assertGreaterThan($old_pos, $new_pos);
  }
}
