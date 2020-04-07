<?hh // strict
/**
 * This file is generated. Do not modify it manually!
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @generated SignedSource<<f981bbcbcd25ffba54c3ea654179f0db>>
 */
namespace Facebook\ShipIt;

trait ShipItArgumentsTrait {

  private dict<string, ?string> $arg_map = dict[
  ];

  protected function getArgs(): dict<string, ?string> {
    return $this->arg_map;
  }

  /**
   * Path to store repositories
   */
  final public function baseDir(string $value): this {
    $this->arg_map["base-dir"] = $value;
    return $this;
  }

  /**
   * Create a patch to get the destination repository in sync, then exit
   */
  final public function createFixupPatch(): this {
    $this->arg_map["create-fixup-patch"] = null;
    return $this;
  }

  /**
   * Create a new git repository with a single commit, then exit
   */
  final public function createNewRepo(): this {
    $this->arg_map["create-new-repo"] = null;
    return $this;
  }

  /**
   * Like --create-new-repo, but at a specified source commit
   */
  final public function createNewRepoFromCommit(string $value): this {
    $this->arg_map["create-new-repo-from-commit"] = $value;
    return $this;
  }

  /**
   * When using --create-new-repo or --create-new-repo-from-commit, create the
   * new repository in this directory
   */
  final public function createNewRepoOutputPath(string $value): this {
    $this->arg_map["create-new-repo-output-path"] = $value;
    return $this;
  }

  /**
   * Allow FBShipIt to delete the repository if corrupted
   */
  final public function destinationAllowNuke(): this {
    $this->arg_map["destination-allow-nuke"] = null;
    return $this;
  }

  /**
   * Branch to sync to
   */
  final public function destinationBranch(string $value): this {
    $this->arg_map["destination-branch"] = $value;
    return $this;
  }

  /**
   * GitHub Organization []
   */
  final public function destinationGitHubOrg(string $value): this {
    $this->arg_map["destination-github-org"] = $value;
    return $this;
  }

  /**
   * GitHub Project []
   */
  final public function destinationGitHubProject(string $value): this {
    $this->arg_map["destination-github-project"] = $value;
    return $this;
  }

  /**
   * path to push filtered changes to
   */
  final public function destinationRepoDir(string $value): this {
    $this->arg_map["destination-repo-dir"] = $value;
    return $this;
  }

  /**
   * Talk to GitHub anonymously over HTTPS
   */
  final public function destinationUseAnonymousHTTPS(): this {
    $this->arg_map["destination-use-anonymous-https"] = null;
    return $this;
  }

  /**
   * Use HTTPS to talk to GitHub
   */
  final public function destinationUseAuthenticatedHTTPS(): this {
    $this->arg_map["destination-use-authenticated-https"] = null;
    return $this;
  }

  /**
   * Use ssh to talk to GitHub
   */
  final public function destinationUseSSH(): this {
    $this->arg_map["destination-use-ssh"] = null;
    return $this;
  }

  /**
   * show this help message and exit
   */
  final public function help(): this {
    $this->arg_map["help"] = null;
    return $this;
  }

  /**
   * Save configuration data for this project here and exit.
   */
  final public function saveConfigTo(string $value): this {
    $this->arg_map["save-config-to"] = $value;
    return $this;
  }

  /**
   * Do not clean the destination repository
   */
  final public function skipDestinationClean(): this {
    $this->arg_map["skip-destination-clean"] = null;
    return $this;
  }

  /**
   * Don't initialize the GitHub checkout
   */
  final public function skipDestinationInit(): this {
    $this->arg_map["skip-destination-init"] = null;
    return $this;
  }

  /**
   * Don't pull the destination repository
   */
  final public function skipDestinationPull(): this {
    $this->arg_map["skip-destination-pull"] = null;
    return $this;
  }

  /**
   * Skip the filter sanity check.
   */
  final public function skipFilterSanityCheck(): this {
    $this->arg_map["skip-filter-sanity-check"] = null;
    return $this;
  }

  /**
   * Skip LFS syncing
   */
  final public function skipLFS(): this {
    $this->arg_map["skip-lfs"] = null;
    return $this;
  }

  /**
   * Skip anything project-specific
   */
  final public function skipProjectSpecific(): this {
    $this->arg_map["skip-project-specific"] = null;
    return $this;
  }

  /**
   * Do not push the destination repository
   */
  final public function skipPush(): this {
    $this->arg_map["skip-push"] = null;
    return $this;
  }

  /**
   * Do not clean the source repository
   */
  final public function skipSourceClean(): this {
    $this->arg_map["skip-source-clean"] = null;
    return $this;
  }

  /**
   * Don't initialize the GitHub checkout
   */
  final public function skipSourceInit(): this {
    $this->arg_map["skip-source-init"] = null;
    return $this;
  }

  /**
   * Don't pull the source repository
   */
  final public function skipSourcePull(): this {
    $this->arg_map["skip-source-pull"] = null;
    return $this;
  }

  /**
   * Allow FBShipIt to delete the repository if corrupted
   */
  final public function sourceAllowNuke(): this {
    $this->arg_map["source-allow-nuke"] = null;
    return $this;
  }

  /**
   * Branch to sync from
   */
  final public function sourceBranch(string $value): this {
    $this->arg_map["source-branch"] = $value;
    return $this;
  }

  /**
   * GitHub Organization []
   */
  final public function sourceGitHubOrg(string $value): this {
    $this->arg_map["source-github-org"] = $value;
    return $this;
  }

  /**
   * GitHub Project []
   */
  final public function sourceGitHubProject(string $value): this {
    $this->arg_map["source-github-project"] = $value;
    return $this;
  }

  /**
   * path to fetch source from
   */
  final public function sourceRepoDir(string $value): this {
    $this->arg_map["source-repo-dir"] = $value;
    return $this;
  }

  /**
   * Talk to GitHub anonymously over HTTPS
   */
  final public function sourceUseAnonymousHTTPS(): this {
    $this->arg_map["source-use-anonymous-https"] = null;
    return $this;
  }

  /**
   * Use HTTPS to talk to GitHub
   */
  final public function sourceUseAuthenticatedHTTPS(): this {
    $this->arg_map["source-use-authenticated-https"] = null;
    return $this;
  }

  /**
   * Use ssh to talk to GitHub
   */
  final public function sourceUseSSH(): this {
    $this->arg_map["source-use-ssh"] = null;
    return $this;
  }

  /**
   * Find the latest synced source commit to use as a base for verify
   */
  final public function useLatestSourceCommit(): this {
    $this->arg_map["use-latest-source-commit"] = null;
    return $this;
  }

  /**
   * Give more verbose output
   */
  final public function verbose(): this {
    $this->arg_map["verbose"] = null;
    return $this;
  }

  /**
   * Verify that the destination repository is in sync, then exit
   */
  final public function verify(): this {
    $this->arg_map["verify"] = null;
    return $this;
  }

  /**
   * Hash of first commit that needs to be synced
   */
  final public function verifySourceCommit(string $value): this {
    $this->arg_map["verify-source-commit"] = $value;
    return $this;
  }
}
