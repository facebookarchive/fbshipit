<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

type ShipItGitHubCredentials = shape(
  'name' => string,
  'user' => ?string,
  'email' => string,
  'password' => ?string,
  'access_token' => ?string,
);

abstract class ShipItGitHubUtils {
  /** Fetch information on a user that has permission to write to a project.
   *
   * For example, for most projects on github.com/facebook/, it will return
   * information on a random facebook-github-bot user - though for
   * github.com/facebook/hhvm/ it wil return hhvm-bot.
   *
   * This is used by ::initializeRepo().
   */
  const string GIT_HTTPS_URL_PREFIX = 'https://';
  public abstract static function getCredentialsForProject(
    string $organization,
    string $project,
  ): ShipItGitHubCredentials;

  /**
   * Configure the user and origin for a repository, cloning if necessary.
   *
   * - requires getCredentialsForProject() to be implemented
   * - configures 'origin' to be authenticated HTTPS
   */
  final public static function initializeRepo(
    string $organization,
    string $project,
    string $local_path,
    ShipItTransport $transport,
    ?ShipItGitHubCredentials $credentials,
  ): void {
    $git_config = ($key, $value) ==> new ShipItShellCommand(
      $local_path,
      'git', 'config', $key, $value,
    );

    $origin = null;

    switch ($transport) {
      case ShipItTransport::SSH:
        invariant(
          $credentials === null,
          'Credentials should not be specified for SSH transport',
        );
        $origin = \sprintf(
          'git@github.com:%s/%s.git',
          $organization,
          $project,
        );

        self::cloneAndVerifyRepo($origin, $local_path);
        break;
      case ShipItTransport::HTTPS:
        $origin =
          \sprintf('https://github.com/%s/%s.git', $organization, $project);
        if ($credentials === null) {
          self::cloneAndVerifyRepo($origin, $local_path);
          break;
        }
        $origin = self::authHttpsRemoteUrl($origin, $transport, $credentials);
        self::cloneAndVerifyRepo($origin, $local_path);

        $git_config('user.name', $credentials['name'])->runSynchronously();
        $git_config('user.email', $credentials['email'])->runSynchronously();
        break;
    }

    invariant(
      $origin !== null,
      'No origin specified :(',
    );

    $git_config('remote.origin.url', $origin)->runSynchronously();
  }

  public static function authHttpsRemoteUrl(
    string $remote_url,
    ShipItTransport $transport,
    ShipItGitHubCredentials $credentials,
  ): string {
    if ($transport !== ShipItTransport::HTTPS) {
      return $remote_url;
    }
    $access_token = Shapes::idx($credentials, 'access_token');
    $auth_user = $access_token !== null
      ? $access_token
      : \sprintf(
          '%s:%s',
          \urlencode($credentials['user']),
          \urlencode($credentials['password']),
        );
    if (\strpos($remote_url, self::GIT_HTTPS_URL_PREFIX) === 0) {
      $prefix_len = \strlen(self::GIT_HTTPS_URL_PREFIX);
      return \substr($remote_url, 0, $prefix_len).
        $auth_user.
        '@'.
        \substr($remote_url, $prefix_len);
    }
    return $remote_url;
  }

  private static function cloneAndVerifyRepo(
    string $origin,
    string $local_path,
  ): void {
    if (!\file_exists($local_path)) {
      ShipItRepoGIT::cloneRepo(
        $origin,
        $local_path,
      );
    }
    invariant(
      \file_exists($local_path.'/.git'),
      '%s is not a git repo',
      $local_path,
    );
  }

  final public static async function makeAPIRequest(
    ShipItGitHubCredentials $credentials,
    string $path,
  ): Awaitable<ImmVector<string>> {
    $results = Vector { };
    $request_headers = Vector {
      'Accept: application/vnd.github.v3.patch'
    };

    $access_token = Shapes::idx($credentials, 'access_token');
    $use_oauth = $access_token !== null;

    if ($use_oauth) {
      $request_headers->add(
        \sprintf('Authorization: token %s', $access_token),
      );
    }

    $url = \sprintf('https://api.github.com%s', $path);

    while ($url !== null) {
      $ch = \curl_init($url);
      \curl_setopt($ch, \CURLOPT_USERAGENT, 'Facebook/ShipIt');
      \curl_setopt($ch, \CURLOPT_HTTPHEADER, $request_headers);
      if (!$use_oauth) {
        \curl_setopt(
          $ch,
          \CURLOPT_USERPWD,
          \sprintf('%s:%s', $credentials['user'], $credentials['password']),
        );
      }
      \curl_setopt($ch, \CURLOPT_HEADER, 1);
      $response = await \HH\Asio\curl_exec($ch);
      $header_len = \curl_getinfo($ch, \CURLINFO_HEADER_SIZE);
      $response_header = \substr($response, 0, $header_len);
      $results[] = \substr($response, $header_len);

      $url = null;
      foreach (\explode("\n", \trim($response_header)) as $header_line) {
        if (\substr($header_line, 0, 5) === 'HTTP/') {
          continue;
        }
        $sep = \strpos($header_line, ':');
        if ($sep === false) {
          continue;
        }

        $name = \strtolower(\substr($header_line, 0, $sep));
        if ($name === 'link') {
          $matches = [];
          if (
            \preg_match(
              '@<(?<next>https://api.github.com[^>]+)>; rel="next"@',
              $header_line,
              &$matches,
            )
          ) {
            $url = $matches['next'];
            break;
          }
        }
      }
    }
    return $results->toImmVector();
  }
}
