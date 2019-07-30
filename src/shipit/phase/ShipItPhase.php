<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/lhprnur6
 */
namespace Facebook\ShipIt;

abstract class ShipItPhase {
  private bool $skipped = false;

  abstract public function getReadableName(): string;
  abstract protected function runImpl(ShipItBaseConfig $config): void;

  /**
   * This allows you to build multi-project automation.
   *
   * It gives you a guarantee that your generic tooling is only going to do
   * generic things.
   *
   * For example, Facebook will be using this to automatically test that diffs
   * don't break the push by running with --skip-push --skip-project-specific;
   * some projects have custom build and test phases that aren't relevant,
   * others do secondary pushes to an internal mirror or public gh-pages
   * branches which would be undesired and harmful in this context.
   */
  abstract protected function isProjectSpecific(): bool;

  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[];
  }

  final public function isSkipped(): bool {
    return $this->skipped;
  }

  final protected function skip(): void {
    $this->skipped = true;
  }

  final protected function unskip(): void {
    $this->skipped = false;
  }

  final public function run(ShipItBaseConfig $config): void {
    if (
      $this->isProjectSpecific() && !$config->areProjectSpecificPhasesEnabled()
    ) {
      $this->skip();
    }

    if ($this->isSkipped()) {
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \printf("Skipping phase: %s\n", $this->getReadableName());
      return;
    }
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \printf(
      "Starting phase%s: %s\n",
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      $config->isVerboseEnabled() ? ' ('.\date('H:i:s').')' : '',
      $this->getReadableName(),
    );
    $this->runImpl($config);
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \printf(
      "Finished phase%s: %s\n",
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      $config->isVerboseEnabled() ? ' ('.\date('H:i:s').')' : '',
      $this->getReadableName(),
    );
  }
}
