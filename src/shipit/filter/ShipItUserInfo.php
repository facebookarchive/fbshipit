<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

// Not an interface: https://github.com/facebook/hhvm/issues/6820
abstract class ShipItUserInfo {
  // eg convert a local unix account name to a github account name
  abstract public static function getDestinationUserFromLocalUser(
    string $local_user,
  ): Awaitable<?string>;

  // eg convert a local unix account name to "Foo Bar <foobar@example.com>"
  abstract public static function getDestinationAuthorFromLocalUser(
    string $local_user,
  ): Awaitable<?string>;
}
