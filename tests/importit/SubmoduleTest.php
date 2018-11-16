<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ImportIt;


use \Facebook\ShipIt\ {
  ShipItRepo,
  ShipItRepoGIT,
  ShipItShellCommand,
  ShipItSubmoduleFilter,
  ShipItTempDir
};

final class SubmoduleTest extends \Facebook\ShipIt\BaseTest {
  public function testSubmoduleCommitFile(): void {
    $changeset = ShipItRepoGIT::getChangesetFromExportedPatch(
      \file_get_contents(
        __DIR__.'/git-diffs/submodule-hhvm-third-party.header',
      ),
      \file_get_contents(
        __DIR__.'/git-diffs/submodule-hhvm-third-party.patch',
      ),
    );
    $this->assertNotNull($changeset);
    assert($changeset !== null); // for typechecker
    $this->assertTrue($changeset->isValid());

    $changeset = ImportItSubmoduleFilter::moveSubmoduleCommitToTextFile(
      $changeset,
      'third-party',
      'fbcode/hphp/facebook/third-party-rev.txt',
    );

    $this->assertSame(1, $changeset->getDiffs()->keys()->count());
    $change = $changeset->getDiffs()->firstValue();
    assert($change !== null);
    $change = $change['body'];
    $this->assertNotEmpty($change);

    $this->assertContains(
      '--- a/fbcode/hphp/facebook/third-party-rev.txt',
      $change,
    );
    $this->assertContains(
      '+++ b/fbcode/hphp/facebook/third-party-rev.txt',
      $change,
    );

    $old_pos = \strpos($change, '6d9dffd0233c53bb83e4daf5475067073df9cdca');
    $new_pos = \strpos($change, 'ae031dcc9594163f5b0c35e7026563f1c8372595');

    $this->assertNotFalse($old_pos);
    $this->assertNotFalse($new_pos);
    $this->assertGreaterThan($old_pos, $new_pos);
  }

  public function testImportCommitPatchWithSubmodule(): void {
    // First create a repo that we'll use as our submodule.
    $submodule_dir = new ShipItTempDir('submodule');
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'init',
    ))
      ->runSynchronously();
    $this->configureGit($submodule_dir);
    \file_put_contents($submodule_dir->getPath().'/somefile', '');
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'add',
      'somefile',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'commit',
      '-m', 'only commit to submodule repo',
    ))
      ->runSynchronously();
    $submodule_first_id = ShipItRepo::open($submodule_dir->getPath(), 'master')
      ->getHeadChangeset()?->getID();
    invariant($submodule_first_id !== null, 'impossible');
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'mv',
      'somefile',
      'otherfile',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $submodule_dir->getPath(),
      'git',
      'commit',
      '-m', 'move file in submodule repo',
    ))
      ->runSynchronously();
    $submodule_second_id = ShipItRepo::open($submodule_dir->getPath(), 'master')
      ->getHeadChangeset()?->getID();
    invariant($submodule_second_id !== null, 'impossible');

    // Setup the destination repo (what we import to).
    $dest_dir = new ShipItTempDir('dest-repo');
    (new ShipItShellCommand(
      $dest_dir->getPath(),
      'git',
      'init',
    ))
      ->runSynchronously();
    $this->configureGit($dest_dir);
    \file_put_contents(
      $dest_dir->getPath().'/rev.txt',
      'Subproject commit '.$submodule_first_id."\n",
    );
    \file_put_contents(
      $dest_dir->getPath().'/.gitmodules',
      '[submodule "test"]
         path=submodule-test
         url='.$submodule_dir->getPath(),
    );
    (new ShipItShellCommand(
      $dest_dir->getPath(),
      'git',
      'add',
      'rev.txt',
      '.gitmodules',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $dest_dir->getPath(),
      'git',
      'commit',
      '-m', 'add new submodule',
    ))
      ->runSynchronously();

    // Setup the source repo (what we import from).
    $source_dir = new ShipItTempDir('source-dir');
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'init',
    ))
      ->runSynchronously();
    $this->configureGit($source_dir);
    $source_dir = \Facebook\ShipIt\ShipItCreateNewRepoPhase::createNewGitRepo(
      (new \Facebook\ShipIt\ShipItBaseConfig(
        '',
        $dest_dir->getPath(),
        //$source_dir->getPath(),
        (new ShipItTempDir('source-dir'))->getPath(),
        ImmSet {},
      ))
        ->withDestinationBranch('master')
        ->withSourceBranch('master'),
      $c ==> ShipItSubmoduleFilter::useSubmoduleCommitFromTextFile(
        $c,
        'rev.txt',
        'submodule-test',
      ),
      shape(
        'name' => 'Test User',
        'email' => 'someone@example.com',
      ),
    );
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'submodule',
      'init',
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $source_dir->getPath().'/submodule-test',
      'git',
      'checkout',
      $submodule_second_id,
    ))
      ->runSynchronously();
    (new ShipItShellCommand(
      $source_dir->getPath(),
      'git',
      'commit',
      '--all',
      '-m', 'update submodule',
    ))
      ->runSynchronously();
    $changeset = ShipItRepo::open($source_dir->getPath(), 'master')
      ->getHeadChangeset();
    invariant($changeset !== null, 'impossible');
    ShipItRepoGIT::typedOpen(
      ShipItRepoGIT::class,
      $dest_dir->getPath(),
      'master',
    )
      ->commitPatch(ImportItSubmoduleFilter::moveSubmoduleCommitToTextFile(
        $changeset,
        'submodule-test',
        'rev.txt',
      ));

    // Now we can finally check stuff!
    $this->assertEquals(
      'Subproject commit '.$submodule_second_id."\n",
      \file_get_contents($dest_dir->getPath().'/rev.txt'),
      'File should be updated with new hash.',
    );
  }
}
