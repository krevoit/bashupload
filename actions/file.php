<?php

declare(strict_types=1);

# Download file handler



# file stats
$parts = array_values(array_filter(explode('/', trim($uri, '/')), 'strlen'));
$id = $parts[0] ?? '';
$requested_name = isset($parts[1]) ? rawurldecode($parts[1]) : '';

$file_path = $id !== '' ? storage_path($id) : '';
$metadata = $file_path !== '' ? read_metadata($file_path) : [];

if (!is_file($file_path) && $id !== '' && $requested_name !== '') {
  $legacy_path = '/' . $id . '-' . $requested_name;
  $file_path = rtrim(STORAGE, '/') . '/' . md5($legacy_path);
  $metadata = [
    'id' => $id,
    'name' => $requested_name,
    'size' => is_file($file_path) ? filesize($file_path) : 0,
    'password_protected' => false,
    'downloads' => 0,
    'max_downloads' => MAX_DOWNLOADS,
    'expires_at' => null,
  ];
}

$file = [
  'id' => $id,
  'name' => sanitize_file_name((string)($metadata['name'] ?? $requested_name)),
  'extension' => strtolower(pathinfo((string)($metadata['name'] ?? $requested_name), PATHINFO_EXTENSION)),
  'size' => is_file($file_path) ? filesize($file_path) : 0,
  'password_protected' => !empty($metadata['password_protected']),
  'expires_at' => $metadata['expires_at'] ?? null,
];

if ($file['size'] && !empty($file['expires_at']) && time() >= (int)$file['expires_at']) {
  delete_upload($file_path);
  $file['size'] = 0;
}

if ($file['size'] && $requested_name !== '' && isset($metadata['name']) && $requested_name !== $metadata['name']) {
  $file['size'] = 0;
}

$is_direct_download = (($_GET['download'] ?? null) || $renderer !== 'html');

if ($file['size'] && $is_direct_download && $file['password_protected'] && !password_matches(supplied_password())) {
  header('HTTP/1.0 403 Forbidden');
  $error = 'Password required.';
}


# title for rendering info
$title = $file['name'] . ' / download from bashupload.com';


# render
if ( !$is_direct_download ) {
  $sorry = !$file['size'];
}

# direct download
else if ( $file['size'] && !$error )
{
  $metadata['downloads'] = (int)($metadata['downloads'] ?? 0) + 1;
  write_metadata($file_path, $metadata);
  
	header('Content-type: ' . system_extension_mime_type($file['name']));
  header('Content-Disposition: attachment; filename="' . safe_download_filename($file['name']) . '"; filename*=UTF-8\'\'' . rawurlencode($file['name']));
	header('Content-Transfer-Encoding: binary');
	header('Content-Length: ' . $file['size']);
	readfile($file_path);

  if (empty($metadata['expires_at']) && (int)($metadata['downloads'] ?? 0) >= (int)($metadata['max_downloads'] ?? 1)) {
    delete_upload($file_path);
  }

	exit;
}

# no file found
else
{
  if ($error) {
    echo $error;
    exit;
  }

	header('HTTP/1.0 404 Not Found');
	exit;
}
