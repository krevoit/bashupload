<?php

declare(strict_types=1);

# Clean expired and outdated files from storage

require __DIR__ . '/../config.php';
require __DIR__ . '/../lib.php';

$storage = realpath(STORAGE);
if ($storage === false || !is_dir($storage)) {
  exit;
}

$now = time();
$legacy_cutoff = $now - (EXPIRE_DAYS * 86400);

foreach (new DirectoryIterator($storage) as $entry) {
  if (!$entry->isFile()) {
    continue;
  }

  $path = $entry->getPathname();

  if (str_ends_with($path, '.json') || str_ends_with($path, '.delete')) {
    continue;
  }

  $metadata = read_metadata($path);

  if ($metadata) {
    $expires_at = $metadata['expires_at'] ?? null;
    if ($expires_at && $now >= (int)$expires_at) {
      echo $path . "\n";
      delete_upload($path);
    }

    continue;
  }

  $download_mark_file = $path . '.delete';
  $downloads = is_file($download_mark_file) ? (int)file_get_contents($download_mark_file) : 0;
  $download_mark_old_enough = is_file($download_mark_file) && filemtime($download_mark_file) < ($now - 3600);

  if ($entry->getMTime() < $legacy_cutoff || ($download_mark_old_enough && max($downloads, 1) >= MAX_DOWNLOADS)) {
    echo $path . "\n";
    delete_upload($path);
  }
}
