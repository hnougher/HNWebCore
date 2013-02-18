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
if (isset($_SESSION['showDebug']) && $_SESSION['showDebug'] == 1)
	define('DEBUG', true);
if (isset($_SESSION['showStats']) && $_SESSION['showStats'] == 1)
	define('STATS', true);

// Check that there is a reason for them to be here and the reason for being here is valid
if (empty($_REQUEST['q']) || empty($_SESSION['AJAX_QUERIES']) || empty($_SESSION['AJAX_QUERIES'][$_REQUEST['q']]))
	die('Error: Requested stored query does not exist.');
$QUERY = $_SESSION['AJAX_QUERIES'][$_REQUEST['q']];

// Prepare PARAM array
$PARAM = array();
if (isset($_REQUEST['p'])) {
	foreach ((array) $_REQUEST['p'] as $k => $p)
		$PARAM[] = &$_REQUEST['p'][$k]; // Looks nasty but saves mem
}

// Now we start to get going
require_once 'config.php';
require_once CLASS_PATH. '/ErrorHandler.class.php';
require_once CLASS_PATH. '/HNMySQL.class.php';
date_default_timezone_set(SERVER_TIMEZONE);
HNMySQL::connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DATABASE);

// Prepare the statement for use
$stmt = HNMySQL::prepare($QUERY[0]);

// Check that there is enough parameters sent to execute the query
if ($stmt->param_count != count($PARAM))
	die('Error: Requested stored query needs ' .$stmt->param_count. ' parameters, ' .count($PARAM). ' given.');

// Bind the parameters
if ($stmt->param_count > 0) {
	array_unshift($PARAM, $QUERY[1]);
	call_user_func_array(array($stmt, 'bind_param'), $PARAM);
}

// Execute
if (!$stmt->execute())
	die('Error: Statement failed to execute.');

// Collect result into JSON
echo '[';
$doneFirst = false;
$result = $stmt->get_result();
while ($row = $result->fetch_row()) {
	if ($doneFirst)
		echo ',';
	else
		$doneFirst = true;
	echo json_encode($row);
}
echo ']';

// Clean up for the sake of it
$result->free();
$stmt->close();
