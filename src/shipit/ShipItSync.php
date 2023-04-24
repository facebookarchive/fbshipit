<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/j2zf8wd1
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C, Regex, Str, Vec}; // @oss-enable

class ShipItException extends \Exception {}

<<__Sealed(ShipItMultipleImportPathsException::class)>>
class ShipItImportDisallowedException extends ShipItException {}

final class ShipItMultipleImportPathsException
  extends ShipItImportDisallowedException {}

final class ShipItSync {
  <<__Reifiable>>
  const type TSyncStats = shape(
    'source' => shape(
      'id' => ?string,
      'timestamp' => ?int,
      'branch' => string,
    ),
    'destination' => shape(
      'id' => ?string,
      'timestamp' => ?int,
      'branch' => string,
    ),
    'changesets' => vec<shape(
      'id' => string,
      'timestamp' => int,
    )>,
    'skipped' => vec<shape(
      'id' => string,
      'timestamp' => int,
    )>,
  );

  public function __construct(
    private ShipItManifest $manifest,
    private ShipItSyncConfig $syncConfig,
  ) {
  }

  private async function genFirstSourceID(): Awaitable<?string> {
    $config = $this->syncConfig;
    $rev = $config->getFirstCommit();
    if ($rev === null) {
      $src = await $this->genRepo<ShipItSourceRepo>();

      $last_synced_commit = await $this->genFindLastSyncedCommit();
      $rev = await $src->genFindNextCommit(
        $last_synced_commit,
        $config->getSourceRoots(),
      );
    }
    return $rev;
  }

  private async function genSourceChangesets(
  ): Awaitable<vec<ShipItChangeset>> {
    $config = $this->syncConfig;
    $src = await $this->genRepo<ShipItSourceRepo>();

    $changesets = vec[];
    $rev = await $this->genFirstSourceID();
    while ($rev !== null) {
      // @lint-ignore AWAIT_IN_LOOP We need to do this serially
      $changeset = await $src->genChangesetFromID($rev);

      if (!$changeset) {
        throw new ShipItException("Unable to get patch for $rev");
      }

      $changesets[] = $changeset;
      // @lint-ignore AWAIT_IN_LOOP We need to do this serially
      $rev = await $src->genFindNextCommit($rev, $config->getSourceRoots());
    }
    return $changesets;
  }

  private async function genFilteredChangesets(
  ): Awaitable<vec<ShipItChangeset>> {
    $manifest = $this->manifest;
    $skipped_ids = $this->syncConfig->getSkippedSourceCommits();
    $gen_filter = $this->syncConfig->getFilter();

    $source_changesets = await $this->genSourceChangesets();
    $changesets = vec[];
    foreach ($source_changesets as $changeset) {
      $skip_match = null;
      foreach ($skipped_ids as $skip_id) {
        if (Str\search($changeset->getID(), $skip_id) === 0) {
          $skip_match = $skip_id;
          break;
        }
      }
      if ($skip_match !== null) {
        $changesets[] = $changeset
          ->skip(Str\format(
            'USER SKIPPED COMMIT: id "%s" matches "%s"',
            $changeset->getID(),
            $skip_match,
          ));
        continue;
      }

      // @lint-ignore AWAIT_IN_LOOP We need to run this serially
      $changeset = await $gen_filter($manifest, $changeset);
      if (!$this->isValidChangeToSync($changeset)) {
        $changesets[] =
          $changeset->withDebugMessage('SKIPPED COMMIT: no matching files');
      } else {
        $changesets[] = self::addTrackingData($manifest, $changeset);
      }
    }
    return $changesets;
  }

