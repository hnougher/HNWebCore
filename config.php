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
	'DEBUG' => false,
	'STATS' => false,

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

	// Main Database
	'MYSQL_HOST'		=> '127.0.0.1',
	'MYSQL_USER'		=> 'hnwc_ucms',
	'MYSQL_PASS'		=> 'bEZ4hXrQMH52WyUJ',
	'MYSQL_DATABASE'	=> 'hnwc_ucms',
	
	// LDAP Database
	'LDAP_ENABLED'		=> false,
	'LDAP_VERSION'		=> 3, // Gives protocol errors when set wrong
	'LDAP_HOST'			=> 'localhost',
	'LDAP_PORT'			=> 10389,
	'LDAP_BIND_RDN'		=> NULL,
	'LDAP_BIND_PASS'	=> NULL,
	
	'CLASS_PATH'		=> BASE_PATH . '/classes',
	'DBSTRUCT_PATH'		=> BASE_PATH . '/dbstruct',
	'PAGE_PATH'			=> BASE_PATH . '/pages',
	'TEMPLATE_PATH'		=> BASE_PATH . '/hntpl',
	'NO_LOGIN_PAGES'	=> 'root,help', // (comma seperated, lowercase) List of paths that should not require login

	// Page Specific
	'SITE_TITLE'		=> 'HNWebCore',
	'SITE_DESCRIPTION'	=> 'HNWebCore',
	'SITE_KEYWORDS'		=> 'HNWebCore',
	);



// ############### DO NOT CHANGE BELOW HERE! #################
// Set the Constants
foreach( $config AS $const => $value )
{
	if( !defined( $const ) )
		define( $const, $value );
}
unset( $config, $const, $value );
