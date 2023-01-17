<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/gl8oxfjx
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Regex, Str}; // @oss-enable

type ShipItGitHubCredentials = shape(
  'name' => string,
  'email' => string,
  'access_token' => string,
);

abstract class ShipItGitHubUtils {
  /** Fetch information on a user that has permission to write to a project.
   *
   * For example, for projects on github.com/facebook/, it will return
   * information on facebook-github-bot user
   *
   * This is used by ::initializeRepo().
   */
  const string GIT_HTTPS_URL_PREFIX = 'https://';
  public abstract static function genCredentialsForProject(
    string $organization,
    string $project,
  ): Awaitable<ShipItGitHubCredentials>;

  /**
   * Configure the user and origin for a repository, cloning if necessary.
   *
   * - requires getCredentialsForProject() to be implemented
   * - configures 'origin' to be authenticated HTTPS
   */
  final public static async function genInitializeRepo(
    string $organization,
    string $project,
    string $local_path,
    ShipItTransport $transport,
    ?ShipItGitHubCredentials $credentials,
  ): Awaitable<void> {
    $git_config = (string $key, string $value) ==>
      new ShipItShellCommand($local_path, 'git', 'config', $key, $value);

    switch ($transport) {
      case ShipItTransport::SSH:
        invariant(
          $credentials === null,
          'Credentials should not be specified for SSH transport',
        );
        $origin =
          Str\format('git@github.com:%s/%s.git', $organization, $project);

        await self::genCloneAndVerifyRepo($origin, $local_path);
        break;
      case ShipItTransport::HTTPS:
        $origin =
          Str\format('https://github.com/%s/%s.git', $organization, $project);
        if ($credentials === null) {
          await self::genCloneAndVerifyRepo($origin, $local_path);
          break;
        }
        $origin = self::authHttpsRemoteUrl($origin, $credentials);
        await self::genCloneAndVerifyRepo($origin, $local_path);

        await $git_config('user.name', $credentials['name'])->genRun();
        await $git_config('user.email', $credentials['email'])->genRun();
        break;
    }

    await $git_config('remote.origin.url', $origin)->genRun();
  }

  final public static function authHttpsRemoteUrl(
    string $remote_url,
    ShipItGitHubCredentials $credentials,
  ): string {
    $auth_user = $credentials['access_token'];
    if (Str\search($remote_url, self::GIT_HTTPS_URL_PREFIX) === 0) {
      $prefix_len = Str\length(self::GIT_HTTPS_URL_PREFIX);
      return Str\slice($remote_url, 0, $prefix_len).
        $auth_user.
        '@'.
        Str\slice($remote_url, $prefix_len);
    }
    return $remote_url;
  }

  private static async function genCloneAndVerifyRepo(
    string $origin,
    string $local_path,
  ): Awaitable<void> {
    if (!PHP\file_exists($local_path)) {
      await ShipItRepoGIT::genCloneRepo($origin, $local_path);
    }
    invariant(
      PHP\file_exists($local_path.'/.git'),
      '%s is not a git repo',
      $local_path,
    );
  }
}
