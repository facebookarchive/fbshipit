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

final class RenameFileTest extends BaseTest {
  /**
   * We need separate 'delete file', 'create file' diffs for renames, in case
   * one side is filtered out - eg:
   *
   *   mv fbonly/foo public/foo
   *
   * The filter is likely to strip out the fbonly/foo change, leaving 'rename
   * from fbonly/foo' in the diff, but as fbonly/foo isn't on github, that's
   * not enough information.
   */
  public function testRenameFile(): void {
    $temp_dir = new ShipItTempDir('rename-file-test');
    file_put_contents(
      $temp_dir->getPath().'/initial.txt',
      'my content here',
    );

    $this->execSteps(
      $temp_dir->getPath(),
      ImmVector { 'hg', 'init' },
    );
    $this->configureHg($temp_dir);

    $this->execSteps(
      $temp_dir->getPath(),
      ImmVector { 'hg', 'commit', '-Am', 'initial commit' },
      ImmVector { 'hg', 'mv', 'initial.txt', 'moved.txt' },
      ImmVector { 'chmod', '755', 'moved.txt' },
      ImmVector { 'hg', 'commit', '-Am', 'moved file' },
    );

    $repo = new ShipItRepoHG(
      $temp_dir->getPath(),
      'master',
    );
    $changeset = $repo->getChangesetFromID('.');
    assert($changeset !== null);
    shell_exec('rm -rf '.escapeshellarg($temp_dir->getPath()));

    $this->assertSame('moved file', $changeset->getSubject());

    $diffs = Map { };
    foreach ($changeset->getDiffs() as $diff) {
      $diffs[$diff['path']] = $diff['body'];
    }
    $wanted_files = ImmSet { 'initial.txt', 'moved.txt' };
    foreach ($wanted_files as $file) {
      $this->assertContains($file, $diffs->keys());
      $diff = $diffs[$file];
      $this->assertContains('my content here', $diff);
    }

    $this->assertContains('deleted file mode 100644', $diffs['initial.txt']);
    $this->assertContains('new file mode 100755', $diffs['moved.txt']);
  }
}
