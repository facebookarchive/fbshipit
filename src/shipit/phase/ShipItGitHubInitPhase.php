<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItGitHubInitPhase extends ShipItPhase {

  private bool $anonymousHttps = false;

  public function __construct(
    private string $organization,
    private string $project,
    private ShipItRepoSide $side,
    private ShipItTransport $transport,
    private classname<ShipItGitHubUtils> $github_utils,
  ): void {
  }

  <<__Override>>
  protected function isProjectSpecific(): bool {
    return false;
  }

  <<__Override>>
  public function getReadableName(): string {
    return 'Initialize '.$this->side.' GitHub repository';
  }

  <<__Override>>
  public function getCLIArguments(): ImmVector<ShipItCLIArgument> {
    return ImmVector {
      shape(
        'long_name' => 'skip-'.$this->side.'-init',
        'description' => "Don't initialize the GitHub checkout",
        'write' => $_ ==> $this->skip(),
      ),
      shape(
        'long_name' => $this->side.'-github-org::',
        'description' => 'GitHub Organization ['.$this->organization.']',
        'write' => $v ==> $this->organization = $v,
      ),
      shape(
        'long_name' => $this->side.'-github-project::',
        'description' => 'GitHub Project ['.$this->project.']',
        'write' => $v ==> $this->project = $v,
      ),
      shape(
        'long_name' => $this->side.'-use-ssh',
        'description' => 'Use ssh to talk to GitHub',
        'write' => $_ ==> $this->transport = ShipItTransport::SSH,
      ),
      shape(
        'long_name' => $this->side.'-use-authenticated-https',
        'description' => 'Use HTTPS to talk to GitHub',
        'write' => $_ ==> $this->transport = ShipItTransport::HTTPS,
      ),
      shape(
        'long_name' => $this->side.'-use-anonymous-https',
        'description' => 'Talk to GitHub anonymously over HTTPS',
        'write' => $_ ==> {
          $this->transport = ShipItTransport::HTTPS;
          $this->anonymousHttps = true;
        }
      ),
      shape(
        'long_name' => $this->side.'-use-system-credentials',
        'replacement' => $this->side.'-use-anonymous-https',
      ),
    };
  }

  public function runImpl(
    ShipItBaseConfig $config,
  ): void {
    $class = $this->github_utils;
    $local_path =
      $this->side === ShipItRepoSide::SOURCE
      ? $config->getSourcePath()
      : $config->getDestinationPath();

    $credentials = null;
    if ($this->transport !== ShipItTransport::SSH && !$this->anonymousHttps) {
      $credentials = $class::getCredentialsForProject(
        $this->organization,
        $this->project,
      );
    }
    $class::initializeRepo(
      $this->organization,
      $this->project,
      $local_path,
      $this->transport,
      $credentials,
    );
  }
}
