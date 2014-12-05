<?php
# Customization
define('X3_CHECKIN_TITLE', getenv('X3CS_TITLE'));
define('X3_CHECKIN_THEME', getenv('X3CS_THEME'));

# Database
define('X3_CHECKIN_DB_DSN', sprintf(
    'mysql:dbname=%s;host=%s;port=%s',
    getenv('MYSQL_DB'),
    getenv('MYSQL_ADDR'),
    getenv('MYSQL_PORT')
));
define('X3_CHECKIN_DB_USER', getenv('MYSQL_USER'));
define('X3_CHECKIN_DB_PASS', getenv('MYSQL_PASSWORD'));

# Misc
define('X3_CHECKIN_DEBUG', getenv('DEBUG') === '1');

# Importer
$config = (object)array(
    'importer' => (object)array(
        'flags' => empty(getenv('X3CS_FLAGS')) ? [] : array(explode(',', getenv('X3CS_FLAGS')))
    ),
    'reset_password' => getenv('X3CS_RESET_PASSWORD')
);
