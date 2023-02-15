<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
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
class ShipItRepoGIT extends ShipItRepo {

  const type TSubmoduleSpec = shape(
    'name' => string,
    'path' => string,
    'url' => string,
  );

  private string $branch = 'master';
  private ShipItTempDir $fakeHome;

  public function __construct(IShipItLock $lock, string $path) {
    $this->fakeHome = new ShipItTempDir('fake_home_for_git');
    parent::__construct($lock, $path);
  }

  <<__Override>>
  public async function genSetBranch(string $branch): Awaitable<bool> {
    $this->branch = $branch;
    await $this->genGitCommand('checkout', $branch);
    return true;
  }

  <<__Override>>
  public async function genUpdateBranchTo(string $base_rev): Awaitable<void> {
    if (Str\is_empty($this->branch)) {
      throw
        new ShipItRepoGITException($this, 'setBranch must be called first.');
    }
    await $this->genGitCommand('checkout', '-B', $this->branch, $base_rev);
  }

  <<__Override>>
  public async function genHeadChangeset(): Awaitable<?ShipItChangeset> {
    $rev = await $this->genGitCommand('rev-parse', $this->branch);

    $rev = Str\trim($rev);
    if (Str\trim($rev) === '') {
      return null;
    }
    return await $this->genChangesetFromID($rev);
  }

  public async function genFindLastSourceCommit(
    keyset<string> $roots,
    string $commit_marker,
  ): Awaitable<?string> {
    $log = await $this->genGitCommand(
      'log',
      '-1',
      '--grep',
      Str\format('^%s: \\?[a-z0-9]\\+\\s*$', $commit_marker),
      ...$roots
    );
    $log = Str\trim($log);
    return ShipItSync::getTrackingDataFromString($log, $commit_marker);
  }

