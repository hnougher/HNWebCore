<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* This file changes the error handler to a custom one that we can be much
* more flexible with.
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

/**
* This class does the actual error handling.
*/
class ErrorHandler
{
	/**
	* Contains the strings that can be used to display to the user
	* the type of error that has occured.
	* @var array
	*/
	private static $errorType = array(
			E_ERROR				 =>	'Error',
			E_WARNING			 =>	'Warning',
			E_PARSE				 =>	'Parsing Error',
			E_NOTICE			 =>	'Notice',
			E_CORE_ERROR		 =>	'Core Error',
			E_CORE_WARNING		 =>	'Core Warning',
			E_COMPILE_ERROR		 =>	'Compile Error',
			E_COMPILE_WARNING	 =>	'Compile Warning',
			E_USER_ERROR		 =>	'Custom Error',
			E_USER_WARNING		 =>	'Custom Warning',
			E_USER_NOTICE		 =>	'Custom Notice',
			E_STRICT			 =>	'Runtime Notice',
			E_RECOVERABLE_ERROR	 =>	'Catchable Fatal Error'
			);

	/**
	* Flag Value to disable error processing.
	* @var boolean
	*/
	public static $processErrors = true;

	/**
	* The handler function that the main php error system refers the errors
	* to to get processed.
	* 
	* @param integer $errNo Contains the level of the error raised.
	* @param string $errStr Contains the error message.
	* @param string $errFile Contains the filename that the error was raised in.
	* @param integer $errLine Contains the line number the error was raised at.
	* @see set_error_handler()
	*/
	public static function handler($errNo, $errStr, $errFile, $errLine) {
		$fatals = array(E_USER_ERROR, E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_RECOVERABLE_ERROR);
		if (self::$processErrors || in_array($errNo, $fatals)) {
			// Ignore notice in PEAR
			if (!preg_match('|[\\\/]pear[\\\/]|i', $errFile) || in_array($errNo, $fatals)) {
				echo self::$errorType[$errNo]. ': ';
				echo "$errStr in '$errFile' on line $errLine.\n";

				if (DEBUG) {
					ob_start();
						debug_print_backtrace();
					$dt = ob_get_clean();
					$dt = self::replaceFieldList($dt);
					echo "\n";
				}
			}
		}

		if (in_array($errNo, $fatals))
			die;
	}
	
	/*private static function replaceFieldList($str) {
		$start = -1;
		$ostr = $str;
		while (($start = strpos($str, 'Object (', $start + 1)) !== false) {
			$start += 7;
			$part1 = substr($str, 0, $start);
			$part2 = substr($str, $start);
			$str = $part1 . preg_replace("#\s\(((?>[^\(\)]+)|(?R))*\)#x", "*", $part2, 1);
			if ($start >= strlen($str))
				break;
		}
		return $ostr . "\n" . $str;
	}*/
	public static function replaceFieldList($str) {
		// Strip contents of Objects and Arrays
		$m1 = '\(\.{0,3}\)'; // Match (...) and (..) and (.) and ()
		$m2 = '(?:(Object|Array) \()'; // Match "Object (" and "Array ("
		$m3 = '[^\(\)]*'; // Match all non ( and ) chars
		$exp = '#' .$m2. '(?>(?:' .$m1. '|' .$m3. ')*)\)#';
		do {
			$oldStrLen = strlen($str);
			$str = preg_replace($exp, "$1*", $str);
		} while ($oldStrLen != strlen($str));
		
		// Replace built in passwords
#		$str = str_replace(MYSQL_PASS, '********', $str);
		return $str;
	}
	
	public static function crashHandler() {
		$ignoreCodes = array(E_STRICT);
		if (is_null($e = error_get_last()) === false
			&& !stripos($_SERVER['HTTP_HOST'], 'localhost')
			&& !in_array($e['type'], $ignoreCodes)
			) {
			$e['type'] = self::$errorType[$e['type']];
			mail(ADMIN_EMAILS, 'A Fatality has Occured on ' .SERVER_IDENT, print_r($e, true));
		}
	}
}

// This function does not exactly belong here but it is related to this section
function HNvar_dump($str) {
	return ErrorHandler::replaceFieldList($str);
}

set_error_handler( 'ErrorHandler::handler' );
register_shutdown_function('ErrorHandler::crashHandler');
