<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItSaveConfigPhase extends ShipItPhase {
  const type TSavedConfig = shape(
    'destination' => shape(
      'branch' => string,
      'owner' => string,
      'project' => string,
    ),
    'source' => shape(
      'branch' => string,
      'roots' => ImmSet<string>,
    ),
  );

  private ?string $outputFile;

  public function __construct(
    private string $owner,
    private string $project,
  ) {
    $this->skip();
  }

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
        'write' => $x ==> {
          $this->unskip();
          $this->outputFile = $x;
        },
      ),
    };
  }

  public function renderConfig(
    ShipItBaseConfig $config,
  ): self::TSavedConfig {
    return shape(
      'destination' => shape(
        'branch' => $config->getDestinationBranch(),
        'owner' => $this->owner,
        'project' => $this->project,
      ),
      'source' => shape(
        'branch' => $config->getSourceBranch(),
        'roots' => $config->getSourceRoots(),
      ),
    );
  }

  <<__Override>>
  protected function runImpl(ShipItBaseConfig $config): void {
    invariant($this->outputFile !== null, 'impossible');
    \file_put_contents(
      $this->outputFile,
      \json_encode($this->renderConfig($config), \JSON_PRETTY_PRINT),
    );
    \printf("Finished phase: %s\n", $this->getReadableName());
    exit(0);
  }
}
