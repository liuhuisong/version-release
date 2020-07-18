<?php
define('VERSION', '0.1.3.2');

//log
define('LOG_MAX_SIZE', 4096);
if (PHP_OS == 'Linux') {
    define('LOG_PATH', '/var/log/version');
} else {
    define('LOG_PATH', 'c:\\temp\\version');
}

//you can set your filter in configure/ext in every directory
define('CONF_PKG_DEF_EXT', ['zip', 'bz2', 'gz', 'Z', 'xz', 'tar', 'tgz', 'apk', 'exe', 'iso', 'img']);
