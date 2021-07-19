<?hh
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/0rx59too
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{C, Dict, Regex, Str, Vec}; // @oss-enable

final class ShipItRepoGITException extends ShipItRepoException {}

/**
 * GIT specialization of ShipItRepo
 */
class ShipItRepoGIT
  extends ShipItRepo
  implements ShipItSourceRepo, ShipItDestinationRepo {

  const type TSubmoduleSpec = shape(
    'name' => string,
    'path' => string,
    'url' => string,
  );

  private string $branch = 'master';
  private ShipItTempDir $fakeHome;

  public function __construct(IShipItLock $lock, string $path, string $branch) {
    $this->fakeHome = new ShipItTempDir('fake_home_for_git');
    parent::__construct($lock, $path, $branch);
  }

  <<__Override>>
  public function setBranch(string $branch): bool {
    $this->branch = $branch;
    $this->gitCommand('checkout', $branch);
    return true;
  }

  <<__Override>>
  public function updateBranchTo(string $base_rev): void {
    if (Str\is_empty($this->branch)) {
      throw new ShipItRepoGITException(
        $this,
        'setBranch must be called first.',
      );
    }
    $this->gitCommand('checkout', '-B', $this->branch, $base_rev);
  }

  <<__Override>>
  public function getHeadChangeset(): ?ShipItChangeset {
    $rev = $this->gitCommand('rev-parse', $this->branch);

    $rev = Str\trim($rev);
    if (Str\trim($rev) === '') {
      return null;
    }
    return $this->getChangesetFromID($rev);
  }

  public function findLastSourceCommit(keyset<string> $roots): ?string {
    $log = $this->gitCommand(
      'log',
      '-1',
      '--grep',
      '^\\(fb\\)\\?shipit-source-id: \\?[a-z0-9]\\+\\s*$',
      ...$roots
    );
    $log = Str\trim($log);
    return ShipItSync::getTrackingDataFromString($log);
  }

  public function findNextCommit(
    string $revision,
    keyset<string> $roots,
  ): ?string {
    $log = $this->gitCommand(
      'log',
      $revision.'..',
      '--ancestry-path',
      '--no-merges',
      '--oneline',
      ...$roots
    );

    $log = Str\trim($log);
    if (Str\trim($log) === '') {
      return null;
    }
    $revs = Str\split(Str\trim($log), "\n");
    list($rev) = Str\split(C\lastx($revs), ' ', 2);
    return $rev;
  }

  private static function parseHeader(string $header): ShipItChangeset {
    $parts = Str\split(Str\trim($header), "\n\n", 2);
    $envelope = $parts[0];
    $message = C\count($parts) === 2 ? Str\trim($parts[1]) : '';

    $start_of_filelist = Str\search_last($message, "\n---\n ");
    if ($start_of_filelist !== null) {
      // Get rid of the file list when a summary is
      // included in the commit message
      $message = Str\trim(Str\slice($message, 0, $start_of_filelist));
    }

    $changeset = (new ShipItChangeset())->withMessage($message);

    $envelope = Str\replace_every($envelope, dict["\n\t" => ' ', "\n " => ' ']);
    foreach (Str\split($envelope, "\n") as $line) {
      $colon = Str\search($line, ':');
      if ($colon === null) {
        continue;
      }
      list($key, $value) = Str\split($line, ':', 2);
      $value = Str\trim($value);
      switch (Str\lowercase(Str\trim($key))) {
        case 'from':
          $changeset = $changeset->withAuthor($value);
          break;
        case 'subject':
          if (Str\starts_with_ci($value, '[PATCH] ')) {
            $value = Str\trim(Str\slice($value, 8));
          }
          $changeset = $changeset->withSubject($value);
          break;
        case 'date':
          $changeset = $changeset->withTimestamp(PHP\strtotime($value));
          break;
        /* added due to nonexhaustive switch */
        default:
          break;
      }

    }

    return $changeset;
  }

  public function getNativePatchFromID(string $revision): string {
    return $this->gitCommand(
      'format-patch',
      '--no-renames',
      '--no-stat',
      '--stdout',
      // use full SHAs to avoid inconsistent SHAs between calls
      '--full-index',
      '--format=', // Contain nothing but the code changes
      '-1',
      $revision,
    );
  }

  public function getNativeHeaderFromID(string $revision): string {
    $patch = $this->getNativePatchFromID($revision);
    return $this->getNativeHeaderFromIDWithPatch($revision, $patch);
  }

  private function getNativeHeaderFromIDWithPatch(
    string $revision,
    string $patch,
  ): string {
    $full_patch = $this->gitCommand(
      'format-patch',
      '--always',
      '--no-renames',
      '--no-stat',
      '--stdout',
      // use full SHAs to avoid inconsistent SHAs between calls
      '--full-index',
      '-1',
      $revision,
    );
    if (Str\length($patch) === 0) {
      // This is an empty commit, so everything is the header.
      return $full_patch;
    }
    $index = Str\search($full_patch, $patch);
    if ($index !== null) {
      return Str\slice($full_patch, 0, $index);
    }
    throw new ShipItRepoGITException($this, 'Could not extract patch header.');
  }

  public function getChangesetFromID(string $revision): ShipItChangeset {
    $patch = $this->getNativePatchFromID($revision);
    $header = $this->getNativeHeaderFromIDWithPatch($revision, $patch);
    $changeset = self::getChangesetFromExportedPatch($header, $patch);
    $changeset = $changeset->withID($revision);
    return $changeset;
  }

  <<__Override>>
  public static function getDiffsFromPatch(string $patch): vec<ShipItDiff> {
    $diffs = vec[];
    foreach (self::parsePatch($patch) as $hunk) {
      $diff = self::parseDiffHunk($hunk);
      if ($diff !== null) {
        $diffs[] = $diff;
      }
    }
    return $diffs;
  }

  public static function getChangesetFromExportedPatch(
    string $header,
    string $patch,
  ): ShipItChangeset {
    $ret = self::parseHeader($header);
    return $ret->withDiffs(self::getDiffsFromPatch($patch));
  }

  /**
   * Render patch suitable for `git am`
   */
  public static function renderPatch(ShipItChangeset $patch): string {
    /* Insert a space before patterns that will make `git am` think that a
     * line in the commit message is the start of a patch, which is an artifact
     * of the way `git am` tries to tell where the message ends and the diffs
     * begin. This fix is a hack; a better fix might be to use `git apply` and
     * `git commit` directly instead of `git am`, but this is an edge-case so
     * it's not worth it right now.
     *
     * https://github.com/git/git/blob/77bd3ea9f54f1584147b594abc04c26ca516d987/builtin/mailinfo.c#L701
     */
    $message = Regex\replace(
      $patch->getMessage(),
      re"/^(diff -|Index: |---(?:\s\S|\s*$))/m",
      ' $1',
    );

    // Mon Sep 17 is a magic date used by format-patch to distinguish from real
    // mailboxes. cf. https://git-scm.com/docs/git-format-patch
    $ret = "From {$patch->getID()} Mon Sep 17 00:00:00 2001\n".
      Str\format("From: %s\n", self::encodePatchHeader($patch->getAuthor())).
      "Date: ".
      PHP\date('r', $patch->getTimestamp()).
      "\n".
      Str\format(
        "Subject: [PATCH] %s\n\n",
        self::encodePatchHeader($patch->getSubject()),
      ).
      "{$message}\n---\n\n";
    foreach ($patch->getDiffs() as $diff) {
      $path = $diff['path'];
      $body = $diff['body'];

      $ret .= "diff --git a/{$path} b/{$path}\n{$body}";
    }
    $ret .= "--\n1.7.9.5\n";
    return $ret;
  }

  /**
   * Commit a standardized patch to the repo
   */
  public function commitPatch(
    ShipItChangeset $patch,
    bool $do_submodules = true,
  ): string {
    if (C\is_empty($patch->getDiffs())) {
      // This is an empty commit, which `git am` does not handle properly.
      $this->gitCommand(
        'commit',
        '--allow-empty',
        '--author',
        $patch->getAuthor(),
        '--date',
        (string)$patch->getTimestamp(),
        '-m',
        self::getCommitMessage($patch),
      );
      return $this->getHEADSha();
    }

    $diff = self::renderPatch($patch);
    try {
      $this->gitPipeCommand($diff, 'am', '--keep-non-patch', '--keep-cr');
    } catch (ShipItRepoGITException $e) {
      // If we are trying to git am on a non-git repo, for example
      $this->gitCommand('am', '--abort');
      throw $e;
    } catch (ShipItRepoException $e) {
      $this->gitCommand('am', '--abort');
      throw $e;
    } catch (ShipItShellCommandException $e) {
      $this->gitCommand('am', '--abort');
      throw $e;
    }

    if ($do_submodules) {
      $submodules = $this->getSubmodules();
    } else {
      $submodules = vec[];
    }
    foreach ($submodules as $submodule) {
      try {
        $this->prepareSubmoduleForPatch($submodule);
      } catch (ShipItShellCommandException $e) {
        // HACK: try once again with GitHub HTTPS authentication.
        // Since most submodules in use don't need auth, it's least disruptive
        // to do this only when the first attempt fails.
        $url = $submodule['url'];
        $match = Regex\first_match(
          $url,
          re"@https://github.com/(?<org>[^\./]+)/(?<project>[^\./]+)[\./$]@",
        );
        if ($match is null) {
          throw $e;
        }
        // FIXME: FB-specific
        try {
          // @oss-disable: FBGitHubUtils::configureSubmodule(
            // @oss-disable: $match['org'],
            // @oss-disable: $match['project'],
            // @oss-disable: $this->getPath(),
            // @oss-disable: $submodule,
          // @oss-disable: );
          $this->prepareSubmoduleForPatch($submodule);
        } finally {
          // Cleanup from submodule auth
          try {
            $this->gitCommand('restore', '.gitmodules');
          } catch (ShipItShellCommandException $_) {}
        }
      }
    }
    // DANGER ZONE!  Cleanup any removed submodules.
    $this->gitCommand('clean', '-f', '-f', '-d');
    return $this->getHEADSha();
  }

  private function prepareSubmoduleForPatch(
    self::TSubmoduleSpec $submodule,
  ): void {
    // If a submodule has changed, then we need to actually update to the
    // new version. + before commit hash represents changed submdoule.
    // - before commit hash represents an uninitialized submodule. Make
    // sure there is no leading whitespace that comes back when we get the
    // status since the first character will tell us whether submodule
    // changed.
    $sm_status = Str\trim_left(
      $this->gitCommand('submodule', 'status', $submodule['path']),
    );
    if ($sm_status === '') {
      // If the path exists, we know we are adding a submodule.
      $full_path = $this->getPath().'/'.$submodule['path'];
      $sha = Str\trim(Str\slice(
        \file_get_contents($full_path),
        Str\length('Subproject commit '),
      ));
      $this->gitCommand('rm', $submodule['path']);
      $this->gitCommand(
        'submodule',
        'add',
        '-f',
        '--name',
        $submodule['name'],
        $submodule['url'],
        $submodule['path'],
      );
      (new ShipItShellCommand($full_path, 'git', 'checkout', $sha))
        ->runSynchronously();
      $this->gitCommand('add', $submodule['path']);
      // Preserve any whitespace in the .gitmodules file.
      $this->gitCommand('checkout', 'HEAD', '.gitmodules');
      $this->gitCommand('commit', '--amend', '--no-edit');
    } else if ($sm_status[0] === '+') {
      $this->gitCommand(
        'submodule',
        'update',
        '--recursive',
        $submodule['path'],
      );
    } else if ($sm_status[0] === '-') {
      $this->gitCommand(
        'submodule',
        'update',
        '--init',
        '--recursive',
        $submodule['path'],
      );
    }
  }

  protected function gitPipeCommand(?string $stdin, string ...$args): string {
    if (!PHP\file_exists("{$this->path}/.git")) {
      throw new ShipItRepoGITException($this, $this->path." is not a GIT repo");
    }

    $command = (new ShipItShellCommand($this->path, 'git', ...$args))
      ->setEnvironmentVariables(dict[
        'GIT_CONFIG_NOSYSTEM' => '1',
        // GIT_CONFIG_NOGLOBAL was dropped because it was possible to use
        // HOME instead - see commit 8f323c00dd3c9b396b01a1aeea74f7dfd061bb7f in
        // git itself.
        'HOME' => $this->fakeHome->getPath(),
      ]);
    if ($stdin !== null) {
      $command->setStdIn($stdin);
    }
    return $command->runSynchronously()->getStdOut();
  }

  protected function gitCommand(string ...$args): string {
    return $this->gitPipeCommand(null, ...$args);
  }

  public static function cloneRepo(string $origin, string $path): void {
    invariant(
      !PHP\file_exists($path),
      '%s already exists, cowardly refusing to overwrite',
      $path,
    );

    $parent_path = PHP\dirname($path);
    if (!PHP\file_exists($parent_path)) {
      PHP\mkdir($parent_path, 0755, /* recursive = */ true);
    }

    if (ShipItRepo::$verbose & ShipItRepo::VERBOSE_FETCH) {
      ShipItLogger::err("** Cloning %s to %s\n", $origin, $path);
    }

    (
      new ShipItShellCommand($parent_path, 'git', 'clone', $origin, $path)
    )->runSynchronously();
  }

  <<__Override>>
  public function clean(): void {
    $this->gitCommand('clean', '-x', '-f', '-f', '-d');
  }

  <<__Override>>
  public function pushLfs(string $pull_endpoint, string $push_endpoint): void {
    invariant(
      PHP\file_exists($this->getPath().'/.gitattributes'),
      '.gitattributes not exists, cowardly refusing to pull lfs',
    );
    // ignore .lfsconfig. otherwise this would interfere
    // with the downstream consumer.
    invariant(
      !PHP\file_exists($this->getPath().'/.lfsconfig'),
      '.lfsconfig exists, needs to strip it in your config',
    );
    $this->gitCommand('lfs', 'install', '--local');
    $this->gitCommand('config', '--local', 'lfs.url', $pull_endpoint);
    $this->gitCommand('config', '--local', 'lfs.fetchrecentcommitsdays', '7');
    $this->gitCommand('lfs', 'fetch', '--recent');
    $this->gitCommand('config', '--local', 'lfs.pushurl', $push_endpoint);
    $this->gitCommand('lfs', 'push', 'origin', $this->branch);
  }

  <<__Override>>
  public function pull(): void {
    if (ShipItRepo::$verbose & ShipItRepo::VERBOSE_FETCH) {
      ShipItLogger::err("** Updating checkout in %s\n", $this->path);
    }

    try {
      $this->gitCommand('am', '--abort');
    } catch (ShipItShellCommandException $_e) {
      // ignore
    }

    $this->gitCommand('fetch', 'origin');
    $this->gitCommand('reset', '--hard', 'origin/'.$this->branch);
  }

  <<__Override>>
  public function getOrigin(): string {
    return Str\trim($this->gitCommand('remote', 'get-url', 'origin'));
  }

  public function push(): void {
    $this->gitCommand('push', 'origin', 'HEAD:'.$this->branch);
  }

  public function export(
    keyset<string> $roots,
    bool $do_submodules,
    ?string $rev = null,
  ): shape('tempDir' => ShipItTempDir, 'revision' => string) {
    if ($rev === null) {
      $rev = Str\trim($this->gitCommand('rev-parse', 'HEAD'));
    }

    $dest = new ShipItTempDir('git-export');
    $archive_name = Str\format('%s/archive', $dest->getPath());

    $command = vec[
      'archive',
      '--format=tar',
      '--output',
      $archive_name,
      $rev,
    ];
    $command = Vec\concat($command, $roots);
    $this->gitCommand(...$command);

    (
      new ShipItShellCommand(
        $dest->getPath(),
        'tar',
        'xf',
        $archive_name,
      )
    )
      ->runSynchronously();

    (
      new ShipItShellCommand($this->path, 'rm', $archive_name)
    )->runSynchronously();

    if ($do_submodules) {
      $submodules = $this->getSubmodules($roots);
    } else {
      $submodules = vec[];
    }
    // If we have any submodules, we'll need to set them up manually.
    foreach ($submodules as $submodule) {
      $status = $this->gitCommand('submodule', 'status', $submodule['path']);
      $sha = $status
        // Strip any -, +, or U at the start of the status (see the man page for
        // git-submodule).
        |> Regex\replace($$, re"@^[\-\+U]@", '')
        |> Str\split($$, ' ')[0];
      $dest_submodule_path = $dest->getPath().'/'.$submodule['path'];
      // This removes the empty directory for the submodule that gets created
      // by the git-archive command.
      PHP\rmdir($dest_submodule_path);
      // This will setup a file that looks just like how git stores submodules.
      PHP\file_put_contents($dest_submodule_path, 'Subproject commit '.$sha);
    }

    return shape('tempDir' => $dest, 'revision' => $rev);
  }

  protected function getHEADSha(): string {
    return Str\trim($this->gitCommand('log', '-1', "--pretty=format:%H"));
  }

  private function getSubmodules(
    ?keyset<string> $roots = null,
  ): vec<self::TSubmoduleSpec> {
    // The gitmodules file is in the repo root, so if this application is for
    // a set of source roots that does not contain the entire repository then
    // there are no relevant submodules.
    if ($roots !== null && !C\is_empty($roots) && !C\contains($roots, '')) {
      return vec[];
    }
    if (!PHP\file_exists($this->getPath().'/.gitmodules')) {
      return vec[];
    }
    $configs = $this->gitCommand('config', '-f', '.gitmodules', '--list');
    $configs = dict(PHP\parse_ini_string($configs) as KeyedContainer<_, _>)
      |> Dict\filter_keys($$, ($key) ==> {
        return Str\slice($key as string, 0, 10) === 'submodule.' &&
          (Str\slice($key as string, -5) === '.path' || Str\slice($key as string, -4) === '.url');
      });
    return Vec\keys($configs)
      |> Vec\filter($$, $key ==> Str\slice($key as string, -4) === '.url')
      |> Vec\map($$, $key ==> Str\slice($key as string, 10, Str\length($key as string) - 10 - 4))
      |> Vec\map(
        $$,
        $name ==> shape(
          'name' => $name,
          'path' => $configs['submodule.'.$name.'.path'] as string,
          'url' => $configs['submodule.'.$name.'.url'] as string,
        ),
      )
      |> Vec\filter(
        $$,
        $config ==> PHP\file_exists($this->getPath().'/'.($config['path'])),
      );
  }

  private static function encodePatchHeader(string $to_encode): string {
    return PHP\mb_encode_mimeheader($to_encode);
  }
}
