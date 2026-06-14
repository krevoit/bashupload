<?php

declare(strict_types=1);

# Utilities



# Get system MIME types list
function system_extension_mime_types(): array {
    $out = array();
    $file = is_readable('/etc/mime.types') ? fopen('/etc/mime.types', 'r') : false;

    if (!$file) {
      return $out;
    }
    
    while(($line = fgets($file)) !== false) {
        $line = trim(preg_replace('/#.*/', '', $line));
        if(!$line) continue;
        $parts = preg_split('/\s+/', $line);
        if(count($parts) == 1) continue;
        $type = array_shift($parts);
        foreach($parts as $part) $out[$part] = $type;
    }
    
    fclose($file);
    
    return $out;
}


# Get file MIME type by its extension
function system_extension_mime_type(string $file): string {
    static $types;
    
    if(!isset($types)) $types = system_extension_mime_types();
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    
    if(!$ext) $ext = $file;
    $ext = strtolower($ext);
    
    return isset($types[$ext]) ? $types[$ext] : 'application/octet-stream';
}


# generate random file prefix-id
function gen_id(int $length = 8): string
{
    $id   = '';
    $abc  = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
    $abc .= "abcdefghijklmnopqrstuvwxyz";
    $abc .= "0123456789";
    $abc .= '_-';

    $max  = strlen($abc);

    for ($i=0; $i < $length; $i++) $id .= $abc[random_int(0, $max-1)];

    return $id;
}


# Build an absolute URL for an uploaded file.
function file_url(array $file): string
{
  $name = rawurlencode($file['name']);
  $path = !empty($file['short_url']) ? $file['id'] : $file['id'] . '/' . $name;

  return upload_base_url() . '/' . $path;
}


function upload_base_url(): string
{
  return request_scheme() . '://' . HOST;
}


# Detect request scheme behind a proxy-aware web server.
function request_scheme(): string
{
  if (force_https_enabled()) {
    return 'https';
  }

  return actual_request_scheme();
}


function actual_request_scheme(): string
{
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    return strtolower((string)explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]) === 'https' ? 'https' : 'http';
  }

  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    return 'https';
  }

  return $_SERVER['REQUEST_SCHEME'] ?? 'http';
}


function force_https_enabled(): bool
{
  return (bool)FORCE_HTTPS;
}


# Convert user-controlled file names into safe display/download names.
function sanitize_file_name(string $name): string
{
  $name = trim($name, "/ \t\n\r\0\x0B");
  $name = str_replace(['/', '\\', "\r", "\n", "\0"], '_', $name);
  $name = preg_replace('/[^\w.\-]+/u', '_', $name) ?: '';
  $name = trim($name, '._-');

  if ($name === '') {
    return gen_id() . '.bin';
  }

  if (strlen($name) > 80) {
    $extension = pathinfo($name, PATHINFO_EXTENSION);
    return gen_id() . ($extension ? '.' . strtolower($extension) : '');
  }

  return $name;
}


# Storage path for the opaque upload id.
function storage_path(string $id): string
{
  return rtrim(STORAGE, '/') . '/' . hash('sha256', '/' . $id);
}


function metadata_path(string $file_path): string
{
  return $file_path . '.json';
}


function write_metadata(string $file_path, array $metadata): void
{
  file_put_contents(metadata_path($file_path), json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
}


function read_metadata(string $file_path): array
{
  $metadata_file = metadata_path($file_path);
  if (!is_file($metadata_file)) {
    return [];
  }

  $metadata = json_decode((string)file_get_contents($metadata_file), true);
  return is_array($metadata) ? $metadata : [];
}


function delete_upload(string $file_path): void
{
  foreach ([$file_path, metadata_path($file_path), $file_path . '.delete'] as $path) {
    if (is_file($path)) {
      unlink($path);
    }
  }
}


function parse_expiration_seconds(?string $seconds): ?int
{
  if ($seconds === null || trim($seconds) === '') {
    return null;
  }

  if (!ctype_digit(trim($seconds))) {
    return null;
  }

  $seconds = (int)$seconds;
  if ($seconds <= 0) {
    return null;
  }

  $max = defined('MAX_EXPIRATION_SECONDS') ? MAX_EXPIRATION_SECONDS : EXPIRE_DAYS * 86400;
  return min($seconds, $max);
}


function wants_short_url(): bool
{
  return truthy($_POST['short_url'] ?? $_SERVER['HTTP_X_SHORT_URL'] ?? null);
}


function wants_password_protection(): bool
{
  return truthy($_POST['password_protected'] ?? $_SERVER['HTTP_X_PASSWORD_PROTECT'] ?? null);
}


function truthy(mixed $value): bool
{
  if (is_bool($value)) {
    return $value;
  }

  return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
}


function configured_password(): string
{
  return defined('PASSWORD') ? (string)PASSWORD : '';
}


function supplied_password(): string
{
  $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  if (stripos($authorization, 'Bearer ') === 0) {
    return trim(substr($authorization, 7));
  }

  return trim((string)($_POST['password'] ?? $authorization));
}


function password_matches(string $password): bool
{
  $configured = configured_password();
  return $configured !== '' && hash_equals($configured, $password);
}


function safe_download_filename(string $name): string
{
  return str_replace(['"', '\\', "\r", "\n", "\0"], '_', $name);
}


# get max file upload size from settings
function file_upload_max_size(): string {
  static $max_size_bytes = -1;

  if ($max_size_bytes < 0) {
    // Start with post_max_size.
    $post_max_size = parse_ini_bytes_size((string)ini_get('post_max_size'));
    if ($post_max_size > 0) {
      $max_size_bytes = $post_max_size;
    }

    // If upload_max_size is less, then reduce. Except if upload_max_size is
    // zero, which indicates no limit.
    $upload_max = parse_ini_bytes_size((string)ini_get('upload_max_filesize'));
    if ($upload_max > 0 && $upload_max < $max_size_bytes) {
      $max_size_bytes = $upload_max;
    }
  }
  
  // Convert to GB
  $max_size = $max_size_bytes / (1024 * 1024 * 1024);
  if ( $max_size < 1 ) {
    $max_size = round($max_size, 3);
  }
  else {
    $max_size = round($max_size);
  }
  
  return $max_size . 'G';
}

# parse php.ini size value
function parse_ini_bytes_size(string $size): float {
  $unit = preg_replace('/[^bkmgtpezy]/i', '', $size); // Remove the non-unit characters from the size.
  $size = preg_replace('/[^0-9\.]/', '', $size); // Remove the non-numeric characters from the size.
  if ($unit) {
    // Find the position of the unit in the ordered string which is the power of magnitude to multiply a kilobyte by.
    return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
  }
  else {
    return round($size);
  }
}
