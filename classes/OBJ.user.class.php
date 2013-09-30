<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* User Object Class
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

/**
* This is the class specific for the User Object
*/
class OBJUser extends HNOBJBasic
{
	// Table Defs
	/* Valid param for OBJ/MOB are type, SQL, readBy, writeBy, insertBy, (validation OR values), default */
	/*    SQL defaults to the virtual field name. */
	/* Valid param for AutoQuery are autoVisibleSQL, autoInternSQL, autoRequireOBJs */
	/* Types can be found on http://pear.php.net/manual/en/package.database.mdb2.datatypes.php
	* They include text, integer, date, time, timestamp, boolean, decimal, float, clob, blob */
	protected static $tableDefOrig = '{
		"connection": "DEFAULT",
		"table": "user",
		"readBy": ["user"],
		"writeBy": ["user"],
		"insertBy": ["user"],
		"deleteBy": [],
		"keys": ["userid"],
		"fields": {
			"userid":		{"type":"autoid", "readBy":["all"], "writeBy":[], "insertBy":[]},
			"username":		{"type":"text", "validation":"/^.{0,320}$/", "readBy":["all"]},
			"password":		{"type":"text", "validation":"/^.{0,128}$/", "readBy":[]},
			"first_name":	{"type":"text", "validation":"/^.{0,40}$/"},
			"last_name":	{"type":"text", "validation":"/^.{0,40}$/"},
			"full_name":	{"type":"text", "SQL":"CONCAT(`T`.`first_name`,\' \',`T`.`last_name`)", "writeBy":[], "insertBy":[]},
			"gender":		{"type":"text", "values":["Male","Female","Other"]}
			},
		"subtables": {}
		}';


	/** Pre-created DB statements for this OBJ Type */
	protected static $tableDefFor;
	protected static $tableDef;
	protected static $selectStatement;
	
	public function Authenticate($username, $password) {
		static::prepareObjectDefs();
		
		$password = hash(LOGIN_HASHALG, LOGIN_PRESALT.$password.LOGIN_POSTSALT);
		
		$DB =& HNDB::singleton(constant(static::$tableDef->connection));
		$stmt = $DB->prepare('SELECT `userid` FROM `user` WHERE `username`=? AND `password`=? LIMIT 1');
		$result = $stmt->execute(array($username, $password));
		if ($result->numRows() != 1 || PEAR::isError($row = $result->fetchRow())) {
			$result->free();
			error('Incorrect username or password entered.');
			return false;
		}
		$result->free();
		$stmt->free();
		
		$DBUser = HNOBJBasic::loadObject('user', $row[0]);
		$DBUser->load();
		
		return $DBUser;
	}
	
	private $userTypes = false;
	
	/**
	* Gets the first and last name of the user in one standard string.
	* 
	* @return string
	*/
	public function get_full_name() {
		return $this->replaceFields('{first_name} {last_name}');
	}
	
	/**
	* Collects all the user types for this current user.
	*/
	public function userTypes() {
		if ($this->userTypes === false) {
			$this->userTypes = array('all' => 0, 'user' => 0);
			/*$roles = explode(',', $this['role']);
			foreach ($roles as $role) {
				$other = strtolower(str_replace(' ', '_', $role));
				$this->userTypes[$other] = 0;
			}*/
		}
		return $this->userTypes;
	}
	
	/**
	 * Use this to test if this user has permission to use a restricted resource.
	 * 
	 * @param string $toCheck
	 *        String format should be like "all user programmer".
	 *        If any of them match what the user has then returns TRUE.
	 * @return TRUE when the user has permission or FALSE otherwise.
	 */
	public function testUserType($toCheck = '') {
		if ($this->userTypes === false)
			$this->userTypes();
		
		// programmer can access anything
		if (isset($this->userTypes['programmer']))
			return true;
		
		// If we are to check an array go fastpath
		if (is_array($toCheck)) {
			foreach ($toCheck as $token) {
				if (isset($this->userTypes[$token]))
					return true;
			}
			return false;
		}
		
		// Split the string by space
		$token = strtok(''.$toCheck, ' ');
		while ($token !== false) {
			$token = trim($token);
			if (isset($this->userTypes[$token]))
				return true;
			$token = strtok(' ');
		}
		return false;
	}
}
