<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

final class DemoGitHubUtils extends ShipItGitHubUtils {

  public static string $committerName = "FBShipIt Demo Committer";
  public static string $committerUser = 'CHANGEME';
  public static string $committerEmail = "demo@example.com";

  <<__Override>>
  public static async function genCredentialsForProject(
    string $org,
    string $proj,
  ): Awaitable<ShipItGitHubCredentials> {
    return shape(
      'name' => self::$committerName,
      'email' => self::$committerEmail,
      'access_token' => 'ACCESS_TOKEN_HERE',
    );
  }
}
