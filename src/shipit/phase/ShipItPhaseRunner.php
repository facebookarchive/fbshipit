<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/8yredn7r
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\{Str, Math, Dict, C, Vec};

class ShipItPhaseRunner {
  public function __construct(
    protected ShipItBaseConfig $config,
    protected vec<ShipItPhase> $phases,
  ) {}

  public function run(): void {
    $this->parseCLIArguments();
    foreach ($this->phases as $phase) {
      $phase->run($this->config);
    }
  }

  protected function getBasicCLIArguments(): vec<ShipItCLIArgument> {
    return vec[
      shape(
        'short_name' => 'h',
        'long_name' => 'help',
        'description' => 'show this help message and exit',
      ),
      shape(
        'long_name' => 'base-dir::',
        'description' => 'Path to store repositories',
        'write' => $x ==> {
          $this->config = $this->config
            ->withBaseDirectory(Str\trim($x));
          return $this->config;
        },
      ),
      shape(
        'long_name' => 'temp-dir::',
        'replacement' => 'base-dir',
        'write' => $x ==> {
          $this->config = $this->config
            ->withBaseDirectory(Str\trim($x));
          return $this->config;
        },
      ),
      shape(
        'long_name' => 'source-repo-dir::',
        'description' => 'path to fetch source from',
        'write' => $x ==> {
          $this->config = $this->config
            ->withSourcePath(Str\trim($x));
          return $this->config;
        },
      ),
      shape(
        'long_name' => 'destination-repo-dir::',
        'description' => 'path to push filtered changes to',
        'write' => $x ==> {
          $this->config = $this->config
            ->withDestinationPath(Str\trim($x));
          return $this->config;
        },
      ),
      shape(
        'long_name' => 'source-branch::',
        'description' => "Branch to sync from",
        'write' => $x ==> {
          $this->config = $this->config
            ->withSourceBranch(Str\trim($x));
          return $this->config;
        },
      ),
      shape(
        'long_name' => 'src-branch::',
        'replacement' => 'source-branch',
        'write' => $x ==> {
          $this->config = $this->config
            ->withSourceBranch(Str\trim($x));
          return $this->config;
        },
      ),
      shape(
        'long_name' => 'destination-branch::',
        'description' => 'Branch to sync to',
        'write' => $x ==> {
          $this->config = $this->config
            ->withDestinationBranch(Str\trim($x));
          return $this->config;
        },
      ),
      shape(
        'long_name' => 'dest-branch::',
        'replacement' => 'destination-branch',
        'write' => $x ==> {
          $this->config = $this->config
            ->withDestinationBranch(Str\trim($x));
          return $this->config;
        },
      ),
      shape(
        'long_name' => 'debug',
        'replacement' => 'verbose',
      ),
      shape(
        'long_name' => 'skip-project-specific',
        'description' => 'Skip anything project-specific',
        'write' => $_ ==> {
          $this->config = $this->config
            ->withProjectSpecificPhasesDisabled();
          return $this->config;
        },
      ),
      shape(
        'short_name' => 'v',
        'long_name' => 'verbose',
        'description' => 'Give more verbose output',
        'write' => $_ ==> {
          $this->config = $this->config->withVerboseEnabled();
          return $this->config;
        },
      ),
    ];
  }

  final protected function getCLIArguments(): vec<ShipItCLIArgument> {
    $args = $this->getBasicCLIArguments();
    foreach ($this->phases as $phase) {
      $args = Vec\concat($args, $phase->getCLIArguments());
    }

    // Check for correctness
    foreach ($args as $arg) {
      $description = Shapes::idx($arg, 'description');
      $replacement = Shapes::idx($arg, 'replacement');
      $handler = Shapes::idx($arg, 'write');
      $name = $arg['long_name'];

      invariant(
        !($description !== null && $replacement !== null),
        '--%s is documented and deprecated',
        $name,
      );

      invariant(
        !(
          $handler !== null && !($description !== null || $replacement !== null)
        ),
        '--%s does something, and is undocumented',
        $name,
      );
    }

    return $args;
  }

