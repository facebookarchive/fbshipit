<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

final class DemoGitHubUtils extends ShipItGitHubUtils {

  static string $committer_name = "FBShipIt Demo Committer";
  static string $committer_user = 'CHANGEME';
  static string $committer_email = "demo@example.com";

  public static function getCredentialsForProject(
    string $org,
    string $proj,
  ): ShipItGitHubCredentials {
    return shape(
      'name' => self::$committer_name,
      'user' => self::$committer_user,
      'email' => self::$committer_email,
      'access_token' => 'ACCESS_TOKEN_HERE',
      'password' => null,
    );
  }
}
