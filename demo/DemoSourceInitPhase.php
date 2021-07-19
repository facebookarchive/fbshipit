<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

final class DemoSourceRepoInitPhase extends ShipItPhase {
  private bool $allowNuke = false;
  private string $name = "fbshipit-demo";

  <<__Override>>
  public function getReadableName(): string {
    return 'Initialize source '.$this->name.' repository';
  }

  <<__Override>>
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'skip-source-init',
        'description' => "Don't initialize the repository",
        'write' => $_ ==> $this->skip(),
      ),
    ];
  }

  <<__Override>>
  public async function genRunImpl(ShipItManifest $manifest): Awaitable<void> {
    $local_path = $manifest->getSourcePath();

    if (PHP\is_dir($local_path)) {
      return;
    }

    $command = vec['git', 'clone', 'https://github.com/facebook/fbshipit.git'];
    $local_parent_path = PHP\dirname($local_path);
    if (!PHP\is_dir($local_parent_path)) {
      PHP\mkdir($local_parent_path, 0755, /* recursive = */ true);
    }
    // Make sure that "remove stale temp file" jobs don't clean this up
    PHP\touch($local_parent_path);

    (new ShipItShellCommand($local_parent_path, ...$command))
      ->setRetries(2)
      ->setFailureHandler(
        $_ ==> (
          new ShipItShellCommand($local_parent_path, 'rm', '-rf', $local_path)
        )->runSynchronously(),
      )
      ->runSynchronously();
  }

  public static function isMonorepo(string $_name): bool {
    return true;
  }
}
