<?hh // strict
/**
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\ShipIt;

final class ShipItShellCommandTest extends BaseTest {
  public function testExitCodeZero(): void {
    $result = (new ShipItShellCommand('/', 'true'))->runSynchronously();
    $this->assertSame(0, $result->getExitCode());
  }

  public function testExitOneException(): void {
    try {
      (new ShipItShellCommand('/', 'false'))->runSynchronously();
      $this->fail('Expected exception');
    } catch (ShipItShellCommandException $e) {
      $this->assertSame(1, $e->getExitCode());
    }
  }

  public function testExitOneWithoutException(): void {
    $result = (new ShipItShellCommand('/', 'false'))
      ->setNoExceptions()
      ->runSynchronously();
    $this->assertSame(1, $result->getExitCode());
  }

  public function testStdIn(): void {
    $result = (new ShipItShellCommand('/', 'cat'))
      ->setStdIn('Hello, world.')
      ->runSynchronously();
    $this->assertSame('Hello, world.', $result->getStdOut());
    $this->assertSame('', $result->getStdErr());
  }

  public function testSettingEnvironmentVariable(): void {
    $herp = \bin2hex(\random_bytes(16));
    $result = (new ShipItShellCommand('/', 'env'))
      ->setEnvironmentVariables(ImmMap { 'HERP' => $herp })
      ->runSynchronously();
    $this->assertContains(
      'HERP='.$herp,
      $result->getStdOut(),
    );
  }

  public function testInheritingEnvironmentVariable(): void {
    $to_try = ImmSet {
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
    };

    $output = (new ShipItShellCommand('/', 'env'))
      ->setEnvironmentVariables(ImmMap { })
      ->runSynchronously()
      ->getStdOut();

    $matched_any = false;
    foreach ($to_try as $var) {
      $value = \getenv($var);
      if ($value !== false) {
        $this->assertContains($var.'='.$value."\n", $output);
        $matched_any = true;
      }
    }
    $this->assertTrue(
      $matched_any,
      'No acceptable variables found',
    );
  }

  public function testWorkingDirectory(): void {
    $this->assertSame(
      '/',
      (new ShipItShellCommand('/', 'pwd'))
        ->runSynchronously()
        ->getStdOut()
        |> \trim($$),
    );

    $tmp = \sys_get_temp_dir();
    $this->assertSame(
      $tmp,
      (new ShipItShellCommand($tmp, 'pwd'))
        ->runSynchronously()
        ->getStdOut()
        |> \trim($$),
    );
  }

  public function testMultipleArguments(): void {
    $output = (new ShipItShellCommand('/', 'echo', '-n', 'foo', 'bar'))
      ->runSynchronously()
      ->getStdOut();
    $this->assertSame('foo bar', $output);
  }

  public function testEscaping(): void {
    $output = (new ShipItShellCommand('/', 'echo', 'foo', '$FOO'))
      ->setEnvironmentVariables(ImmMap { 'FOO' => 'variable value' })
      ->runSynchronously()
      ->getStdOut();
    $this->assertSame("foo \$FOO\n", $output);
  }

  public function testFailureHandlerNotCalledWhenNoFailure(): void {
    (new ShipItShellCommand('/', 'true'))
      ->setFailureHandler($_ ==> {throw new \Exception("handler called");})
      ->runSynchronously();
    // no exception
  }

  /**
   * @expectedException \Exception
   * @expectedExceptionMessage handler called
   */
  public function testFailureHandlerCalledOnFailure(): void {
    // Using exceptions because locals are passed to lambdas byval
    (new ShipItShellCommand('/', 'false'))
      ->setFailureHandler($_ ==> {throw new \Exception("handler called");})
      ->runSynchronously();
  }

  public function testNoRetriesByDefault(): void {
    $file = \tempnam(\sys_get_temp_dir(), __CLASS__);
    \unlink($file);
    $result = (new ShipItShellCommand('/', 'test', '-e', $file))
      ->setFailureHandler($_ ==> \touch($file))
      ->setNoExceptions()
      ->runSynchronously();
    \unlink($file);
    $this->assertSame(1, $result->getExitCode());
  }

  public function testRetries(): void {
    $file = \tempnam(\sys_get_temp_dir(), __CLASS__);
    \unlink($file);
    $result = (new ShipItShellCommand('/', 'test', '-e', $file))
      ->setFailureHandler($_ ==> \touch($file))
      ->setNoExceptions()
      ->setRetries(1)
      ->runSynchronously();
    if (\file_exists($file)) {
      \unlink($file);
    }
    $this->assertSame(0, $result->getExitCode());
  }

  public function testRetriesNotUsedOnSuccess(): void {
    $file = \tempnam(\sys_get_temp_dir(), __CLASS__);
    // rm will fail if ran twice with same arg
    $result = (new ShipItShellCommand('/', 'rm', '--preserve-root', $file))
      ->setRetries(1)
      ->runSynchronously();
    if (\file_exists($file)) {
      \unlink($file);
    }
    $this->assertSame(0, $result->getExitCode());
  }
}