  public async function genFindNextCommit(
    string $revision,
    keyset<string> $roots,
  ): Awaitable<?string> {
    $log = await $this->genGitCommand(
      'log',
      $revision.'..',
      '--ancestry-path',
      '--no-merges',
      '--format=%H',
      '--',
      ...$roots
    );

    $log = Str\trim($log);
    if ($log === '') {
      return null;
    }
    return Str\split($log, "\n") |> C\lastx($$);
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

  public async function genNativePatchFromID(
    string $revision,
  ): Awaitable<string> {
    if ($this->useNativeRenames) {
      return await $this->genGitCommand(
        'format-patch',
        '--no-stat',
        '--stdout',
        '--full-index',
        '--format=',
        '-1',
        $revision,
      );
    } else {
      return await $this->genGitCommand(
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
  }

  public async function genNativeHeaderFromID(
    string $revision,
  ): Awaitable<string> {
    $patch = await $this->genNativePatchFromID($revision);
    return await $this->genNativeHeaderFromIDWithPatch($revision, $patch);
  }

  private async function genNativeHeaderFromIDWithPatch(
    string $revision,
    string $patch,
  ): Awaitable<string> {
    if ($this->useNativeRenames) {
      $full_patch = await $this->genGitCommand(
        'format-patch',
        '--always',
        '--no-stat',
        '--stdout',
        '--full-index',
        '-1',
        $revision,
      );
    } else {
      $full_patch = await $this->genGitCommand(
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
    }
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

  public async function genChangesetFromID(
    string $revision,
  ): Awaitable<ShipItChangeset> {
    $patch = await $this->genNativePatchFromID($revision);
    $header = await $this->genNativeHeaderFromIDWithPatch($revision, $patch);
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
    $message = self::fixInlinePatchesInCommitMessage($patch->getMessage());

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
      $new_path = Shapes::idx($diff, 'new_path', $path) ?? $path;
      $body = $diff['body'];

      $ret .= "diff --git a/{$path} b/{$new_path}\n{$body}";
    }
    $ret .= "--\n1.7.9.5\n";
    return $ret;
  }

  /**
   * Commit a standardized patch to the repo
   */
  public async function genCommitPatch(
    ShipItChangeset $patch,
    bool $do_submodules = true,
  ): Awaitable<string> {
    if (C\is_empty($patch->getDiffs())) {
      // This is an empty commit, which `git am` does not handle properly.
      await $this->genGitCommand(
        'commit',
        '--allow-empty',
        '--author',
        $patch->getAuthor(),
        '--date',
        (string)$patch->getTimestamp(),
        '-m',
        self::getCommitMessage($patch),
      );
      return await $this->genHEADSha();
    }

    $diff = self::renderPatch($patch);
    try {
      await $this->genGitPipeCommand(
        $diff,
        'am',
        '--keep-non-patch',
        '--keep-cr',
        '--committer-date-is-author-date',
      );
    } catch (ShipItRepoGITException $e) {
      // If we are trying to git am on a non-git repo, for example
      await $this->genGitCommand('am', '--abort');
      throw $e;
    } catch (ShipItRepoException $e) {
      await $this->genGitCommand('am', '--abort');
      throw $e;
    } catch (ShipItShellCommandException $e) {
      await $this->genGitCommand('am', '--abort');
      throw $e;
    }

    if ($do_submodules) {
      $submodules = await $this->genSubmodules();
    } else {
      $submodules = vec[];
    }
    foreach ($submodules as $submodule) {
      try {
        // @lint-ignore AWAIT_IN_LOOP We need to do this serially
        await $this->genPrepareSubmoduleForPatch($submodule);
      } catch (ShipItShellCommandException $e) {
        // HACK: try once again with GitHub HTTPS authentication.
        // Since most submodules in use don't need auth, it's least disruptive
        // to do this only when the first attempt fails.
        $url = $submodule['url'];
        $match = Regex\first_match(
          $url,
          re"@https://github.com/(?<org>[^\./]+)/(?<project>[^\./]+)[\./]?@",
        );
        if ($match is null) {
          throw $e;
        }
        // FIXME: FB-specific
        try {
          // @lint-ignore AWAIT_IN_LOOP We need to do this serially
          // @oss-disable: await FBGitHubUtils::genConfigureSubmodule(
            // @oss-disable: $match['org'],
            // @oss-disable: $match['project'],
            // @oss-disable: $this->getPath(),
            // @oss-disable: $submodule,
          // @oss-disable: );
          // @lint-ignore AWAIT_IN_LOOP We need to do this serially
          await $this->genPrepareSubmoduleForPatch($submodule);
        } finally {
          // Cleanup from submodule auth
          try {
            // @lint-ignore AWAIT_IN_LOOP We need to do this serially
            await $this->genGitCommand('restore', '.gitmodules');
          } catch (ShipItShellCommandException $_) {
          }
        }
      }
    }
    // DANGER ZONE!  Cleanup any removed submodules.
    await $this->genGitCommand('clean', '-f', '-f', '-d');
    return await $this->genHEADSha();
  }

  private async function genPrepareSubmoduleForPatch(
    self::TSubmoduleSpec $submodule,
  ): Awaitable<void> {
    // If a submodule has changed, then we need to actually update to the
    // new version. + before commit hash represents changed submdoule.
    // - before commit hash represents an uninitialized submodule. Make
    // sure there is no leading whitespace that comes back when we get the
    // status since the first character will tell us whether submodule
    // changed.
    $sm_status = Str\trim_left(
      await $this->genGitCommand('submodule', 'status', $submodule['path']),
    );
    if ($sm_status === '') {
      // If the path exists, we know we are adding a submodule.
      $full_path = $this->getPath().'/'.$submodule['path'];
      $sha = Str\trim(Str\slice(
        \file_get_contents($full_path),
        Str\length('Subproject commit '),
      ));
      await $this->genGitCommand('rm', $submodule['path']);
      await $this->genGitCommand(
        'submodule',
        'add',
        '-f',
        '--name',
        $submodule['name'],
        $submodule['url'],
        $submodule['path'],
      );
      await (new ShipItShellCommand($full_path, 'git', 'checkout', $sha))
        ->genRun();
      await $this->genGitCommand('add', $submodule['path']);
      // Preserve any whitespace in the .gitmodules file.
      await $this->genGitCommand('checkout', 'HEAD', '.gitmodules');
      await $this->genGitCommand('commit', '--amend', '--no-edit');
    } else if ($sm_status[0] === '+') {
      await $this->genGitCommand(
        'submodule',
        'update',
        '--recursive',
        $submodule['path'],
      );
    } else if ($sm_status[0] === '-') {
      await $this->genGitCommand(
        'submodule',
        'update',
        '--init',
        '--recursive',
        $submodule['path'],
      );
    }
  }

  protected async function genGitPipeCommand(
    ?string $stdin,
    string ...$args
  ): Awaitable<string> {
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
    return (await $command->genRun())->getStdOut();
  }

  protected async function genGitCommand(string ...$args): Awaitable<string> {
    return await $this->genGitPipeCommand(null, ...$args);
  }

  public static async function genCloneRepo(
    string $origin,
    string $path,
  ): Awaitable<void> {
    invariant(
      !PHP\file_exists($path),
      '%s already exists, cowardly refusing to overwrite',
      $path,
    );

    $parent_path = PHP\dirname($path);
    if (!PHP\file_exists($parent_path)) {
      PHP\mkdir($parent_path, 0755, /* recursive = */ true);
    }

    await (
      new ShipItShellCommand($parent_path, 'git', 'clone', $origin, $path)
    )->genRun();
  }

  <<__Override>>
  public async function genClean(): Awaitable<void> {
    await $this->genGitCommand('clean', '-x', '-f', '-f', '-d');
  }

  <<__Override>>
  public async function genPushLfs(
    string $pull_endpoint,
    string $push_endpoint,
  ): Awaitable<void> {
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
    await $this->genGitCommand('lfs', 'install', '--local');
    await $this->genGitCommand('config', '--local', 'lfs.url', $pull_endpoint);
    await $this->genGitCommand(
      'config',
      '--local',
      'lfs.fetchrecentcommitsdays',
      '7',
    );
    await $this->genGitCommand('lfs', 'fetch', '--recent');
    await $this->genGitCommand(
      'config',
      '--local',
      'lfs.pushurl',
      $push_endpoint,
    );
    await $this->genGitCommand('lfs', 'push', 'origin', $this->branch);
  }

  <<__Override>>
  public async function genPull(): Awaitable<void> {
    try {
      await $this->genGitCommand('am', '--abort');
    } catch (ShipItShellCommandException $_e) {
      // ignore
    }

    await $this->genGitCommand('fetch', 'origin');
    try {
      await $this->genGitCommand('reset', '--hard', 'origin/'.$this->branch);
    } catch (ShipItShellCommandException $_) {
      // In the case we are using a raw SHA as a "branch", this will work
      // instead:
      await $this->genGitCommand('reset', '--hard', $this->branch);
    }
  }

  <<__Override>>
  public async function genOrigin(): Awaitable<string> {
    return Str\trim(await $this->genGitCommand('remote', 'get-url', 'origin'));
  }

  public async function genPush(): Awaitable<void> {
    await $this->genGitCommand('push', 'origin', 'HEAD:'.$this->branch);
  }

  public async function genExport(
    keyset<string> $roots,
    bool $do_submodules,
    ?string $rev = null,
  ): Awaitable<shape('tempDir' => ShipItTempDir, 'revision' => string)> {
    if ($rev === null) {
      $rev = Str\trim(await $this->genGitCommand('rev-parse', 'HEAD'));
    }

    // Some projects have a .gitattributes file that does things we don't want
    // (like exclude files from exports, and enabling format subsitutions).
    // We need to disable all that in order to get a correct base to compare
    // against.
    PHP\file_put_contents(
      Str\format('%s/.git/info/attributes', $this->getPath()),
      "* !export-ignore -export-subst",
    );

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
    await $this->genGitCommand(...$command);

    await (new ShipItShellCommand($dest->getPath(), 'tar', 'xf', $archive_name))
      ->genRun();

    await (new ShipItShellCommand($this->path, 'rm', $archive_name))->genRun();

    if ($do_submodules) {
      $submodules = await $this->genSubmodules($roots);
    } else {
      $submodules = vec[];
    }
    // If we have any submodules, we'll need to set them up manually.
    foreach ($submodules as $submodule) {
      // @lint-ignore AWAIT_IN_LOOP We need to do this serially
      $status = await $this->genGitCommand(
        'submodule',
        'status',
        $submodule['path'],
      );
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

  protected async function genHEADSha(): Awaitable<string> {
    return
      Str\trim(await $this->genGitCommand('log', '-1', "--pretty=format:%H"));
  }

  private async function genSubmodules(
    ?keyset<string> $roots = null,
  ): Awaitable<vec<self::TSubmoduleSpec>> {
    // The gitmodules file is in the repo root, so if this application is for
    // a set of source roots that does not contain the entire repository then
    // there are no relevant submodules.
    if ($roots !== null && !C\is_empty($roots) && !C\contains($roots, '')) {
      return vec[];
    }
    if (!PHP\file_exists($this->getPath().'/.gitmodules')) {
      return vec[];
    }
    $configs =
      await $this->genGitCommand('config', '-f', '.gitmodules', '--list');
    $configs = dict(PHP\parse_ini_string($configs) as KeyedContainer<_, _>)
      |> Dict\filter_keys($$, ($key) ==> {
        return Str\slice($key as string, 0, 10) === 'submodule.' &&
          (
            Str\slice($key as string, -5) === '.path' ||
            Str\slice($key as string, -4) === '.url'
          );
      });
    return Vec\keys($configs)
      |> Vec\filter($$, $key ==> Str\slice($key as string, -4) === '.url')
      |> Vec\map(
        $$,
        $key ==>
          Str\slice($key as string, 10, Str\length($key as string) - 10 - 4),
      )
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