  final protected function parseOptions(
    vec<ShipItCLIArgument> $config,
    dict<string, mixed> $raw_opts,
  ): void {
    foreach ($config as $opt) {
      $is_optional = Str\slice($opt['long_name'], -2) === '::';
      $is_required = !$is_optional && Str\slice($opt['long_name'], -1) === ':';
      $is_bool = !$is_optional && !$is_required;
      $short = Str\trim_right(Shapes::idx($opt, 'short_name', ''), ':');
      $long = Str\trim_right($opt['long_name'], ':');

      if ($short is nonnull && C\contains_key($raw_opts, $short)) {
        $key = '-'.$short;
        $value = $is_bool ? true : $raw_opts[$short];
      } else if (C\contains_key($raw_opts, $long)) {
        $key = '--'.$long;
        $value = $is_bool ? true : $raw_opts[$long];
      } else {
        $key = null;
        $value = $is_bool ? false : '';
        $have_value = false;
        $isset_func = Shapes::idx($opt, 'isset');
        if ($isset_func) {
          $have_value = $isset_func();
        }

        if ($is_required && !$have_value) {
          echo "ERROR: Expected --".$long."\n\n";
          self::printHelp($config);
          exit(1);
        }
      }

      $handler = Shapes::idx($opt, 'write');
      if ($handler && $value !== '' && $value !== false) {
        $handler((string)$value);
      }

      if ($key === null) {
        continue;
      }

      $description = Shapes::idx($opt, 'description');
      if ($description !== null && !Str\is_empty($description)) {
        continue;
      }

      $replacement = Shapes::idx($opt, 'replacement');
      if ($replacement !== null) {
        ShipItLogger::err(
          "%s %s, use %s instead\n",
          $key,
          $handler ? 'is deprecated' : 'has been removed',
          $replacement,
        );
        if ($handler === null) {
          exit(1);
        }
      } else {
        invariant(
          $handler === null,
          "Option '%s' is not a no-op, is undocumented, and doesn't have a ".
          'documented replacement.',
          $key,
        );
        ShipItLogger::err("%s is deprecated and a no-op\n", $key);
      }
    }
  }

  protected function parseCLIArguments(): void {
    $config = $this->getCLIArguments();
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    $raw_opts = \getopt(
      Vec\map($config, $opt ==> Shapes::idx($opt, 'short_name', ''))
        |> Str\join($$, ''),
      Vec\map($config, $opt ==> $opt['long_name']),
    )
      |> dict($$);
    if (C\contains_key($raw_opts, 'h') || C\contains_key($raw_opts, 'help')) {
      self::printHelp($config);
      exit(0);
    }
    $this->parseOptions($config, $raw_opts);
  }

  protected static function printHelp(vec<ShipItCLIArgument> $config): void {
    /* HH_FIXME[2050] Previously hidden by unsafe_expr */
    $filename = $_SERVER['SCRIPT_NAME'];
    $max_left = 0;
    $rows = dict[];
    foreach ($config as $opt) {
      $description = Shapes::idx($opt, 'description');
      if ($description === null) {
        $replacement = Shapes::idx($opt, 'replacement');
        if ($replacement !== null) {
          continue;
        } else {
          invariant(
            !Shapes::idx($opt, 'write'),
            '--%s is undocumented, does something, and has no replacement',
            $opt['long_name'],
          );
          $description = 'deprecated, no-op';
        }
      }

      $short = Shapes::idx($opt, 'short_name');
      $long = $opt['long_name'];
      $is_optional = Str\slice($long, -2) === '::';
      $is_required = !$is_optional && Str\slice($long, -1) === ':';
      $long = Str\trim_right($long, ':');
      $prefix = $short !== null ? '-'.Str\trim_right($short, ':').', ' : '';
      $suffix = $is_optional ? "=VALUE" : ($is_required ? "=$long" : '');
      $left = '  '.$prefix.'--'.$long.$suffix;
      $max_left = Math\maxva(Str\length($left), $max_left);

      $rows[$long] = tuple($left, $description);
    }
    $rows = Dict\sort_by_key($rows);

    $help = $rows['help'];
    unset($rows['help']);
    $rows = Dict\merge(dict['help' => $help], $rows);

    $opt_help = Str\join(
      Dict\map(
        $rows,
        $row ==>
          Str\format("%s  %s\n", Str\pad_right($row[0], $max_left), $row[1]),
      ),
      "",
    );
    echo <<<EOF
Usage:
{$filename} [options]

Options:
{$opt_help}

EOF;
  }
}
