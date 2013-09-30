<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* This scipt is called for collecting results via recorded MySQLi Statements.
* It is optimised for result collecting speed while still being secure from attacks.
* 
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

// Allow IE to save cookies when we are loaded inside an iframe
header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');

// Session should already be started from index.php
// If the session is not started then they should be able to access anything later
$GLOBALS['ScriptStartTime'] = microtime(1);
session_start();

// Non-changable constants
#if (isset($_SESSION['showDebug']) && $_SESSION['showDebug'] == 1)
#	define('DEBUG', true);
#if (isset($_SESSION['showStats']) && $_SESSION['showStats'] == 1)
#	define('STATS', true);

require_once 'config.php';
require_once CLASS_PATH. '/ErrorHandler.class.php';
require_once CLASS_PATH. '/HNDB.class.php';
date_default_timezone_set(SERVER_TIMEZONE);

// Check all queries and params for problems and fix them up
$queryQueue = array();
for ($i = 0; ; $i++) {
	$j = ($i ? $i : '');
	if (empty($_REQUEST['q'.$j]))
		break;
	
	// Test if query code exists
	if (empty($_SESSION['AJAX_QUERIES']) || empty($_SESSION['AJAX_QUERIES'][$_REQUEST['q'.$j]]))
		die('Error: Requested stored query ' .$i. ' does not exist.');
	
	// Prepare query for queue
	$query = array();
	$query['def'] = $_SESSION['AJAX_QUERIES'][$_REQUEST['q'.$j]];
	$query['param'] = (array) $_REQUEST['p'.$j];
	
	// Do we have the right amount of params?
	$ACTPARAM = count($query['param']);
	$REQPARAM = count($query['def'][2]);
	if ($REQPARAM != $ACTPARAM)
		die('Error: Requested stored query ' .$i. ' needs ' .$REQPARAM. ' parameters, ' .$ACTPARAM. ' given.');
	
	$queryQueue[] = $query;
}

// Clean up
unset($_REQUEST, $_POST, $_GET);
unset($ACTPARAM, $REQPARAM, $query, $i, $j);

// Start outer array of JSON
echo '[';

foreach ($queryQueue as $queryNumer => $QUERY) {
	$DB =& HNDB::singleton(constant('HNDB_' .$QUERY['def'][0]));
	$stmt =& $DB->prepare($QUERY['def'][1], $QUERY['def'][2], $QUERY['def'][3]);
	$result =& $stmt->execute($QUERY['param']);
	if (PEAR::isError($result))
		die('Error: Statement failed to execute.');
	
	if ($queryNumer > 0)
		echo ',';
	if ($result instanceof MDB2_Result_Common) {
		// Return result data
		echo '[';
		$rowNum = 0;
		while (($row = $result->fetchRow()) && !PEAR::isError($row)) {
			if ($rowNum)
				echo ',';
			echo json_encode($row);
			$rowNum++;
			
			// Flush to browser every 25 rows
			if ($rowNum % 25 == 24)
				flush();
		}
		echo ']';
		flush();
		$result->free();
	} else {
		// Return number of affected rows
		echo $result;
	}
	
	// Clean up
	$stmt->free();
}

// End outer array of JSON
if (STATS)
	printf(',"Total Script Time: %1.2e sec"', microtime(1) - $GLOBALS['ScriptStartTime']);
echo ']';
