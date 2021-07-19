<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/xm1y32k1
 */
namespace Facebook\ShipIt;

abstract class ShellTest extends \Facebook\HackTest\HackTest { // @oss-enable
// @oss-disable: abstract class ShellTest extends \HackTest {

  protected static function execSteps(
    string $cwd,
    Container<string> ...$steps
  ): void {
    foreach ($steps as $step) {
      (new ShipItShellCommand($cwd, ...$step))->setOutputToScreen()
        ->runSynchronously();
    }
  }

  protected static function configureGit(ShipItTempDir $temp_dir): void {
    self::execSteps(
      $temp_dir->getPath(),
      vec['git', 'config', 'user.name', 'FBShipIt Unit Test'],
      vec['git', 'config', 'user.email', 'fbshipit@example.com'],
    );
  }

  protected static function configureHg(ShipItTempDir $temp_dir): void {
    PHP\file_put_contents(
      $temp_dir->getPath().'/.hg/hgrc',
      '[ui]
username = FBShipIt Unit Test <fbshipit@example.com>',
    );
  }
}
