<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
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
  final public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-push',
        'description' => 'Do not push the destination repository',
        'write' => $_ ==> $this->skip(),
      ),
    };
  }

  <<__Override>>
  final protected function runImpl(ShipItBaseConfig $config): void {
    $repo = ShipItRepo::open(
      $config->getDestinationPath(),
      $config->getDestinationBranch(),
    );
    invariant(
      $repo instanceof ShipItDestinationRepo,
      '%s is not a writable repository type - got %s, needed %s',
      $config->getDestinationPath(),
      \get_class($repo),
      ShipItDestinationRepo::class,
    );
    $repo->push();
  }
}
