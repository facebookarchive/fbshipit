<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

// Annotation classes in the global namespace.

class Oncalls implements HH\ClassAttribute, HH\MethodAttribute, HH\TypeAliasAttribute,
    HH\EnumAttribute, HH\FunctionAttribute {
  public function __construct(mixed ...$args) {}
}

class TestsBypassVisibility implements HH\ClassAttribute, HH\MethodAttribute, HH\TypeAliasAttribute,
    HH\EnumAttribute, HH\FunctionAttribute {
  public function __construct(mixed ...$args) {}
}
