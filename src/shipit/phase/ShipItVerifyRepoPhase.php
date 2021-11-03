<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 * @format
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/j3hwi4do
 */
namespace Facebook\ShipIt;


final class ShipItVerifyRepoPhase extends ShipItPhase {
  private bool $createPatch = false;
  private bool $useLatestSourceCommit = false;
  private ?string $verifySourceCommit = null;
  private bool $shouldDoSubmodules = true;

  public function __construct(
    private (function(ShipItChangeset): Awaitable<ShipItChangeset>) $genFilter,
  ) {
    $this->skip();
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Verify that destination repository is sync';
  }

  <<__Override>>
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'verify',
        'description' =>
          'Verify that the destination repository is in sync, then exit',
        'write' => $_ ==> $this->unskip(),
      ),
      shape(
        'long_name' => 'create-fixup-patch',
        'description' =>
          'Create a patch to get the destination repository in sync, then exit',
        'write' => $_ ==> {
          $this->unskip();
          $this->createPatch = true;
          return true;
        },
      ),
      shape(
        'long_name' => 'verify-source-commit::',
        'description' => 'Hash of first commit that needs to be synced',
        'write' => $x ==> {
          $this->verifySourceCommit = $x;
          return $this->verifySourceCommit;
        },
      ),
      shape(
        'long_name' => 'use-latest-source-commit',
        'description' =>
          'Find the latest synced source commit to use as a base for verify',
        'write' => $_ ==> {
          $this->useLatestSourceCommit = true;
          return $this->useLatestSourceCommit;
        },
      ),
      shape(
        'long_name' => 'skip-submodules',
        'description' => 'Don\'t sync submodules',
        'write' => $_ ==> {
          $this->shouldDoSubmodules = false;
          return $this->shouldDoSubmodules;
        },
      ),
    ];
  }

  <<__Override>>
  protected async function genRunImpl(
    ShipItManifest $manifest,
  ): Awaitable<void> {
    if ($this->useLatestSourceCommit) {
      if ($this->verifySourceCommit !== null) {
        throw new ShipItException(
          "the 'verify-source-commit' flag cannot be used with the ".
          "'use-latest-source-commit' flag since the latter automatically ".
          "sets the verify source commit",
        );
      }
      $repo = await ShipItRepo::genTypedOpen<ShipItDestinationRepo>(
        $manifest->getDestinationSharedLock(),
        $manifest->getDestinationPath(),
        $manifest->getDestinationBranch(),
      );
      $this->verifySourceCommit = await $repo->genFindLastSourceCommit(
        keyset[],
        $manifest->getCommitMarker(),
      );
    }
    $clean_dir = await ShipItCreateNewRepoPhase::genCreateNewGitRepo(
      $manifest,
      $this->genFilter,
      shape(
        'name' => 'FBShipIt Internal User',
        'email' => 'fbshipit@example.com',
      ),
      $this->shouldDoSubmodules,
      $this->verifySourceCommit,
    );
    $clean_path = $clean_dir->getPath();
    $dirty_remote = 'shipit_dest';
    $dirty_ref = $dirty_remote.'/'.$manifest->getDestinationBranch();

    await (
      new ShipItShellCommand(
        $clean_path,
        'git',
        'remote',
        'add',
        $dirty_remote,
        $manifest->getDestinationPath(),
      )
    )->genRun();
    await (
      new ShipItShellCommand($clean_path, 'git', 'fetch', $dirty_remote)
    )->genRun();

    $diffstat = (
      await (
        new ShipItShellCommand(
          $clean_path,
          'git',
          'diff',
          '--stat',
          $dirty_ref,
          'HEAD',
        )
      )->genRun()
    )->getStdOut();

    if ($diffstat === '') {
      if ($this->createPatch) {
        ShipItLogger::err(
          "  CREATE PATCH FAILED: destination is already in sync.\n",
        );
        throw new ShipItExitException(1);
      }
      ShipItLogger::out("  Verification OK: destination is in sync.\n");
      throw new ShipItExitException(0);
    }

    if (!$this->createPatch) {
      ShipItLogger::err(
        "  VERIFICATION FAILED: destination repo does not match:\n\n%s\n",
        $diffstat,
      );
      throw new ShipItExitException(1);
    }

    $diff = (
      await (
        new ShipItShellCommand(
          $clean_path,
          'git',
          'diff',
          '--full-index',
          '--binary',
          '--no-color',
          $dirty_ref,
          'HEAD',
        )
      )->genRun()
    )->getStdOut();

    $source_sync_id = $this->verifySourceCommit;
    if ($source_sync_id === null) {
      $repo = await ShipItRepo::genTypedOpen<ShipItSourceRepo>(
        $manifest->getSourceSharedLock(),
        $manifest->getSourcePath(),
        $manifest->getSourceBranch(),
      );
      $changeset = await $repo->genHeadChangeset();
      if ($changeset === null) {
        throw new ShipItException('Could not find source id.');
      }
      $source_sync_id = $changeset->getID();
    }

    $patch_file = PHP\tempnam(PHP\sys_get_temp_dir(), 'shipit-resync-patch-')
      as string;
    PHP\file_put_contents($patch_file, $diff);

    ShipItLogger::out(
      "  Created patch file: %s\n\n".
      "%s\n\n".
      "  To apply:\n\n".
      "    $ cd %s\n".
      "    $ git apply < %s\n".
      "    $ git status\n".
      "    $ git add --all --patch\n".
      "    $ git commit -m '%s: %s'\n".
      "    $ git push\n\n".
      "  WARNING: there are 4 possible causes for differences:\n\n".
      "    1. changes in source haven't been copied to destination\n".
      "    2. changes were made to destination that aren't in source\n".
      "    3. the filter function has a bug\n".
      "    4. FBShipIt has a bug\n\n".
      "  APPLYING THE PATCH IS ONLY CORRECT FOR THE FIRST SITUATION; review\n".
      "  the changes carefully.\n\n",
      $patch_file,
      $diffstat,
      $manifest->getDestinationPath(),
      $patch_file,
      $manifest->getCommitMarker(),
      $source_sync_id,
    );
    throw new ShipItExitException(0);
  }
}
