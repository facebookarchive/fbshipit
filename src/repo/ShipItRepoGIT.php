<?hh
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

class ShipItRepoGITException extends ShipItRepoException {}

/**
 * GIT specialization of ShipItRepo
 */
class ShipItRepoGIT
  extends ShipItRepo
  implements ShipItSourceRepo, ShipItDestinationRepo {

  private string $branch = 'master';
  private ShipItTempDir $fakeHome;

  public function __construct(
    string $path,
    string $branch,
  ) {
    $this->fakeHome = new ShipItTempDir('fake_home_for_git');
    parent::__construct($path, $branch);
  }

  <<__Override>>
  public function setBranch(string $branch): bool {
    $this->branch = $branch;
    $this->gitCommand('checkout', $branch);
    return true;
  }

  public function findLastSourceCommit(
    ImmSet<string> $roots,
  ): ?string {
    $log = $this->gitCommand(
      'log', '-1', '--grep',
      '^\\(fb\\)\\?shipit-source-id: [a-z0-9]\\+$',
      ...$roots,
    );
    $log = trim($log);
    $matches = null;
    if (
      !preg_match(
        '/^ *(fb)?shipit-source-id: (?<commit>[a-z0-9]+)$/m',
        $log,
        $matches,
      )
    ) {
      return null;
    }
    if (!is_array($matches)) {
      return null;
    }
    if (!array_key_exists('commit', $matches)) {
      return null;
    }
    return $matches['commit'];
  }

  public function findNextCommit(
    string $revision,
    ImmSet<string> $roots,
  ): ?string {
    $log = $this->gitCommand(
      'log',
      $revision.'..',
      '--ancestry-path',
      '--no-merges',
      '--oneline',
      ...$roots,
    );

    $log = trim($log);
    if (trim($log) === '') {
      return null;
    }
    $revs = explode("\n", trim($log));
    list($rev) = explode(' ', array_pop($revs), 2);
    return $rev;
  }

  private static function parseHeader(string $header): ShipItChangeset {
    list($envelope, $message) = explode("\n\n", trim($header), 2);

    $message = trim($message);

    $start_of_filelist = strrpos($message, "\n---\n ");
    if ($start_of_filelist !== false) {
      // Get rid of the file list when a summary is
      // included in the commit message
      $message = trim(substr($message, 0, $start_of_filelist));
    } else if (strpos($message, "---\n ") === 0) {
      // Git rid of the file list in the situation where there is
      // no summary in the commit message (when it starts with "---\n").
      $message = '';
    }

    $changeset = (new ShipItChangeset())->withMessage($message);

    $envelope = str_replace(["\n\t","\n "], ' ', $envelope);
    foreach(explode("\n", $envelope) as $line) {
      $colon = strpos($line, ':');
      if ($colon === false) {
        continue;
      }
      list($key, $value) = explode(':', $line, 2);
      $value = trim($value);
      switch(strtolower(trim($key))) {
        case 'from':
          $changeset = $changeset->withAuthor($value);
          break;
        case 'subject':
          if (!strncasecmp($value, '[PATCH] ', 8)) {
            $value = trim(substr($value, 8));
          }
          $changeset = $changeset->withSubject($value);
          break;
        case 'date':
          $changeset = $changeset->withTimestamp(strtotime($value));
          break;
      }

    }

    return $changeset;
  }

  public function getNativePatchFromID(string $revision): string {
    return $this->gitCommand('format-patch', '-1', $revision, '--stdout');
  }

  public function getChangesetFromID(string $revision): ?ShipItChangeset {
    $patch = $this->getNativePatchFromID($revision);
    $changeset = self::getChangesetFromExportedPatch($patch);
    if ($changeset !== null) {
      $changeset = $changeset->withID($revision);
    }
    return $changeset;
  }

  public static function getChangesetFromExportedPatch(
    string $patch,
  ): ?ShipItChangeset {
    $ret = null;
    $diffs = Vector { };
    foreach(ShipItUtil::parsePatchWithHeader($patch) as $region) {
      if ($ret === null) {
        $ret = self::parseHeader($region);
        continue;
      }
      list($path, $body) = ShipItUtil::parseDiffRegion($region);
      $diffs[] = shape(
        'path' => $path,
        'body' => $body,
      );
    }
    if ($ret === null) {
      return $ret;
    }
    return $ret->withDiffs($diffs->toImmVector());
  }

  /**
   * Render patch suitable for `git am`
   */
  public function renderPatch(ShipItChangeset $patch): string {
    /* Insert a space before patterns that will make `git am` think that a
     * line in the commit message is the start of a patch, which is an artifact
     * of the way `git am` tries to tell where the message ends and the diffs
     * begin. This fix is a hack; a better fix might be to use `git apply` and
     * `git commit` directly instead of `git am`, but this is an edge-case so
     * it's not worth it right now.
     *
     * https://github.com/git/git/blob/77bd3ea9f54f1584147b594abc04c26ca516d987/builtin/mailinfo.c#L701
     */
    $message = preg_replace(
      '/^(diff -|Index: |---(?:\s\S|\s*$))/m',
      ' $1',
      $patch->getMessage(),
    );

    $ret = "From {$patch->getID()} ".
            date('D M d H:i:s Y', $patch->getTimestamp()) . "\n".
            "From: {$patch->getAuthor()}\n".
            "Date: " . date('r', $patch->getTimestamp()) . "\n".
            "Subject: [PATCH] {$patch->getSubject()}\n\n".
            "{$message}\n---\n\n";
    foreach($patch->getDiffs() as $diff) {
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
  public function commitPatch(ShipItChangeset $patch): string {
    $diff = $this->renderPatch($patch);
    try {
      $this->gitPipeCommand($diff, 'am', '--keep-non-patch', '--keep-cr');
      // If a submodule has changed, then we need to actually update to the
      // new version. + before commit hash represents changed submdoule. Make
      // sure there is no leading whitespace that comes back when we get the
      // status since the first character will tell us whether submodule
      // changed.
      $sm_status = ltrim($this->gitPipeCommand(null, 'submodule', 'status'));
      if ($sm_status !== '' && $sm_status[0] === '+') {
        $this->gitPipeCommand(null, 'submodule', 'update', '--recursive');
      }
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
    $log = trim($this->gitCommand('log', '-1'));
    list($commit) = explode("\n", $log, 2);
    list($_,$sha)   = explode(' ', $commit, 2);
    return (string)$sha;
  }

  protected function gitPipeCommand(?string $stdin, ...$args): string {
    if (!file_exists("{$this->path}/.git")) {
      throw new ShipItRepoGITException(
        $this,
        $this->path." is not a GIT repo",
      );
    }

    $command = (new ShipItShellCommand($this->path, 'git', ...$args))
      ->setEnvironmentVariables(ImmMap {
        'GIT_CONFIG_NOSYSTEM' => '1',
        // GIT_CONFIG_NOGLOBAL was dropped because it was possible to use
        // HOME instead - see commit 8f323c00dd3c9b396b01a1aeea74f7dfd061bb7f in
        // git itself.
        'HOME' => $this->fakeHome->getPath(),
      });
    if ($stdin) {
      $command->setStdIn($stdin);
    }
    return $command->runSynchronously()->getStdOut();
  }

  protected function gitCommand(...$args): string {
    return $this->gitPipeCommand(null, ...$args);
  }

  public static function cloneRepo(
    string $origin,
    string $path,
  ): void {
    invariant(
      !file_exists($path),
      '%s already exists, cowardly refusing to overwrite',
      $path,
    );

    $parent_path = dirname($path);
    if (!file_exists($parent_path)) {
      mkdir($parent_path, 0755, /* recursive = */ true);
    }

    if (ShipItRepo::$VERBOSE & ShipItRepo::VERBOSE_FETCH) {
      fwrite(STDERR, "** Cloning $origin to $path\n");
    }

    (new ShipItShellCommand(
      $parent_path,
      'git', 'clone', $origin, $path,
    ))->runSynchronously();
  }

  <<__Override>>
  public function pull(): void {
    if (ShipItRepo::$VERBOSE & ShipItRepo::VERBOSE_FETCH) {
      fwrite(STDERR, "** Updating checkout in {$this->path}\n");
    }

    try {
      $this->gitCommand('am', '--abort');
    } catch (ShipItShellCommandException $e) {
      // ignore
    }

    $this->gitCommand('fetch', 'origin');
    $this->gitCommand('reset', '--hard', 'origin/'.$this->branch);
  }

  public function push(): void {
    $this->gitCommand('push', 'origin', 'HEAD:'.$this->branch);
  }

  public function export(
    ImmSet<string> $roots,
    ?string $rev = null,
  ): shape('tempDir' => ShipItTempDir, 'revision' => string) {
    if ($rev === null) {
      $rev = trim($this->gitCommand('rev-parse', 'HEAD'));
    }

    $command = Vector {
      'archive',
      '--format=tar',
      $rev,
    };
    $command->addAll($roots);
    $tar = $this->gitCommand(...$command);

    $dest = new ShipItTempDir('git-export');
    (new ShipItShellCommand(
      $dest->getPath(),
      'tar',
      'x',
    ))->setStdIn($tar)->runSynchronously();

    return shape('tempDir' => $dest, 'revision' => $rev);
  }
}
