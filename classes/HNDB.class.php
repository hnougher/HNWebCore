<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN DB Parent Class
*
* This page contains the base class to access the database.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

abstract class HNDB
{
	/**
	* Holds the running total of number of queries run.
	* @var integer
	*/
	protected static $sumQueries = 0;

	/**
	* Holds the running total of time in queries.
	* @var integer
	*/
	protected static $sumTime = 0;

	/**
	* Holds the time it took for the last to run.
	* @var integer
	*/
	protected static $lastTime = 0;

	/**
	* @return integer The time it took the last query to complete.
	* @uses HNMySQL::lastTime
	*/
	public static function last_time()
	{
		return self::$lastTime;
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
	* @uses HNMySQL::sumQueries
	*/
	public static function sum_of_queries()
	{
		return self::$sumQueries;
	}
}

class HNDBException extends Exception
{
	const NOT_CONNECTED = 1;
	const BAD_QUERY = 2;
}