  public async function genRun(): Awaitable<void> {
    $changesets = await $this->genFilteredChangesets();
    if (C\is_empty($changesets)) {
      ShipItLogger::out("  No new commits to sync.\n");
      await $this->genMaybeLogStats(vec[], vec[]);
      return;
    }

    $patches_dir = $this->syncConfig->getPatchesDirectory();
    if ($patches_dir !== null && !PHP\file_exists($patches_dir)) {
      PHP\mkdir($patches_dir, 0755, /* recursive = */ true);
    }

    $verbose = $this->manifest->isVerboseEnabled();
    $dest = await $this->genRepo<ShipItDestinationRepo>();

    $changesets = await $this->syncConfig
      ->genPostFilterChangesets($changesets, $dest);

    $changesets_applied = vec[];
    $changesets_skipped = vec[];
    foreach ($changesets as $changeset) {
      if ($patches_dir !== null) {
        $file = $patches_dir.
          '/'.
          $this->manifest->getDestinationBranch().
          '-'.
          $changeset->getID().
          '.patch';
        if (PHP\file_exists($file)) {
          ShipItLogger::out("Overwriting patch file: %s\n", $file);
        }
        PHP\file_put_contents($file, $dest::renderPatch($changeset));
        $changeset =
          $changeset->withDebugMessage('Saved patch file: %s', $file);
      }

      if ($verbose) {
        $changeset->dumpDebugMessages();
      }

      if (!$this->isValidChangeToSync($changeset)) {
        ShipItLogger::out(
          "  SKIP %s %s\n",
          $changeset->getShortID(),
          $changeset->getSubject(),
        );
        $changesets_skipped[] = $changeset;
        continue;
      }

      try {
        // @lint-ignore AWAIT_IN_LOOP These need to be committed one at a time
        await $dest->genCommitPatch(
          $changeset,
          $this->syncConfig->getShouldDoSubmodules(),
        );
        ShipItLogger::out(
          "  OK %s %s\n",
          $changeset->getShortID(),
          $changeset->getSubject(),
        );
        $changesets_applied[] = $changeset;
        continue;
      } catch (ShipItRepoException $e) {
        ShipItLogger::err(
          "Failed to apply patch %s (%s): %s\n",
          $changeset->getID(),
          $changeset->getMessage(),
          $e->getMessage(),
        );
        throw $e;
      } catch (\Exception $e) {
        ShipItLogger::err(
          "  !! %s ERROR: sync failed:\n",
          $changeset->getShortID(),
        );
        if ($verbose) {
          ShipItLogger::err("%s\n", $dest::renderPatch($changeset));
        }
        throw $e;
      }
    }

    await $this->genMaybeLogStats($changesets_applied, $changesets_skipped);
  }

  /**
   * Optionally logs stats about the sync to the user-specified file.
   *
   * @param $changesets_applied the changesets that were applied.
   */
  private async function genMaybeLogStats(
    vec<ShipItChangeset> $changesets_applied,
    vec<ShipItChangeset> $changesets_skipped,
  ): Awaitable<void> {
    $filename = $this->syncConfig->getStatsFilename();
    if ($filename === null) {
      return;
    }
    $destination_branch = $this->manifest->getDestinationBranch();
    // Support logging stats for a project with multiple branches.
    if (PHP\is_dir($filename)) {
      // Slashes are allowed in branch names but not filenames.
      $namesafe_branch =
        Regex\replace($destination_branch, re"/[^a-zA-Z0-9_\-.]/", '_');
      $filename = $filename.'/'.$namesafe_branch.'.json';
    }
    $source_repo = await $this->genRepo<ShipItSourceRepo>();
    $source_changeset = await $source_repo->genHeadChangeset();
    $destination_repo = await $this->genRepo<ShipItDestinationRepo>();
    $destination_changeset = await $destination_repo
      ->genHeadChangeset();
    $this->dumpSyncStatsToFile(
      $filename,
      shape(
        'source' => shape(
          'id' => $source_changeset?->getID(),
          'timestamp' => $source_changeset?->getTimestamp(),
          'branch' => $this->manifest->getSourceBranch(),
        ),
        'destination' => shape(
          'id' => $destination_changeset?->getID(),
          'timestamp' => $destination_changeset?->getTimestamp(),
          'branch' => $destination_branch,
        ),
        'changesets' => Vec\map(
          $changesets_applied,
          $changeset ==> shape(
            'id' => $changeset->getID(),
            'timestamp' => $changeset->getTimestamp(),
          ),
        ),
        'skipped' => Vec\map(
          $changesets_skipped,
          $changeset ==> shape(
            'id' => $changeset->getID(),
            'timestamp' => $changeset->getTimestamp(),
          ),
        ),
      ),
    );
  }

