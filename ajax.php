<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* This scipt is called for collecting results via recorded MySQLi Statements.
* It is optimised for result collecting speed while still being secure from attacks.
* 
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

// Allow IE to save cookies when we are loaded inside an iframe
header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');

// Session should already be started from index.php
// If the session is not started then they should be able to access anything later
session_start();

// Non-changable constants
#if (isset($_SESSION['showDebug']) && $_SESSION['showDebug'] == 1)
#	define('DEBUG', true);
#if (isset($_SESSION['showStats']) && $_SESSION['showStats'] == 1)
#	define('STATS', true);

require_once 'config.php';
require_once CLASS_PATH. '/ErrorHandler.class.php';
require_once CLASS_PATH. '/HNMySQL.class.php';
date_default_timezone_set(SERVER_TIMEZONE);
HNMySQL::connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DATABASE);

// Check all queries and params for problems and fix them up
for ($i = 0; !empty($_REQUEST['q'.($i?$i:'')]); $i++) {
	$j = ($i ? $i : '');
	if (empty($_SESSION['AJAX_QUERIES']) || empty($_SESSION['AJAX_QUERIES'][$_REQUEST['q'.$j]]))
		die('Error: Requested stored query ' .$i. ' does not exist.');
	$_REQUEST['q'.$j] = $_SESSION['AJAX_QUERIES'][$_REQUEST['q'.$j]];
	if (!isset($_REQUEST['p'.$j]))
		$_REQUEST['p'.$j] = array();
	$_REQUEST['p'.$j] = (array) $_REQUEST['p'.$j];
	$ACTPARAM = count($_REQUEST['p'.$j]);
	$REQPARAM = strlen($_REQUEST['q'.$j][1]);
	if ($REQPARAM != $ACTPARAM)
		die('Error: Requested stored query ' .$i. ' needs ' .$REQPARAM. ' parameters, ' .$ACTPARAM. ' given.');
}
$totalQueries = $i;

// Start outer array of JSON
echo '[';

for ($i = 0; $i < $totalQueries; $i++) {
	$j = ($i ? $i : '');
	$QUERY = $_REQUEST['q'.$j];

	// Prepare PARAM array
	$PARAM = array();
	if (isset($_REQUEST['p'.$j])) {
		foreach ((array) $_REQUEST['p'.$j] as $k => $p)
			$PARAM[] = &$_REQUEST['p'.$j][$k]; // Looks nasty but saves mem
	}

	// Prepare the statement for use
	$stmt = HNMySQL::prepare($QUERY[0]);

	// Bind the parameters
	if ($stmt->param_count > 0) {
		array_unshift($PARAM, $QUERY[1]);
		call_user_func_array(array($stmt, 'bind_param'), $PARAM);
	}

	// Execute
	if (!$stmt->execute())
		die('Error: Statement failed to execute.');

	// Collect result into JSON
	if ($i > 0)
		echo ',';
	echo '[';
	$doneFirst = false;
	$aarray = array();
	$barray = array();
	for ($x = 0; $x < $stmt->field_count; $x++) {
		$aarray[] = '';
		$barray[] = &$aarray[$x];
	}
	call_user_func_array(array($stmt, 'bind_result'), $barray);
	while ($stmt->fetch()) {
		if ($doneFirst)
			echo ',';
		else
			$doneFirst = true;
		echo json_encode($barray);
	}
	echo ']';

	// Clean up for the sake of it
	$stmt->close();
}

// End outer array of JSON
echo ']';
