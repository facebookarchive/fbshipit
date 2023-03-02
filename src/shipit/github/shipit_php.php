<?hh
/**
 * Copyright (c) Meta Platforms, Inc. and affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */

namespace Facebook\ShipIt\PHP;

// NOTE: These functions exist as a compatibility layer with FB internal infrastructure.
// As such, do not depend on these functions or their type signatures outside of ShipIt!

function is_dir(string $filename): bool {
  return \is_dir($filename);
}

function dirname(string $path): string {
  return \dirname($path);
}

function mkdir(
  string $pathname,
  int $mode = 0777,
  bool $recursive = false,
  mixed $context = null,
): bool {
  return \mkdir($pathname, $mode, $recursive, $context);
}

function touch(string $filename, int $mtime = 0, int $atime = 0): bool {
  return \touch($filename, $mtime, $atime);
}

function file_put_contents(
  string $filename,
  mixed $data,
  int $flags = 0,
  mixed $context = null,
): dynamic {
  return \file_put_contents($filename, $data, $flags, $context);
}

function hash(string $algo, string $data, bool $raw_output = false): dynamic {
  return \hash($algo, $data, $raw_output);
}

function putenv(string $setting): bool {
  return \putenv($setting);
}

function getenv(string $varname): dynamic {
  return \getenv($varname);
}

function bin2hex(string $str): string {
  return \bin2hex($str);
}

function file_exists(string $filename): bool {
  return \file_exists($filename);
}

function php_uname(string $mode = ""): dynamic {
  return \php_uname($mode);
}

function sys_get_temp_dir(): string {
  return \sys_get_temp_dir();
}

function tempnam(string $dir, string $prefix): dynamic {
  return \tempnam($dir, $prefix);
}

function escapeshellarg(string $arg): string {
  if ($arg === '') {
    // Preserving legacy broken HHVM behavior that www currently relies on.
    return '';
  }
  return \escapeshellarg($arg);
}

function date(string $format, ?int $timestamp = null): string {
  return \date($format, $timestamp);
}

function mb_encode_mimeheader(
  string $str,
  ?string $charset = null,
  ?string $transfer_encoding = null,
  string $linefeed = "\r\n",
  int $indent = 0,
): dynamic {
  return \mb_encode_mimeheader(
    $str,
    $charset,
    $transfer_encoding,
    $linefeed,
    $indent,
  );
}

function parse_ini_string(
  string $ini,
  bool $process_sections = false,
  int $scanner_mode = \INI_SCANNER_NORMAL,
): dynamic {
  return \parse_ini_string($ini, $process_sections, $scanner_mode);
}

function rmdir(string $dirname, mixed $context = null): bool {
  return \rmdir($dirname, $context);
}

function strtotime(string $input, ?int $timestamp = null): dynamic {
  return \strtotime($input, $timestamp);
}

function stripslashes(string $str): string {
  return \stripslashes($str);
}

function preg_match(
  string $pattern,
  string $subject,
  inout vec_or_dict<string> $matches,
  int $flags = 0,
  int $offset = 0,
): int {
  return \preg_match_with_matches(
    $pattern,
    $subject,
    inout $matches,
    $flags,
    $offset,
  );
}

function preg_replace(
  mixed $pattern,
  mixed $replacement,
  mixed $subject,
  int $limit = -1,
): dynamic {
  return \preg_replace($pattern, $replacement, $subject, $limit);
}

function preg_quote(string $str, ?string $delimiter = null): string {
  return \preg_quote($str, $delimiter);
}

function ctype_space(mixed $text): bool {
  return \ctype_space($text);
}

function preg_match_all(
  string $pattern,
  string $subject,
  inout vec_or_dict<mixed> $matches,
  int $flags = 0,
  int $offset = 0,
): mixed {
  return \preg_match_all_with_matches(
    $pattern,
    $subject,
    inout $matches,
    $flags,
    $offset,
  );
}

function vsprintf(mixed $format, mixed $args): dynamic {
  return \vsprintf($format, $args);
}

function proc_close(resource $process): int {
  return \proc_close($process);
}

function fread(resource $handle, int $length): dynamic {
  return \fread($handle, $length);
}

function stream_select(
  inout mixed $read,
  inout mixed $write,
  inout mixed $except,
  mixed $vtv_sec,
  int $tv_usec = 0,
): dynamic {
  return \stream_select(
    inout $read,
    inout $write,
    inout $except,
    $vtv_sec,
    $tv_usec,
  );
}

function stream_set_blocking(resource $stream, bool $mode): bool {
  return \stream_set_blocking($stream, $mode);
}

function fclose(resource $handle): bool {
  return \fclose($handle);
}

function proc_get_status(resource $process): dict<string, dynamic> {
  return \proc_get_status($process);
}

function fwrite(resource $handle, string $data, int $length = 0): dynamic {
  return \fwrite($handle, $data, $length);
}

function basename(string $path, string $suffix = ""): string {
  return \basename($path, $suffix);
}

function flock(resource $handle, int $operation): bool {
  $wouldblock = null;
  return \flock($handle, $operation, inout $wouldblock);
}

function fopen(
  string $filename,
  string $mode,
  bool $use_include_path = false,
  mixed $context = null,
): dynamic {
  return \fopen($filename, $mode, $use_include_path, $context);
}

function fprintf(resource $handle, string $format, mixed ...$args): dynamic {
  return \fprintf($handle, $format, ...$args);
}

function curl_setopt(resource $ch, int $option, mixed $value): bool {
  return \curl_setopt($ch, $option, $value);
}

function curl_getinfo(resource $ch, int $opt = 0): dynamic {
  return \curl_getinfo($ch, $opt);
}

function curl_init(?string $url = null): dynamic {
  return \curl_init($url);
}

function urlencode(string $str): string {
  return \urlencode($str);
}

function gettype(mixed $v): string {
  return \gettype($v);
}

function getopt(
  string $options,
  mixed $longopts = null,
): dict<arraykey, dynamic> {
  return \getopt($options, $longopts);
}
