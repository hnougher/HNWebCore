<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* Core Running page
* 
* This page is what is called to access any website pages for this site.
* The .htaccess file in this directory enforces this restriction so the
* rest of the pages on the site are reasonably secure.
* 
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

// Allow IE to save cookies when we are loaded inside an iframe
header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');

$GLOBALS['ScriptStartTime'] = microtime(1);
session_start();

// Non-changable constants and config
if (isset($_SESSION['showDebug']) && $_SESSION['showDebug'] == 1)
	define('DEBUG', true);
if (isset($_SESSION['showStats']) && $_SESSION['showStats'] == 1)
	define('STATS', true);
require_once 'config.php';

// Small amount of code to test if we are still logged in
// Call ?TEST_SESSION_TIMEOUT to test or ?TEST_SESSION_TIMEOUT=NOTIFY to extend timeout if not already timed out.
// Returns zero if timed out or >0 otherwise. The number is also the number of seconds left until timeout.
if (isset($_REQUEST['TEST_SESSION_TIMEOUT'])) {
	if (!isset($_SESSION['UserLoggedIn']) || $_SESSION['UserLastSeen'] + LOGIN_TIMEOUT <= time())
		die('0');
	if ($_REQUEST['TEST_SESSION_TIMEOUT'] == 'NOTIFY')
		$_SESSION['UserLastSeen'] = time();
	die(($_SESSION['UserLastSeen'] + LOGIN_TIMEOUT - time()). '');
}

require_once CLASS_PATH. '/ErrorHandler.class.php';
require_once CLASS_PATH. '/HNDB.class.php';
require_once CLASS_PATH. '/HNOBJBasic.obj.class.php';
require_once CLASS_PATH. '/HNMOBBasic.class.php';
require_once CLASS_PATH. '/AutoQuery.class.php';
require_once TEMPLATE_PATH. '/page.php';

// This is where everything actually starts for HNWebCore
Loader::initiate();

// Error and Confirm message functions
function confirm($message) {
	if (empty($_SESSION['confirm']))
		$_SESSION['confirm'] = array();
	$_SESSION['confirm'][] = $message;
}
function error($message) {
	if (empty($_SESSION['error']))
		$_SESSION['error'] = array();
	$_SESSION['error'][] = $message;
}
function isFalse($condition, $message) {
	if (!$condition)
		error($message);
	return !$condition;
}

// AJAX stored query functions
/**
* This function is used to store a query in $_SESSION.
* It returns a basically random code for use within JS.
* NOTE: This function will get *very* slow when lots are stored.
* NOTE: Stored querys get cleared when session ends (logout or timeout).
*
* @param $connection The DB connection on which this query will run.
* @param $sql The SQL which is of the style of a MDB2 statement.
* @param $paramTypes An array of types that a MDB2 Statement uses for input types.
* @param $resultTypes An array of types that a MDB2 Statement uses for output types OR MDB2_PREPARE_MANIP.
* @return string unique code which is to be put into a JS request to the URI like
*      "/ajax.php?q=<code>&p[]=<param1>&p[]=<param2>" which when called returns a
*      JSON array of results.
*/
function StoreAJAXQuery($connection, $sql, $paramTypes = array(), $resultTypes = array()) {
	if (empty($_SESSION['AJAX_QUERIES']))
		$_SESSION['AJAX_QUERIES'] = array();
	
	// Check if this query already exists in the stored set.
	foreach ($_SESSION['AJAX_QUERIES'] as $k => $v) {
		if ($v[0] == $connection && $v[1] == $sql)
			return $k;
	}
	
	// Check the parameters are reasonably valid
	if (!defined('HNDB_' .$connection))
		throw new Exception('Undefined connection used');
	
	// Generate unique code
	do {
		$code = substr(md5(SERVER_ADDRESS . mt_rand(0, PHP_INT_MAX)), 16);
	} while (isset($_SESSION['AJAX_QUERIES'][$code]));
	
	// Store the query and return code
	$_SESSION['AJAX_QUERIES'][$code] = array($connection, $sql, $paramTypes, $resultTypes);
	return $code;
}

/**
* The website loader class.
*/
class Loader
{
	public static function fixPath($path) {
		if (PHP_OS_WINDOWS) $path = str_replace('\\', '/', $path);
		return $path;
	}

