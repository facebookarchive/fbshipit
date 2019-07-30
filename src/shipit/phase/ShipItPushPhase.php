<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/c1c26x5q
 */
namespace Facebook\ShipIt;

final class ShipItPushPhase extends ShipItPhase {
  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  final public function getReadableName(): string {
    return "Push destination repository";
  }

  <<__Override>>
  final public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'skip-push',
        'description' => 'Do not push the destination repository',
        'write' => $_ ==> $this->skip(),
      ),
    ];
  }

  <<__Override>>
  final protected function runImpl(ShipItBaseConfig $config): void {
    $repo = ShipItRepo::open(
      $config->getDestinationPath(),
      $config->getDestinationBranch(),
    );
    invariant(
      $repo is ShipItDestinationRepo,
      '%s is not a writable repository type - got %s, needed %s',
      $config->getDestinationPath(),
      \get_class($repo),
      ShipItDestinationRepo::class,
    );
    $repo->push();
  }
}
