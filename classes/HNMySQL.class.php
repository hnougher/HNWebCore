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

/**
* Gives static functions that deal with accesses to the database.
*/
class HNMySQL
{
	/**
	* Holds the mysqli object after it has been initialised.
	* @var mysqli
	*/
	private static $mysqli;

	/**
	* Holds the running total of number of queries run.
	* @var integer
	*/
	private static $sumSql = 0;

	/**
	* Holds the running total of time in queries.
	* @var integer
	*/
	private static $sumTime = 0;

	/**
	* Holds the time it took for the last to run.
	* @var integer
	*/
	private static $lastTime = 0;

	/**
	* Initiates the mysqli object for use.
	*
	* @param string $host The MySQL server hostname
	* @param string $user The MySQL server username
	* @param string $pass The MySQL server password
	* @param string $db The Database we are dealing with
	* @uses HNMySQL::mysqli
	*/
	public static function connect( $host, $user, $pass, $db )
	{
		self::$mysqli = new mysqli( $host, $user, $pass, $db );
		if( mysqli_connect_error() )
		{
			// Failed to connect to database
			die( 'Cannot Connect to Database: ' .mysqli_connect_error() );
		}
	}

	/**
	* Processes the Query
	*
	* @param string $sql The sql to be processed
	* @param string $sql The sql to be processed
	* @return mysqli_result
	*/
	public static function query($sql, $resultMode = MYSQLI_STORE_RESULT) {
		// Check that the mysqli object is running
		if (!isset(self::$mysqli)) {
			throw new HNMySQLException('Not connected to a MySQL Server', HNMySQLException::NOT_CONNECTED);
		}

		$startTime = microtime( true );

		$result = self::$mysqli->query($sql, $resultMode);
		if ($result === false) {
			if (DEBUG)
				throw new HNMySQLException( 'Query Error: SQL="' .$sql. '", ERROR="' .self::$mysqli->error. '"', HNMySQLException::BAD_QUERY );
			else
				throw new HNMySQLException('Query Error: ' .self::$mysqli->error, HNMySQLException::BAD_QUERY);
		}
		self::$lastTime = microtime(true) - $startTime;
		self::$sumTime += self::$lastTime;
		self::$sumSql++;
		// HNTPLPage::set_debug_raw(sprintf("%1.2e sec => %s", self::$lastTime, self::highlight_sql($sql, true, true)));
		return $result;
	}

	/**
	* @return integer Returns the most recent auto insert id.
	* @uses HNMySQL::mysqli
	*/
	public static function insert_id()
	{
		// Check that the mysqli object is running
		if( !isset( self::$mysqli ) )
		{
			throw new HNMySQLException( 'Not connected to a MySQL Server', HNMySQLException::NOT_CONNECTED );
		}

		return self::$mysqli->insert_id;
	}

	/**
	* Hugh's implementation of the PHP > 5.3 mysqli result method fetch_all.
	* 
	* @param mysqli_result $result A result object from a query.
	* @param ? $resulttype This optional parameter is a constant indicating what type of array should be produced from the current row data. The possible values for this parameter are the constants MYSQLI_ASSOC, MYSQLI_NUM, or MYSQLI_BOTH. 
	* @return array
	*/
	public static function fetch_all( $result, $resulttype = MYSQLI_NUM )
	{
		$ret = array();
		while( $row = $result->fetch_array( $resulttype ) )
			$ret[] = $row;
		return $ret;
	}
	
	/**
	 * Expose the mysqli info parameter
	 */
	public static function info()
	{
		// Check that the mysqli object is running
		if( !isset( self::$mysqli ) )
		{
			throw new HNMySQLException( 'Not connected to a MySQL Server', HNMySQLException::NOT_CONNECTED );
		}

		return self::$mysqli->info;
	}

	/**
	* @return integer The time it took the last query to complete.
	* @uses HNMySQL::lastTime
	*/
	public static function last_time()
	{
		return self::$lastTime;
	}

	/**
	 * @return mysqli The MySQLi object that is being used by this class.
	 */
	public static function getMysqli()
	{
		return self::$mysqli;
	}

	/**
	* @return integer The sum of the time it has taken all queries to complete upto now.
	* @uses HNMySQL::sumTime
	*/
	public static function sum_of_time()
	{
		return self::$sumTime;
	}

	/**
	* @return integer The count of queries to complete upto now.
	* @uses HNMySQL::sumSql
	*/
	public static function sum_of_sql()
	{
		return self::$sumSql;
	}

	/**
	* Test if the server is running
	*
	* @param string $text The text to be escaped
	* @return string The escaped string
	* @uses HNMySQL::mysqli
	*/
	public static function running( )
	{
		// Check that the mysqli object is running
		if( isset( self::$mysqli ) )
		  return true;
		else
		  return false;
	}

	/**
	* Escapes some text
	*
	* @param string $text The text to be escaped
	* @return string The escaped string
	* @uses HNMySQL::mysqli
	*/
	public static function escape( $text )
	{
		// Check that the mysqli object is running
		if( !isset( self::$mysqli ) )
		{
			throw new HNMySQLException( 'Not connected to a MySQL Server', HNMySQLException::NOT_CONNECTED );
		}

		return self::$mysqli->real_escape_string( $text );
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

class HNMySQLException extends Exception
{
	const NOT_CONNECTED = 1;
	const BAD_QUERY = 2;
}
