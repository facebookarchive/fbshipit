<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * This file was moved from fbsource to www. View old history in diffusion:
 * https://fburl.com/bd6ijkr5
 */
namespace Facebook\ShipIt;

use namespace HH\Lib\Str; // @oss-enable

<<\Oncalls('open_source')>>
final class ShipItShellCommandTest extends ShellTest {
  public function testExitCodeZero(): void {
    $result = (new ShipItShellCommand('/', 'true'))->runSynchronously();
    \expect($result->getExitCode())->toEqual(0);
  }

  public function testExitOneException(): void {
    try {
      (new ShipItShellCommand('/', 'false'))->runSynchronously();
      self::fail('Expected exception');
    } catch (ShipItShellCommandException $e) {
      \expect($e->getExitCode())->toEqual(1);
    }
  }

  public function testExitOneWithoutException(): void {
    $result = (new ShipItShellCommand('/', 'false'))
      ->setNoExceptions()
      ->runSynchronously();
    \expect($result->getExitCode())->toEqual(1);
  }

  public function testStdIn(): void {
    $result = (new ShipItShellCommand('/', 'cat'))
      ->setStdIn('Hello, world.')
      ->runSynchronously();
    \expect($result->getStdOut())->toEqual('Hello, world.');
    \expect($result->getStdErr())->toEqual('');
  }

  public function testSettingEnvironmentVariable(): void {
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    $herp = PHP\bin2hex(\random_bytes(16));
    $result = (new ShipItShellCommand('/', 'env'))
      ->setEnvironmentVariables(dict['HERP' => $herp])
      ->runSynchronously();
    \expect($result->getStdOut())->toContainSubstring('HERP='.$herp);
  }

  public function testInheritingEnvironmentVariable(): void {
    $to_try = keyset[
      // Need to keep SSH/Kerberos environment variables to be able to access
      // repositories
      'SSH_AUTH_SOCK',
      'KRB5CCNAME',
      // Arbitrary common environment variables so we can test /something/ if
      // the above aren't set
      'MAIL',
      'EDITOR',
      'HISTFILE',
      'PATH',
    ];

    $output = (new ShipItShellCommand('/', 'env'))
      ->setEnvironmentVariables(dict[])
      ->runSynchronously()
      ->getStdOut();

    $matched_any = false;
    foreach ($to_try as $var) {
      $value = PHP\getenv($var);
      if ($value !== false) {
        \expect($output)->toContainSubstring($var.'='.(string)$value."\n");
        $matched_any = true;
      }
    }
    \expect($matched_any)->toBeTrue('No acceptable variables found');
  }

  public function testWorkingDirectory(): void {
    \expect(
      (new ShipItShellCommand('/', 'pwd'))
        ->runSynchronously()
        ->getStdOut()
        |> Str\trim($$),
    )->toEqual('/');

    $tmp = PHP\sys_get_temp_dir();
    \expect(
      (new ShipItShellCommand($tmp, 'pwd'))
        ->runSynchronously()
        ->getStdOut()
        |> Str\trim($$),
    )->toContainSubstring(Str\trim($tmp, '/'));
  }

  public function testMultipleArguments(): void {
    $output = (new ShipItShellCommand('/', 'echo', 'foo', 'bar'))
      ->runSynchronously()
      ->getStdOut();
    \expect($output)->toEqual("foo bar\n");
  }

  public function testEscaping(): void {
    $output = (new ShipItShellCommand('/', 'echo', 'foo', '$FOO'))
      ->setEnvironmentVariables(dict['FOO' => 'variable value'])
      ->runSynchronously()
      ->getStdOut();
    \expect($output)->toEqual("foo \$FOO\n");
  }

  public function testFailureHandlerNotCalledWhenNoFailure(): void {
    (new ShipItShellCommand('/', 'true'))
      ->setFailureHandler($_ ==> {
        throw new \Exception("handler called");
      })
      ->runSynchronously();
    // no exception
  }

  public function testFailureHandlerCalledOnFailure(): void {
    \expect(() ==> {
      (new ShipItShellCommand('/', 'false'))
        ->setFailureHandler($_ ==> {
          throw new \Exception("handler called");
        })
        ->runSynchronously();
    })->toThrow(\Exception::class);
  }

  public function testNoRetriesByDefault(): void {
    $file = PHP\tempnam(PHP\sys_get_temp_dir(), __CLASS__) as string;
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \unlink($file);
    $result = (new ShipItShellCommand('/', 'test', '-e', $file))
      ->setFailureHandler($_ ==> PHP\touch($file))
      ->setNoExceptions()
      ->runSynchronously();
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \unlink($file);
    \expect($result->getExitCode())->toEqual(1);
  }

  public function testRetries(): void {
    $file = PHP\tempnam(PHP\sys_get_temp_dir(), __CLASS__) as string;
    /* HH_IGNORE_ERROR[2049] __PHPStdLib */
    /* HH_IGNORE_ERROR[4107] __PHPStdLib */
    \unlink($file);
    $result = (new ShipItShellCommand('/', 'test', '-e', $file))
      ->setFailureHandler($_ ==> PHP\touch($file))
      ->setNoExceptions()
      ->setRetries(1)
      ->runSynchronously();
    if (PHP\file_exists($file)) {
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \unlink($file);
    }
    \expect($result->getExitCode())->toEqual(0);
  }

  public function testRetriesNotUsedOnSuccess(): void {
    $file = PHP\tempnam(PHP\sys_get_temp_dir(), __CLASS__) as string;
    // rm will fail if ran twice with same arg
    if (Str\contains(PHP\php_uname('s'), 'Darwin')) {
      // MacOS doesn't have GNU rm
      $result = (new ShipItShellCommand('/', 'rm', $file))
        ->setRetries(1)
        ->runSynchronously();
    } else {
      $result = (new ShipItShellCommand('/', 'rm', '--preserve-root', $file))
        ->setRetries(1)
        ->runSynchronously();
    }
    if (PHP\file_exists($file)) {
      /* HH_IGNORE_ERROR[2049] __PHPStdLib */
      /* HH_IGNORE_ERROR[4107] __PHPStdLib */
      \unlink($file);
    }
    \expect($result->getExitCode())->toEqual(0);
  }
}
