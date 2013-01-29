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
	* Contains the path to where the DBStructure XML data is located.
	* @var string
	*/
	#protected static $structPath;
	
	/**
	* Contains field list objects which have already been collected
	* @var array
	*/
	protected static $fieldLists = array();
	
	/**
	* Used to collect a new or cached version of a field list object for use.
	*/
	public static function loadFieldList($table) {
		$userTypes = (empty($GLOBALS['uo']) ? array('all' => 0) : $GLOBALS['uo']->user_types());
		if (!isset(self::$fieldLists[$table]) || self::$fieldLists[$table][1] != array_keys($userTypes)) {
			// Need to create a new Field List
			self::$fieldLists[$table] = array(
				new self($table),
				array_keys($userTypes)
				);
		}
		
		return self::$fieldLists[$table][0];
	}
	
	/**
	* Contains the table this instance is related to.
	* @var string
	*/
	protected $myTable = '';

	/**
	* Contains the SimpleXML object for this instance only.
	* @var SimpleXML
	*/
	protected $myFields = false;

	/**
	* Contains caches for other areas.
	* @var array
	*/
	protected $cache = array();

	/**
	* This constructor checks if the table structure has been loaded in this
	* session and uses it if it has been. If it has not then it will attempt
	* to load it from the XML structure file.
	* 
	* @param string $table The table that this instance is to deal with.
	*/
	public function __construct($table) {
		$path = DBSTRUCT_PATH . '/' . $table . '.xml';
		if (file_exists($path)) {
			$this->myFields = simplexml_load_file($path);
			$this->myTable = $table;
		}
		else {
			echo "FieldList failed to load XML for table '" .$table. "'\n";
		}
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
		
		// Fallback for when they are not logged in yet
		if (preg_match('/^all | all | all$/', $toCheck))
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
	* Gets the object handler to be used when creating live objects of this.
	* 
	* @return string The class name of the object handler.
	*/
	public function getClass() {
		return (!empty($this->myFields['class']) ? (string) $this->myFields['class'] : 'HNOBJBasic');
	}

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
		$myExtFields = array();
		foreach ($this->myFields->field AS $field)
			$myExtFields[(string) $field['name']] = $field;

		foreach ($this->myFields->subtable AS $table) {
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
		if (!isset($this->cache['getReadable']))
			$this->cache['getReadable'] = $this->check_fields_thingable('read');
		return $this->cache['getReadable'];
	}

	/**
	* Returns an array that can be used to restrict the fields that the current
	* user can write to in a query. Any field in the return array is allowed to be
	* written to by the current user.
	* 
	* @return array An array of records like 'array('name'=>'fieldname','type'=>'int','max'=>'123')
	*/
	public function getWriteable() {
		if (!isset($this->cache['getWriteable']))
			$this->cache['getWriteable'] = $this->check_fields_thingable('write');
		return $this->cache['getWriteable'];
	}

	/**
	* Returns an array that can be used to restrict the fields that the current
	* user can write to in an insert query. Any field in the return array is
	* allowed to be written to by the current user during insert.
	* 
	* @return array An array of records like 'array('name'=>'fieldname','type'=>'int','max'=>'123')
	*/
	public function getInsertable() {
		if (!isset($this->cache['getInsertable']))
			$this->cache['getInsertable'] = $this->check_fields_thingable('insert');
		return $this->cache['getInsertable'];
	}

	/**
	* Check if the given fields are able to do the given action.
	* 
	* @param string $action The action that is being checked for.
	* @return array The fields that passed the test.
	*/
	private function check_fields_thingable($action) {
		// Set some internal variables
		$okFields = array();
		$tableOk = $this->checkIfInside($this->myFields[$action]);

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
		return $this->checkIfInside($this->myFields['delete']);
	}

	/**
	* Gets the field name of the ID Field for this table.
	* 
	* @return mixed Returns a String if there is a unique field, Array if its a combined key or False if there is none at all.
	*/
	public function getIdField() {
		if ($this->myFields->field[0]['key'] == 'auto' || $this->myFields->field[0]['key'] == 'unique') {
			return (string) $this->myFields->field[0]['name'];
		}
		elseif ($this->myFields->field[0]['key'] == 'combined') {
			$tmp = array((string) $this->myFields->field[0]['name']);
			$counter = 1;
			while ($this->myFields->field[$counter]['key'] == 'combined') {
				$tmp[] = (string) $this->myFields->field[$counter]['name'];
				$counter++;
			}
			return $tmp;
		}
		return false;
	}

	/**
	* Checks if this table has an AutoID field for the ID Field.
	* 
	* @return boolean True if the ID Field is AutoId, false otherwise.
	*/
	public function hasAutoId() {
		return ($this->myFields->field[0]['key'] == 'auto');
	}
}
