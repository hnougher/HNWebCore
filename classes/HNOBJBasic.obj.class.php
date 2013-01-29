<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Object Class
*
* This page contains the basic class for all objects.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

// Constants for this class
define('FLAG_RESET', 0);
define('FLAG_SET', 1);

/**
* This is a basic class that loads objects from the database.
*/
class HNOBJBasic implements IteratorAggregate, ArrayAccess, Countable
{

	/**
	 * Contains a count of total OBJs Created in this program run.
	 * @var array
	 */
	private static $totalCreated = array();

	/**
	 * Contains a count of OBJs destroyed in this program.
	 * @var array
	 */
	private static $totalDestroyed = array();

	/**
	 * This is an array of objects that have already been loaded.
	 * @var array
	 */
	private static $cachedObjects = array();

	/**
	* Will contain an instance of the FieldList class.
	* @var FieldList
	*/
	protected $fieldList;

	/**
	* Will contain the id for the id field of this object.
	* @var integer
	*/
	protected $myId;

	/**
	* Used to determine if this object has been loaded.
	* @var boolean
	*/
	protected $isLoaded = false;

	/**
	* Used to determine if this object has no record.
	* @var boolean
	*/
	protected $noRecord = false;

	/**
	* This contains the same data that the Database does.
	* As in the database values are loaded straight into this for reference
	* later by other objects. This does not get changed.
	* @var array
	*/
	private $myData = array();

	/**
	* Constains the values that have been changed compared to the
	* database values. These are saved when {@link save()} is called.
	* @var array
	*/
	private $myChangedData = array();

	/**
	 * Reverse Link To Parent.
	 */
	private $myParentObject = null;

	/**
	* Loads an object using the config specified object class so that the
	* programmer doesnt have to do such complex things often.
	*
	* @param $table
	* @return HNOBJBasic A object class that extends the HNOBJBasic base class.
	*/
	public static function loadObject($table, $id, $loadNow = false, $fieldList = false) {
		$fieldList = ($fieldList === false ? FieldList::loadFieldList($table) : $fieldList);
		$className = $fieldList->getClass();
		if (!class_exists($className)) {
			$tmpPrefix = substr(__FILE__, 0, strrpos(__FILE__, '/'));
			require_once $tmpPrefix. '/' .$className. '.obj.class.php';
		}

		$idFix = implode('!', (array) $id);
		if (isset(self::$cachedObjects[$table]) && isset(self::$cachedObjects[$table][$idFix])) {
			#echo "USE(" .$table. "," .$idFix. ") \n";
			// There is already an object loaded for this ID
			return self::$cachedObjects[$table][$idFix];
		}
		else {
			#echo "NEW(" .$table. "," .$idFix. ") \n";
			// We need a new object
			$obj = new $className($table, (array) $id, $loadNow, $fieldList);

			if ($idFix != '0') {
				if( !isset( self::$cachedObjects[$table]))
					self::$cachedObjects[$table] = array();
				self::$cachedObjects[$table][$idFix] = $obj;
			}

			return $obj;
		}
	}

	/**
	 * @return array containing totalcreated and totalDestroyed.
	 */
	public static function getObjectCounts() {
		return array(
			array_sum(self::$totalCreated),
			array_sum(self::$totalDestroyed),
			self::$totalCreated,
			self::$totalDestroyed,
			);
	}

	/**
	* @param string $table The table name used for this object.
	* @param integer|array $id The id of the record we are getting from the table.
	* @param boolean $loadNow If set to yes then the DB data is loaded now.
	*/
	public function __construct($table, $id, $loadNow, $fieldList) {
		$this->fieldList = $fieldList;
		$this->myId = $id;

		// Object Count Statistic Update
		if (STATS) {
			if (!isset(self::$totalCreated[$table])) {
				self::$totalCreated[$table] = 0;
				self::$totalDestroyed[$table] = 0;
			}
			self::$totalCreated[$table]++;
		}

		if ($loadNow && !empty($id))
			$this->load();
	}

	/**
	 * Used to decrement the count of currently loaded.
	 */
	public function __destruct() {
		if (STATS)
			self::$totalDestroyed[$this->fieldList->getTable()]++;
	}

	/**
	 * Sets the parent object for this object.
	 * Basicially this is the only known reverse link that gives us troubles.
	 */
	public function set_reverse_link($field, $obj) {
		$this->myData[$field] = $obj;
		$this->myParentObject = $obj;
	}
	
