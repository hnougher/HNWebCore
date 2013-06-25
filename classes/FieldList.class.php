<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN FieldList Class
*
* This page contains the class that deals with database
* fields and access control.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

/**
* Provides the functions that are used to do automated queries and
* deals with access control that is enforced by other parts of the
* webbase system.
*/
class FieldList
{
	/**
	* Used to collect a new or cached version of a field list object for use.
	*/
	public static function loadFieldList($table) {
		static $fieldLists = array();
		$userTypes = (empty($GLOBALS['uo']) ? array('all' => 0) : $GLOBALS['uo']->user_types());
		if (defined('HNWC_CRON'))
			$userTypes['cron'] = 0;
		if (!isset($fieldLists[$table]) || $fieldLists[$table][1] != array_keys($userTypes)) {
			// Need to create a new Field List
			$fieldLists[$table] = array(
				new self($table),
				array_keys($userTypes)
				);
		}
		return $fieldLists[$table][0];
	}
	
	/** Contains the table this instance is related to. */
	protected $myTable;

	/** Unique ID for this class instance which is used for the storage cache. */
	private $FLID;

	/**
	* @param string $table The table that this instance is to deal with.
	*/
	public function __construct($table) {
		$this->myTable = $table;
		$this->FLID = _FieldListStaticStorage::getUniqueC();
		_FieldListStaticStorage::$C[$this->FLID] = array();
	}
	public function __destruct() {
		unset(_FieldListStaticStorage::$C[$this->FLID]);
	}

	/**
	* Checks a premissions string to see if a group matches.
	* 
	* @param string $toCheck The string to check.
	* @return boolean True if there was a match, false otherwise.
	*/
	private function checkIfInside($toCheck) {
		if (!empty($GLOBALS['uo']))
			return $GLOBALS['uo']->testUserType($toCheck);
		
		// Fallback for when they are not logged in yet or under cron
		$types = (defined('HNWC_CRON') ? 'all|cron' : 'all');
		if (preg_match('/(^| )(' .$types. ')( |$)/', $toCheck))
			return true;
		return false;
	}

	/**
	* @return string The table.
	*/
	public function getTable() {
		return $this->myTable;
	}

	/**
	* This is used for LDAP based tables.
	* 
	* @return string The tree base string.
	*/
	public function getLDAPBase() {
		if (!isset($this->getLDAPBase)) {
			$myXML = _FieldListStaticStorage::getXML($this->myTable);
			$this->getLDAPBase = $myXML['LDAPBase'];
		}
		return $this->getLDAPBase;
	}
	private $getLDAPBase;

	/**
	* Gets the object handler to be used when creating live objects of this.
	* 
	* @return string The class name of the object handler.
	*/
	public function getClass() {
		if (!isset($this->getClassCache)) {
			$myXML = _FieldListStaticStorage::getXML($this->myTable);
			$this->getClassCache = (!empty($myXML['class']) ? (string) $myXML['class'] : 'HNOBJBasic');
		}
		return $this->getClassCache;
	}
	private $getClassCache;

	/**
	* Get the Fields Parameters
	* 
	* @param SimpleXML $xmlField
	* @return array
	*/
	protected function getFieldParam(&$xmlField) {
		// Sort out the attributes
		$attrib = array();
		foreach ($xmlField->attributes() AS $key => $val) {
			if ($key == 'write' || $key == 'read' || $key == 'key')
				continue;
			$attrib[$key] = (string) $val;
		}
		return $attrib;
	}

	/**
	* @return array The extended field list.
	*/
	protected function getFieldList() {
		$myXML = _FieldListStaticStorage::getXML($this->myTable);
		$myExtFields = array();
		foreach ($myXML->field AS $field)
			$myExtFields[(string) $field['name']] = $field;

		foreach ($myXML->subtable AS $table) {
			if (!isset($table['type']))
				$table->addAttribute('type', 'rev_object');
			$myExtFields[(string) $table['name']] = $table;
		}

		return $myExtFields;
	}

	/**
	* Returns an array that can be used to restrict the fields that the current
	* user can see from a query. Any field in the return array is allowed to be
	* viewed.
	* 
	* @return array An array of records like 'array('name'=>'fieldname','type'=>'int','max'=>'123')
	*/
	public function getReadable() {
		$C =& _FieldListStaticStorage::$C[$this->FLID];
		if (!isset($C[self::GET_READABLE]))
			$C[self::GET_READABLE] = $this->check_fields_thingable('read');
		return $C[self::GET_READABLE];
	}

	/**
	* Returns an array that can be used to restrict the fields that the current
	* user can write to in a query. Any field in the return array is allowed to be
	* written to by the current user.
	* 
	* @return array An array of records like 'array('name'=>'fieldname','type'=>'int','max'=>'123')
	*/
	public function getWriteable() {
		$C =& _FieldListStaticStorage::$C[$this->FLID];
		if (!isset($C[self::GET_WRITEABLE]))
			$C[self::GET_WRITEABLE] = $this->check_fields_thingable('write');
		return $C[self::GET_WRITEABLE];
	}