	/**
	* The very first function to be run for any webpage.
	* 
	* @uses HNTPLPage
	* @uses run_script()
	*/
	public static function initiate() {
		ob_start();

		// Set the Default Timezone
		date_default_timezone_set(SERVER_TIMEZONE);

		// Collect the normal vars from the input
		$query = !empty($_GET['autoquery']) ? explode('/', $_GET['autoquery']) : array('root');
		if (empty($query[count($query) - 1]))
			array_pop($query);

		// Do a login_end / logout if required
		if (isset($_REQUEST['logout'])
			|| (isset($_SESSION['UserLoggedIn']) && $_SESSION['UserLastSeen'] + LOGIN_TIMEOUT <= time())
			) {
			self::logout();
		}
		if (isset($_REQUEST['login']))
			self::login_end();

		// Clean up the input lists for security sake
		unset($_GET['login'], $_POST['login'], $_REQUEST['login']);
		unset($_GET['logout'], $_POST['logout'], $_REQUEST['logout']);
		unset($_GET['autoquery'], $_POST['autoquery'], $_REQUEST['autoquery']);

		// Do login if required
		if (!isset($_SESSION['UserLoggedIn'])) {
			if (!in_array(strtolower($query[0]), explode(',', NO_LOGIN_PAGES))) {
				self::login_start();
				die;
			} else {
				$uo = null;
			}
		} else {
			// Update the last seen time
			$_SESSION['UserLastSeen'] = time();

			// Load the staff object
			$uo = HNOBJBasic::loadObject('user', $_SESSION['UserLoggedIn']);
			self::loggedInUser($uo);
			$GLOBALS['UserRolesAtStart'] = $uo->userTypes();

			// Delay set the debug showing for programmers
			if ($uo->testUserType('programmer')) {
				$_SESSION['showDebug'] = 1;
				$_SESSION['showStats'] = 1;
			}

			unset($thisParam, $thisKey);
		}

		// Display the page
		$HNTPL = new HNTPLPage(SITE_TITLE, false);
		try {
			$return = self::run_page($HNTPL, $query, array('uo' => $uo));
		} catch (Exception $e) {
			ob_start();
			printf("<b>Uncaught page exception</b> '%s' with message '%s'<br/>",
				$HNTPL->escape_text(get_class($e)),
				$HNTPL->escape_text($e->getMessage()));
			echo str_replace("\n", "<br/>", $e->getTraceAsString());
			printf("<br/>thrown in <b>%s</b> on line <b>%s</b><br/>",
				$HNTPL->escape_text($e->getFile()),
				$e->getLine());
			$HNTPL->new_raw('<br/>' . ob_get_clean());
		}

		// Check if we are redirecting
		if (isset($return) && is_string($return)) {
			// Redirect the client
			$location = SERVER_ADDRESS.$return.(isset($_REQUEST['noTemplate']) ? '&noTemplate=1' : '');
			header('Location: ' .$location);
			die('Redirecting to <a href="' .$location. '">' .$location. '</a>');
		}

		// Set the base template variables
		if ($uo != null) {
			$HNTPL->set_user_name($uo['username']);
		}

		self::InsertDebugStats();

		// Output the template
		if (isset($_REQUEST['pdf']) && $_REQUEST['pdf'] == 1) {
			$HNTPL->outputPDF();
		} else {
			#ob_start( "ob_gzhandler" );
			$HNTPL->output();

			if (STATS)
				printf('<span class="screenOnly">Total Script Time: %1.4f sec</span>', microtime(1) - $GLOBALS['ScriptStartTime']);
		}
		
		// Check the uo has not changed
		self::loggedInUser('check');
	}

	private static function InsertDebugStats() {
		// Add the debug text if it exists and allowed
		$debug = trim(ob_get_clean());
		if (DEBUG) {
			if (!empty($debug))
				HNTPLPage::set_debug($debug);
		} else {
			HNTPLPage::clear_debug();
		}

		// Add statistic information
		if (STATS)
			HNTPLPage::set_debug_raw(self::get_stats());
	}