	/**
	* This method is only to be used inside the OBJ classes.
	* It sets the incomming parameter as though it came from sql directly.
	*/
	public function _internal_set_data($dataSet) {
		// Setup the data structure
		$this->storeArrayToLocal($dataSet);
		$this->noRecord = false;
		$this->isLoaded = true;
	}
	
	/**
	* Check if this object has a record that is physically stored in the database.
	* @return Boolean True if the record is physically located in the database, False otherwise.
	*/
	public function has_record() {
		if ($this->getId() == 0 || !$this->checkLoaded(true))
			return false;
		return !$this->noRecord;
	}
	
	/**
	* Checks if this object is loaded.
	* It can attempt to load the object now if it has not been loaded yet.
	*
	* @param boolean $loadNow If this is true then the data will be loaded now if it has not been already.
	* @return boolean True if it is now loaded, false otherwise.
	*/
	public function checkLoaded($loadNow = false) {
		if ($this->isLoaded)
			return true;
		if ($loadNow)
			return $this->load();
		return false;
	}

	/**
	* Gets the table this instance is using.
	*
	* @return FieldList::getTable()
	*/
	public function getTable() {
		return $this->fieldList->getTable();
	}

	/**
	* Gets the array of all the fields in the objects table.
	*
	* @return FieldList::getFields()
	*/
	public function getFields() {
		return $this->fieldList->getFields();
	}

	/**
	* Gets the id of the record this instance is using.
	*
	* @return array|string
	*/
	public function getId() {
		if (count($this->myId) == 1)
			return $this->myId[0];
		return $this->myId;
	}

	/**
	* Clears all changes that have been done back to the last save.
	* This does not reload the data from the database.
	*
	* @return array The array of previously set changes.
	*/
	public function clearChanges() {
		$tmp = $this->myChangedData;
		$this->myChangedData = array();
		return $tmp;
	}

	/**
	* Get the data type parameters that are associated with a field.
	*
	* @param string $field The name of the field.
	* @return array The field parameters.
	*/
	public function getValParam($field) {
		if (!$this->checkLoaded(true))
			return false;
		$fields = $this->fieldList->getReadable();
		return (isset($fields[$field]) ? $fields[$field] : false);
	}

	/**
	* Replaces all occurances of field names matching $pre$field$post in
	* a passed in string.
	*
	* @param string $str The string to replace the fields in. May not contain pipe.
	* @param string $pre The string to prepend onto the match. May not contain pipe.
	* @param string $post The string to append onto the match. May not contain pipe.
	* @return string The string with all replacements done.
	*/
	public function replaceFields($str, $pre = '{', $post = '}') {
		$allFields = $this->fieldList->getReadable();
		foreach ($allFields as $name => &$field)
			$field = sprintf('|%s(%s)%s|', $pre, $name, $post);
		return preg_replace_callback($allFields, array(&$this, 'replaceFieldsReplacer'), $str);
	}

	/**
	* This is the callback for the replaceFields method above.
	* It does the actual replacement of values.
	*/
	private function replaceFieldsReplacer($matches) {
		$field = $matches[1];
		$val = $this[$matches[1]];
		$readableFields = $this->fieldList->getReadable();
		if ($val instanceof HNOBJBasic) {
			if (!empty($readableFields[$field]['display']))
				$val = $val->replaceFields($readableFields[$field]['display']);
			else
				$val = sprintf('[%s #%d]', $field, $val->getId());
		}
		elseif ($val instanceof HNMOBBasic) {
			$val = '[Multi Object]';
		}
		return $val;
	}
	
