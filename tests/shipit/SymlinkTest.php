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

enum SymlinkTestOperation: string {
  DELETE_FILE = 'deleted file mode 100644';
  DELETE_SYMLINK = 'deleted file mode 120000';
  CREATE_FILE = 'new file mode 100644';
  CREATE_SYMLINK = 'new file mode 120000';
}

final class SymlinkTest extends BaseTest {
  public function getFileToFromSymlinkExamples(
  ): array<string, (
    classname<ShipItSourceRepo>,
    ImmVector<ImmVector<string>>,
    SymlinkTestOperation,
    SymlinkTestOperation,
    string,
  )> {
    return [
      'git file to symlink' => tuple(
        ShipItRepoGIT::class,
        ImmVector {
          ImmVector { 'git', 'init' },
          ImmVector { 'touch', 'foo' },
          ImmVector { 'git', 'add', 'foo' },
          ImmVector { 'git', 'commit', '-m', 'add file' },
          ImmVector { 'git', 'rm', 'foo' },
          ImmVector { 'ln', '-s', 'bar', 'foo' },
          ImmVector { 'git', 'add', 'foo' },
          ImmVector { 'git', 'commit', '-m', 'add symlink' },
        },
        SymlinkTestOperation::DELETE_FILE,
        SymlinkTestOperation::CREATE_SYMLINK,
        'HEAD',
      ),
      'hg file to symlink' => tuple(
        ShipItRepoHG::class,
        ImmVector {
          ImmVector { 'hg', 'init' },
          ImmVector { 'touch', 'foo' },
          ImmVector { 'hg', 'commit', '-Am', 'add file' },
          ImmVector { 'hg', 'rm', 'foo' },
          ImmVector { 'ln', '-s', 'bar', 'foo' },
          ImmVector { 'hg', 'commit', '-Am', 'add symlink' },
        },
        SymlinkTestOperation::DELETE_FILE,
        SymlinkTestOperation::CREATE_SYMLINK,
        '.',
      ),
      'git symlink to file' => tuple(
        ShipItRepoGIT::class,
        ImmVector {
          ImmVector { 'git', 'init' },
          ImmVector { 'ln', '-s', 'bar', 'foo' },
          ImmVector { 'git', 'add', 'foo' },
          ImmVector { 'git', 'commit', '-m', 'add symlink' },
          ImmVector { 'git', 'rm', 'foo' },
          ImmVector { 'touch', 'foo' },
          ImmVector { 'git', 'add', 'foo' },
          ImmVector { 'git', 'commit', '-m', 'add file' },
        },
        SymlinkTestOperation::DELETE_SYMLINK,
        SymlinkTestOperation::CREATE_FILE,
        '.',
      ),
      'hg symlink to file' => tuple(
        ShipItRepoHG::class,
        ImmVector {
          ImmVector { 'hg', 'init' },
          ImmVector { 'ln', '-s', 'bar', 'foo' },
          ImmVector { 'hg', 'commit', '-Am', 'add symlink' },
          ImmVector { 'hg', 'rm', 'foo' },
          ImmVector { 'touch', 'foo' },
          ImmVector { 'hg', 'commit', '-Am', 'add file' },
        },
        SymlinkTestOperation::DELETE_SYMLINK,
        SymlinkTestOperation::CREATE_FILE,
        '.',
      ),
    ];
  }

  /** Symlinks <=> file operations are interesting because they create two diffs
   * for the same path:
   *
   * 1. delete the old thing
   * 2. create the new thing
   *
   * @dataProvider getFileToFromSymlinkExamples
   */
  public function testFileToFromSymlink(
    classname<ShipItSourceRepo> $repo_type,
    ImmVector<ImmVector<string>> $steps,
    SymlinkTestOperation $first_op,
    SymlinkTestOperation $second_op,
    string $rev,
  ): void {
    // make sure we don't pick up any user configs in git
    $home_dir = new ShipItTempDir('fake-home-for-git');
    $name = 'FBShipIt';
    $email = 'fbshipit@example.com';
    $temp_dir = new ShipItTempDir('symlink-test');
    foreach ($steps as $step) {
      (new ShipItShellCommand($temp_dir->getPath(), ...$step))
        ->setEnvironmentVariables(ImmMap {
          'HG_PLAIN' => '1',
          'GIT_CONFIG_NOSYSTEM' => '1',
          'HOME' => $home_dir->getPath(),
          'GIT_AUTHOR_NAME' => $name,
          'GIT_AUTHOR_EMAIL' => $email,
          'GIT_COMMITTER_NAME' => $name,
          'GIT_COMMITTER_EMAIL' => $email,
          'HGUSER' => $name.' <'.$email.'>',
        })
        ->runSynchronously();
    }

    $repo = ShipItRepo::typedOpen(
      $repo_type,
      $temp_dir->getPath(),
      'master',
    );

    $changeset = $repo->getChangesetFromID($rev);
    $this->assertNotNull($changeset);
    assert($changeset !== null); // for typechecker
    $this->assertTrue($changeset->isValid());

    $this->assertEquals(
      2,
      $changeset->getDiffs()->count(),
      'Expected a deletion chunk and a separate creation chunk',
    );

    $this->assertEquals(
      ImmVector { 'foo', 'foo' },
      $changeset->getDiffs()->map($diff ==> $diff['path']),
      'Expected chunks to affect the same file',
    );

    // Order is important: the old thing needs to be deleted before the new one
    // is created.
    $delete_file = $changeset->getDiffs()[0];
    $this->assertContains($first_op, $delete_file['body']);
    $create_symlink = $changeset->getDiffs()[1];
    $this->assertContains($second_op, $create_symlink['body']);
  }
}
