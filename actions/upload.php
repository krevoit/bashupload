<?php

declare(strict_types=1);

# Upload file(s) handler


$expiration_seconds = parse_expiration_seconds($_POST['expiration_seconds'] ?? $_SERVER['HTTP_X_EXPIRATION_SECONDS'] ?? null);
$password_protected = wants_password_protection();
$short_url = wants_short_url();

if ($password_protected) {
  if (configured_password() === '') {
    $error = 'Password protection is not configured on this server.';
  }
  else if (!password_matches(supplied_password())) {
    $error = 'Invalid password for password-protected upload.';
  }
}

if ($error) {
  header('HTTP/1.0 400 Bad Request');
}

# First, let's check raw input data
if (!$error && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'PUT' && $f = fopen('php://input', 'r'))
{
	$name = trim($uri, '/');
	if ( !$name ) $name = gen_id() . '.bin';
  $tmp_dir = rtrim(STORAGE, '/') . '/tmp';
  if (!is_dir($tmp_dir)) {
    mkdir($tmp_dir, 0700, true);
  }
  $ftmp = false;
	$tmp = tempnam($tmp_dir, 'upload');

  if ($tmp === false) {
    $error = 'Failed to create temporary upload file.';
  }
  else {
	  $ftmp = fopen($tmp, 'w');
  }

	if (!$error && $ftmp) {
    stream_copy_to_stream($f, $ftmp);
  }

	fclose($f);
	if ($ftmp) {
    fclose($ftmp);
  }

	if ( !$error && $tmp && filesize($tmp) ) $_FILES[] = [
		'tmp_name' => $tmp,
		'name' => $name
	];
}



# Next, let's move uploaded files to the storage
foreach ( $_FILES as $key_file => $file )
{
  if ($error) {
    if (isset($file['tmp_name']) && is_file($file['tmp_name'])) {
      unlink($file['tmp_name']);
    }
    continue;
  }

  # make file name safe
	$file['name'] = sanitize_file_name((string)$file['name']);

  # move file to a final location
  do {
    $id = gen_id();
	  $destination = storage_path($id);
  } while (is_file($destination) || is_file(metadata_path($destination)));

	if (!rename($file['tmp_name'], $destination)) {
    $error = 'Failed to store uploaded file.';
    continue;
  }

  $metadata = [
    'id' => $id,
    'name' => $file['name'],
    'size' => filesize($destination),
    'uploaded_at' => time(),
    'expires_at' => $expiration_seconds ? time() + $expiration_seconds : null,
    'password_protected' => $password_protected,
    'short_url' => $short_url,
    'downloads' => 0,
    'max_downloads' => $expiration_seconds ? null : 1,
  ];
  write_metadata($destination, $metadata);

  # register this uploaded file data
	$uploads[] = [
		'id' => $id,
		'name' => $file['name'],
		'path' => $destination,
		'size' => filesize($destination),
		'upload_name' => $key_file,
		'is_rewritten' => false,
    'short_url' => $short_url,
    'expires_at' => $metadata['expires_at'],
    'password_protected' => $password_protected,
	];
}

if ($error && !headers_sent()) {
  header('HTTP/1.0 400 Bad Request');
}
