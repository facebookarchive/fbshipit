<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/83mi107w
 */
namespace Facebook\ShipIt;

final class ShipItPullPhase extends ShipItPhase {
  public function __construct(private ShipItRepoSide $side) {}

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Pull '.$this->side.' repository';
  }

  <<__Override>>
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    $skip_arg = shape(
      'long_name' => 'skip-'.$this->side.'-pull',
      'description' => "Don't pull the ".$this->side." repository",
      'write' => (string $_) ==> $this->skip(),
    );

    if ($this->side === ShipItRepoSide::SOURCE) {
      return vec[
        $skip_arg,
        shape(
          'long_name' => 'skip-src-pull',
          'replacement' => 'skip-source-pull',
        ),
      ];
    } else {
      return vec[
        $skip_arg,
        shape(
          'long_name' => 'skip-dest-pull',
          'replacement' => 'skip-destination-pull',
        ),
      ];
    }
  }

  <<__Override>>
  protected function runImpl(ShipItBaseConfig $config): void {
    switch ($this->side) {
      case ShipItRepoSide::SOURCE:
        $lock = $config->getSourceSharedLock();
        $local_path = $config->getSourcePath();
        $branch = $config->getSourceBranch();
        break;
      case ShipItRepoSide::DESTINATION:
        $lock = $config->getDestinationSharedLock();
        $local_path = $config->getDestinationPath();
        $branch = $config->getDestinationBranch();
        break;
    }
    ShipItRepo::open($lock, $local_path, $branch)->pull();
  }
}
