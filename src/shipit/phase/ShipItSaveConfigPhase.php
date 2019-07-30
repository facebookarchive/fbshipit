<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/el6u7wp5
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
      'roots' => keyset<string>,
    ),
  );

  private ?string $outputFile;

  public function __construct(private string $owner, private string $project) {
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
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'save-config-to::',
        'description' =>
          'Save configuration data for this project here and exit.',
        'write' => $x ==> {
          $this->unskip();
          $this->outputFile = $x;
          return true;
        },
      ),
    ];
  }

  public function renderConfig(ShipItBaseConfig $config): self::TSavedConfig {
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
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \file_put_contents(
      $this->outputFile,
      \json_encode($this->renderConfig($config), \JSON_PRETTY_PRINT),
    );
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \printf("Finished phase: %s\n", $this->getReadableName());
    exit(0);
  }
}
