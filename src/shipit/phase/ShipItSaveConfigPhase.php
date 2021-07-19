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

  public function renderConfig(ShipItManifest $manifest): self::TSavedConfig {
    return shape(
      'destination' => shape(
        'branch' => $manifest->getDestinationBranch(),
        'owner' => $this->owner,
        'project' => $this->project,
      ),
      'source' => shape(
        'branch' => $manifest->getSourceBranch(),
        'roots' => $manifest->getSourceRoots(),
      ),
    );
  }

  <<__Override>>
  protected async function genRunImpl(
    ShipItManifest $manifest,
  ): Awaitable<void> {
    invariant($this->outputFile !== null, 'impossible');
    PHP\file_put_contents(
      $this->outputFile,
      \json_encode($this->renderConfig($manifest), \JSON_PRETTY_PRINT),
    );
    ShipItLogger::out("Finished phase: %s\n", $this->getReadableName());
    throw new ShipItExitException(0);
  }
}