	/**
	* This function loads the data from the database.
	*
	* @return Boolean TRUE on success, otherwise FALSE.
	*/
	public function load() {
		// Reset the local data store
		$this->isLoaded = false;
		$this->noRecord = false;
		$oldData = $this->myData;
		$this->myData = array();

		// Make the SQL statement
		$sqlFields = array();
		foreach ($this->fieldList->getReadable() as $name => $fieldInfo) {
			if ($fieldInfo['type'] != 'rev_object') {
				//$sqlFields[] = '`' .$name. '` AS "' .$name. '"';
				$sqlFields[] = '`' .$name. '`';
			}
		}
		
		if (!count($sqlFields)) {
			// This is hopefully safe.
			// It occurs when the user has no permission to view the record
			return false;
		}
		
		$sql = 'SELECT ' .implode(',', $sqlFields). ' ';
		$sql .= 'FROM `' .$this->fieldList->getTable(). '` ';
		$sql .= 'WHERE ';
		foreach ((array) $this->fieldList->getIdField() AS $key => $idField)
			$sql .= '`' .$idField. '`="' .HNMySQL::escape($this->myId[$key]). '" AND ';
		$sql = substr($sql, 0, -4);
		$sql .= 'LIMIT 1';

		// Do the Query
		$result = HNMySQL::query($sql);
		if (!$result) {
			// The query has failed for some reason
			// NOTE: HNMySQL logs and displays the error before return.
			return false;
		}

		// Check if the record does exist
		if( $result->num_rows == 0 )
			$this->noRecord = true;
		else
			// Get the Data
			$data = $result->fetch_assoc();

		// Process the Data into the correct data types and locally store it
		$this->storeArrayToLocal($this->noRecord ? array() : $data);

		// YAY! We have loaded successfully
		$this->isLoaded = true;
		return true;
	}
	
	/**
	* Processes the Data into the correct data types and locally store it.
	*/
	private function storeArrayToLocal($dataArray) {
		foreach ($this->fieldList->getReadable() as $name => $fieldInfo) {
			// In case the value to set is not defined, we give it an empty string
			if (!isset($dataArray[$name]))
				$dataArray[$name] = '';
			
			// Save the value in correct type for later use
			if ($fieldInfo['type'] == 'str')
				$this->myData[$name] = (string) $dataArray[$name];
			elseif ($fieldInfo['type'] == 'int') {
				if ($dataArray[$name] <= PHP_INT_MAX)
					$this->myData[$name] = (integer) $dataArray[$name];
				else
					$this->myData[$name] = (string) $dataArray[$name];
			}
			elseif ($fieldInfo['type'] == 'float')
				$this->myData[$name] = (float) $dataArray[$name];
			elseif ($fieldInfo['type'] == 'bool')
				$this->myData[$name] = (boolean) $dataArray[$name];
			elseif ($fieldInfo['type'] == 'byte')
				$this->myData[$name] = ord($dataArray[$name]);
			elseif ($fieldInfo['type'] == 'object') {
				if (isset($oldData[$name]) && $oldData[$name] instanceof HNOBJBasic && $oldData[$name]->getId() == $fieldData)
					$this->myData[$name] = $oldData[$name];
				else
					$this->myData[$name] = array(1, $fieldInfo['table'], $dataArray[$name]);
			}
			elseif ($fieldInfo['type'] == 'rev_object') {
				if (isset($oldData[$name])) {
					$this->myData[$name] = $oldData[$name];
				}
				else {
					$newField = (!empty($fieldInfo['field']) ? $fieldInfo['field'] : $this->fieldList->getIdField());
					#$this->myData[$name] = new HNMOBBasic($fieldInfo['table'], $newField, $this->getId(), $this);
					$this->myData[$name] = array(2, $fieldInfo['table'], $newField);
				}
			}
		}
	}

