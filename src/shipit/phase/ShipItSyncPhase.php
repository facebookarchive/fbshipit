<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/aa279jbn
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str;

final class ShipItSyncPhase extends ShipItPhase {
  private ?string $firstCommit = null;
  private keyset<string> $skippedSourceCommits = keyset[];
  private ?string $patchesDirectory = null;
  private ?string $statsFilename = null;
  private bool $shouldDoSubmodules = true;

  public function __construct(
    private ShipItSyncConfig::TFilterFn $filter,
    private keyset<string> $destinationRoots = keyset[],
    private ?ShipItSyncConfig::TPostFilterChangesetsFn $postFilterChangesets =
      null,
    private ?bool $allowEmptyCommit = false,
  ) {}

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Synchronize commits';
  }

  <<__Override>>
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'skip-sync-commits',
        'description' => "Don't copy any commits. Handy for testing.",
        'write' => $_ ==> $this->skip(),
      ),
      shape(
        'long_name' => 'first-commit::',
        'description' => 'Hash of first commit that needs to be synced',
        'write' => $x ==> {
          $this->firstCommit = $x;
          return $this->firstCommit;
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
        'long_name' => 'skip-source-commits::',
        'description' => "Comma-separate list of source commit IDs to skip.",
        'write' => $x ==> {
          $this->skippedSourceCommits = keyset(Str\split($x, ','));
          foreach ($this->skippedSourceCommits as $commit) {
            // 7 happens to be the usual output
            if (Str\length($commit) < ShipItChangeset::SHORT_REV_LENGTH) {
              throw new ShipItException(
                'Skipped rev '.
                $commit.
                ' is potentially ambiguous; use a '.
                'longer id instead.',
              );
            }
          }
          return true;
        },
      ),
      shape(
        'long_name' => 'log-sync-stats-to::',
        'description' => 'The filename to log a JSON-encoded file with stats '.
          'about the sync, or a directory name to log a file '.
          'for each configured branch.',
        'write' => $x ==> {
          $this->statsFilename = $x;
          return $this->statsFilename;
        },
      ),
      shape(
        'long_name' => 'skip-post-filter-changesets',
        'description' =>
          'Skip any custom definitions for processing changesets after syncing',
        'write' => $_ ==> {
          $this->postFilterChangesets = null;
          return $this->postFilterChangesets;
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
  protected function runImpl(ShipItManifest $manifest): void {
    $sync = (
      new ShipItSyncConfig(
        $manifest->getSourceRoots(),
        $this->filter,
        $this->postFilterChangesets,
      )
    )
      ->withDestinationRoots($this->destinationRoots)
      ->withFirstCommit($this->firstCommit)
      ->withSkippedSourceCommits($this->skippedSourceCommits)
      ->withPatchesDirectory($this->patchesDirectory)
      ->withStatsFilename($this->statsFilename)
      ->withAllowEmptyCommits($this->allowEmptyCommit)
      ->withShouldDoSubmodules($this->shouldDoSubmodules);

    (new ShipItSync($manifest, $sync))->run();
  }
}
