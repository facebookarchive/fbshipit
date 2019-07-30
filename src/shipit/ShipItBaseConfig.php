<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/kxx0wgbs
 */
namespace Facebook\ShipIt;

final class ShipItBaseConfig {

  public function __construct(
    private string $baseDirectoryPath,
    private string $defaultSourceDirectoryName,
    private string $defaultDestinationDirectoryName,
    private keyset<string> $sourceRoots,
    private bool $commitMarkerPrefix = false,
  ) {}

  public function getBaseDirectory(): string {
    return $this->baseDirectoryPath;
  }

  public function withBaseDirectory(string $v): this {
    return $this->modified(
      $ret ==> {
        $ret->baseDirectoryPath = $v;
        return $ret->baseDirectoryPath;
      },
    );
  }

  private ?string $sourcePath;

  public function getSourcePath(): string {
    return $this->sourcePath ??
      $this->baseDirectoryPath.'/'.$this->defaultSourceDirectoryName;
  }

  public function withSourcePath(string $v): this {
    return $this->modified(
      $ret ==> {
        $ret->sourcePath = $v;
        return $ret->sourcePath;
      },
    );
  }

  private ?string $destinationPath;
  public function getDestinationPath(): string {
    return $this->destinationPath ??
      $this->baseDirectoryPath.'/'.$this->defaultDestinationDirectoryName;
  }

  public function withDestinationPath(string $v): this {
    return $this->modified(
      $ret ==> {
        $ret->destinationPath = $v;
        return $ret->destinationPath;
      },
    );
  }

  private bool $verbose = false;
  public function isVerboseEnabled(): bool {
    return $this->verbose;
  }

  public function withVerboseEnabled(): this {
    return $this->modified(
      $ret ==> {
        $ret->verbose = true;
        return $ret->verbose;
      },
    );
  }

  private bool $projectSpecificPhases = true;
  public function areProjectSpecificPhasesEnabled(): bool {
    return $this->projectSpecificPhases;
  }

  public function withProjectSpecificPhasesDisabled(): this {
    return $this->modified(
      $ret ==> {
        $ret->projectSpecificPhases = false;
        return $ret->projectSpecificPhases;
      },
    );
  }

  private string $sourceBranch = 'master';
  public function getSourceBranch(): string {
    return $this->sourceBranch;
  }

  public function withSourceBranch(string $branch): this {
    return $this->modified(
      $ret ==> {
        $ret->sourceBranch = $branch;
        return $ret->sourceBranch;
      },
    );
  }

  public function getSourceRoots(): keyset<string> {
    return $this->sourceRoots;
  }

  public function withSourceRoots(keyset<string> $roots): this {
    return $this->modified(
      $ret ==> {
        $ret->sourceRoots = $roots;
        return $ret->sourceRoots;
      },
    );
  }

  private string $destinationBranch = 'master';
  public function getDestinationBranch(): string {
    return $this->destinationBranch;
  }

  public function withDestinationBranch(string $branch): this {
    return $this->modified(
      $ret ==> {
        $ret->destinationBranch = $branch;
        return $ret->destinationBranch;
      },
    );
  }

  public function getCommitMarkerPrefix(): bool {
    return $this->commitMarkerPrefix;
  }

  public function withCommitMarkerPrefix(bool $bool): this {
    return $this->modified($ret ==> {
      $ret->commitMarkerPrefix = $bool;
      return $ret->commitMarkerPrefix;
    });
  }

  private function modified<Tignored>(
    (function(ShipItBaseConfig): Tignored) $mutator,
  ): ShipItBaseConfig {
    $ret = clone $this;
    $mutator($ret);
    return $ret;
  }
}
