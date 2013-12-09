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

class HNDB
{
	/**
	* This array contains child arrays which hold stats from a DB class.
	* The structure looks like "'mysqli' => array(<runCount>, <runTime>)".
	* 
	* Note: this is public access because the data in it is fully controlled and
	*       only ever seen by people who need this (ie: programmers).
	*/
	public static $runStats = array();

	private static $MDB2;
	public static function MDB2() {
		if (!isset(self::$MDB2)) {
			require_once 'MDB2.php';
			self::$MDB2 = new MDB2();
		}
		return self::$MDB2;
	}

	private static function checkCustomDSN($dsn) {
		$dsn = self::MDB2()->parseDSN($dsn);
		
		// hnoci8_ldap has initial lookup here
		if (in_array($dsn['phptype'], array('hnoci8_ldap'))) {
			$startTime = microtime(true);
			$ds = ldap_connect($dsn['hostspec'], $dsn['port']); // Connect to ldap
			$r = ldap_bind($ds); // Bind to ldap
			$sr = ldap_search($ds, $ds, 'cn='.$dsn['database']); // Run query
			$info = ldap_get_entries($ds, $sr); // Get entries
			ldap_close($ds); // Close connection

			if (!isset($info[0]["orclnetdescstring"][0]))
				throw new Exception('Cannot reparse hnoci8_ldap connection');
			$dsn['hostspec'] = $info[0]["orclnetdescstring"][0]; // Extract db connect string from ldap search result array
			$dsn['phptype'] = $dsn['dbsyntax'] = 'hnoci8';
			$dsn['port'] = null;
			$dsn['database'] = null;
			
			if (empty(HNDB::$runStats['oci8_pre'])) {
				HNDB::$runStats['oci8_pre'] = array(
					'total_calls' => 0,
					'look_uptime' => 0,
				);
			}
			HNDB::$runStats['oci8_pre']['total_calls']++;
			HNDB::$runStats['oci8_pre']['look_uptime'] += microtime(true) - $startTime;
		}
		
		if (in_array($dsn['phptype'], array('hnmysqli','hnoci8','hnldap')))
			require_once CLASS_PATH . '/HNDB.' . $dsn['phptype'] . '.class.php';
		return $dsn;
	}

	public static function &factory($dsn, $options = false) {
		if ($options === false) $options = array();
#		$options['debug'] = (DEBUG ? 1 : 0);
		$options['persistent'] = true;
		
		$dsn = self::checkCustomDSN($dsn);
		$result =& self::MDB2()->factory($dsn, $options);
		if (HNDB::MDB2()->isError($result))
			throw new HNDBException($result->getMessage(), HNDBException::CANT_CONNECT);
		return $result;
	}

	public static function &connect($dsn, $options = false) {
		if ($options === false) $options = array();
#		$options['debug'] = (DEBUG ? 1 : 0);
		$options['persistent'] = true;
		
		$dsn = self::checkCustomDSN($dsn);
		$result =& self::MDB2()->connect($dsn, $options);
		if (HNDB::MDB2()->isError($result))
			throw new HNDBException($result->getMessage(), HNDBException::CANT_CONNECT);
		return $result;
	}

	public static function &singleton($dsn = null, $options = false) {
		if ($options === false) $options = array();
#		$options['debug'] = (DEBUG ? 1 : 0);
		$options['persistent'] = true;
		
		$dsn = self::checkCustomDSN($dsn);
		$result =& self::MDB2()->singleton($dsn, $options);
		if (HNDB::MDB2()->isError($result))
			throw new HNDBException($result->getMessage(), HNDBException::CANT_CONNECT);
		return $result;
	}

	/**
	* Highlights an SQL string to look more cool.
	*
	* @param string $sql The SQL string to highlight.
	* @return string An HTML string that is the SQL statement highlighted.
	*/
	public static function highlight_sql($sql, $color = true, $multiline = true)
	{
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
				'/(SELECT|UPDATE|INSERT|DELETE|GROUP_CONCAT|CONCAT_WS|CONCAT|COUNT|DISTINCT|IF|MAX|MIN|NULL|SUM|FROM|SET|LEFT JOIN|USING|ON|WHERE|GROUP BY|ORDER BY|ASC|DESC|HAVING|LIMIT|AS|UNIX_TIMESTAMP|SEPARATOR| AND | OR )/',
				);
			$replace = array(
				'<span style="color:#077">$1</span>',
				'<span style="color:#077">$1</span>',
				'<span style="color:#070">$1</span>',
				'<span style="color:#707;font-weight:bold">$1</span>',
				);
			$sql = preg_replace($find, $replace, $sql);
			$sql = '<span style="color:#F0F">' .$sql. '</span>';
		}
		if ($multiline) {
			$find = array(
				'/(FROM|SET|WHERE|GROUP BY|ORDER BY|HAVING|LIMIT)/',
				'/(LEFT JOIN| AND | OR )/',
				'/(,)/',
				'/([^C])(ON)/',
				);
			$replace = array(
				'<br/>$1',
				'<br/>&nbsp;&nbsp;&nbsp;&nbsp;$1',
				'$1<br/>&nbsp;&nbsp;&nbsp;&nbsp;',
				'$1<br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$2',
				);
			$sql = preg_replace($find, $replace, $sql);
		}
		return $sql;
	}
}

/** Contains WhereParts and tells how they go together. */
define('WHERE_AND', 1);
define('WHERE_OR', 2);
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
		if (count($proto) % 2 == count($this->parts) % 2)
			throw new Exception('Invalid number of sections in where append');
		$this->parts = $this->block($proto, $this->parts);
	}
	
	public function insertAt($proto, $insertAt) {
		throw new Exception('Not Implemented');
	}
	
	private function block($args, $block = array()) {
		if (count($args) % 2 != 1)
			throw new Exception('Invalid number of sections in where block');
		
		foreach ($args as $key => $arg) {
			if ($key % 2 == 1) {
				if ($arg != WHERE_AND && $arg != WHERE_OR)
					throw new Exception('Expected either WAND or WOR in index ' .$key);
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
	public $dontEscape;
	
	/**
	* @param string $field The field the restriction is to relate to.
	* @param string $sign The sign the restriction will use (ie: =, <=, >=, LIKE).
	* @param string $value Either a field name or a SQL code segment.
	* @param boolean $dontEscape If $compare is an SQL code segment then set this to true.
	*/
	public function __construct($field, $sign, $value, $dontEscape = false) {
		parent::__construct($field);
		$this->sign = $sign;
		$this->value = $value;
		$this->dontEscape = $dontEscape;
	}
}

define('ORDER_ASC', 1);
define('ORDER_DESC', 2);
class OrderList extends FieldList
{
	public function __construct($proto) {
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