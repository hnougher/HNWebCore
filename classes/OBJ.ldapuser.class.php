<?php

/**
* This is the class specific for the User Object
*/
class OBJLDAPUser extends HNOBJBasic
{
	*//EXAMPLE ONLY
	protected static $tableDefOrig = '{
		"connection": "LDAP",
		"table": "ou=users,o=mojo",
		"readBy": ["all"],
		"writeBy": [],
		"insertBy": [],
		"deleteBy": [],
		"keys": ["uid"],
		"fields": {
			"cn":			{"type":"ldaparray"},
			"uid":			{"type":"ldaparray"},
			"title":		{"type":"ldaparray"},
			"givenName":	{"type":"ldaparray"},
			"sn":			{"type":"ldaparray"},
			"displayName":	{"type":"ldaparray"},
			"employeeType":	{"type":"ldaparray"},
			"mail":			{"type":"ldaparray"}
			},
		"subtables": {
			"users":		{"object":"user", "remoteField":"ldap_dn"}
			}
		}';

	/** Pre-created DB statements for this OBJ Type */
	protected static $tableDefFor;
	protected static $tableDef;
	protected static $selectStatement;
	
	public function Authenticate($username, $password) {
		static::prepareObjectDefs();
		
		try {
			$DBConnection = sprintf(LDAP_LOGIN, $username, $password);
			$DB =& HNDB::factory($DBConnection);
			$DB->connect();
		}
		catch (Exception $e) {
			error('Incorrect username or password entered.');
			return false;
		}
		
		// To be as safe as possible we unset all secure variables now
		$DB->free();
		unset($DB, $DBConnection, $password);
		
		$DBLDAPUser = HNOBJBasic::loadObject('ldapuser', $username);
		
		// Login is OK so we need to prepare our normal DB
		if (count($DBLDAPUser['users']) == 0) {
			$DBUser = $DBLDAPUser['users']->addNew();
			$DBUser->save();
		}
		$DBUser = $DBLDAPUser['users'][0];
		
		Loader::loggedInUser($DBUser);
		$DBLDAPUser->prepareObjectDefs();
		$DBUser->prepareObjectDefs();
		#$DBUser->load();
		//var_dump($DBUser->user_types(), $DBUser->getTableDef()->fields);
		
		// Copy all required data to mysql object
		$DBUser['username']		= (is_array($DBLDAPUser['uid'])		? $DBLDAPUser['uid'][0]		: $DBLDAPUser['uid']);
		$DBUser['email']		= (is_array($DBLDAPUser['mail'])	? $DBLDAPUser['mail'][0]	: $DBLDAPUser['mail']);
		$DBUser['first_name']	= (is_array($DBLDAPUser['givenName']) ? $DBLDAPUser['givenName'][0] : $DBLDAPUser['givenName']);
		$DBUser['last_name']	= (is_array($DBLDAPUser['sn'])		? $DBLDAPUser['sn'][0]		: $DBLDAPUser['sn']);
		
		// Save
		OBJLog_Login::insertLog($username, $_SERVER["REMOTE_ADDR"], 'Login', 'Success');
		$DBUser->save();
		
		return $DBUser;
	}

}
