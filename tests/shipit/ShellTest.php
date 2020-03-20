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

  public async function setUp(): Awaitable<void> {} // @oss-enable
  public async function tearDownAsync(): Awaitable<void> {} // @oss-enable

  <<__Override>> // @oss-enable
  public async function beforeEachTestAsync(): Awaitable<void> { // @oss-enable
    await $this->setUp(); // @oss-enable
  } // @oss-enable

  <<__Override>> // @oss-enable
  public async function afterEachTestAsync(): Awaitable<void> { // @oss-enable
    await $this->tearDownAsync(); // @oss-enable
  } // @oss-enable

  protected function execSteps(string $cwd, Container<string> ...$steps): void {
    foreach ($steps as $step) {
      /* HH_FIXME[4128] Use ShipItShellCommand */
      ShipItUtil::shellExec(
        $cwd,
        /* stdin = */ null,
        ShipItUtil::DONT_VERBOSE,
        ...$step
      );
    }
  }

  protected function configureGit(ShipItTempDir $temp_dir): void {
    $this->execSteps(
      $temp_dir->getPath(),
      vec['git', 'config', 'user.name', 'FBShipIt Unit Test'],
      vec['git', 'config', 'user.email', 'fbshipit@example.com'],
    );
  }

  protected function configureHg(ShipItTempDir $temp_dir): void {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \file_put_contents(
      $temp_dir->getPath().'/.hg/hgrc',
      '[ui]
username = FBShipIt Unit Test <fbshipit@example.com>',
    );
  }
}
