<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* The configuration file. Contains all the constant settings for the
* rest of the scripts to run without constant changes.
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/
define('PHP_OS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
define('BASE_PATH', str_replace('\\', '/', dirname(__FILE__)));


$config = array(
	'DEBUG' => true,
	'STATS' => true,

	'CSS_CACHETIME'		=> 7 * 24 * 60 * 60,
	'LOGIN_TIMEOUT'		=> 1200,
	'LOGIN_PRESALT'		=> '',
	'LOGIN_POSTSALT'	=> '',
	'LOGIN_HASHALG'		=> 'md5', // See PHP hash() or hash_algos()

	'SERVER_IDENT'		=> 'HNWebCore',
	'SERVER_ADDRESS'	=> 'http://localhost/HNWebCore',
	'SERVER_TIMEZONE'	=> 'Australia/NSW',
	'SERVER_EMAIL'		=> '', // The server will send emails from this email
	'ADMIN_EMAILS'		=> '', // (comma seperated) Emails about system errors will be sent here

	// Mail (uncomment what you want/need)
	// See http://pear.php.net/manual/en/package.mail.mail.factory.php
	'MAIL_BACKEND'		=> 'smtp',	// mail, smtp, sendmail
	#'MAIL_SENDMAIL_PATH'	=> '',	// Default: /usr/bin/sendmail
	#'MAIL_SENDMAIL_ARGS'	=> '',	// Default: -i
	#'MAIL_SMTP_HOST'	=> '',		// <host>[:<port>] Default: localhost:25
	#'MAIL_SMTP_AUTH'	=> '',		// <user>[:<pass>] Default: NONE
	'MAIL_SMTP_TIMEOUT'	=> 30,		// Default: NULL
	'MAIL_SMTP_DEBUG'	=> false,	// Default: FALSE
	'MAIL_SMTP_PERSIST'	=> true,	// Default: FALSE. Note: only a hint as there are hacks involved.

	// Main Database
	'MYSQL_HOST'		=> '127.0.0.1',
	'MYSQL_USER'		=> 'hnwc',
	'MYSQL_PASS'		=> 'RCWSn2su7MjJFp6F',
	'MYSQL_DATABASE'	=> 'hnwc',
	
	// LDAP Database
	'LDAP_ENABLED'		=> false,
	'LDAP_VERSION'		=> 3, // Gives protocol errors when set wrong
	'LDAP_HOST'			=> 'localhost',
	'LDAP_PORT'			=> 10389,
	'LDAP_BIND_RDN'		=> NULL,
	'LDAP_BIND_PASS'	=> NULL,
	
	'CLASS_PATH'		=> BASE_PATH . '/classes',
	'CRON_PATH'			=> BASE_PATH . '/cron',
	'DBSTRUCT_PATH'		=> BASE_PATH . '/dbstruct',
	#'INCLUDE_PATH'		=> array(BASE_PATH . '/PEAR'),
	'PAGE_PATH'			=> BASE_PATH . '/pages',
	'TEMPLATE_PATH'		=> BASE_PATH . '/hntpl',
	'NO_LOGIN_PAGES'	=> 'root,help', // (comma seperated, lowercase) List of paths that should not require login

	// Page Specific
	'SITE_TITLE'		=> 'HNWebCore',
	'SITE_DESCRIPTION'	=> 'HNWebCore',
	'SITE_KEYWORDS'		=> 'HNWebCore',
	);



// ############### DO NOT CHANGE BELOW HERE! #################
// Update Include Path
if (isset($config['INCLUDE_PATH'])) {
	set_include_path('.' . PATH_SEPARATOR . implode(PATH_SEPARATOR, $config['INCLUDE_PATH']));
	unset($config['INCLUDE_PATH']);
}
// Set the Constants
foreach ($config AS $const => $value) {
	if (!defined($const))
		define($const, $value);
}
unset($config, $const, $value);
