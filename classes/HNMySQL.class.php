<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN MySQL Class
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
class HNMySQL extends HNDB
{
	const DATE_FORMAT = 'Y-m-d';
	const TIME_FORMAT = 'H:i:s';
	const DATETIME_FORMAT = 'Y-m-d H:i:s';

	/**
	* Holds the mysqli object after it has been initialised.
	* @var conn
	*/
	private static $conn;

	/**
	* Initiates the mysqli object for use.
	*
	* @param string $host The MySQL server hostname
	* @param string $user The MySQL server username
	* @param string $pass The MySQL server password
	* @param string $db The Database we are dealing with
	* @uses HNMySQL::conn
	*/
	public static function connect( $host, $user, $pass, $db )
	{
		self::$conn = new mysqli( $host, $user, $pass, $db );
		if( mysqli_connect_error() )
		{
			// Failed to connect to database
			die( 'Cannot Connect to Database: ' .mysqli_connect_error() );
		}
	}

	/**
	* Prepares a MySQLi Statement.
	* NOTE: Its a good idea to free_result() or close() the statement after finishing with a execution.
	* 
	* @param $sql The SQL which is directly used to create the statement.
	*/
	public static function prepare($sql) {
		self::running(true);
		$stmt = self::$conn->prepare($sql);
		if (!$stmt) {
			if (DEBUG)
				throw new HNDBException('Query Error: ' .self::$conn->error. '. SQL="' .$sql. '"', HNDBException::BAD_QUERY);
			else
				throw new HNDBException('Query Error: ' .self::$conn->error, HNDBException::BAD_QUERY);
		}
		$stmt->attr_set(MYSQLI_STMT_ATTR_PREFETCH_ROWS, 10); // Prefetch 10 rows at a time from server when using cursor
		return $stmt;
	}

	/**
	* Processes the Query
	*
	* @param string $sql The sql to be processed
	* @param string $resultMode The MySQL result mode to use
	* @return mysqli_result
	*/
	public static function query($sql, $resultMode = MYSQLI_STORE_RESULT) {
		self::running(true);
		$startTime = microtime( true );

		$result = self::$conn->query($sql, $resultMode);
		if ($result === false) {
			if (DEBUG)
				throw new HNDBException('Query Error: ' .self::$conn->error. '. SQL="' .$sql. '"', HNDBException::BAD_QUERY);
			else
				throw new HNDBException('Query Error: ' .self::$conn->error, HNDBException::BAD_QUERY);
		}
		self::$lastTime = microtime(true) - $startTime;
		self::$sumTime += self::$lastTime;
		self::$sumQueries++;
		// HNTPLPage::set_debug_raw(sprintf("%1.2e sec => %s", self::$lastTime, self::highlight_sql($sql, true, true)));
		return $result;
	}

	/**
	* @return integer Returns the most recent auto insert id.
	* @uses HNMySQL::conn
	*/
	public static function insert_id() {
		self::running(true);
		return self::$conn->insert_id;
	}

	/**
	* Hugh's implementation of the PHP > 5.3 mysqli result method fetch_all.
	* 
	* @param mysqli_result $result A result object from a query.
	* @param ? $resulttype This optional parameter is a constant indicating what type of array should be produced from the current row data. The possible values for this parameter are the constants MYSQLI_ASSOC, MYSQLI_NUM, or MYSQLI_BOTH. 
	* @return array
	*/
	public static function fetch_all($result, $resulttype = MYSQLI_NUM) {
		$ret = array();
		while ($row = $result->fetch_array($resulttype))
			$ret[] = $row;
		return $ret;
	}
	
	/**
	 * Expose the mysqli info parameter
	 */
	public static function info() {
		self::running(true);
		return self::$conn->info;
	}

	/**
	 * @return mysqli The MySQLi object that is being used by this class.
	 */
	public static function getMysqli() {
		return self::$conn;
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

	/**
	* Escapes some text
	*
	* @param string $text The text to be escaped
	* @return string The escaped string
	* @uses HNMySQL::conn
	*/
	public static function escape($text) {
		self::running(true);
		return self::$conn->real_escape_string( $text );
	}

	/**
	* Highlights an SQL string to look more cool.
	*
	* @param string $sql The SQL string to highlight.
	* @return string An HTML string that is the SQL statement highlighted.
	*/
	public static function highlight_sql( $sql, $color = false, $multiline = false )
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
		$sql = preg_replace( $find, $replace, $sql );
		if( $color )
		{
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
			$sql = preg_replace( $find, $replace, $sql );
			$sql = '<span style="color:#F0F">' .$sql. '</span>';
		}
		if( $multiline )
		{
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
			$sql = preg_replace( $find, $replace, $sql );
		}
		return $sql;
	}
}