	public static function get_stats() {
		$OBJStats = HNOBJBasic::getObjectCounts();
		$MOBStats = HNMOBBasic::getObjectCounts();
		$out = sprintf("<hr/><b>User Roles at Start:</b> %s\n<b>Pre Output Time:</b> %1.4f sec\n<b>Memory Used At End, Peak:</b> %01.1f MB, %01.1f MB",
			(isset($GLOBALS['UserRolesAtStart']) ? implode(', ', array_keys($GLOBALS['UserRolesAtStart'])) : 'all'),
			microtime(1) - $GLOBALS['ScriptStartTime'],
			memory_get_usage() / 1024 / 1024,
			memory_get_peak_usage() / 1024 / 1024
			);
		
		foreach (HNDB::$runStats as $type => $stats) {
			$out .= "\n<b>" .$type. "</b>";
			foreach ($stats as $key => $val) {
				$out .= sprintf("\n\t<b>%s</b>: %1.4" .(is_int($val) ? 'd' : 'f sec'),
					$key, $val);
			}
		}
		
		$out .= sprintf("\n<b>OBJ Total Created, Current:</b> %d, %d",
			$OBJStats[0],
			$OBJStats[0] - $OBJStats[1]
			);
		
		foreach ($OBJStats[2] AS $table => $total)
			$out .= "\n\t$table: $total, " .($total - $OBJStats[3][$table]);
		
		$out .= sprintf("\n<b>MOB Total Created, Current:</b> %d, %d",
			$MOBStats[0],
			$MOBStats[0] - $MOBStats[1]
			);
		
		foreach ($MOBStats[2] AS $table => $total)
			$out .= "\n\t$table: $total, " .($total - $MOBStats[3][$table]);
		
		$MOBProtoStats = _MOBPrototype::getObjectCounts();
		$OBJProtoStats = _OBJPrototype::getObjectCounts();
		$out .= sprintf( "\n<b>OBJProto Created, Unused, Current:</b> %d, %d, %d\n<b>MOBProto Created, Unused, Current:</b> %d, %d, %d",
			$OBJProtoStats[0],
			$OBJProtoStats[0] - $OBJProtoStats[1],
			$OBJProtoStats[0] - $OBJProtoStats[2],
			$MOBProtoStats[0],
			$MOBProtoStats[0] - $MOBProtoStats[1],
			$MOBProtoStats[0] - $MOBProtoStats[2]
			);
		
		return $out;
	}
	
	/**
	 * Perpares a query path then attempts to display the page requested.
	 */
	private static function run_page($HNTPL, $query, $lots0param) {
		// Find the script that this request is for
		$pagePath = PAGE_PATH;
		$helpPath = PAGE_PATH. '/HELP';
		while (count($query)
				 && (file_exists($pagePath. '/' .$query[0])
					|| file_exists($pagePath. '/' .$query[0]. '.php')
					)
				) {
			$piece = '/' .array_shift($query);
			$pagePath .= $piece;
			$helpPath .= $piece;
		}
		$pagePath = Loader::fixPath(realpath($pagePath. '.php'));
		$helpPath = Loader::fixPath(realpath($helpPath. '.php'));
		
		// Check that the found path is in the allowed directory
		if (substr($pagePath, 0, strlen(PAGE_PATH)) != PAGE_PATH) {
			// Hey! Thats not allowed!
			if (DEBUG) $HNTPL->new_plaintext('Page "' .$pagePath. '" does not exist in "' .PAGE_PATH. '"!');
			else $HNTPL->new_plaintext('Page does not exist!');
			return;
		}
		
		// See if there is a help file
		if (file_exists($helpPath))
			$HNTPL->set_helpLoc(substr($helpPath, 0, -4));
		
		// Call the script
		$HNTPL->set_title(substr($pagePath, strlen(PAGE_PATH), -4));
		return self::run_script($pagePath, $query, $HNTPL, $lots0param);
	}
	
	/**
	 * This function is used to make certain the called page is run
	 * in a different context to everything else here.
	 * All variables defined in this function are available in the script.
	 */
	public static function run_script($path, $query, $HNTPL, $lots0param) {
		// Extract variables we wish to pass
		foreach ($lots0param AS $lots0key => $lots0val)
			${$lots0key} = $lots0val;
		unset($lots0param, $lots0key, $lots0val);
		
		return require $path;
	}

