<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN DB Parent Class
*
* This page contains the base class to access the database.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
* @requires PEAR/MDB2
*/

/** Base working class of HNWebCore access to databases */
class HNDB
{
	const DATE_FORMAT = 'Y-m-d';
	const TIME_FORMAT = 'H:i:s';
	const DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	* This array contains child arrays which hold stats from a DB class.
	* The structure looks like "'mysqli' => array(<runCount>, <runTime>)".
	* 
	* Note: this is public access because the data in it is fully controlled and
	*       only ever seen by people who need this (ie: programmers).
	*/
	public static $runStats = array();

	/** Use this to get an instance of MDB2 for use anywhere */
	public static function MDB2() {
		static $MDB2;
		if (!isset($MDB2)) {
			$startTime = microtime(true);
			require_once 'MDB2.php';
			require_once 'PEAR/Exception.php';
			$MDB2 = new MDB2();
			if (class_exists('Loader'))
				Loader::$loadingTime += microtime(true) - $startTime;
		}
		return $MDB2;
	}

	/** Custom DSN processing.
	* Because of the overrides HNWebCore uses for making errors throw exception and
	* recording how long things take, this DSN function should be called by the
	* factories to make certain the correct files are loaded for continued loading.
	* 
	* @param $dsn is the DSN which needs to be processed.
	* @returns The updated DSN for sending into MDB2 loaders.
	*/
	private static function checkCustomDSN($dsn) {
		$dsn = self::MDB2()->parseDSN($dsn);
		
		// hnoci8_ldap has initial lookup here
		if (in_array($dsn['phptype'], array('hnoci8_ldap'))) {
			// Try cache
			if (empty($_SESSION['HNDB_HNOCI8_LDAP']))
				$_SESSION['HNDB_HNOCI8_LDAP'] = array();
			if (empty($_SESSION['HNDB_HNOCI8_LDAP'][implode('#',$dsn)])) {
				$startTime = microtime(true);
				
				$ds = ldap_connect($dsn['hostspec'], $dsn['port']); // Connect to ldap
				$r = ldap_bind($ds); // Bind to ldap
				$sr = ldap_search($ds, $ds, 'cn='.$dsn['database']); // Run query
				$_SESSION['HNDB_HNOCI8_LDAP'][implode('#',$dsn)] = ldap_get_entries($ds, $sr); // Get entries
				ldap_close($ds); // Close connection
				
				if (empty(HNDB::$runStats['oci8_pre'])) {
					HNDB::$runStats['oci8_pre'] = array(
						'total_calls' => 0,
						'look_uptime' => 0,
					);
				}
				HNDB::$runStats['oci8_pre']['total_calls']++;
				HNDB::$runStats['oci8_pre']['look_uptime'] += microtime(true) - $startTime;
			}

			$info = $_SESSION['HNDB_HNOCI8_LDAP'][implode('#',$dsn)];
			if (!isset($info[0]["orclnetdescstring"][0]))
				throw new Exception('Cannot reparse hnoci8_ldap connection');
			$dsn['hostspec'] = $info[0]["orclnetdescstring"][0]; // Extract db connect string from ldap search result array
			$dsn['phptype'] = $dsn['dbsyntax'] = 'hnoci8';
			$dsn['port'] = null;
			$dsn['database'] = null;
			
		}
		
		if (in_array($dsn['phptype'], array('hnmysqli','hnoci8','hnldap'))) {
			$startTime = microtime(1);
			require_once CLASS_PATH . '/HNDB.' . $dsn['phptype'] . '.class.php';
			if (class_exists('Loader'))
				Loader::$loadingTime += microtime(1) - $startTime;
		}
		return $dsn;
	}

	/** @see MDB2::factory() */
	public static function &factory($dsn, $options = false) {
		if ($options === false) $options = array();
#		$options['debug'] = (DEBUG ? 1 : 0);
		$options['persistent'] = true;
		$MDB2 = HNDB::MDB2();
		
		$dsn = self::checkCustomDSN($dsn);
		$result =& self::MDB2()->factory($dsn, $options);
		if ($MDB2->isError($result))
			throw new HNDBException($result->getMessage(), HNDBException::CANT_CONNECT);
		return $result;
	}

