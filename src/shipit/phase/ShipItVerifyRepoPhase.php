<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/j3hwi4do
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str;

final class ShipItVerifyRepoPhase extends ShipItPhase {
  private bool $createPatch = false;
  private bool $useLatestSourceCommit = false;
  private ?string $verifySourceCommit = null;

  public function __construct(
    private (function(ShipItChangeset): ShipItChangeset) $filter,
  ) {
    $this->skip();
  }

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
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
    ];
  }

  <<__Override>>
  public function runImpl(ShipItBaseConfig $config): void {
    if ($this->useLatestSourceCommit) {
      if ($this->verifySourceCommit !== null) {
        throw new ShipItException(
          "the 'verify-source-commit' flag cannot be used with the ".
          "'use-latest-source-commit' flag since the latter automatically ".
          "sets the verify source commit",
        );
      }
      $repo = ShipItRepo::typedOpen(
        ShipItDestinationRepo::class,
        $config->getDestinationPath(),
        $config->getDestinationBranch(),
      );
      $this->verifySourceCommit = $repo->findLastSourceCommit(keyset[]);
    }
    $clean_dir = ShipItCreateNewRepoPhase::createNewGitRepo(
      $config,
      $this->filter,
      shape(
        'name' => 'FBShipIt Internal User',
        'email' => 'fbshipit@example.com',
      ),
      $this->verifySourceCommit,
    );
    $clean_path = $clean_dir->getPath();
    $dirty_remote = 'shipit_dest';
    $dirty_ref = $dirty_remote.'/'.$config->getDestinationBranch();

    /* HH_FIXME[4128] Use ShipItShellCommand */
    ShipItUtil::shellExec(
      $clean_path,
      /* stdin = */ null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'remote',
      'add',
      $dirty_remote,
      $config->getDestinationPath(),
    );
    /* HH_FIXME[4128] Use ShipItShellCommand */
    ShipItUtil::shellExec(
      $clean_path,
      null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'fetch',
      $dirty_remote,
    );

    /* HH_FIXME[4128] Use ShipItShellCommand */
    $diffstat = Str\trim_right(ShipItUtil::shellExec(
      $clean_path,
      null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'diff',
      '--stat',
      'HEAD',
      $dirty_ref,
    ));

    if ($diffstat === '') {
      if ($this->createPatch) {
        ShipItLogger::err(
          "  CREATE PATCH FAILED: destination is already in sync.\n",
        );
        exit(1);
      }
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \printf("  Verification OK: destination is in sync.\n");
      exit(0);
    }

    if (!$this->createPatch) {
      ShipItLogger::err(
        "  VERIFICATION FAILED: destination repo does not match:\n\n%s\n",
        $diffstat,
      );
      exit(1);
    }

    /* HH_FIXME[4128] Use ShipItShellCommand */
    $diff = ShipItUtil::shellExec(
      $clean_path,
      /* stdin = */ null,
      ShipItUtil::DONT_VERBOSE,
      'git',
      'diff',
      '--full-index',
      '--binary',
      '--no-color',
      $dirty_ref,
      'HEAD',
    );


    $source_sync_id = $this->verifySourceCommit;
    if ($source_sync_id === null) {
      $repo = ShipItRepo::typedOpen(
        ShipItSourceRepo::class,
        $config->getSourcePath(),
        $config->getSourceBranch(),
      );
      $changeset = $repo->getHeadChangeset();
      if ($changeset === null) {
        throw new ShipItException('Could not find source id.');
      }
      $source_sync_id = $changeset->getID();
    }

    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    $patch_file = \tempnam(\sys_get_temp_dir(), 'shipit-resync-patch-');
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \file_put_contents($patch_file, $diff);

    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \printf(
      "  Created patch file: %s\n\n".
      "%s\n\n".
      "  To apply:\n\n".
      "    $ cd %s\n".
      "    $ git apply < %s\n".
      "    $ git status\n".
      "    $ git add --all --patch\n".
      "    $ git commit -m 'fbshipit-source-id: %s'\n".
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
      $config->getDestinationPath(),
      $patch_file,
      $source_sync_id,
    );
    exit(0);
  }
}
