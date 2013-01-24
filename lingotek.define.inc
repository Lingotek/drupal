<?php

/**
 * @file
 * Lingotek Module Constants.
 */

/**
 * Local Override - a local override config file may be used by either: 
 * (1) setting the environment var 'LINGOTEK_CONFIG_PATH' to a file, or by
 * (2) having a "config.php" file in your lingotek module folder 
 */
$localEnvConfigFilepath = getenv('LINGOTEK_CONFIG_PATH');
$localConfigFile = $localEnvConfigFilepath !== FALSE ? $localEnvConfigFilepath : getcwd() . '/' . drupal_get_path('module', 'lingotek') . '/config.php';
$useLocalOverride = file_exists($localConfigFile);

/**
 *  Config
 */
if ($useLocalOverride) {
  include_once $localConfigFile;
}
else {
  define('LINGOTEK_GMC_SERVER', 'https://gmc.lingotek.com');
  define('LINGOTEK_API_SERVER', 'http://myaccount.lingotek.com');
  define('LINGOTEK_BILLING_SERVER', 'http://cp.lingotek.com:8080/billing/account.json');
  define('LINGOTEK_AP_OAUTH_KEY', 'd944c2ae-b66e-4322-b37e-40ba0a495eb7');
  define('LINGOTEK_AP_OAUTH_SECRET', 'e4ae98ca-835b-4d9f-8faf-116ce9c69424');
}

/**
 * Defines the current module release version.
 */
define('LINGOTEK_VERSION', '7.x-2.2');

/**
 * Identifies a content type which has Lingotek translation enabled.
 */
define('LINGOTEK_ENABLED', 'lingotek');

/**
 * Defines the local path of the callback URL.
 */
define('LINGOTEK_NOTIFICATIONS_URL', 'admin/settings/lingotek/notifications');
