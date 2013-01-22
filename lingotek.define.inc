<?php
/**
 * @file
 * Module constants.
 */

/**
 * Production Servers
 */
/*
define('LINGOTEK_GMC_SERVER', 'https://gmc.lingotek.com');
define('LINGOTEK_API_SERVER', 'http://myaccount.lingotek.com');
define('LINGOTEK_BILLING_SERVER', 'http://cp.lingotek.com:8080/billing/account.json');
define('LINGOTEK_AP_OAUTH_KEY', 'd944c2ae-b66e-4322-b37e-40ba0a495eb7');
define('LINGOTEK_AP_OAUTH_SECRET', 'e4ae98ca-835b-4d9f-8faf-116ce9c69424');
/**/

/**
 * Staging Servers
 */
/*
define('LINGOTEK_GMC_SERVER', 'https://gmc.lingotek.com');
define('LINGOTEK_API_SERVER', 'http://cms.lingotek.com');
define('LINGOTEK_BILLING_SERVER', 'http://cp.lingotek.com:8080/billing/account.json');
define('LINGOTEK_AP_OAUTH_KEY', 'd944c2ae-b66e-4322-b37e-40ba0a495eb7');
define('LINGOTEK_AP_OAUTH_SECRET', 'e4ae98ca-835b-4d9f-8faf-116ce9c69424');
/**/

/**
 * Development Servers
 */
/**/
define('LINGOTEK_GMC_SERVER', 'http://10.0.11.220:3000');
define('LINGOTEK_API_SERVER', 'http://10.0.11.136:8080');
define('LINGOTEK_BILLING_SERVER', 'http://10.0.11.136:8080/billing_api/account.json');
define('LINGOTEK_AP_OAUTH_KEY', '61225fa9-215e-4762-853c-951c176fb938');
define('LINGOTEK_AP_OAUTH_SECRET', '8dcbf1eb-44d9-4e38-b770-263deab63b8f');
/**/


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