	/**
	* Returns an array that can be used to restrict the fields that the current
	* user can write to in an insert query. Any field in the return array is
	* allowed to be written to by the current user during insert.
	* 
	* @return array An array of records like 'array('name'=>'fieldname','type'=>'int','max'=>'123')
	*/
	public function getInsertable() {
		$C =& _FieldListStaticStorage::$C[$this->FLID];
		if (!isset($C[self::GET_INSERTABLE]))
			$C[self::GET_INSERTABLE] = $this->check_fields_thingable('insert');
		return $C[self::GET_INSERTABLE];
	}

	/**
	* Check if the given fields are able to do the given action.
	* 
	* @param string $action The action that is being checked for.
	* @return array The fields that passed the test.
	*/
	private function check_fields_thingable($action) {
		$myXML = _FieldListStaticStorage::getXML($this->myTable);
		// Set some internal variables
		$okFields = array();
		$tableOk = $this->checkIfInside($myXML[$action]);

		foreach ($this->getFieldList() AS $field) {
			if ($field === false)
				continue;
			if (($tableOk && !isset($field[$action]))
				|| (isset($field[$action]) && $this->checkIfInside($field[$action])))
			{
				// This field is writeable for this user
				$okFields[(string) $field['name']] = $this->getFieldParam($field);
			}
		}

		return $okFields;
	}

	/**
	* Tells you if the current user is allowed to delete from this table.
	* 
	* @return boolean True if they are allowed to delete, false otherwise.
	*/
	public function isDeleteable() {
		$C =& _FieldListStaticStorage::$C[$this->FLID];
		if (!isset($C[self::GET_DELETEABLE])) {
			$myXML = _FieldListStaticStorage::getXML($this->myTable);
			$C[self::GET_DELETEABLE] = $this->checkIfInside($myXML['delete']);
		}
		return $C[self::GET_DELETEABLE];
	}

	/**
	* Gets the field name of the ID Field for this table.
	* 
	* @return mixed Returns a String if there is a unique field, Array if its a combined key or False if there is none at all.
	*/
	public function getIdField() {
		$C =& _FieldListStaticStorage::$C[$this->FLID];
		if (!isset($C[self::GET_IDFIELD])) {
			$myXML = _FieldListStaticStorage::getXML($this->myTable);
			if ($myXML->field[0]['key'] == 'auto' || $myXML->field[0]['key'] == 'unique') {
				$C[self::GET_IDFIELD] = (string) $myXML->field[0]['name'];
			} elseif ($myXML->field[0]['key'] == 'combined') {
				$C[self::GET_IDFIELD] = array((string) $myXML->field[0]['name']);
				$counter = 1;
				while ($myXML->field[$counter]['key'] == 'combined') {
					$C[self::GET_IDFIELD][] = (string) $myXML->field[$counter]['name'];
					$counter++;
				}
			} else {
				$C[self::GET_IDFIELD] = false;
			}
		}
		return $C[self::GET_IDFIELD];
	}

	/**
	* Checks if this table has an AutoID field for the ID Field.
	* 
	* @return boolean True if the ID Field is AutoId, false otherwise.
	*/
	public function hasAutoId() {
		$C =& _FieldListStaticStorage::$C[$this->FLID];
		if (!isset($C[self::HAS_AUTOID])) {
			$myXML = _FieldListStaticStorage::getXML($this->myTable);
			$C[self::HAS_AUTOID] = ($myXML->field[0]['key'] == 'auto');
		}
		return $C[self::HAS_AUTOID];
	}
	
	// CONSTs for _FieldListStaticStorage
	const GET_READABLE = 1;
	const GET_WRITEABLE = 2;
	const GET_INSERTABLE = 3;
	const GET_DELETEABLE = 4;
	const GET_IDFIELD = 5;
	const HAS_AUTOID = 6;
}

/**
* This class is simply to get these large SimpleXML objects out of the main object
* so that var_dump and co are not overly large.
*/
class _FieldListStaticStorage
{
	// @see CONSTs in bottom of main class
	public static $C = array();
	public static function getUniqueC() {
		static $counter = 0;
		return $counter++;
	}

	private static $xmls = array();
	public static function getXML($table) {
		if (isset($xmls[$table]))
			return $xmls[$table];
		
		$path = DBSTRUCT_PATH . '/' . $table . '.xml';
		if (file_exists($path)) {
			$xmls[$table] = simplexml_load_file($path);
			return $xmls[$table];
		}
		
		throw new Exception("FieldList failed to load XML for table '" .$table. "'");
	}
}
