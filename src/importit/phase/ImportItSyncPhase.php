<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/tvejazta
 */
namespace Facebook\ImportIt;

use namespace HH\Lib\Str; // @oss-enable
use type Facebook\ShipIt\{
  ShipItChangeset,
  ShipItDestinationRepo,
  ShipItLogger,
  ShipItManifest,
};

final class ImportItSyncPhase extends \Facebook\ShipIt\ShipItPhase {

  private ?string $expectedHeadRev;
  private ?string $patchesDirectory;
  private ?string $pullRequestNumber;
  private bool $skipPullRequest = false;
  private bool $applyToLatest = false;
  private ?string $applyToTarget = null;
  private bool $shouldDoSubmodules = true;

  public function __construct(
    private (function(ShipItChangeset): ShipItChangeset) $filter,
  ) {
  }

  private function assertApplyToMutualExclusivity(): void {
    invariant(
      $this->applyToTarget is null || $this->applyToLatest === false,
      'apply-to-latest and apply-to-target cannot be used at the same time',
    );
  }

  <<__Override>>
  final public function getReadableName(): string {
    return 'Import Commits';
  }

  <<__Override>>
  final public function getCLIArguments(
  ): vec<\Facebook\ShipIt\ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'expected-head-revision::',
        'description' => 'The expected revision at the HEAD of the PR',
        'write' => $x ==> {
          $this->expectedHeadRev = $x;
          return $this->expectedHeadRev;
        },
      ),
      shape(
        'long_name' => 'pull-request-number::',
        'description' => 'The number of the Pull Request to import',
        'write' => $x ==> {
          $this->pullRequestNumber = $x;
          return $this->pullRequestNumber;
        },
      ),
      shape(
        'long_name' => 'save-patches-to::',
        'description' =>
          'Directory to copy created patches to. Useful for '.'debugging',
        'write' => $x ==> {
          $this->patchesDirectory = $x;
          return $this->patchesDirectory;
        },
      ),
      shape(
        'long_name' => 'skip-pull-request',
        'description' => 'Dont fetch a PR, instead just use the local '.
          'expected-head-revision',
        'write' => $_ ==> {
          $this->skipPullRequest = true;
          return $this->skipPullRequest;
        },
      ),
      shape(
        'long_name' => 'apply-to-latest',
        'description' => 'Apply the PR patch to the latest internal revision, '.
          'instead of on the internal commit that matches the '.
          'PR base. Mutually exclusive with apply-to-target',
        'write' => $_ ==> {
          $this->applyToLatest = true;
          $this->assertApplyToMutualExclusivity();
          return $this->applyToLatest;
        },
      ),
      shape(
        'long_name' => 'apply-to-target::',
        'description' => 'Apply the PR patch to the given target revision, '.
          'instead of on the internal commit that matches the '.
          'PR base. Mutually exclusive with apply-to-latest',
        'write' => $x ==> {
          $this->applyToTarget = $x;
          $this->assertApplyToMutualExclusivity();
          return $this->applyToTarget;
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
    \Facebook\ShipIt\ShipItManifest $manifest,
  ): Awaitable<void> {
    list($changeset, $destination_base_rev) =
      await $this->genSourceChangsetAndDestinationBaseRevision($manifest);
    if ($this->applyToTarget is nonnull) {
      $destination_base_rev = $this->applyToTarget;
    }
    await $this->genApplyPatchToDestination(
      $manifest,
      $changeset,
      $destination_base_rev,
    );
  }

  private async function genSourceChangsetAndDestinationBaseRevision(
    ShipItManifest $manifest,
  ): Awaitable<(ShipItChangeset, ?string)> {
    $pr_number = null;
    $expected_head_rev = $this->expectedHeadRev;
    if ($this->skipPullRequest) {
      invariant(
        $expected_head_rev !== null,
        '--expected-head-revision must be set!',
      );
    } else {
      $pr_number = $this->pullRequestNumber;
      invariant(
        $pr_number !== null && $expected_head_rev !== null,
        '--expected-head-revision must be set! '.
        'And either --pull-request-number or --skip-pull-request must be set',
      );
    }
    $source_repo = new ImportItRepoGIT(
      $manifest->getSourceSharedLock(),
      $manifest->getSourcePath(),
    );
    await $source_repo->genSetBranch($manifest->getSourceBranch());
    $source_repo->setUseNativeRenames(true);
    return await $source_repo->genChangesetAndBaseRevisionForPullRequest(
      $pr_number,
      $expected_head_rev,
      $manifest->getSourceBranch(),
      $this->applyToLatest,
      $manifest->getCommitMarker(),
    );
  }

  private async function genApplyPatchToDestination(
    ShipItManifest $manifest,
    ShipItChangeset $changeset,
    ?string $base_rev,
  ): Awaitable<void> {
    $destination_repo = await ImportItRepo::genOpen(
      $manifest->getDestinationSharedLock(),
      $manifest->getDestinationPath(),
      $manifest->getDestinationBranch(),
    );
    if ($base_rev !== null) {
      ShipItLogger::out(
        "  Updating destination branch to new base revision...\n",
      );
      await $destination_repo->genUpdateBranchTo($base_rev);
    }
    invariant(
      $destination_repo is ShipItDestinationRepo,
      'The destination repository must implement ShipItDestinationRepo!',
    );
    ShipItLogger::out("  Filtering...\n");
    $filter_fn = $this->filter;
    $changeset = $filter_fn($changeset);
    if ($manifest->isVerboseEnabled()) {
      $changeset->dumpDebugMessages();
    }
    ShipItLogger::out("  Exporting...\n");
    $this->maybeSavePatch($destination_repo, $changeset);
    try {
      $rev = await $destination_repo->genCommitPatch(
        $changeset,
        $this->shouldDoSubmodules,
      );
      ShipItLogger::out(
        "  Done.  %s committed in %s\n",
        $rev,
        $destination_repo->getPath(),
      );
    } catch (\Exception $e) {
      if ($this->patchesDirectory !== null) {
        ShipItLogger::out(
          "  Failure to apply patch at %s\n",
          $this->getPatchLocationForChangeset($changeset),
        );
      } else {
        ShipItLogger::out(
          "  Failure to apply patch:\n%s\n",
          $destination_repo::renderPatch($changeset),
        );
      }
      throw $e;
    }
  }

  private function maybeSavePatch(
    ShipItDestinationRepo $destination_repo,
    ShipItChangeset $changeset,
  ): void {
    $patchesDirectory = $this->patchesDirectory;
    if ($patchesDirectory === null) {
      return;
    }
    if (!PHP\file_exists($patchesDirectory)) {
      PHP\mkdir($patchesDirectory, 0755, /* recursive = */ true);
    } else if (!PHP\is_dir($patchesDirectory)) {
      ShipItLogger::err(
        "Cannot log to %s: the path exists and is not a directory.\n",
        $patchesDirectory,
      );
      return;
    }
    $file = $this->getPatchLocationForChangeset($changeset);
    PHP\file_put_contents($file, $destination_repo::renderPatch($changeset));
    $changeset->withDebugMessage('Saved patch file: %s', $file);
  }

  private function getPatchLocationForChangeset(
    ShipItChangeset $changeset,
  ): string {
    return (string)$this->patchesDirectory.'/'.$changeset->getID().'.patch';
  }
}
