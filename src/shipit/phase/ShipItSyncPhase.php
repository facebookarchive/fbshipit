<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItSyncPhase extends ShipItPhase {
  private ?string $firstCommit = null;
  private ImmSet<string> $skippedSourceCommits = ImmSet { };
  private ?string $patchesDirectory = null;
  private ?string $statsFilename = null;

  public function __construct(
    private ShipItSyncConfig::FilterFn $filter,
    private ImmSet<string> $destinationRoots = ImmSet { },
    private ?ShipItSyncConfig::PostFilterChangesetsFn $postFilterChangesets
      = null,
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
  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-sync-commits',
        'description' => "Don't copy any commits. Handy for testing.\n",
        'write' => $_ ==> $this->skip(),
      ),
      shape(
        'long_name' => 'first-commit::',
        'description' => 'Hash of first commit that needs to be synced',
        'write' => $x ==> $this->firstCommit = $x,
      ),
      shape(
        'long_name' => 'save-patches-to::',
        'description' => 'Directory to copy created patches to. Useful for '.
                         'debugging',
        'write' => $x ==> $this->patchesDirectory = $x,
      ),
      shape(
        'long_name' => 'skip-source-commits::',
        'description' => "Comma-separate list of source commit IDs to skip.",
        'write' =>
          $x ==> {
            $this->skippedSourceCommits = new ImmSet(\explode(',', $x));
            foreach ($this->skippedSourceCommits as $commit) {
              // 7 happens to be the usual output
              if (\strlen($commit) < ShipItUtil::SHORT_REV_LENGTH) {
                throw new ShipItException(
                  'Skipped rev '.$commit.' is potentially ambiguous; use a '.
                  'longer id instead.'
                );
              }
            }
          },
      ),
      shape(
        'long_name' => 'log-sync-stats-to::',
        'description' => 'The filename to log a JSON-encoded file with stats '.
                         'about the sync, or a directory name to log a file '.
                         'for each configured branch.',
        'write' => $x ==> $this->statsFilename = $x,
      ),
      shape(
        'long_name' => 'skip-post-filter-changesets',
        'description' =>
          'Skip any custom definitions for processing changesets after syncing',
        'write' => $_ ==> $this->postFilterChangesets = null,
      )
    };
  }

  <<__Override>>
  protected function runImpl(
    ShipItBaseConfig $base,
  ): void {
    $sync = (new ShipItSyncConfig(
      $base->getSourceRoots(),
      $this->filter,
      $this->postFilterChangesets,
    ))
     ->withDestinationRoots($this->destinationRoots)
     ->withFirstCommit($this->firstCommit)
     ->withSkippedSourceCommits($this->skippedSourceCommits)
     ->withPatchesDirectory($this->patchesDirectory)
     ->withStatsFilename($this->statsFilename);

    (new ShipItSync($base, $sync))->run();
  }
}
