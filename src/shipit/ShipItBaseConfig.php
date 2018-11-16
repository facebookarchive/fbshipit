<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItBaseConfig {
  public function __construct(
    private string $baseDirectoryPath,
    private string $defaultSourceDirectoryName,
    private string $defaultDestinationDirectoryName,
    private ImmSet<string> $sourceRoots,
  ) { }

  public function getBaseDirectory(): string {
    return $this->baseDirectoryPath;
  }

  public function withBaseDirectory(string $v): this {
    return $this->modified($ret ==> $ret->baseDirectoryPath = $v);
  }

  private ?string $sourcePath;

  public function getSourcePath(): string {
    return $this->sourcePath
      ?? $this->baseDirectoryPath.'/'.$this->defaultSourceDirectoryName;
  }

  public function withSourcePath(string $v): this {
    return $this->modified($ret ==> $ret->sourcePath = $v);
  }

  private ?string $destinationPath;
  public function getDestinationPath(): string {
    return $this->destinationPath
      ?? $this->baseDirectoryPath.'/'.$this->defaultDestinationDirectoryName;
  }

  public function withDestinationPath(string $v): this {
    return $this->modified($ret ==> $ret->destinationPath = $v);
  }

  private bool $verbose = false;
  public function isVerboseEnabled(): bool {
    return $this->verbose;
  }

  public function withVerboseEnabled(): this {
    return $this->modified($ret ==> $ret->verbose = true);
  }

  private bool $projectSpecificPhases = true;
  public function areProjectSpecificPhasesEnabled(): bool {
    return $this->projectSpecificPhases;
  }

  public function withProjectSpecificPhasesDisabled(): this {
    return $this->modified($ret ==> $ret->projectSpecificPhases = false);
  }

  private string $sourceBranch = 'master';
  public function getSourceBranch(): string {
    return $this->sourceBranch;
  }

  public function withSourceBranch(string $branch): this {
    return $this->modified($ret ==> $ret->sourceBranch = $branch);
  }

  public function getSourceRoots(): ImmSet<string> {
    return $this->sourceRoots;
  }

  public function withSourceRoots(ImmSet<string> $roots): this {
    return $this->modified($ret ==> $ret->sourceRoots = $roots);
  }

  private string $destinationBranch = 'master';
  public function getDestinationBranch(): string {
    return $this->destinationBranch;
  }

  public function withDestinationBranch(string $branch): this {
    return $this->modified($ret ==> $ret->destinationBranch = $branch);
  }

  private function modified<Tignored>(
    (function(ShipItBaseConfig):Tignored) $mutator,
  ): ShipItBaseConfig {
    $ret = clone $this;
    $mutator($ret);
    return $ret;
  }
}
