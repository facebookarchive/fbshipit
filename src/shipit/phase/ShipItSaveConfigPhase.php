<?hh // strict
/**
 * Copyright (c) 2017-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the BSD-style license found in the
 * LICENSE file in the root directory of this source tree. An additional grant
 * of patent rights can be found in the PATENTS file in the same directory.
 */
namespace Facebook\ShipIt;

final class ShipItSaveConfigPhase extends ShipItPhase {
  private ?string $outputFile;

  public function __construct(
    private string $owner,
    private string $project,
    private ImmSet<string> $sourceRoots,
  ) {}

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Output ShipIt Config';
  }

  <<__Override>>
  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'save-config-to::',
        'description' =>
          'Save configuration data for this project here and exit.',
        'write' => $x ==> $this->outputFile = $x,
      ),
    };
  }

  <<__Override>>
  protected function runImpl(ShipItBaseConfig $config): void {
    if ($this->outputFile === null) {
      // Nothing to do here; carry on!
      return;
    }

    $data = ImmMap {
      'destinationBranch' => $config->getDestinationBranch(),
      'owner' => $this->owner,
      'project' => $this->project,
      'sourceBranch' => $config->getSourceBranch(),
      'sourceRoots' => $this->sourceRoots,
    };
    file_put_contents($this->outputFile, json_encode($data, JSON_PRETTY_PRINT));
    printf("Finished phase: %s\n", $this->getReadableName());
    exit(0);
  }
}
