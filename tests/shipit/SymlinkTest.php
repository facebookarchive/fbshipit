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
 * https://fburl.com/vfbtaguj
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C, Vec}; // @oss-enable
use type Facebook\HackTest\DataProvider; // @oss-enable
// @oss-disable: use type DataProvider;


enum SymlinkTestOperation: string {
  DELETE_FILE = 'deleted file mode 100644';
  DELETE_SYMLINK = 'deleted file mode 120000';
  CREATE_FILE = 'new file mode 100644';
  CREATE_SYMLINK = 'new file mode 120000';
}

<<\Oncalls('open_source')>>
final class SymlinkTest extends ShellTest {
  public static function getFileToFromSymlinkExamples(): dict<string, (
    classname<ShipItSourceRepo>,
    vec<vec<string>>,
    SymlinkTestOperation,
    SymlinkTestOperation,
    string,
  )> {
    return dict[
      'git file to symlink' => tuple(
        ShipItRepoGIT::class,
        vec[
          vec['git', 'init'],
          vec['touch', 'foo'],
          vec['git', 'add', 'foo'],
          vec['git', 'commit', '-m', 'add file'],
          vec['git', 'rm', 'foo'],
          vec['ln', '-s', 'bar', 'foo'],
          vec['git', 'add', 'foo'],
          vec['git', 'commit', '-m', 'add symlink'],
        ],
        SymlinkTestOperation::DELETE_FILE,
        SymlinkTestOperation::CREATE_SYMLINK,
        'HEAD',
      ),
      'hg file to symlink' => tuple(
        ShipItRepoHG::class,
        vec[
          vec['hg', 'init'],
          vec['touch', 'foo'],
          vec['hg', 'commit', '-Am', 'add file'],
          vec['hg', 'rm', 'foo'],
          vec['ln', '-s', 'bar', 'foo'],
          vec['hg', 'commit', '-Am', 'add symlink'],
        ],
        SymlinkTestOperation::DELETE_FILE,
        SymlinkTestOperation::CREATE_SYMLINK,
        '.',
      ),
      'git symlink to file' => tuple(
        ShipItRepoGIT::class,
        vec[
          vec['git', 'init'],
          vec['ln', '-s', 'bar', 'foo'],
          vec['git', 'add', 'foo'],
          vec['git', 'commit', '-m', 'add symlink'],
          vec['git', 'rm', 'foo'],
          vec['touch', 'foo'],
          vec['git', 'add', 'foo'],
          vec['git', 'commit', '-m', 'add file'],
        ],
        SymlinkTestOperation::DELETE_SYMLINK,
        SymlinkTestOperation::CREATE_FILE,
        '.',
      ),
      'hg symlink to file' => tuple(
        ShipItRepoHG::class,
        vec[
          vec['hg', 'init'],
          vec['ln', '-s', 'bar', 'foo'],
          vec['hg', 'commit', '-Am', 'add symlink'],
          vec['hg', 'rm', 'foo'],
          vec['touch', 'foo'],
          vec['hg', 'commit', '-Am', 'add file'],
        ],
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
   */
  <<DataProvider('getFileToFromSymlinkExamples')>>
  public async function testFileToFromSymlink(
    classname<ShipItSourceRepo> $repo_type,
    vec<vec<string>> $steps,
    SymlinkTestOperation $first_op,
    SymlinkTestOperation $second_op,
    string $rev,
  ): Awaitable<void> {
    // make sure we don't pick up any user configs in git
    $home_dir = new ShipItTempDir('fake-home-for-git');
    $name = 'FBShipIt';
    $email = 'fbshipit@example.com';
    $temp_dir = new ShipItTempDir('symlink-test');
    foreach ($steps as $step) {
      // @lint-ignore AWAIT_IN_LOOP These need to be run serially
      await (new ShipItShellCommand($temp_dir->getPath(), ...$step))
        ->setEnvironmentVariables(dict[
          'HG_PLAIN' => '1',
          'GIT_CONFIG_NOSYSTEM' => '1',
          'HOME' => $home_dir->getPath(),
          'GIT_AUTHOR_NAME' => $name,
          'GIT_AUTHOR_EMAIL' => $email,
          'GIT_COMMITTER_NAME' => $name,
          'GIT_COMMITTER_EMAIL' => $email,
          'HGUSER' => $name.' <'.$email.'>',
        ])
        ->genRun();
    }

    if ($repo_type === ShipItRepoGIT::class) {
      $repo = await ShipItRepo::genTypedOpen<ShipItRepoGIT>(
        new ShipItDummyLock(),
        $temp_dir->getPath(),
        'master',
      );
    } else if ($repo_type === ShipItRepoHG::class) {
      $repo = await ShipItRepo::genTypedOpen<ShipItRepoHG>(
        new ShipItDummyLock(),
        $temp_dir->getPath(),
        'master',
      );
    } else {
      invariant_violation(
        'Invalid repo: %s, must be %s or %s',
        $repo_type,
        ShipItRepoHG::class,
        ShipItRepoGIT::class,
      );
    }

    $changeset = await $repo->genChangesetFromID($rev);
    $changeset = \expect($changeset)->toNotBeNull();
    \expect($changeset->isValid())->toBeTrue();

    \expect(C\count($changeset->getDiffs()))->toEqual(
      2,
      'Expected a deletion chunk and a separate creation chunk',
    );

    \expect(Vec\map($changeset->getDiffs(), $diff ==> $diff['path']))
      ->toEqual(
        vec['foo', 'foo'],
        'Expected chunks to affect the same file',
      );

    // Order is important: the old thing needs to be deleted before the new one
    // is created.
    $delete_file = $changeset->getDiffs()[0];
    \expect($delete_file['body'])->toContainSubstring((string)$first_op);
    $create_symlink = $changeset->getDiffs()[1];
    \expect($create_symlink['body'])->toContainSubstring((string)$second_op);
  }
}
