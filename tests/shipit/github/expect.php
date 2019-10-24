<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/44p4e73v
 */
// Global namespace for \expect() calls.

class ShimExpectObj<T> extends Facebook\FBExpect\ExpectObj<T> {

  public function __construct(private T $varShim) {
    parent::__construct($varShim);
  }

  public function toMatchRegex(string $expected, string $msg = '', mixed ...$args): void {
    $msg = \vsprintf($msg, $args);
    $this->assertRegExp($expected, (string) $this->varShim, $msg);
  }

  public function toNotMatchRegex(string $expected, string $msg = '', mixed ...$args): void {
    $msg = \vsprintf($msg, $args);
    $this->assertNotRegExp($expected, (string) $this->varShim, $msg);
  }
}

function expect<T>(T $obj): ShimExpectObj<T> {
  return new ShimExpectObj($obj);
}