	/** @see MDB2::connect() */
	public static function &connect($dsn, $options = false) {
		if ($options === false) $options = array();
#		$options['debug'] = (DEBUG ? 1 : 0);
		$options['persistent'] = true;
		$MDB2 = HNDB::MDB2();
		
		$dsn = self::checkCustomDSN($dsn);
		$result =& self::MDB2()->connect($dsn, $options);
		if ($MDB2->isError($result))
			throw new HNDBException($result->getMessage(), HNDBException::CANT_CONNECT);
		return $result;
	}

	/** @see MDB2::singleton() */
	public static function &singleton($dsn = null, $options = false) {
		if ($options === false) $options = array();
#		$options['debug'] = (DEBUG ? 1 : 0);
		$options['persistent'] = true;
		$MDB2 = HNDB::MDB2();
		
		$dsn = self::checkCustomDSN($dsn);
		$result = self::MDB2()->singleton($dsn, $options);
		if ($MDB2->isError($result))
			throw new HNDBException($result->getMessage(), HNDBException::CANT_CONNECT);
		return $result;
	}

	/** Creates a singleton of just the DEFAULT connection */
	public static function &_DEFAULT() {
		$DB =& HNDB::singleton(HNDB_DEFAULT);
		return $DB;
	}

	/** @see MDB2::query() */
	function &query($query, $types = null, $result_class = true, $result_wrap_class = false) {
		$result =& query($query, $types, $result_class, $result_wrap_class);
		if ($MDB2->isError($result))
			throw new Exception(DEBUG ? $result->getUserInfo() : $result->getMessage());
		return $result;
	}

	/**
	* Highlights an SQL string to look more cool.
	*
	* @param string $sql The SQL string to highlight.
	* @param boolean $color Whether to use color highlighting. Default is true.
	* @param boolean $multiline Whether to add line breaks for better display.
	* @return string An HTML string that is the SQL statement highlighted.
	*/
	public static function highlight_sql($sql, $color = true, $multiline = true)
	{
		$keywords = array(
			'SELECT',
			'UPDATE',
			'INSERT',
			'DELETE',
			'GROUP_CONCAT',
			'WITHIN GROUP',
			'CONCAT_WS',
			'CONCAT',
			'COUNT',
			'DISTINCT',
			'IF',
			'MAX',
			'MIN',
			'IS NULL',
			'IS NOT NULL',
			'SUM',
			'FROM',
			'SET',
			'LEFT JOIN',
			'RIGHT JOIN',
			'INNER JOIN',
			'OUTER JOIN',
			'JOIN',
			'USING',
			'ON',
			'WHERE',
			'GROUP BY',
			'ORDER BY',
			'ASC',
			'DESC',
			'HAVING',
			'LIMIT',
			'AS',
			'UNIX_TIMESTAMP',
			'SEPARATOR',
			'CASE',
			'WHEN',
			'THEN',
			'ELSE',
			'END',
			'AND',
			'OR',
			'NOT',
			'LIKE',
		);
		$operators = array(
			'(?<=^|\\s)=(?=\\s|$)',
			'(?<=^|\\s)\+(?=\\s|$)',
			'(?<=^|\\s)-(?=\\s|$)',
			'(?<=^|\\s)\*(?=\\s|$)',
			'(?<=^|\\s)\/(?=\\s|$)',
			'(?<=^|\\s)%(?=\\s|$)',
			'\|\|',
			'\(',
			'\)',
			);
	
	
		$find = array(
			'/(\s{2,})/',

			// NOTE: This commented line is the same as something in the color area
/*			'/(SELECT|UPDATE|INSERT|DELETE|GROUP_CONCAT|CONCAT_WS|CONCAT|COUNT|DISTINCT|IF|MAX|MIN|NULL|SUM|FROM|SET|LEFT JOIN|USING|ON|WHERE|GROUP BY|ORDER BY|ASC|DESC|HAVING|LIMIT|AS|UNIX_TIMESTAMP|SEPARATOR)/',*/
			);
		$replace = array(
			' ',
/*			'<b>$1</b>',*/
			);
		$sql = preg_replace($find, $replace, $sql);
		if ($color) {
			$find = array(
				'/("[^"]*")/',
				'/(\'[^\']*\')/',
				'/((?:t\d.)?(?:`[^`]*`|\*))/',
				'/(?<=^|[^a-z0-9])(' .implode('|', $keywords). ')(?=[^a-z0-9]|$)/i',
				'/(?<=^|[^a-z0-9])([a-z][a-z_0-9\.]*(?=\()|:[a-z0-9]+)/i',
				'/(' .implode('|', $operators). ')/',
				);
			$replace = array(
				'<span style="color:#077">$1</span>',
				'<span style="color:#077">$1</span>',
				'<span style="color:#070">$1</span>',
				'<span style="color:#707;font-weight:bold">$1</span>',
				'<span style="color:#770;font-weight:bold">$1</span>',
				'<span style="color:#707">$1</span>',
				);
			$sql = preg_replace($find, $replace, $sql);
			$sql = '<span style="color:#F0F">' .$sql. '</span>';
		}
		if ($multiline) {
			$find = array(
				'/(?<=^|[^a-z0-9])(FROM|SET|WHERE|GROUP BY|ORDER BY|HAVING|LIMIT)(?=[^a-z0-9]|$)/i',
				'/(?<=^|[^a-z0-9])(LEFT JOIN|WHEN|ELSE|END|AND|OR)(?=[^a-z0-9]|$)/i',
				'/(,)/',
				);
			$replace = array(
				'<br/>$1',
				'<br/>&nbsp;&nbsp;&nbsp;&nbsp;$1',
				'$1<br/>&nbsp;&nbsp;&nbsp;&nbsp;',
				);
			$sql = preg_replace($find, $replace, $sql);
		}
		return $sql;
	}
}