	/**
	* Saves the data to the database.
	* If the id is zero or the record doesnt exist, the record is inserted into
	* the database following database rules.
	*
	* @return boolean TRUE on success, otherwise FALSE.
	*/
	public function save() {
		// Check that we have field to save in $this->myChangedData
		if (count($this->myChangedData) == 0)
			return false;

		// Check if we have to insert the new row And make the SQL
		if ($this->myId[0] == 0 || $this->noRecord) {
			// Get the field names that we are allowed to insert to
			$fields = $this->fieldList->getInsertable();

			if(!count($fields))
				// This user cannot insert into this table
				return false;

			// Time to make the SQL for Inserting
			$sql = 'INSERT INTO `' .$this->fieldList->getTable(). '` SET ';
			$endSql = '';
		}
		else {
			// Get the field names that we are allowed to write
			$fields = $this->fieldList->getWriteable();

			// Make the SQL for Updating
			$sql = 'UPDATE `' .$this->fieldList->getTable(). '` SET ';
			$endSql = 'WHERE ';

			foreach ((array) $this->fieldList->getIdField() AS $key => $idField)
				$endSql .= '`' .$idField. '`="' .HNMySQL::escape($this->myId[$key]). '" AND ';
			$endSql = substr( $endSql, 0, -4 );
		}

		foreach ($fields as $name => $fieldInfo) {
			if ($fieldInfo['type'] == 'rev_object')
				continue;
			elseif ($fieldInfo['type'] == 'object')
				$tmpData = '"' .HNMySQL::escape($this[$name]->getId()). '"';
			elseif (!isset($this->myChangedData[$name]))
				continue;
			elseif ($fieldInfo['type'] == 'bool')
				$tmpData = (empty($this->myChangedData[$name]) ? 0 : 1);
			elseif ($fieldInfo['type'] == 'byte')
				$tmpData = sprintf('0x%X', $this->myChangedData[$name]);
			else
				$tmpData = '"' .HNMySQL::escape((string) $this->myChangedData[$name]). '"';
			$sql .= '`' .$name. '`=' .$tmpData. ',';
		}
		$sql[strlen($sql) - 1] = ' ';
		$sql .= $endSql;

		// Do the Database Query
		$result = HNMySQL::query( $sql );
		if (!$result) {
			// The query has failed for some reason
			// NOTE: HNMySQL logs and displays the error before return.
			return false;
		}

		// YAY! we are done! Now to reload and clean up
		$idFields = $this->fieldList->getIdField();
		if ($sql[0] == 'I' && $this->fieldList->hasAutoId()) {
			$this->myId[0] = HNMySQL::insert_id();
		}
		elseif (is_array($idFields)) {
			foreach ($idFields AS $key => $val) {
				if (isset($this->myChangedData[$val]))
					$this->myId[$key] = $this->myChangedData[$val];
				else
					$this->myId[$key] = $this->myData[$val];
				if (is_object($this->myId[$key]) && $this->myId[$key] instanceof HNOBJBasic)
					$this->myId[$key] = $this->myId[$key]->getId();
			}
		}

		$this->load();
		$this->myChangedValues = array();
		return true;
	}

	/**
	* Removes the current record from the database.
	*
	* @return boolean TRUE on success, otherwise FALSE.
	*/
	public function remove() {
		// Check that this user can delete this
		if (!$this->fieldList->isDeleteable() || !$this->has_record())
			return false;

		// Make the SQL
		$sql = 'DELETE FROM `' .$this->fieldList->getTable(). '` ';
		$sql .= 'WHERE ';
		foreach ((array) $this->fieldList->getIdField() AS $key => $idField)
			$sql .= '`' .$idField. '`="' .HNMySQL::escape($this->myId[$key]). '" AND ';
		$sql = substr($sql, 0, -4);
		$sql .= ' LIMIT 1';

		// Do the Database Query
		$result = HNMySQL::query($sql);
		if(!$result) {
			// The query has failed for some reason
			// NOTE: HNMySQL logs and displays the error before return.
			return false;
		}

		// YAY! We have deleted the record!
		// Now we change it noRecord mode
		$this->noRecord = true;
		$this->myChangedData = array_merge($this->myChangedData, $this->myData);
		$this->myData = array();
		return true;
	}

	/**
	 * Clean up the horrible reverse links.
	 * OR for now just clean up everything.
	 */
	public function clean() {
		foreach ($this->myData AS $data) {
			if (!is_object($data))
				continue;

			if ($this->myParentObject && $data === $this->myParentObject)
				//$this->myData = null;
				continue;

			if ($data instanceof HNOBJBasic || $data instanceof HNMOBBasic)
				$data->clean();
		}

		$this->isLoaded = false;
		$this->myData = array();
		$this->myChangedData = array();
		$this->myParentObject = null;

		$idFix = implode('!', (array) $this->getId());
		$table = $this->fieldList->getTable();
		if (isset(self::$cachedObjects[$table][$idFix])) {
			#echo "CLEAN(" .$table. "," .$idFix. ") \n";
			unset(self::$cachedObjects[$table][$idFix]);
		}
	}


	// ########################################
	// ##### Implementation of Interfaces #####
	// ########################################

	/**
	* Prints this object as a string.
	* @return string
	*/
	public function __toString() {
		$return = '';
		foreach ($this AS $field => $value) {
			$return .= $field. ' => ';
			if (is_object($value)) {
				$tmpClsName = 'HNOBJBasic';
				if ($value instanceof $tmpClsName)
					$return .= strtoupper($value->getTable()). ' { ID ' .$value->getId(). ' }';
				else
					$return .= $value;
			}
			else {
				$return .= var_export($value, 1);
			}
			$return .= "\n";
		}
		return $return;
	}

	/**
	* Required definition of interface IteratorAggregate
	*/
	public function getIterator() {
		return new HNOBJBasicIterator($this, array_values($this->fieldList->getReadable()));
	}

