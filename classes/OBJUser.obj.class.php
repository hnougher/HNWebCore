<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* User Object Class
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

/**
* This is the class specific for the User Object
*/
class OBJUser extends HNOBJBasic
{
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
		// Stub: Use it how you want
	}
	
	public function user_types() {
		static $cache;
		if (!isset($cache)) {
			$cache = array('all' => 0, 'user' => 0);
			//$other = strtolower(str_replace(' ', '_', $this['role']));
			//$cache[$other] = 0;
		}
		return $cache;
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
		$userTypes = $this->user_types();
		
		// programmer can access anything
		if (isset($userTypes['programmer']))
			return true;
		
		// Split the string by space
		$token = strtok(''.$toCheck, ' ');
		while ($token !== false) {
			$token = trim($token);
			if (isset($userTypes[$token]))
				return true;
			$token = strtok(' ');
		}
		return false;
	}
}
