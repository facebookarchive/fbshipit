<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/5ky8o11m
 */
namespace Facebook\ShipIt;

final class ShipItGitHubInitPhase extends ShipItPhase {

  private bool $anonymousHttps = false;

  public function __construct(
    private string $organization,
    private string $project,
    private ShipItRepoSide $side,
    private ShipItTransport $transport,
    private classname<ShipItGitHubUtils> $githubUtils,
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
  public function getCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'long_name' => 'skip-'.$this->side.'-init',
        'description' => "Don't initialize the GitHub checkout",
        'write' => $_ ==> $this->skip(),
      ),
      shape(
        'long_name' => $this->side.'-github-org::',
        'description' => 'GitHub Organization ['.$this->organization.']',
        'write' => $v ==> {
          $this->organization = $v;
          return $this->organization;
        },
      ),
      shape(
        'long_name' => $this->side.'-github-project::',
        'description' => 'GitHub Project ['.$this->project.']',
        'write' => $v ==> {
          $this->project = $v;
          return $this->project;
        },
      ),
      shape(
        'long_name' => $this->side.'-use-ssh',
        'description' => 'Use ssh to talk to GitHub',
        'write' => $_ ==> {
          $this->transport = ShipItTransport::SSH;
          return $this->transport;
        },
      ),
      shape(
        'long_name' => $this->side.'-use-authenticated-https',
        'description' => 'Use HTTPS to talk to GitHub',
        'write' => $_ ==> {
          $this->transport = ShipItTransport::HTTPS;
          return $this->transport;
        },
      ),
      shape(
        'long_name' => $this->side.'-use-anonymous-https',
        'description' => 'Talk to GitHub anonymously over HTTPS',
        'write' => $_ ==> {
          $this->transport = ShipItTransport::HTTPS;
          $this->anonymousHttps = true;
          return true;
        },
      ),
      shape(
        'long_name' => $this->side.'-use-system-credentials',
        'replacement' => $this->side.'-use-anonymous-https',
      ),
    ];
  }

  <<__Override>>
  public function runImpl(ShipItManifest $manifest): void {
    $class = $this->githubUtils;
    $local_path = $this->side === ShipItRepoSide::SOURCE
      ? $manifest->getSourcePath()
      : $manifest->getDestinationPath();

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