  private function dumpSyncStatsToFile(
    string $filename,
    self::TSyncStats $sync_stats,
  ): void {
    PHP\file_put_contents($filename, \json_encode($sync_stats));
  }

  /** Sync the change ONLY if:
  *  ChangeSet is not Empty OR
  *  ChangeSet isTaggedEmpty and the project allows empty commit.
  */
  private function isValidChangeToSync(ShipItChangeset $changeset): bool {
    $is_empty = $changeset->isEmptyChange();
    $is_tagged_empty_commit = $changeset->getIsTaggedEmptyCommit();
    return (
      !$is_empty ||
      ($this->syncConfig->getAllowEmptyCommits() && $is_tagged_empty_commit)
    );
  }

  <<__Memoize>>
  private async function genRepo<<<__Enforceable>> reify Trepo as IShipItRepo>(
  ): Awaitable<Trepo> {
    $manifest = $this->manifest;

    if (Trepo::class === ShipItSourceRepo::class) {
      $repo = await ShipItRepo::genTypedOpen<Trepo>(
        $manifest->getSourceSharedLock(),
        $manifest->getSourcePath(),
        $manifest->getSourceBranch(),
      );
      $repo->setUseNativeRenames($this->syncConfig->getNativeRenames());
      return $repo;
    }

    if (Trepo::class === ShipItDestinationRepo::class) {
      $repo = await ShipItRepo::genTypedOpen<Trepo>(
        $manifest->getDestinationSharedLock(),
        $manifest->getDestinationPath(),
        $manifest->getDestinationBranch(),
      );
      $repo->setUseNativeRenames($this->syncConfig->getNativeRenames());
      return $repo;
    }

    invariant_violation(
      'Got class %s, expected %s or %s',
      Trepo::class,
      ShipItSourceRepo::class,
      ShipItDestinationRepo::class,
    );
  }

  private async function genFindLastSyncedCommit(): Awaitable<string> {
    $dest = await $this->genRepo<ShipItDestinationRepo>();

    $src_commit = await $dest->genFindLastSourceCommit(
      $this->syncConfig->getDestinationRoots(),
      $this->manifest->getCommitMarker(),
    );
    if ($src_commit === null) {
      throw new ShipItException("Couldn't find synced commit id");
    }
    return $src_commit;
  }

  public static function addTrackingDataToString(
    string $message,
    string $commit_marker,
    string $rev,
  ): string {
    return Str\format("%s\n\n%s: %s", $message, $commit_marker, $rev);
  }

  public static function addTrackingData(
    ShipItManifest $manifest,
    ShipItChangeset $changeset,
    ?string $rev = null,
  ): ShipItChangeset {
    if ($rev === null) {
      $rev = $changeset->getID();
    }
    $new_message = self::addTrackingDataToString(
      $changeset->getMessage(),
      $manifest->getCommitMarker(),
      $rev,
    );
    // Co-authored-by must be the absolute last thing in the message
    $co_author_lines = $changeset->getCoAuthorLines();
    if (Str\length($co_author_lines) > 0) {
      $new_message .= "\n\n".$co_author_lines;
    }
    return $changeset->withMessage(Str\trim($new_message));
  }

  public static function getTrackingDataFromString(
    string $raw_changeset,
    string $commit_marker = '(fb)?shipit-source-id',
  ): ?string {
    $matches = dict[];
    PHP\preg_match_all(
      Str\format("/^ *%s: ?(?<commit>[a-z0-9]+)$/m", $commit_marker),
      $raw_changeset,
      inout $matches,
    );
    $last_match = C\last($matches as dict<_, _>) ?as dict<_, _>;
    if ($last_match is null) {
      return null;
    }
    $last_match = C\last($last_match);
    if ($last_match is null) {
      return null;
    }
    return $last_match as string;
  }
}
