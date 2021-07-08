<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/4zrm06z0
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C, Str, Vec}; // @oss-enable

final class ShipItCreateNewRepoPhase extends ShipItPhase {
  private ?string $sourceCommit = null;
  private ?string $outputPath = null;
  private bool $shouldDoSubmodules = true;

  public function __construct(
    private (function(ShipItChangeset): Awaitable<ShipItChangeset>) $genFilter,
    private shape('name' => string, 'email' => string) $committer,
  ) {
    $this->skip();
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Create a new git repo with an initial commit';
  }

  <<__Override>>
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'create-new-repo',
        'description' =>
          'Create a new git repository with a single commit, then exit',
        'write' => $_ ==> $this->unskip(),
      ),
      shape(
        'long_name' => 'create-new-repo-from-commit::',
        'description' =>
          'Like --create-new-repo, but at a specified source commit',
        'write' => $rev ==> {
          $this->sourceCommit = $rev;
          $this->unskip();
          return true;
        },
      ),
      shape(
        'long_name' => 'create-new-repo-output-path::',
        'description' =>
          'When using --create-new-repo or --create-new-repo-from-commit, '.
          'create the new repository in this directory',
        'write' => $path ==> {
          $this->outputPath = $path;
          return $this->outputPath;
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
    $output = $this->outputPath;
    try {
      if ($output === null) {
        $temp_dir = await self::genCreateNewGitRepo(
          $manifest,
          $this->genFilter,
          $this->committer,
          $this->shouldDoSubmodules,
          $this->sourceCommit,
        );
        // Do not delete the output directory.
        $temp_dir->keep();
        $output = $temp_dir->getPath();
      } else {
        await self::genCreateNewGitRepoAt(
          $manifest,
          $output,
          $this->genFilter,
          $this->committer,
          $this->shouldDoSubmodules,
          $this->sourceCommit,
        );
      }
    } catch (\Exception $e) {
      ShipItLogger::err("  Error: %s\n", $e->getMessage());
      throw new ShipItExitException(1);
    }

    ShipItLogger::out("  New repository created at %s\n", $output);
    throw new ShipItExitException(0);
  }

  private static function initGitRepo(
    string $path,
    shape('name' => string, 'email' => string) $committer,
  ): void {
    self::execSteps(
      $path,
      vec[
        vec['git', 'init'],
        vec['git', 'config', 'user.name', $committer['name']],
        vec['git', 'config', 'user.email', $committer['email']],
      ],
    );
  }

  public static async function genCreateNewGitRepo(
    ShipItManifest $manifest,
    (function(ShipItChangeset): Awaitable<ShipItChangeset>) $gen_filter,
    shape('name' => string, 'email' => string) $committer,
    bool $do_submodules = true,
    ?string $revision = null,
  ): Awaitable<ShipItTempDir> {
    $temp_dir = new ShipItTempDir('git-with-initial-commit');
    await self::genCreateNewGitRepoImpl(
      $temp_dir->getPath(),
      $manifest,
      $gen_filter,
      $committer,
      $do_submodules,
      $revision,
    );
    return $temp_dir;
  }

  public static async function genCreateNewGitRepoAt(
    ShipItManifest $manifest,
    string $output_dir,
    (function(ShipItChangeset): Awaitable<ShipItChangeset>) $gen_filter,
    shape('name' => string, 'email' => string) $committer,
    bool $do_submodules = true,
    ?string $revision = null,
  ): Awaitable<void> {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    if (\file_exists($output_dir)) {
      throw new ShipItException("path '$output_dir' already exists");
    }
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \mkdir($output_dir, 0755, /* recursive = */ true);

    try {
      await self::genCreateNewGitRepoImpl(
        $output_dir,
        $manifest,
        $gen_filter,
        $committer,
        $do_submodules,
        $revision,
      );
    } catch (\Exception $e) {
      (
        new ShipItShellCommand(null, 'rm', '-rf', $output_dir)
      )->runSynchronously();
      throw $e;
    }
  }

  private static async function genCreateNewGitRepoImpl(
    string $output_dir,
    ShipItManifest $manifest,
    (function(ShipItChangeset): Awaitable<ShipItChangeset>) $gen_filter,
    shape('name' => string, 'email' => string) $committer,
    bool $do_submodules,
    ?string $revision = null,
  ): Awaitable<void> {
    $logger = new ShipItVerboseLogger($manifest->isVerboseEnabled());

    $source = ShipItRepo::typedOpen(
      ShipItSourceRepo::class,
      $manifest->getSourceSharedLock(),
      $manifest->getSourcePath(),
      $manifest->getSourceBranch(),
    );

    $logger->out("  Exporting...");
    $export = $source->export(
      $manifest->getSourceRoots(),
      $do_submodules,
      $revision,
    );
    $export_dir = $export['tempDir'];
    $rev = $export['revision'];

    $logger->out("  Creating unfiltered commit...");
    self::initGitRepo($export_dir->getPath(), $committer);

    // The following code is necessarily convoluted. In order to support
    // creating/verifying repos that are greater than 2 GB we need to break the
    // unfiltered initial commit into a series of chunks that are small enough
    // to be processed by ShipIt (max Hack string size is 2GB). After ShipIt
    // has processed each chunked commit we use git commands to directly squash
    // everything, dodging the Hack string size limit.
    //
    // `git ls-files` is used to get a list of all files, which is then split
    // into chunks
    //
    // For each chunk, `git add` the files and then `git commit`
    //
    // To filter, find the initial commit SHA with `git rev-parse` and then
    // read all commits into ShipItChangesets, apply filtering, and commit.
    //
    // After everything, squash to a single commit (with ShipIt tracking info).

    $all_filenames_chunked = (
      new ShipItShellCommand(
        $export_dir->getPath(),
        'git',
        'ls-files',
        '--others',
      )
    )->runSynchronously()->getStdOut()
      |> Str\split($$, "\n")
      |> Vec\filter($$, ($line) ==> !Str\is_empty($line))
      // `git ls-files` returns files with escaping, if necessary. Since we
      // already escape arguments in ShipItShellCommand, we need to remove
      // the escaping from any files that have it:
      |> Vec\map($$, ($line) ==> Str\trim($line, '"'))
      // In an ideal world, we could chunk based on file size. But that's
      // non-trivial so the next best thing is to hope that average file size
      // is less than or equal to 4MB (aka 2GB / 500), fingers crossed:
      |> Vec\chunk($$, 500);

    $chunk_count = C\count($all_filenames_chunked);

    // @lint-ignore UNUSED_RESULT
    Vec\map_with_key($all_filenames_chunked, ($i, $chunk_filenames) ==> {
      if ($manifest->isVerboseEnabled()) {
        $logger->out("    Processing chunk %d/%d", $i + 1, $chunk_count);
      }
      self::execSteps(
        $export_dir->getPath(),
        vec[
          Vec\concat(vec['git', 'add', '--force'], $chunk_filenames),
          vec[
            'git',
            'commit',
            '--message',
            Str\format('unfiltered commit chunk #%d', $i),
          ],
        ],
      );
    });

    $logger->out("  Filtering...");
    $export_lock = ShipItScopedFlock::createShared(
      ShipItScopedFlock::getLockFilePathForRepoPath($export_dir->getPath()),
    );
    try {
      $exported_repo = ShipItRepo::typedOpen(
        ShipItSourceRepo::class,
        $export_lock,
        $export_dir->getPath(),
        'master',
      );
      $current_commit = (
        new ShipItShellCommand(
          $export_dir->getPath(),
          'git',
          'rev-list',
          '--max-parents=0',
          'HEAD',
        )
      )->runSynchronously()->getStdOut()
        |> Str\trim($$);
      $changesets = vec[];
      while ($current_commit !== null) {
        if ($manifest->isVerboseEnabled()) {
          $logger->out("    Processing %s", $current_commit);
        }
        $changesets[] =
          $exported_repo->getChangesetFromID($current_commit)?->withID($rev);
        $current_commit = $exported_repo->findNextCommit(
          $current_commit,
          keyset[],
        );
      }
    } finally {
      $export_lock->release();
    }
    $changesets = Vec\filter_nulls($changesets);
    invariant(!C\is_empty($changesets), 'got a null changeset :/');
    $changesets = await Vec\map_async($changesets, async ($changeset) ==> {
      $changeset = await $gen_filter($changeset);
      if ($manifest->isVerboseEnabled()) {
        $changeset->dumpDebugMessages();
      }
      return $changeset;
    });
    $changesets[0] = $changesets[0]
      |> $$->withSubject('Initial commit')
      |> ShipItSync::addTrackingData($manifest, $$, $rev);

    $logger->out("  Creating new repo...");
    self::initGitRepo($output_dir, $committer);
    $output_lock = ShipItScopedFlock::createShared(
      ShipItScopedFlock::getLockFilePathForRepoPath($output_dir),
    );
    try {
      $filtered_repo = ShipItRepo::typedOpen(
        ShipItDestinationRepo::class,
        $output_lock,
        $output_dir,
        '--orphan='.$manifest->getDestinationBranch(),
      );
      foreach ($changesets as $changeset) {
        $filtered_repo->commitPatch($changeset, $do_submodules);
      }

      // Now that we've filtered and committed all files into disparate chunks,
      // we need to squash the chunks into a single commit. Fortunately, the
      // following commands work just fine if HEAD == initial commit
      $initial_commit_sha = (
        new ShipItShellCommand(
          $output_dir,
          'git',
          'rev-list',
          '--max-parents=0',
          'HEAD',
        )
      )->runSynchronously()->getStdOut()
        |> Str\trim($$);
      self::execSteps(
        $output_dir,
        vec[
          // Rewind HEAD (but NOT checked out file contents) to initial commit:
          vec['git', 'reset', '--soft', $initial_commit_sha],
          // Amend initial commit with content from all chunks
          // (this preserves initial commit's message w/ ShipIt tracking details)
          vec['git', 'commit', '--amend', '--no-edit'],
        ],
      );
    } finally {
      $output_lock->release();
    }
  }

  private static function execSteps(
    string $path,
    vec<vec<string>> $steps,
  ): void {
    foreach ($steps as $step) {
      (new ShipItShellCommand($path, ...$step))->runSynchronously();
    }
  }
}
