<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, C, Vec};
use Facebook\HackCodegen\{
  HackCodegenFactory,
  HackCodegenConfig,
  HackBuilderValues,
  HackBuilderKeys,
};

type ShipItFunctionArg = shape(
  'cli_name' => string,
  'func_name' => string,
  'takes_arg' => bool,
  'description' => ?string,
);

final abstract class ShipItCodegenInvocationArguments {
  const string ARG_SUFFIX = "::";
  const dict<string, string> CAPITALIZATION_MAP = dict[
    "ssh" => "SSH",
    "https" => "HTTPS",
    "lfs" => "LFS",
    "github" => "GitHub",
  ];

  public static function generate(
    shape(
      "phases" => vec<ShipItPhase>,
      "filename" => string,
      "trait_name" => string,
    ) $args,
  ): void {
    $cli_args = (
      new ShipItPhaseRunner(
        new ShipItBaseConfig('', '', '', keyset['.']),
        $args["phases"],
        new ShipItCLIArgumentParser(),
      )
    )->getCLIArguments();

    $function_args = Vec\sort($cli_args)
      |> Vec\map($$, ($arg) ==> self::getFunctionArg($arg))
      |> Vec\filter_nulls($$);

    $arguments = vec[];
    foreach ($function_args as $arg) {
      if (C\last($arguments) == $arg) {
        continue;
      }
      $arguments[] = $arg;
    }

    self::generateTrait($args["filename"], $args["trait_name"], $arguments);
  }

  private static function getFunctionArg(
    ShipItCLIArgument $arg,
  ): ?ShipItFunctionArg {
    $name = $arg['long_name'];
    $has_arg = false;
    if (Str\ends_with($name, self::ARG_SUFFIX)) {
      $name = Str\strip_suffix($name, self::ARG_SUFFIX);
      $has_arg = true;
    }
    $split = Str\split($name, "-");
    $result = $split[0];
    for ($i = 1; $i < C\count($split); ++$i) {
      $component = $split[$i];
      if (C\contains_key(self::CAPITALIZATION_MAP, $component)) {
        $result .= self::CAPITALIZATION_MAP[$component];
        continue;
      }
      $result .= Str\uppercase($component[0]).Str\slice($component, 1);
    }
    if (Shapes::idx($arg, 'description') == null) {
      $result = "DEPRECATED_".$result;
    }
    if (Shapes::idx($arg, 'replacement') != null) {
      return null;
    }
    return shape(
      'cli_name' => $name,
      'func_name' => $result,
      'takes_arg' => $has_arg,
      'description' => Shapes::idx($arg, 'description'),
    );
  }

  private static function generateTrait(
    string $filename,
    string $traitName,
    vec<ShipItFunctionArg> $arguments,
  ): void {
    $cg = new HackCodegenFactory(new HackCodegenConfig());
    $trait = $cg->codegenTrait($traitName);

    $trait->addProperty(
      $cg->codegenProperty('arg_map')
        ->setType('dict<string, ?string>')
        ->setValue(dict[], HackBuilderValues::dict(
          HackBuilderKeys::export(),
          HackBuilderValues::literal(),
        )),
    );
    $trait->addMethod(
      $cg->codegenMethod('getArgs')
        ->setReturnType('dict<string, ?string>')
        ->setProtected()
        ->setBody('return $this->arg_map;'),
    );

    foreach ($arguments as $arg) {
      $method = $cg->codegenMethod($arg['func_name'])
        ->setPublic()
        ->setIsFinal()
        ->setReturnType('this');
      $right_hand_side = 'null';
      if ($arg['takes_arg']) {
        $method->addParameter('string $value');
        $right_hand_side = '$value';
      }
      $method->setBody(
        '$this->arg_map["'.
        $arg['cli_name'].
        '"] = '.
        $right_hand_side.
        ";\nreturn \$this;",
      );
      if (Shapes::idx($arg, 'description') != null) {
        $method->setDocBlock($arg['description']);
      }
      $trait->addMethod($method);
    }
    $cg->codegenFile($filename)
      ->setDocBlock("Copyright (c) Facebook, Inc. and its affiliates.

This source code is licensed under the MIT license found in the
LICENSE file in the root directory of this source tree.")
      ->setNamespace("Facebook\ShipIt")
      ->addTrait($trait)
      ->save();
  }
}
