<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/ihs48fay
 */
namespace Facebook\ImportIt;

use namespace HH\Lib\{Str, Vec}; // @oss-enable
use type Facebook\ShipIt\ShipItChangeset;

/**
 * Specialization of ShipItRepoGIT
 */
final class ImportItRepoGIT extends \Facebook\ShipIt\ShipItRepoGIT {

  /**
   * Obtain a changeset from the GitHub repository for the Pull Request and
   * possibly return a revision that this PR is based on in the destination
   * repository.
   */
  public async function genChangesetAndBaseRevisionForPullRequest(
    ?string $pr_number,
    string $expected_head_rev,
    string $source_default_branch,
    bool $use_latest_base_revision,
  ): Awaitable<(ShipItChangeset, ?string)> {
    using $this->getSharedLock()->getExclusive();
    return await $this->genChangesetAndBaseRevisionForPullRequestLocked(
      $pr_number,
      $expected_head_rev,
      $source_default_branch,
      $use_latest_base_revision,
    );
  }

  private async function genChangesetAndBaseRevisionForPullRequestLocked(
    ?string $pr_number,
    string $expected_head_rev,
    string $source_default_branch,
    bool $use_latest_base_revision,
  ): Awaitable<(ShipItChangeset, ?string)> {
    if ($pr_number === null) {
      $actual_head_rev = Str\trim(
        await $this->genGitCommand('rev-parse', $expected_head_rev),
      );
      invariant(
        $expected_head_rev === $actual_head_rev,
        'Expected %s to be the HEAD of the import, but got %s',
        $expected_head_rev,
        $actual_head_rev,
      );
      $branch_name = 'ImportIt-patch';
      $commit_title = 'ImportIt commit';
    } else {
      // First, fetch the special head ref that GitHub creates for the PR.
      await $this->genGitCommand(
        'fetch',
        'origin',
        'refs/pull/'.$pr_number.'/head',
      );
      $actual_head_rev = Str\trim(
        await $this->genGitCommand('rev-parse', 'FETCH_HEAD'),
      );
      invariant(
        $expected_head_rev === $actual_head_rev,
        'Expected %s to be the HEAD of the pull request, but got %s',
        $expected_head_rev,
        $actual_head_rev,
      );
      $branch_name = 'ImportIt-patch-for-'.$pr_number;
      $commit_title = 'ImportIt commit for #'.$pr_number;
    }
    // Now compute the merge base with the default branch (that we would land
    // the pull request to).
    $merge_base = Str\trim(
      await $this->genGitCommand(
        'merge-base',
        $actual_head_rev,
        $source_default_branch,
      ),
    );
    // We now have enough information to generate a binary diff and commit it.
    $diff = await $this->genGitCommand(
      'diff',
      '--binary',
      $merge_base,
      $actual_head_rev,
    );
    await $this->genGitCommand('checkout', '-B', $branch_name, $merge_base);
    await $this->genSetBranch($branch_name);
    await $this->genGitPipeCommand(
      $diff,
      'apply',
      '--binary',
      // Ensure that submodule hashes are updated.
      '--index',
      // Read from stdin.
      '-',
    );
    await $this->genGitCommand('add', '--all');
    $full_status = Str\trim(await $this->genGitCommand('status'));
    $abbreviated_status = Str\split($full_status, "\n")
      |> Vec\take($$, 100)
      |> Str\join($$, "\n");
    if ($full_status !== $abbreviated_status) {
      $abbreviated_status .= "\n...";
    }
    await $this->genGitCommand(
      'commit',
      '--allow-empty',
      '-m',
      $commit_title."\n\n".$abbreviated_status,
    );

    $rev = Str\trim(await $this->genGitCommand('rev-parse', 'HEAD'));
    $changeset = await $this->genChangesetFromID($rev);
    if ($use_latest_base_revision) {
      $base_revision = null;
    } else {
      $base_revision = await $this->genFindLastSourceCommit(keyset[]);
    }
    return tuple($changeset, $base_revision);
  }
}