define('WHERE_AND', 1);
define('WHERE_OR', 2);
/** Contains WhereParts and tells how they go together. */
class WhereList extends FieldList
{
	/** @see append() */
	public function __construct() {
		$args = func_get_args();
		if (count($args))
			$this->parts = $this->block($args);
	}
	
	/**
	* This method can be used two different ways.
	* 1. $proto can be given an array OR
	* 2. append() can be given unlimited parameters as though its an array.
	* 
	* The pieces need to follow the following pattern.
	* 1. If this WhereList has nothing in it so far the first parameter needs to be a WherePart.
	* 2. If this WhereList has something in it then the first parameter needs to be either WHERE_AND or WHERE_OR.
	* 3. There can be any number of parts given as long as every 2nd one is a WherePart and the other a WHERE_AND or WHERE_OR.
	* 4. Additionally a nested array of similar format can be given in place of and WherePart for bracketed WHERE.
	* 
	* eg: Object to Possible SQL.
	* 
	* new WhereList(new WherePart('a','=','1'), WHERE_OR, new WherePart('b','=','2'), WHERE_AND, new WherePart('c','=','3'))
	* WHERE `a`="1" OR `b`="2" AND `c`="3"
	* 
	* new WhereList(array(new WherePart('a','=','1'), WHERE_OR, new WherePart('b','=','2')), WHERE_AND, new WherePart('c','=','3'))
	* WHERE (`a`="1" OR `b`="2") AND `c`="3"
	*/
	public function append($proto) {
		if (!is_array($proto))
			$proto = func_get_args();
		$this->parts = $this->block($proto, $this->parts);
	}
	
	public function insertAt($proto, $insertAt) {
		throw new Exception('Not Implemented');
	}
	
