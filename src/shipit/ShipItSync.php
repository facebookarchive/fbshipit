<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

class ShipItException extends \Exception {}

class ShipItSync {
  public function __construct(
    private ShipItBaseConfig $baseConfig,
    private ShipItSyncConfig $syncConfig,
  ) {
  }

  private function getFirstSourceID(): ?string {
    $config = $this->syncConfig;
    $rev = $config->getFirstCommit();
    if ($rev === null) {
      $src = $this->getRepo(ShipItSourceRepo::class);

      $rev = $src->findNextCommit(
        $this->findLastSyncedCommit(),
        $config->getSourceRoots(),
      );
    }
    return $rev;
  }

  private function getSourceChangesets(): ImmVector<ShipItChangeset> {
    $config = $this->syncConfig;
    $src = $this->getRepo(ShipItSourceRepo::class);

    $changesets = Vector { };
    $rev = $this->getFirstSourceID();
    while ($rev !== null) {
      $changeset = $src->getChangesetFromID($rev);

      if (!$changeset) {
        throw new ShipItException("Unable to get patch for $rev");
      }

      $changesets[] = $changeset;
      $rev = $src->findNextCommit($rev, $config->getSourceRoots());
    }
    return $changesets->toImmVector();
  }

  private function getFilteredChangesets(
  ): ImmVector<ShipItChangeset> {
    $base_config = $this->baseConfig;
    $skipped_ids = $this->syncConfig->getSkippedSourceCommits();
    $filter = $this->syncConfig->getFilter();

    $changesets = Vector { };
    foreach ($this->getSourceChangesets() as $changeset) {
      $skip_match = null;
      foreach ($skipped_ids as $skip_id) {
        if (\strpos($changeset->getID(), $skip_id) === 0) {
          $skip_match = $skip_id;
          break;
        }
      }
      if ($skip_match !== null) {
        $changesets[] = $changeset
          ->withDiffs(ImmVector { })
          ->withDebugMessage(
            'USER SKIPPED COMMIT: id "%s" matches "%s"',
            $changeset->getID(),
            $skip_match,
          );
        continue;
      }

      $changeset = $filter($base_config, $changeset);
      if ($changeset->getDiffs()->isEmpty()) {
        $changesets[] = $changeset->withDebugMessage(
          'SKIPPED COMMIT: no matching files',
        );
      } else {
        $changesets[] = self::addTrackingData($changeset);
      }
    }
    return $changesets->toImmVector();
  }

  public function run(): void {
    $changesets = $this->getFilteredChangesets();
    if ($changesets->isEmpty()) {
      print("  No new commits to sync.\n");
      $this->maybeLogStats(Vector {}, Vector {});
      return;
    }

    $patches_dir = $this->syncConfig->getPatchesDirectory();
    if ($patches_dir !== null && !\file_exists($patches_dir)) {
      \mkdir($patches_dir, 0755, /* recursive = */ true);
    }

    $verbose = $this->baseConfig->isVerboseEnabled();
    $dest = $this->getRepo(ShipItDestinationRepo::class);

    $changesets = $this->syncConfig->postFilterChangesets($changesets, $dest);

    $changesets_applied = Vector {};
    $changesets_skipped = Vector {};
    foreach ($changesets as $changeset) {
      if ($patches_dir !== null) {
        $file = $patches_dir.'/'.$this->baseConfig->getDestinationBranch().'-'.
          $changeset->getID().'.patch';
        if (\file_exists($file)) {
          \printf("Overwriting patch file: %s\n", $file);
        }
        \file_put_contents($file, $dest::renderPatch($changeset));
        $changeset = $changeset->withDebugMessage(
          'Saved patch file: %s',
          $file,
        );
      }

      if ($verbose) {
        $changeset->dumpDebugMessages();
      }

      if (!$changeset->isValid()) {
        \printf(
          "  SKIP %s %s\n",
          $changeset->getShortID(),
          $changeset->getSubject(),
        );
        $changesets_skipped->add($changeset);
        continue;
      }

      try {
        $dest->commitPatch($changeset);
        \printf(
          "  OK %s %s\n",
          $changeset->getShortID(),
          $changeset->getSubject(),
        );
        $changesets_applied->add($changeset);
        continue;
      } catch (ShipItRepoException $e) {
        \fprintf(
          \STDERR,
          "Failed to apply patch %s (%s): %s\n",
          $changeset->getID(),
          $changeset->getMessage(),
          $e->getMessage(),
        );
        throw $e;
      }
    }

    $this->maybeLogStats($changesets_applied, $changesets_skipped);
  }

