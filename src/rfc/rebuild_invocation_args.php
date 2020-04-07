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

const string ARG_SUFFIX = "::";
const dict<string, string> CAPITALIZATION_MAP = dict[
  "ssh" => "SSH",
  "https" => "HTTPS",
  "lfs" => "LFS",
  "github" => "GitHub",
];

type ShipItFunctionArg = shape(
  'cli_name' => string,
  'func_name' => string,
  'takes_arg' => bool,
  'description' => ?string,
);

function getFunctionArg(ShipItCLIArgument $arg): ?ShipItFunctionArg {
  $name = $arg['long_name'];
  $has_arg = false;
  if (Str\ends_with($name, ARG_SUFFIX)) {
    $name = Str\strip_suffix($name, ARG_SUFFIX);
    $has_arg = true;
  }
  $split = Str\split($name, "-");
  $result = $split[0];
  for ($i = 1; $i < C\count($split); ++$i) {
    $component = $split[$i];
    if (C\contains_key(CAPITALIZATION_MAP, $component)) {
      $result .= CAPITALIZATION_MAP[$component];
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

function generateArgumentClass(vec<ShipItFunctionArg> $args): void {
  $cg = new HackCodegenFactory(new HackCodegenConfig());
  $trait = $cg->codegenTrait('ShipItArgumentsTrait');

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

  foreach ($args as $arg) {
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
  $cg->codegenFile(__DIR__.'/ShipItArgumentsTrait.php')
    ->setDocBlock("Copyright (c) Facebook, Inc. and its affiliates.

This source code is licensed under the MIT license found in the
LICENSE file in the root directory of this source tree.")
    ->setNamespace("Facebook\ShipIt")
    ->addTrait($trait)
    ->save();
}

<<__EntryPoint>>
async function run_test_main(): Awaitable<void> {
  require_once(\dirname(\dirname(__DIR__)).'/vendor/autoload.hack');
  \Facebook\AutoloadMap\initialize();

  $phases = vec[
    new ShipItCleanPhase(ShipItRepoSide::SOURCE),
    new ShipItCleanPhase(ShipItRepoSide::DESTINATION),
    new ShipItCreateNewRepoPhase($x ==> $x, shape('name' => '', 'email' => '')),
    new ShipItDeleteCorruptedRepoPhase(ShipItRepoSide::SOURCE),
    new ShipItDeleteCorruptedRepoPhase(ShipItRepoSide::DESTINATION),
    new ShipItFilterSanityCheckPhase($x ==> $x, vec[]),
    new ShipItGitHubInitPhase(
      '',
      '',
      ShipItRepoSide::SOURCE,
      ShipItTransport::HTTPS,
      DemoGitHubUtils::class,
    ),
    new ShipItGitHubInitPhase(
      '',
      '',
      ShipItRepoSide::DESTINATION,
      ShipItTransport::HTTPS,
      DemoGitHubUtils::class,
    ),
    new ShipItPullPhase(ShipItRepoSide::SOURCE),
    new ShipItPullPhase(ShipItRepoSide::DESTINATION),
    new ShipItPushLfsPhase(
      ShipItRepoSide::SOURCE,
      '',
      '',
      false,
      DemoGitHubUtils::class,
    ),
    new ShipItPushLfsPhase(
      ShipItRepoSide::DESTINATION,
      '',
      '',
      false,
      DemoGitHubUtils::class,
    ),
    new ShipItPushPhase(),
    new ShipItSaveConfigPhase('', ''),
    new ShipItVerifyRepoPhase($x ==> $x),
  ];

  $args = (
    new ShipItPhaseRunner(
      new ShipItBaseConfig('', '', '', keyset['.']),
      $phases,
      new ShipItCLIArgumentParser(),
    )
  )->getCLIArguments();

  $function_args = Vec\map($args, ($arg) ==> getFunctionArg($arg))
    |> Vec\filter_nulls($$)
    |> Vec\sort($$);

  $unique_function_args = vec[];
  foreach ($function_args as $arg) {
    if (C\last($unique_function_args) == $arg) {
      continue;
    }
    $unique_function_args[] = $arg;
  }

  generateArgumentClass($unique_function_args);
}
