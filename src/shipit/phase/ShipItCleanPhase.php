<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/pwbsg729
 */
namespace Facebook\ShipIt;

final class ShipItCleanPhase extends ShipItPhase {
  public function __construct(private ShipItRepoSide $side) {}

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  final public function getReadableName(): string {
    return 'Clean '.$this->side.' repository';
  }

  <<__Override>>
  final public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'skip-'.$this->side.'-clean',
        'description' => 'Do not clean the '.$this->side.' repository',
        'write' => $_ ==> $this->skip(),
      ),
    ];
  }

  <<__Override>>
  final protected function runImpl(ShipItBaseConfig $config): void {
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
    ShipItRepo::open($lock, $local_path, $branch)->clean();
  }
}
