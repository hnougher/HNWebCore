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
			"password":		{"type":"text", "validation":"/^.{0,128}$/", "readBy":["all"]},
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
	
	/**
	* @param $obj This is the username and password passed in an object to help hide it from stack dumps.
	*/
	public static function Authenticate($obj) {
		static::prepareObjectDefs();
		
		$password = hash(LOGIN_HASHALG, LOGIN_PRESALT.$obj->password.LOGIN_POSTSALT);
		
		$aq = new AutoQuery();
		$table = $aq->addTable('user');
		$table->addField('userid');
		$table->addWhere(new WherePart('username','=',':username',true), WHERE_AND, new WherePart('password','=',':password',true));
		$stmt = $aq->prepareSQL();
		$result = $stmt->execute(array('username'=>$obj->username, 'password'=>$password, 'limitstart'=>0, 'limitcount'=>1));
		if (HNDB::MDB2()->isError($result))
			throw new Exception(DEBUG ? $result->getUserInfo() : $result->getMessage());
		
		if ($result->numRows() != 1 || HNDB::MDB2()->isError($row = $result->fetchRow())) {
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
	 * index.php callls this method during the login and logout steps.
	 * This login/logout will be for the user in this object.
	 * 
	 * @param Boolean $isLogin TRUE if this is callled during a login, FALSE otherwise like in logout.
	 * @param Boolean $isSuccess TRUE if the login/logout succeeded, FALSE otherwise.
	 */
	public function login_logit($isLogin, $isSuccess) {
		// Noop
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
