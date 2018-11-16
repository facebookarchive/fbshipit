<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItPushLfsPhase extends ShipItPhase {

  public function __construct(
    private ShipItRepoSide $side,
    private string $organization,
    private string $project,
    bool $enabled,
  ) {
    if (!$enabled) {
      $this->skip();
    }
  }

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  final public function getReadableName(): string {
    return 'Push LFS for '.$this->side.' repository';
  }

  <<__Override>>
  final public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-lfs',
        'description' => 'Skip LFS syncing',
        'write' => $_ ==> $this->skip(),
      ),
    };
  }

  <<__Override>>
  final protected function runImpl(ShipItBaseConfig $config): void {
    switch ($this->side) {
      case ShipItRepoSide::SOURCE:
        $local_path = $config->getSourcePath();
        $branch = $config->getSourceBranch();
        break;
      case ShipItRepoSide::DESTINATION:
        $local_path = $config->getDestinationPath();
        $branch = $config->getDestinationBranch();
        break;
    }
    // FIXME LFS syncing only supported for internal->external
    ShipItRepo::open($local_path, $branch)
      ->pushLfs($this->getLfsPullEndpoint(), $this->getLfsPushEndpoint());
  }

  final private function getLfsPushEndpoint(): string {
    $pushUrl = 'https://github.com/'.
      $this->organization.
      '/'.
      $this->project.
      '.git'.
      '/info/lfs';
    $auth_url = ShipItGitHubUtils::authHttpsRemoteUrl(
      $pushUrl,
      ShipItTransport::HTTPS,
      FBGitHubUtils::getCredentialsForProject(
        $this->organization,
        $this->project,
      ),
    );
    return $auth_url;
  }

  // only dewey-lfs endpoint support now
  final private function getLfsPullEndpoint(): string {
    return 'https://dewey-lfs.vip.facebook.com/lfs';
  }
}
