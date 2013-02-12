<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN LDAP Class
*
* This page contains the class to access the database.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

require_once CLASS_PATH. '/HNDB.class.php';

/**
* Gives static functions that deal with accesses to the database.
*/
class HNLDAP extends HNDB
{
	/**
	* Holds the connection after it has been created.
	* @var conn
	*/
	private static $conn;

	/**
	* Initiates the mysqli object for use.
	* 
	* CAUTION: DO NOT LEAVE THESE EXCEPTIONS UNCAUGHT! THEY CONTAIN THE PASSWORD!!
	* 
	* @param string $host The LDAP server hostname
	* @param string $version The LDAP server version
	* @param string $bind_rdn OPTIONAL The LDAP server RDN
	* @param string $bind_pass OPTIONAL The LDAP server password
	* @param string $host_port OPTIONAL The LDAP server port
	* @uses HNMySQL::mysqli
	*/
	public static function connect($host, $version, $rdn = NULL, $pass = NULL, $port = 389) {
		self::$conn = ldap_connect($host, $port);
		if (self::$conn === false) {
			// Failed to connect to database
			throw new HNDBException('Cannot Connect to LDAP Host: ' .$host, HNDBException::CANT_CONNECT);
		}
		ldap_set_option(self::$conn, LDAP_OPT_PROTOCOL_VERSION, $version);
		
		ob_start(); // Curse the warning which cannot be suppressed
		if (!ldap_bind(self::$conn, $rdn, $pass)) {
			ob_end_clean();
			// Failed to bind
			throw new HNDBException('Cannot Bind to LDAP Host: ' .$host, HNDBException::CANT_CONNECT);
		}
		ob_end_clean();
	}

	public static function close() {
		self::running(true);
		ldap_close(self::$conn);
	}

	/**
	* Processes a LDAP search.
	* 
	* @param string $base The tree base that this search is to be run on.
	* @param string $filter The LDAP filter string. eg: "sn=S*" finds all sn starting with S.
	* @param array $attributes An array of attributes to collect for the results.
	* @return 
	*/
	public static function search($base, $filter, $attributes) {
		self::running(true);
		$startTime = microtime( true );
		
		#var_dump('LDAPSearch', $filter, $base, $attributes);
		$sr = ldap_search(self::$conn, $base, $filter, $attributes);
		$result = ldap_get_entries(self::$conn, $sr);
		
		self::$lastTime = microtime(true) - $startTime;
		self::$sumTime += self::$lastTime;
		self::$sumQueries++;
		return $result;
	}

	/**
	* Esacpes the dn and filter strings for LDAP.
	* Code for this function found at http://www.php.net/manual/en/function.ldap-search.php#90158 on 4th Feb 2013.
	*/
	public static function escape($str, $for_dn = false) {
		// see:
		// RFC2254
		// http://msdn.microsoft.com/en-us/library/ms675768(VS.85).aspx
		// http://www-03.ibm.com/systems/i/software/ldap/underdn.html       
		   
		if  ($for_dn)
			$metaChars = array(',','=', '+', '<','>',';', '\\', '"', '#');
		else
			$metaChars = array('*', '(', ')', '\\', chr(0));

		$quotedMetaChars = array();
		foreach ($metaChars as $key => $value) $quotedMetaChars[$key] = '\\'.str_pad(dechex(ord($value)), 2, '0');
		$str=str_replace($metaChars,$quotedMetaChars,$str); //replace them
		return ($str);
	}

	/**
	* Exposes the ldap_sort method for regular use.
	*/
	public static function sort($result, $field) {
		self::running(true);
		return ldap_sort(self::$conn, $result, $feild);
	}

	/**
	* Test if the server connection is running
	*
	* @param boolean $throw (Default: FALSE) Set to true to have this throw an exception if the connecting is dead.
	* @return boolean Returns TRUE if the connection is running, FALSE otherwise.
	* @uses HNMySQL::conn
	*/
	public static function running($throw = false) {
		// Check that the mysqli object is running
		if (isset(self::$conn))
			return true;
		if ($throw)
			throw new HNDBException('Not connected to a DB Server', HNDBException::NOT_CONNECTED);
		return false;
	}
}


