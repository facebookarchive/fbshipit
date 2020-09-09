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

final class ShipItCreateNewRepoPhase extends ShipItPhase {
  private ?string $sourceCommit = null;
  private ?string $outputPath = null;
  private bool $shouldDoSubmodules = true;

  public function __construct(
    private (function(ShipItChangeset): ShipItChangeset) $filter,
    private shape('name' => string, 'email' => string) $committer,
  ) {
    $this->skip();
  }

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
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
      shape( // deprecated, renamed for consistency with verify
        'long_name' => 'special-create-new-repo',
        'replacement' => 'create-new-repo',
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
  public function runImpl(ShipItManifest $manifest): void {
    $output = $this->outputPath;
    try {
      if ($output === null) {
        $temp_dir = self::createNewGitRepo(
          $manifest,
          $this->filter,
          $this->committer,
          $this->shouldDoSubmodules,
          $this->sourceCommit,
        );
        // Do not delete the output directory.
        $temp_dir->keep();
        $output = $temp_dir->getPath();
      } else {
        self::createNewGitRepoAt(
          $manifest,
          $output,
          $this->filter,
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

  public static function createNewGitRepo(
    ShipItManifest $manifest,
    (function(ShipItChangeset): ShipItChangeset) $filter,
    shape('name' => string, 'email' => string) $committer,
    bool $do_submodules = true,
    ?string $revision = null,
  ): ShipItTempDir {
    $temp_dir = new ShipItTempDir('git-with-initial-commit');
    self::createNewGitRepoImpl(
      $temp_dir->getPath(),
      $manifest,
      $filter,
      $committer,
      $do_submodules,
      $revision,
    );
    return $temp_dir;
  }

  public static function createNewGitRepoAt(
    ShipItManifest $manifest,
    string $output_dir,
    (function(ShipItChangeset): ShipItChangeset) $filter,
    shape('name' => string, 'email' => string) $committer,
    bool $do_submodules = true,
    ?string $revision = null,
  ): void {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    if (\file_exists($output_dir)) {
      throw new ShipItException("path '$output_dir' already exists");
    }
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \mkdir($output_dir, 0755, /* recursive = */ true);

    try {
      self::createNewGitRepoImpl(
        $output_dir,
        $manifest,
        $filter,
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

  private static function createNewGitRepoImpl(
    string $output_dir,
    ShipItManifest $manifest,
    (function(ShipItChangeset): ShipItChangeset) $filter,
    shape('name' => string, 'email' => string) $committer,
    bool $do_submodules,
    ?string $revision = null,
  ): void {
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
    self::execSteps(
      $export_dir->getPath(),
      vec[
        vec['git', 'add', '.', '-f'],
        vec[
          'git',
          'commit',
          '-m',
          'initial unfiltered commit',
        ],
      ],
    );

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
    } finally {
      $export_lock->release();
    }
    $changeset = $exported_repo->getChangesetFromID('HEAD');
    invariant($changeset !== null, 'got a null changeset :/');
    $changeset = $changeset->withID($rev);
    $changeset = $filter($changeset)->withSubject('Initial commit');
    $changeset = ShipItSync::addTrackingData($manifest, $changeset, $rev);

    if ($manifest->isVerboseEnabled()) {
      $changeset->dumpDebugMessages();
    }

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
    } finally {
      $output_lock->release();
    }
    $filtered_repo->commitPatch($changeset, $do_submodules);
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