	/**
	* Required definition of interface ArrayAccess
	*/
	public function offsetExists($offset) {
		$fields = $this->fieldList->getReadable();
		return isset($fields[$offset]);
	}

	/**
	* Required definition of interface ArrayAccess
	* 
	* Gets the value currently set to be saved to field or the DB value
	* if it has not been changed. This is what will be in the database
	* after a save.
	*
	* @param string $field The field to get the value for.
	* @return mixed The data in that field or false if it doesnt exist.
	*/
	public function offsetGet($field) {
		if (isset($this->myChangedData[$field]))
			return $this->myChangedData[$field];
		if (!$this->checkLoaded(true)) {
			trigger_error('OBJ failed to load for field ' .$field. ' in ' .$this->getTable(), E_USER_WARNING);
			return false;
		}
		if (!isset($this->myData[$field])) {
			trigger_error('No such field "' .$field. '" in "' .$this->getTable(). '" or unauthorised', E_USER_WARNING);
			return false;
		}
		if (is_array($this->myData[$field])) {
			$setupInfo = $this->myData[$field];
			if ($setupInfo[0] == 1) {
				$this->myData[$field] = HNOBJBasic::loadObject($setupInfo[1], $setupInfo[2]);
			}
			else {
				$this->myData[$field] = new HNMOBBasic($setupInfo[1], $setupInfo[2], $this->getId(), $this);
			}
		}
		return $this->myData[$field];
	}

	/**
	* Required definition of interface ArrayAccess
	* 
	* Sets a field to a specified value if permission is allowed.
	* 
	* <p>This has a special case for byte fields that are also contain packed flags.
	* For these your can pass an array with 2 parts, first is the mask for the flag,
	* second is either FLAG_SET or FLAG_RESET.</p>
	*
	* @param string $field The name of the field.
	* @param mixed $data The data to put in the field.
	* @return boolean True if allowed, false on failure.
	*/
	public function offsetSet($field, $value) {
		if ($this->has_record()) {
			$fields = $this->fieldList->getWriteable();
			if (!isset($fields[$field]))
				throw new Exception('Attempt to set a non-writeable field in existing OBJ');
		}
		else {
			$fields = $this->fieldList->getInsertable();
			if (!isset($fields[$field]))
				throw new Exception('Attempt to set a non-insertable field in new OBJ');
		}
		$fieldParam = $fields[$field];
		unset($fields);
		
		// See if the new value is valid
		switch ($fieldParam['type']) {
		case 'rev_object':
			throw new Exception('Attempt to set a MOB field');
		case 'object':
			if (!is_object($value)) {
				// Convert ID to object of correct type
				$value = self::loadObject($fieldParam['table'], $value);
			}
			elseif ($value->getTable() != $fieldParam['table']) {
				throw new Exception('Attempt to set OBJ of wrong table in link field');
			}
			break;
		case 'byte':
			if (is_array($value)) {
				if ($value[1] == FLAG_RESET)
					$value = $this[$field] & ~$value[0];
				elseif ($value[1] == FLAG_SET)
					$value = $this[$field] | $value[0];
			}
			break;
		}
		
		// Store changed value if it has really changed
		if (isset($this->myData[$field]) && $this->myData[$field] == $value) {
			// Reset changed data on this field since its back to the saved value
			unset($this->myChangedData[$field]);
		}
		else {
			$this->myChangedData[$field] = $value;
		}
		return true;
	}

	/**
	* Required definition of interface ArrayAccess
	*/
	public function offsetUnset($offset) {
		die( 'offsetUnset is not implemented in HNOBJBasic' );
		// return isset( $this->objectList[ $offset ] );
	}

	/**
	* Required definition of interface Countable
	*/
	public function count() {
		return count($this->fieldList->getReadable());
	}
}

class HNOBJBasicIterator implements Iterator
{
	private $theObject;
	private $theFields;
	private $cur = 0;

	public function __construct($parent, $fields) {
		$this->theObject = &$parent;
		$this->theFields = &$fields;
	}

	public function current() {
		return $this->theObject[(string) $this->theFields[$this->cur]['name']];
	}

	public function key() {
		return (string) $this->theFields[$this->cur]['name'];
	}

	public function next() {
		$this->cur++;
	}

	public function rewind() {
		$this->cur = 0;
	}

	public function valid() {
		return ($this->cur < count($this->theFields));
	}
}
