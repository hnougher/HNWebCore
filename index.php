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
* @version 2.1
* @package HNWebCore
*/

// Allow IE to save cookies when we are loaded inside an iframe
header('P3P:CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"');

session_start();

// Non-changable constants
if( isset( $_SESSION['showDebug'] ) && $_SESSION['showDebug'] == 1 )
	define( 'DEBUG', true );
if( isset( $_SESSION['showStats'] ) && $_SESSION['showStats'] == 1 )
	define( 'STATS', true );

require_once 'config.php';
require_once CLASS_PATH. '/ErrorHandler.class.php';
require_once CLASS_PATH. '/HNMySQL.class.php';
require_once CLASS_PATH. '/FieldList.class.php';
require_once CLASS_PATH. '/HNOBJBasic.obj.class.php';
require_once CLASS_PATH. '/HNMOBBasic.class.php';
require_once CLASS_PATH. '/HNAutoQuery.class.php';
require_once TEMPLATE_PATH. '/page.php';
if (LDAP_ENABLED) {
	require_once CLASS_PATH. '/HNLDAP.class.php';
	require_once CLASS_PATH. '/HNLDAPBasic.obj.class.php';
}

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
* It returns a basicallly random code for use within JS.
* NOTE: This function will get *very* slow when lots are stored.
* NOTE: Stored querys get cleared when session ends (logout or timeout).
*
* @param $sql The SQL which is of the style of a MySQLi Statement.
* @param $types The string of types that a MySQLi Statement requires.
* @return string unique code which is to be put into a JS request to the URI like
*      "/ajax.php?q=<code>&p[]=<param1>&p[]=<param2>" which when called returns a
*      JSON array of results.
*/
function StoreAJAXQuery($sql, $types = '') {
	if (empty($_SESSION['AJAX_QUERIES']))
		$_SESSION['AJAX_QUERIES'] = array();
	
	// Check if this query already exists in the stored set.
	foreach ($_SESSION['AJAX_QUERIES'] as $k => $v) {
		if ($v[1] == $types && $v[0] == $sql)
			return $k;
	}
	
	// Generate unique code
	do {
		$code = substr(md5(SERVER_ADDRESS . mt_rand(0, PHP_INT_MAX)), 16);
	} while (isset($_SESSION['AJAX_QUERIES'][$code]));
	
	// Store the query and return code
	$_SESSION['AJAX_QUERIES'][$code] = array($sql, $types);
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
		$GLOBALS['ScriptStartTime'] = microtime(1);

		// Set the Default Timezone
		date_default_timezone_set(SERVER_TIMEZONE);

		// Login to DB Server(s)
		HNMySQL::connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DATABASE);
		if (LDAP_ENABLED) {
			try {
				HNLDAP::connect(LDAP_HOST, LDAP_VERSION, LDAP_BIND_RDN, LDAP_BIND_PASS, LDAP_PORT);
			} catch (Exception $e) {
				error('Cannot connect to LDAP server.');
				if (DEBUG)
					throw $e;
				return;
			}
		}

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
			//if ($query[0] != 'root' && $query[0] != 'HELP') {
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
			$GLOBALS['uo'] = $uo;
			$GLOBALS['UserRolesAtStart'] = $uo->user_types();

			// Delay set the debug showing for programmers
			if ($uo->testUserType('programmer')) {
				$_SESSION['showDebug'] = 1;
				$_SESSION['showStats'] = 1;
			}

			unset($thisParam, $thisKey);
		}

		// Display the page
		$HNTPL = new HNTPLPage(SITE_TITLE, false);
		$return = self::run_page($HNTPL, $query, array('uo' => $uo));

		// Check if we are redirecting
		if (is_string($return)) {
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
				printf('<span class="screenOnly">Total Script Time: %1.2e sec</span>', microtime(1) - $GLOBALS['ScriptStartTime']);
		}
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
		$out = sprintf("<b>User Roles at Start:</b> %s\n<b>Total Queries:</b> %d\n<b>Total Query Time:</b> %1.2e sec\n<b>Pre Output Time:</b> %1.2e sec\n<b>Memory Used At End:</b> %01.1f MB\n<b>Memory Used Peak:</b> %01.1f MB\n<b>Total DB Objects Created:</b> %d\n<b>Current DB Objects:</b> %d",
			(isset($GLOBALS['UserRolesAtStart']) ? implode(', ', array_keys($GLOBALS['UserRolesAtStart'])) : 'all'),
			HNMySQL::sum_of_queries(),
			HNMySQL::sum_of_time(),
			microtime(1) - $GLOBALS['ScriptStartTime'],
			memory_get_usage() / 1024 / 1024,
			memory_get_peak_usage() / 1024 / 1024,
			$OBJStats[0],
			$OBJStats[0] - $OBJStats[1]
			);
		
		foreach ($OBJStats[2] AS $table => $total)
			$out .= "\n\t$table: $total, " .($total - $OBJStats[3][$table]);
		
		$out .= sprintf( "\n<b>Total DB MOBjects Created:</b> %d\n<b>Current DB MOBects:</b> %d",
			$MOBStats[0],
			$MOBStats[0] - $MOBStats[1]
			);
		
		foreach ($MOBStats[2] AS $table => $total)
			$out .= "\n\t$table: $total, " .($total - $MOBStats[3][$table]);
		
		return $out;
	}
	
	/**
	 * Perpares a query path then attempts to display the page requested.
	 */
	private static function run_page($HNTPL, $query, $lots0param) {
		// Find the script that this request is for
		$pagePath = PAGE_PATH;
		$helpPath = PAGE_PATH. '/HELP';
		$tmpQuery = $query;
		while (count($tmpQuery)
				 && (file_exists($pagePath. '/' .$tmpQuery[0])
					|| file_exists($pagePath. '/' .$tmpQuery[0]. '.php')
					)
				) {
			$piece = '/' .array_shift($tmpQuery);
			$pagePath .= $piece;
			$helpPath .= $piece;
		}
		$pagePath = Loader::fixPath(realpath($pagePath. '.php'));
		$helpPath = Loader::fixPath(realpath($helpPath. '.php'));
		
		// Check that the found path is in the allowed directory
		if (substr($pagePath, 0, strlen(PAGE_PATH)) != PAGE_PATH) {
			// Hey! Thats not allowed!
			if (DEBUG) die('Page "' .$pagePath. '" does not exist in "' .PAGE_PATH. '"!');
			die('Page does not exist!');
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
	private static function run_script($path, $query, $HNTPL, $lots0param) {
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
		$password = LOGIN_PRESALT . $_POST['login_pass'] . LOGIN_POSTSALT;
		unset( $_GET['login_pass'], $_POST['login_pass'], $_REQUEST['login_pass'] );
		
		if (preg_match('/^(.*)@example.com$/', $username, $matches)) {
			// Using LDAP login
			$username = 'uid=' .HNLDAP::escape($matches[1]). ',ou=People,dc=example,dc=com';
			
			// Check login to LDAP succeeds
			try {
				HNLDAP::close();
				HNLDAP::connect(LDAP_HOST, LDAP_VERSION, $username, $password, LDAP_PORT);
			} catch (HNDBException $e) {
				error('Incorrect Username or Password Entered.');
				if (DEBUG)
					throw $e;
				return;
			} catch (Exception $e) {
				error('Cannot connect to LDAP server.');
				if (DEBUG)
					throw $e;
				return;
			}
			
			// Check that the user was of staff type
			$uuo = HNOBJBasic::loadObject('mojo_user', $username);
			if ($uuo['employeetype'][0] != 'ps') {
				error('The login supplied did not have required permission.');
				return;
			}
			
			// Login is OK so we need to prepare our normal DB
			if (count($uuo['users']) == 0) {
				$uo = $uuo['users']->addNew();
				$uo['role'] = 'Unit Coordinator';
				$uo->save();
				$uuo->load();
			}
			$uo = $uuo['users'][0];
			$GLOBALS['uo'] = $uo;
			
			// Copy all required data to mysql object
			$fromTo = array(
				'username' => 'uid',
				'email' => 'mail',
				'first_name' => 'givenname',
				'last_name' => 'sn',
				);
			foreach ($fromTo as $to => $from) {
				if (!empty($uuo[$from][0]))
					$uo[$to] = $uuo[$from][0];
			}
			
			// Save
			$uo->save();
			$userid = $uo->getId();
		} else {
			// Using MySQL user login
			
			// Check the login is correct
			$user = HNMySQL::escape($username);
			$pass = HNMySQL::escape(md5($password));
			//$pass2 = HNMySQL::escape(hash('sha256', $password));
			// Note: The pipe in the following query is to stop problems with strange trailing chars being ignored
			$sql = 'SELECT `userid` FROM `user` WHERE CONCAT(`username`,"|")="' .$user. '|" AND '
				.'`password`="' .$pass. '"'
				//.'(`password`="' .$pass. '" OR `password`="' .$pass2. '")'
				.' LIMIT 1';
			$result = HNMySQL::query($sql);

			// Was login successful?
			if (!$result || $result->num_rows != 1) {
				// Record login Failure to login record
				self::login_logit(true, false);
				
				// Set error message
				error('Incorrect Username or Password Entered.');
				return;
			}
			
			$row = $result->fetch_row();
			$userid = $row[0];
		}
		
		// If successful we prepair to go back into the main of the website
		// Setup session so the rest of the program knows we are logged in
		$_SESSION['UserLoggedIn'] = $userid;
		$_SESSION['UserLastSeen'] = time();
		$_SESSION['UserSessionId'] = session_id();
		
		// Record login Success to login record
		self::login_logit(true, true);
		
		// Redirect to this page again
		$getVars = self::make_getVars(array('autoquery', 'login', 'logout', 'auto_login', 'login_user', 'login_pass'));
		header('Location: ' .SERVER_ADDRESS. '/' .$_GET['autoquery']. '?' .$getVars);
		die();
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
		$tmpUT = $GLOBALS['user_types'];
		$GLOBALS['user_types'] = array('user' => true);

		$userid = isset($_SESSION['UserLoggedIn']) ? intval($_SESSION['UserLoggedIn']) : 0;

		$DBUser = HNOBJBasic::loadObject('user', $userid);
		$DBUser->login_logit($isLogin, $isSuccess);
		$DBUser->save();

		$GLOBALS['user_types'] = $tmpUT;
	}
}
