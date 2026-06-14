<?php

declare(strict_types=1);



# Load local config file (ignored in GIT) if available
if ( (basename(__FILE__) != 'config.local.php') && is_file(__DIR__ . '/config.local.php') ) {
  require __DIR__ . '/config.local.php';
  return;
}



# where files will recide (make sure it has writable permissions)
define('STORAGE', '/var/files');

# should we require HTTPS and generate HTTPS links?
define('FORCE_HTTPS', false);

# How many days should we keep files without a per-upload expiration?
define('EXPIRE_DAYS', 30);

# Maximum expiration users can request, in seconds.
define('MAX_EXPIRATION_SECONDS', EXPIRE_DAYS * 86400);

# Password required when users enable per-upload password protection.
# Leave empty to disable password-protected uploads.
define('PASSWORD', '');

# How many downloads should we allow for legacy files without metadata?
define('MAX_DOWNLOADS', 1);

# Our website host
define('HOST', $_SERVER['HTTP_HOST'] ?? 'localhost');

# that's just to reset css/js cache on changes (added as GET parameter)
define('STATIC_VERSION', 11);

# is this available on the web? (will add meta tags and logo)
define('WEB', true);