	private function block($args, $block = array()) {
		if (count($args) % 2 == count($block) % 2)
			throw new Exception('Invalid number of sections in where block');
		
		foreach ($args as $key => $arg) {
			if (count($block) % 2 == 1) {
				if ($arg != WHERE_AND && $arg != WHERE_OR)
					throw new Exception('Expected either WHERE_AND or WHERE_OR in index ' .$key);
			} elseif (is_array($arg)) {
				$arg = $this->block($arg);
			} else {
				if (!($arg instanceof WherePart))
					throw new Exception('Invalid where part');
			}
			$block[] = $arg;
		}
		
		return $block;
	}
}

/** One section of a where filter. */
class WherePart extends FieldPart
{
	public $sign;
	public $value;
	public $dontEscapeField;
	public $dontEscapeValue;
	
	/**
	* @param string $field The field the restriction is to relate to.
	* @param string $sign The sign the restriction will use (ie: =, <=, >=, LIKE).
	* @param string $value Either a field name or a SQL code segment.
	* @param boolean $dontEscapeValue If $value is an SQL code segment then set this to true.
	* @param boolean $dontEscapeField If $field is an SQL code segment then set this to true.
	*/
	public function __construct($field, $sign, $value, $dontEscapeValue = false, $dontEscapeField = false) {
		parent::__construct($field);
		$this->sign = $sign;
		$this->value = $value;
		$this->dontEscapeField = $dontEscapeField;
		$this->dontEscapeValue = $dontEscapeValue;
	}
}

define('ORDER_ASC', 1);
define('ORDER_DESC', 2);
class OrderList extends FieldList
{
	public function __construct() {
		$args = func_get_args();
		if (count($args))
			$this->process($args);
	}
	
	public function append($proto) {
		$this->process($proto);
	}
	
	public function insertAt($index, $proto) {
		$this->process($proto, $index);
	}
	
	private function process($proto, $insertAt = false) {
		for ($i = 0; $i < count($proto); $i++) {
			$field = $proto[$i];
			$order = $proto[$i+1];
			if ($order === ORDER_ASC || $order === ORDER_DESC) {
				$i++;
				$part = new OrderPart($field, $order);
			} else {
				$part = new OrderPart($field);
			}
			
			if ($insertAt === false) {
				$this->parts[] = $part;
			} else {
				array_splice($this->parts, $insertAt, 0, array($part));
				$insertAt++;
			}
		}
	}
}

class OrderPart extends FieldPart
{
	public $order;
	public function __construct($field, $order = ORDER_ASC) {
		parent::__construct($field);
		if ($order != ORDER_ASC && $order != ORDER_DESC)
			throw new Exception('Order must be either ORDER_ASC or ORDER_DESC');
		$this->order = $order;
	}
}

class FieldList
{
	protected $parts = array();
	public function __construct() {
		$args = func_get_args();
		if (count($args))
			$this->append($args);
	}
	
	public function append($fields) {
		if (is_array($fields)) {
			foreach ($fields as &$field) {
				if (!($field instanceof FieldPart))
					$field = new FieldPart($field);
			}
			$this->parts = array_merge($this->parts, $fields);
		} else {
			if (!($fields instanceof FieldPart))
				$fields = new FieldPart($fields);
			$this->parts[] = $fields;
		}
	}
	
	public function insertAt($fields, $insertAt) {
		if (is_array($fields)) {
			foreach ($fields as &$field) {
				if (!($field instanceof FieldPart))
					$field = new FieldPart($field);
			}
			array_splice($this->parts, $insertAt, 0, $fields);
		} else {
			if (!($fields instanceof FieldPart))
				$fields = new FieldPart($fields);
			array_splice($this->parts, $insertAt, 0, array($fields));
		}
	}
	
	public function count() {
		return count($this->parts);
	}
	public function get() {
		return $this->parts;
	}
}

class FieldPart
{
	public $field;
	public function __construct($field) {
		$this->field = $field;
	}
}

class HNDBException extends Exception
{
	const NOT_CONNECTED = 1;
	const BAD_QUERY = 2;
	const CANT_CONNECT = 3;
}