<?php

$databases['default']['default'] = [
  'driver' => 'mysql',
  'host' => getenv('DB_HOST'),
  'database' => getenv('DB_NAME'),
  'username' => getenv('DB_USER'),
  'password' => getenv('DB_PASS'),
  'port' => '3306',
  'prefix' => '',
];

$settings['trusted_host_patterns'] = ['.*'];
$config['system.logging']['error_level'] = 'verbose';


$settings['hash_salt'] = 'C5qBeLVqfDZFK8sN9sf6kfEEpX6GmuCWZXbb0qd9DmV7fE7Hgjhae9TA_sxCXGFYgGS6jLoscA';
$settings['config_sync_directory'] = 'sites/default/files/config_gjpXSKXO8-etluOfnaJwDV8cD7Wtn_H-MV9hVv6FC5Il2XXcoPTWGbfzSxShOiwKLpHG4FXVxA/sync';