	/**
	* Displays the login screen.
	*/
	private static function login_start() {
		$HNTPL = new HNTPLPage('Login');
		self::run_page($HNTPL, array('login'), array('postToPath' => '?' . self::make_getVars(array('logout'))));
		self::InsertDebugStats();
		echo $HNTPL;
	}

	/**
	* Finishes the login procedure.
	* This means that it sets up the session for the newly logged in user
	* and calls functions that occur on login.
	*/
	private static function login_end() {
		$username = $_REQUEST['login_user'];
		$password = $_POST['login_pass'];
		unset($_GET['login_pass'], $_POST['login_pass'], $_REQUEST['login_pass']);
		
		try {
			require_once CLASS_PATH.'/OBJ.user.class.php';
			$obj = new stdClass();
			$obj->username = $username;
			$obj->password = $password;
			$DBUser = OBJUser::Authenticate($obj);
			unset($obj);
		} catch (Exception $e) {
			error(str_replace("\n", '<br/>', $e));
		}
		
		if (!$DBUser)
			return false;
		
		// If successful we prepair to go back into the main of the website
		// Setup session so the rest of the program knows we are logged in
		$_SESSION['UserLoggedIn'] = $DBUser->getId();
		$_SESSION['UserLastSeen'] = time();
		$_SESSION['UserSessionId'] = session_id();
		
		// Redirect to this page again
		$getVars = self::make_getVars(array('autoquery', 'login', 'logout', 'auto_login', 'login_user', 'login_pass'));
		header('Location: ' .SERVER_ADDRESS. '/' .$_GET['autoquery']. '?' .$getVars);
		die();
	}

	/**
	* This method is used to both set the currently logged in user OBJ and
	* also is used to check that the uo has not changed by end of script.
	*/
	public static function loggedInUser($OBJUserOrCheck) {
		static $hasBeenSet = false;
		if ($OBJUserOrCheck == 'check') {
			if ($hasBeenSet !== false && $GLOBALS['uo'] !== $hasBeenSet)
				throw new Exception('GLOBALS[uo] has been changed!');
			return;
		}
		if ($hasBeenSet) throw new Exception('Logged in user cannot be set now');
		$hasBeenSet = $OBJUserOrCheck;
		$GLOBALS['uo'] = $OBJUserOrCheck;
	}

	/**
	* Logs the user out.
	* This means that it clears the session variables out completely
	* and creates a new session id.
	* 
	* @uses login_logit()
	*/
	private static function logout() {
		self::login_logit(false, true);
		$_SESSION = array();
		if (isset($_COOKIE[session_name()]))
			setcookie(session_name(), '', time() - 42000);
		session_destroy();
		session_start();
		session_regenerate_id(true);
	}

	/**
	 * Creates the get vars for links and forms to reuse the currently set vars.
	 *
	 * @param array $ignore An array of strings that specify what var names are to be ignored if they exist.
	 */
	public static function make_getVars($ignore = array()) {
		$getVars = '';
		if (isset($_GET)) {
			foreach ($_GET AS $var => $val) {
				// Ignore certain vars
				if (in_array($var, $ignore))
					continue;

				// If its an array we treat it differently
				if (is_array($val)) {
					foreach ($val AS $key => $value) {
						if (!empty($value))
							$getVars .= $var. '[' .$key. ']=' .$value. '&';
					}
				}

				// Normal Vars
				elseif (!empty($val))
					$getVars .= $var. '=' .$val. '&';
			}

			// Trim Trailing '&'
			$getVars = substr($getVars, 0, -1);
		}
		return $getVars;
	}

	/**
	* @param boolean $isLogin True if the action is login, false for logout.
	* @param boolen $isSuccess True if it succeded, false for failure.
	*/
	private static function login_logit($isLogin, $isSuccess) {
		$tmpUT = (empty($GLOBALS['user_types']) ? array() : $GLOBALS['user_types']);
		$GLOBALS['user_types'] = array('user' => true);

		$userid = isset($_SESSION['UserLoggedIn']) ? intval($_SESSION['UserLoggedIn']) : 0;

		$DBUser = HNOBJBasic::loadObject('user', $userid);
		$DBUser->login_logit($isLogin, $isSuccess);
		$DBUser->save();

		$GLOBALS['user_types'] = $tmpUT;
	}
}