  /**
   * Optionally logs stats about the sync to the user-specified file.
   *
   * @param $changesets_applied the changesets that were applied.
   */
  private function maybeLogStats(
    Vector<ShipItChangeset> $changesets_applied,
    Vector<ShipItChangeset> $changesets_skipped,
  ): void {
    $filename = $this->syncConfig->getStatsFilename();
    if ($filename === null) {
      return;
    }
    $destination_branch = $this->baseConfig->getDestinationBranch();
    // Support logging stats for a project with multiple branches.
    if (\is_dir($filename)) {
      // Slashes are allowed in branch names but not filenames.
      $namesafe_branch = \preg_replace(
        '/[^a-zA-Z0-9_\-.]/',
        '_',
        $destination_branch,
      );
      $filename = $filename.'/'.$namesafe_branch.'.json';
    }
    $source_changeset = $this
      ->getRepo(ShipItSourceRepo::class)
      ->getHeadChangeset();
    $destination_changeset = $this
      ->getRepo(ShipItDestinationRepo::class)
      ->getHeadChangeset();
    \file_put_contents(
      $filename,
      \json_encode(array(
        'source' => array(
          'id' => $source_changeset?->getID(),
          'timestamp' => $source_changeset?->getTimestamp(),
          'branch' => $this->baseConfig->getSourceBranch(),
        ),
        'destination' => array(
          'id' => $destination_changeset?->getID(),
          'timestamp' => $destination_changeset?->getTimestamp(),
          'branch' => $destination_branch,
        ),
        'changesets' => $changesets_applied
          ->map($changeset ==> $changeset->getID())
          ->toArray(),
        'skipped' => $changesets_skipped
          ->map($changeset ==> $changeset->getID())
          ->toArray(),
      )),
    );
  }

  private static function checkLastRev(?string $diff): string {
    if ($diff === null) {
      throw new ShipItException(
        "Unable to determine last differential revision pushed to dest repo"
      );
    }
    if (!\preg_match('/^D[0-9]{6,}$/', $diff)) {
      throw new ShipItException(
        "Last differential revision number ('{$diff}') is invalid"
      );
    }
    return $diff;
  }

  private static function checkFindDiff(?string $id, string $diff): string {
    if ($id === null) {
      throw new ShipItException("Unable to find $diff in source repo");
    }
    return $id;
  }

  <<__Memoize>>
  private function getRepo<Trepo as ShipItRepo>(
    classname<Trepo> $class,
  ): Trepo {
    $config = $this->baseConfig;

    if ($class === ShipItSourceRepo::class) {
      return ShipItRepo::typedOpen(
        $class,
        $config->getSourcePath(),
        $config->getSourceBranch(),
      );
    }

    if ($class === ShipItDestinationRepo::class) {
      return ShipItRepo::typedOpen(
        $class,
        $config->getDestinationPath(),
        $config->getDestinationBranch(),
      );
    }

    invariant_violation(
      'Got class %s, expected %s or %s',
      $class,
      ShipItSourceRepo::class,
      ShipItDestinationRepo::class,
    );
  }

  private function findLastSyncedCommit(
  ): string {
    $dest = $this->getRepo(ShipItDestinationRepo::class);

    $src_commit = $dest->findLastSourceCommit(
      $this->syncConfig->getDestinationRoots(),
    );
    if ($src_commit === null) {
      throw new ShipItException("Couldn't find synced commit id");
    }
    return $src_commit;
  }

  public static function addTrackingData(
    ShipItChangeset $changeset,
    ?string $rev = null,
  ): ShipItChangeset {
    if ($rev === null) {
      $rev = $changeset->getID();
    }
    $new_message = $changeset->getMessage()."\n\n".
      'fbshipit-source-id: '.$rev;
    return $changeset->withMessage(\trim($new_message));
  }
}
