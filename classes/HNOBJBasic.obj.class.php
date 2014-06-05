<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Object Class
*
* This page contains the basic class for all objects.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
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
	* Will contain the id for the id field of this object.
	* @var integer
	*/
	protected $myId;

	/**
	* Stores the current status of this OBJ.
	* This property should always be checked by using has_record or checkLoaded
	* @var enum
	*/
	const NOT_LOADED = 0;
	const NO_RECORD = 1;
	const LOADED = 2;
	protected $status = self::NOT_LOADED;

	/**
	* This property is to hold a reference to the TableDef that this instance was loaded with.
	* It should never be changed or used directly except in load() and checkLoaded().
	* This is used to determine if the OBJ needs reloading due to tableDef changing.
	*/
	private $loadedWithTableDef = null;

	/**
	* This contains the same data that the Database does.
	* As in the database values are loaded straight into this for reference
	* later by other objects. This does not get changed.
	* @var array
	*/
	protected $myData = array();

	/**
	* Constains the values that have been changed compared to the
	* database values. These are saved when {@link save()} is called.
	* @var array
	*/
	protected $myChangedData = array();

	/**
	 * Reverse Link To Parent.
	 */
	protected $myParentObject = null;

	public static function loadClassFor($object) {
		if (empty($object))
			throw new Exception('No object given');
		if (!class_exists('OBJ' . $object))
			require_once CLASS_PATH. '/OBJ.' .$object. '.class.php';
	}

	/**
	* Loads an object using the config specified object class so that the
	* programmer doesnt have to do such complex things often.
	*
	* @param $object The object name to be loaded. eg: user, login_log.
	* @param $id An array of values or single value which will be used as keys to load the object.
	* @param $loadNow Boolean which is TRUE will cause this new OBJ to be immeadiately loaded from DB.
	* @return HNOBJBasic A object class that extends the HNOBJBasic base class.
	*/
	public static function loadObject($object, $id, $loadNow = false) {
		self::loadClassFor($object);
		
		$OBJ = HNOBJCache::get($object, $id);
		if ($OBJ === false || !($OBJ instanceof HNOBJBasic)) {
			// We need a new object
			$className = 'OBJ' . $object;
			$OBJ = new $className($id, $loadNow);
			if (!empty($id))
				HNOBJCache::set($OBJ, $object);
		}
		return $OBJ;
	}

	public static function &getTableDefFor($object) {
		self::loadClassFor($object);
		$className = 'OBJ' . $object;
		return $className::getTableDef();
	}

	public static function &getTableDef() {
		static::prepareObjectDefs();
		return static::$tableDef;
	}

	public static function prepareObjectDefs() {
		$userTypes = (empty($GLOBALS['uo']) ? array('all' => 0) : $GLOBALS['uo']->userTypes());
		if (defined('HNWC_CRON')) $userTypes['cron'] = 0;
		if (empty(static::$tableDefFor) || static::$tableDefFor != array_keys($userTypes)) {
			static::$tableDefFor = array_keys($userTypes);
			
			static::$tableDef = new _DefinitionTable(static::$tableDefOrig);
			#echo "REDO " .static::$tableDef->table. " for " .implode(',', array_keys($userTypes)). "\n";
			
			static::$selectStatement = false;
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
	* @param integer|array $id The id of the record we are getting from the table.
	* @param boolean $loadNow If set to yes then the DB data is loaded now.
	*/
	public function __construct($id, $loadNow) {
		$this->myId = (array) $id;
		self::prepareObjectDefs();
		if ($loadNow)
			$this->load();

		// Object Count Statistic Update
		if (STATS) {
			if (!isset(self::$totalCreated[$this::$tableDef->table])) {
				self::$totalCreated[$this::$tableDef->table] = 0;
				self::$totalDestroyed[$this::$tableDef->table] = 0;
			}
			self::$totalCreated[$this::$tableDef->table]++;
		}
	}

	/**
	 * Used to decrement the count of currently loaded.
	 */
	public function __destruct() {
		if (STATS)
			self::$totalDestroyed[$this::$tableDef->table]++;
	}

	/**
	 * Sets the parent object for this object.
	 * Basically this is the only known reverse link that gives us troubles.
	 */
	public function set_reverse_link($field, $obj) {
		// Assumed this check is already done in its only user HNMOBBasic::__construct()
		/*if (empty($this->getTableDef()->fields[$field]) || empty($this->getTableDef()->fields[$field]->object))
			throw new Exception('Reverse link is not an OBJ field');*/
		$this->myData[$field] = $obj;
		$this->myParentObject = $obj;
	}
	
	/**
	* This method is only to be used inside the OBJ classes.
	* It sets the incoming parameter as though it came from sql directly.
	*/
	public function _internal_set_data($dataSet) {
		// Setup the data structure
		$this->storeArrayToLocal($dataSet);
		$this->status = self::LOADED;
	}
	
	/**
	* Check if this object has a record that is physically stored in the database.
	* @return Boolean True if the record is physically located in the database, False otherwise.
	*/
	public function hasRecord() {
		$this->checkLoaded(true);
		return ($this->status == self::LOADED);
	}
	
	/**
	* Checks if this object is loaded.
	* It can attempt to load the object now if it has not been loaded yet.
	*
	* @param boolean $loadNow If this is true then the data will be loaded now if it has not been already.
	* @return boolean TRUE if it is now loaded, FALSE otherwise.
	*    NOTE: It will always return true if loading now because load always succeeds.
	*/
	public function checkLoaded($loadNow = false) {
		if ($this->status != self::NOT_LOADED && $this->loadedWithTableDef === $this::$tableDef)
			return true;
		if ($loadNow) {
			$this->load();
			return true;
		}
		return false;
	}

	/**
	* Gets the table this instance is using.
	*
	* @return FieldList::getTable()
	*/
	public function getTable() {
		return $this::$tableDef->table;
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
	throw new Exception('No longer implemented! Please use $OBJ::getTableDef()->fields[$field] instead.');
		$fields = $this->fieldList->getReadable();
		return (isset($fields[$field]) ? $fields[$field] : false);
	}

	/**
	* @param $fields An array of fields which defines the output we want.
	*    $fields can also contain a string, in which case we call replaceFields on it.
	* @return array The results of the population.
	* @example $OBJ->collect(array('first_name'=>'','last_name'=>''));
	*/
	public function collect($fields) {
		if (!is_array($fields))
			return $this->replaceFields($fields);
		
		$JRet = array();
		foreach ($fields AS $field => $fcontent) {
			$DBField = $this[$field];
			$JRet[$field] = (!empty($fcontent) ? $DBField->collect($fcontent) : $DBField);
		}
		return $JRet;
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
		$patterns = array();
		foreach ($this::$tableDef->getReadableFields() as $fieldName => $fieldDef)
			$patterns[] = sprintf('|%s(%s)%s|', $pre, $fieldName, $post);
		return preg_replace_callback($patterns, array(&$this, 'replaceFieldsReplacer'), $str);
	}

	/**
	* This is the callback for the replaceFields method above.
	* It does the actual replacement of values.
	*/
	private function replaceFieldsReplacer($matches) {
		$field = $matches[1];
		$val = $this[$matches[1]];
		if ($val instanceof HNOBJBasic) {
			$readableFields = $this::$tableDef->getReadableFields();
			if (!empty($readableFields[$field]->display))
				$val = $val->replaceFields($readableFields[$field]->display);
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
	* Always succeeds.
	*/
	public function load() {
		// Reset the local data store
		$this->status = self::NOT_LOADED;
		$oldData = $this->myData;
		$this->myData = array();
		
		// If not perpared already make the static select statement
#		if (empty(static::$selectStatement)) {
# Major optimisation ignored here because binding fails for unknown reasons
###################################################################################
			$DB =& HNDB::singleton(constant(static::$tableDef->connection));
			static::$selectStatement = $DB->prepareOBJQuery('SELECT', static::$tableDef);
			unset($DB);
#		}
		
		// If there is just one key and that key is zero then there is assumed no record to load
		if (count($this->myId) == 1 && $this->myId[0] === 0) {
			$this->status = self::NO_RECORD;
		} else {
			// Execute the statement
			$ids = array();
			foreach (array_keys($this::$tableDef->keys) as $key => $val)
				$ids[$val] = $this->myId[$key];
			
			#var_dump(static::$selectStatement->query, static::$selectStatement->positions, static::$selectStatement->values, $ids);
			$result = static::$selectStatement->execute($ids);
			if ($result->numRows() == 1) {
				$data =& $result->fetchRow(MDB2_FETCHMODE_ASSOC);
				if (!is_array($data))
					throw new Exception('Result was not an array');
			} else {
				$this->status = self::NO_RECORD;
			}
			$result->free();
		}
		
		// Process the Data into the correct data types and locally store it
		$this->storeArrayToLocal(($this->status == self::NO_RECORD ? array() : $data), $oldData);
		
		// We have loaded successfully if we have a record
		if ($this->status == self::NOT_LOADED)
			$this->status = self::LOADED;
	}
	
	/**
	* Processes the Data into the correct data types and locally store it.
	*/
	protected function storeArrayToLocal($dataArray, $oldData = array()) {
		$this->loadedWithTableDef = $this::$tableDef; // Copy of object not variable slot
		foreach ($this::$tableDef->fields as $name => &$fieldDef) {
			// In case the value to set is not defined, we give it an empty string
			if (!isset($dataArray[$name]))
				$dataArray[$name] = '';
			
			// Special handling of objects
			if (!empty($fieldDef->object)) {
				$object = HNOBJCache::get($fieldDef->object, $dataArray[$name]);
				if ($object === false) {
					$object = new _OBJPrototype($fieldDef->object, $dataArray[$name]);
					HNOBJCache::set($object, $fieldDef->object);
				}
				$this->myData[$name] = $object;
			} else {
				$this->myData[$name] = $dataArray[$name];
			}
			unset($fieldDef);
		}
		
		foreach ($this::$tableDef->subtables as $name => &$fieldDef) {
			// In case the value to set is not defined, we give it an empty string
			if (!isset($dataArray[$name]))
				$dataArray[$name] = '';
			
			if (isset($oldData[$name]))
				$this->myData[$name] = $oldData[$name];
			else
				$this->myData[$name] = new _MOBPrototype($fieldDef);
			unset($fieldDef); // _MOBPrototype takes this as reference so it must be unset
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
		$replacements = array();
		
		#checkLoaded(true); // will be done in hasRecord() later or offsetSet() before.
		
		// Check that we have field to save in $this->myChangedData
		if (empty($this->myChangedData))
			return false;
		
		if (!$this->hasRecord()) {
			$type = 'INSERT';
			$fieldDefs = $this::$tableDef->getInsertableFields();
			if (empty($fieldDefs))
				throw new Exception('Nothing can be inserted in this table');
		} else {
			$type = 'UPDATE';
			$fieldDefs = $this::$tableDef->getWriteableFields();
			if (empty($fieldDefs))
				throw new Exception('Nothing can be written in this table');
			
			// Prepare fields that are keys
			foreach (array_keys($this::$tableDef->keys) as $key => $val)
				$replacements[$val] = $this->myId[$key];
		}
		
		// Prepare fields that are getting changed
		$fields = array();
		foreach ($fieldDefs as $fieldName => $fieldDef) {
			if (!isset($this->myChangedData[$fieldName])) {
				continue;
			} elseif (!empty($fieldDef->object)) {
				if ($type == 'UPDATE' && $this->myData[$fieldName]->getId() == $this->myChangedData[$fieldName]->getId()) {
					#echo 'For ' . $fieldName . ' "' . $this->myData[$fieldName]->getId() . '" == "' . $this->myChangedData[$fieldName]->getId() . '"';
					continue;
				}
				$replacements[$fieldName] = $this->myChangedData[$fieldName]->getId();
				$fields[] = $fieldName;
			} else {
				$replacements[$fieldName] = $this->myChangedData[$fieldName];
				$fields[] = $fieldName;
			}
			
			//echo "Field $fieldName changed from '" .$this->myData[$fieldName]. "' to '" .$this->myChangedData[$fieldName]. "'\n";
		}
		
		// Check that something is going to be saved
		if (empty($fields)) {
			// Nothing needs saving so any changed values mean nothing
			$this->myChangedData = array();
			return true;
		}
		
		// Prepare DB connection and statement
		$DB = HNDB::singleton(constant($this::$tableDef->connection));
		$stmt = $DB->prepareOBJQuery($type, $this::$tableDef, $fields);
		
		#var_dump($stmt->query, $stmt->positions, $stmt->values, $replacements);
		$result = $stmt->execute($replacements);
		if ($result == 0)
			throw new Exception('No rows affected by ' .$type);
		
		// Update my ID values and cache
		if ($type == 'INSERT' && $this::$tableDef->hasAutoId) {
			$this->myId[0] = $DB->lastInsertID($this::$tableDef->table);
			HNOBJCache::set($this);
		} else {
			if ($type == 'UPDATE')
				HNOBJCache::rem($this);
			$keyId = 0;
			foreach ($this::$tableDef->keys AS $fieldName => $fieldDef) {
				if (isset($this->myChangedData[$fieldName]))
					$this->myId[$keyId] = $this->myChangedData[$fieldName];
				else
					$this->myId[$keyId] = $this->myData[$fieldName];
				if (is_object($this->myId[$keyId]) && $this->myId[$keyId] instanceof HNOBJBasic)
					$this->myId[$keyId] = $this->myId[$keyId]->getId();
				$keyId++;
			}
			HNOBJCache::set($this);
		}
		
		// YAY! we are done! Now to clean up
		$stmt->free();
		$this->status = self::NOT_LOADED;
		$this->myChangedData = array();
		return true;
	}

	/**
	* Removes the current record from the database.
	*
	* @return boolean TRUE on success, otherwise FALSE.
	*/
	public function remove() {
		// Check that this user can delete this
		if (!$this::$tableDef->deleteable() || !$this->hasRecord())
			return false;
		
		// Prepare fields that are keys
		$replacements = array();
		foreach (array_keys($this::$tableDef->keys) as $key => $val)
			$replacements[$val] = $this->myId[$key];
		
		// Prepare DB connection and statement
		$DB = HNDB::singleton(constant($this::$tableDef->connection));
		$stmt = $DB->prepareOBJQuery('DELETE', $this::$tableDef);
		
		$result = $stmt->execute($replacements);
		if ($result == 0)
			throw new Exception('No rows affected by DELETE');
		
		// YAY! We have deleted the record!
		$stmt->free();
		$this->status = self::NOT_LOADED;
		HNOBJCache::rem($this);
		return true;
	}

	/**
	 * Clean up the horrible reverse links.
	 * OR for now just clean up everything.
	 */
	public function clean($callingParent = false) {
		if (!empty($callingParent) && $this->myParentObject !== $callingParent)
			return;// Don't clean if the caller parent does not match
		
		// Get rid of cache
		HNOBJCache::rem($this);
		
		if ($this->status == self::NOT_LOADED)
			return; // If not loaded we don't need to continue;
		$this->status = self::NOT_LOADED;
		
		foreach ($this->myData AS $data) {
			if (!is_object($data))
				continue;

			if ($this->myParentObject && $data === $this->myParentObject)
				//$this->myData = null;
				continue;

			if ($data instanceof HNOBJBasic || $data instanceof HNMOBBasic)
				$data->clean($this);
		}

		$this->myData = array();
		$this->myChangedData = array();
		$this->myParentObject = null;
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
		try {
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
		} catch (Exception $e) {
			$return = 'Exception: ' .$e->getMessage() . ' ' . $e->getTraceAsString();
		}
		return $return;
	}

	/**
	* Required definition of interface IteratorAggregate
	*/
	public function getIterator() {
		return new HNOBJBasicIterator($this, array_keys($this::$tableDef->getReadableFieldsAndSubtables()));
	}

	/**
	* Required definition of interface ArrayAccess
	*/
	public function offsetExists($offset) {
		$rfast = $this::$tableDef->getReadableFieldsAndSubtables();
		return isset($rfast[$offset]);
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
		$this->checkLoaded(true);
		if (!array_key_exists($field, $this::$tableDef->getReadableFieldsAndSubtables()))
			throw new Exception('No such field "' .$field. '" in "' .$this::$tableDef->table. '" or unauthorised');
		if (!isset($this->myData[$field]))
			throw new Exception('Field "' .$field. '" in "' .$this::$tableDef->table. '" has not loaded for some reason');
		
		$fieldData = $this->myData[$field];
		if ($fieldData instanceof _MOBOBJPrototype) {
			if ($fieldData instanceof _OBJPrototype) {
				$fieldDef = $this::$tableDef->fields[$field];
				$this->myData[$field] = $fieldData->getUpgrade($fieldDef->remoteField, $this, false);
				// No cache needed here, getUpgrade calls loadObject which caches
			} elseif ($fieldData instanceof _MOBPrototype)
				$this->myData[$field] = $fieldData->getUpgrade($this, false);
			else
				throw new Exception('Unknown instance of _MOBOBJPrototype found as ' .get_class($fieldData));
			$fieldData = $this->myData[$field];
		}
		return $fieldData;
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
		if ($this->hasRecord()) {
			if (!array_key_exists($field, $this::$tableDef->getWriteableFields()))
				throw new Exception('Attempt to set a non-writeable field "' .$field. '" in existing OBJ of type "' .get_class($this). '"');
		}
		else {
			if (!array_key_exists($field, $this::$tableDef->getInsertableFields()))
				throw new Exception('Attempt to set a non-insertable field "' .$field. '" in new OBJ of type "' .get_class($this). '"');
		}
		$fieldDef = $this::$tableDef->fields[$field];
		
		// Fix up the types that are morphic
		if (isset($fieldDef->object)) {
			if (!is_object($value) || !($value instanceof HNOBJBasic))
				$value = self::loadObject($fieldDef->object, $value);
			elseif (strtoupper(get_class($value)) != strtoupper('OBJ'.$fieldDef->object))
				throw new Exception(sprintf('Attempt to set OBJ of wrong table in link field. Got %s wanted OBJ%s.',
					strtoupper(get_class($value)), strtoupper($fieldDef->object)));
		} elseif ($fieldDef->type == 'byte' && is_array($value)) {
			if ($value[1] == FLAG_RESET)
				$value = $this[$field] & ~$value[0];
			elseif ($value[1] == FLAG_SET)
				$value = $this[$field] | $value[0];
		}
		
		// Validate the value if necessary
		if (isset($fieldDef->values)) {
			if (!in_array($value, $fieldDef->values))
				throw new Exception('Invalid value being set for ' .$field);
		} elseif (isset($fieldDef->validation)) {
			if (!preg_match($fieldDef->validation, $value))
				throw new Exception('Value for ' .$field. ' does not pass validation');
		}
		
		// Store changed value if it has really changed
		if (isset($this->myData[$field]) && $this->myData[$field] == $value) {
			// Reset changed data on this field since its back to the saved value
			unset($this->myChangedData[$field]);
		} else {
			#echo "Changing $field from " .print_r($this->myData[$field],1). " to " .print_r($value,1). "\n";
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
		return count($this::$tableDef->getReadableFields());
	}
}

class HNOBJBasicIterator implements Iterator
{
	private $theObject;
	private $theFields;
	private $cur = 0;

	public function __construct($parent, $fields) {
		$this->theObject =& $parent;
		$this->theFields =& $fields;
	}

	public function current() {
		return $this->theObject[$this->theFields[$this->cur]];
	}

	public function key() {
		return (string) $this->theFields[$this->cur];
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


/**
* Holds and tests the permissions of a definition.
*/
class _DefinitionPermission
{
	#public $readBy = array(), $writeBy = array(), $insertBy = array(), $deleteBy = array();
	public $readBy = NULL, $writeBy = NULL, $insertBy = NULL, $deleteBy = NULL;

	public function __construct(&$defs) {
		$this->readBy =& $defs->readBy;
		$this->writeBy =& $defs->writeBy;
		$this->insertBy =& $defs->insertBy;
		$this->deleteBy =& $defs->deleteBy;
		unset($defs->readBy, $defs->writeBy, $defs->insertBy, $defs->deleteBy);
	}

	/*public function isUsed() {
		return ($this->checkIfInside($this->readBy, 'IUR') || $this->checkIfInside($this->writeBy, 'IUW')
			|| $this->checkIfInside($this->insertBy, 'IUI')|| $this->checkIfInside($this->deleteBy, 'IUD'));
	}*/

	public function readable($DEBUG) { return $this->checkIfInside($this->readBy, 'R-'.$DEBUG); }
	public function writeable($DEBUG) { return $this->checkIfInside($this->writeBy, 'W-'.$DEBUG); }
	public function insertable($DEBUG) { return $this->checkIfInside($this->insertBy, 'I-'.$DEBUG); }
	public function deleteable($DEBUG) { return $this->checkIfInside($this->deleteBy, 'D-'.$DEBUG); }

	/**
	* Checks a premissions string to see if a group matches.
	* 
	* @param string $toCheck The string to check.
	* @return boolean True if there was a match, false otherwise.
	*/
	private function checkIfInside($toCheck, $checkType) {
#		echo "checkType$checkType " .implode(',',$toCheck). "\n";
		if (!empty($GLOBALS['uo']))
			return $GLOBALS['uo']->testUserType($toCheck);
		
		// Fallback for when they are not logged in yet or under cron
		foreach ($toCheck as $val) {
			if ($val == 'all' || (defined('HNWC_CRON') && $val == 'cron'))
				return true;
		}
		return false;
	}
}

class _DefinitionBase
{
	private static $nextPermissionId = 0;
	private static $permission = array();
	private $permissionId;
	public function __construct() {
		$this->permissionId = ++self::$nextPermissionId;
	}
	
	public function __destruct() {
		if (isset($this->permissionId))
			unset(self::$permission[$this->permissionId]);
	}
	
	public function &getPermission() {
		if (!isset(self::$permission[$this->permissionId]))
			self::$permission[$this->permissionId] = new _DefinitionPermission();
		return self::$permission[$this->permissionId];
	}
	
	public function setPermission(&$defs) {
		if ($defs instanceof _DefinitionPermission)
			self::$permission[$this->permissionId] =& $defs;
		else
			self::$permission[$this->permissionId] = new _DefinitionPermission($defs);
	}
	
	public function setWithTableCopy(&$TableDef) {
		$myPermission =& $this->getPermission();
		$tablePermission =& $TableDef->getPermission();
		
		if ($myPermission->readBy === NULL) $myPermission->readBy =& $tablePermission->readBy;
		if ($myPermission->writeBy === NULL) $myPermission->writeBy =& $tablePermission->writeBy;
		if ($myPermission->insertBy === NULL) $myPermission->insertBy =& $tablePermission->insertBy;
		if ($myPermission->deleteBy === NULL) $myPermission->deleteBy =& $tablePermission->deleteBy;
		if ($myPermission->readBy == $tablePermission->readBy
			&& $myPermission->writeBy == $tablePermission->writeBy
			&& $myPermission->insertBy == $tablePermission->insertBy
			&& $myPermission->deleteBy == $tablePermission->deleteBy
			)
			self::$permission[$this->permissionId] =& $tablePermission;
	}
}

class _DefinitionTable extends _DefinitionBase
{
	/* PLEASE TREAT ALL AS READ ONLY! */
	public $connection = 'DEFAULT';
	public $table = '';
	public $hasAutoId = false;
	public $keys = array();
	public $fields = array();
	public $subtables = array();
	private $readableFields;
	private $writeableFields;
	private $insertableFields;

	public function __construct($json) {
		parent::__construct();
		
		$jTable = json_decode($json);
		$this->setPermission($jTable);
		if ($errorCode = json_last_error()) {
			$constants = get_defined_constants(true);
			$jsonConstants = array();
			foreach ($constants['json'] as $key => $val) {
				if (!strncmp($key, "JSON_ERROR_", 11))
					$jsonConstants[$val] = $key;
			}
			throw new Exception('Table JSON definition could not be decoded. Code:' .$errorCode. ', Name:' .$jsonConstants[$errorCode]);
		}
		
		foreach ($jTable as $key => &$val) {
			if (!isset($this->{$key}))
				throw new Exception('Invalid Table Parameter "' .$key. '"');
			$this->{$key} =& $val;
		}
		unset($val);
#echo "Do Table $this->table\n";
		
		$this->connection = 'HNDB_' .$this->connection;
		
		$fields = $this->fields;
		$this->fields = array();
		foreach ($fields as $key => $val)
			$this->fields[$key] = new _DefinitionField($this, $key, $val);
		
		$keys = $this->keys;
		$this->keys = array();
		foreach ($keys as $val) {
			if (!isset($this->fields[$val]))
				throw new Exception('Invalid key field "' .$val. '"');
			if ($this->fields[$val]->type == 'autoid') {
				$this->hasAutoId = true;
				$this->fields[$val]->type = 'integer';
			}
			$this->keys[$val] =& $this->fields[$val];
		}
		
		// Check that there is only one autoid if any
		if ($this->hasAutoId && count($this->keys) != 1)
			throw new Exception('There cannot be more than one autoid or key for a table');
		
		$subtables = $this->subtables;
		$this->subtables = array();
		foreach ($subtables as $key => $val)
			$this->subtables[$key] = new _DefinitionSubTable($this, $key, $val);
		
		// Remove fields, keys and subtables that can never be used
		foreach ($this->fields as $fieldName => &$fieldDef) {
			if (!$fieldDef->getPermission()->readable('FE')) {
				unset($this->fields[$fieldName]);
				
				// If field is not readable it cannot be a key either
				unset($this->keys[$fieldName]);
			}
		}
		unset($fieldDef);
		foreach ($this->subtables as $subtableName => &$subtableDef) {
			// Already checked in subtable creation
			// if (!$subtableDef->getPermission()->readable('STE'))
			if (isset($subtableDef->NOT_READABLE))
				unset($this->subtables[$subtableName]);
		}
		unset($subtableDef);
		
		// Readable fields list is already known
		$this->readableFields =& $this->fields;
#echo "End Table $this->table\n";
	}
	
	public function getReadableFields() {
		/* 
		if (!isset($this->readableFields)) {
			$this->readableFields = array();
			foreach ($this->fields as $fieldName => &$fieldDef) {
				if ($fieldDef->getPermission()->readable('GRF'))
					$this->readableFields[$fieldName] =& $fieldDef;
			}
		}*/
		return $this->readableFields;
	}
	
	public function getReadableFieldsAndSubtables() {
		return array_merge($this->getReadableFields(), $this->subtables);
	}
	
	public function getWriteableFields() {
		if (!isset($this->writeableFields)) {
			$this->writeableFields = array();
			foreach ($this->fields as $fieldName => &$fieldDef) {
				if ($fieldDef->getPermission()->writeable('GWF'))
					$this->writeableFields[$fieldName] =& $fieldDef;
			}
		}
		return $this->writeableFields;
	}
	
	public function getInsertableFields() {
		if (!isset($this->insertableFields)) {
			$this->insertableFields = array();
			foreach ($this->fields as $fieldName => &$fieldDef) {
				if ($fieldDef->getPermission()->insertable('GIF'))
					$this->insertableFields[$fieldName] =& $fieldDef;
			}
		}
		return $this->insertableFields;
	}
	
	public function deleteable() {
		return $this->getPermission()->deleteable('TD');
	}
}

class _DefinitionField extends _DefinitionBase
{
	/* PLEASE TREAT ALL AS READ ONLY! */
	private static $allowedParameters = array('type','object','SQL',
		'validation','values','default','localField','remoteField');
	/* Valid param for OBJ/MOB are type, SQL, readBy, writeBy, insertBy, (validation OR values), default */
	/* Default: Used raw as the default value in an insert statement */
#	public $type = '', $object = '', $SQL = '', $validation = false, $values = array(), $default = '';
#	public $type, $object, $SQL, $validation, $values, $default;
	/* Valid param for AutoQuery are autoVisibleSQL, autoInternSQL, autoRequireOBJs */
	//public $autoVisibleSQL = '', $autoInternSQL = '', $autoRequireOBJs = '';
	
	public function __construct(&$TableDef, $virtName, $defs) {
		parent::__construct();
		$this->virtName = $virtName;
		$this->tableDef =& $TableDef;
		
		$this->setPermission($defs);
		foreach ($defs as $key => &$val) {
			if (!in_array($key, self::$allowedParameters))
				throw new Exception('Invalid Field Parameter "' .$key. '"');
			$this->{$key} =& $val;
		}
		if (empty($this->SQL)) $this->SQL = '`T`.' .$virtName;
		$this->setWithTableCopy($TableDef);
		
		// Check that objects are setup correctly
		if (isset($this->object)) {
			if (empty($this->localField))
				$this->localField = $virtName;
			if (empty($this->remoteField))
				$this->remoteField = $virtName;
		} elseif (isset($this->localField) && isset($this->remoteField)) {
			throw new Exception('remoteField not allowed when field is not an object link');
		}
	}
	
	/**
	* Replaces `T` with a real table alias.
	* Use $table = false to just remove the `T`.
	*/
	public function SQLWithTable($table = false) {
		$table = ($table ? $table.'.' : '');
		return str_replace('`T`.', $table, $this->SQL);
	}
}

class _DefinitionSubTable extends _DefinitionBase
{
	/* PLEASE TREAT ALL AS READ ONLY! */
	private static $allowedParameters = array('object','localField','remoteField');
#	public $object = '', $localField = '', $remoteField = '';
#	public $object, $localField, $remoteField;
	
	public function __construct(&$TableDef, $virtName, $defs) {
		parent::__construct();
		$this->virtName = $virtName;
		$this->tableDef =& $TableDef;
		
		$this->setPermission($defs);
		foreach ($defs as $key => &$val) {
			if (!in_array($key, self::$allowedParameters))
				throw new Exception('Invalid SubTable Parameter "' .$key. '"');
			$this->{$key} = &$val;
			unset($val);
		}
		
		// Prove Permissions
		$this->setWithTableCopy($TableDef);
		if (!$this->getPermission()->readable('STEE')) {
			$this->NOT_READABLE = true; // Used for clobber in _DefinitionTable
			return;
		}
		
		if (empty($this->localField)) {
			if (count($TableDef->keys) != 1)
				throw new Exception('Default localField value requires exactly one key for this table');
			reset($TableDef->keys);
			$this->localField = key($TableDef->keys);
		}/* else {
			$localField = $this->localField;
			$this->localField = array();
			foreach ($localField as $fieldName)
				$this->localField[] =& $TableDef->fields[$fieldName];
		}*/
		if (empty($this->remoteField)) {
			if (count($TableDef->keys) != 1)
				throw new Exception('Default remoteField value requires exactly one key for this table');
			reset($TableDef->keys);
			$this->remoteField = key($TableDef->keys);
		}
	}
}


class HNOBJCache
{
	private static $getTotal = 0;
	private static $getHits = 0;
	private static $setTotal = 0;
	private static $setHits = 0;
	private static $remTotal = 0;
	public static function getCacheCounts() {
		return array(self::$getTotal,self::$getHits,self::$setTotal,self::$setHits,self::$remTotal);
	}

	/** This is an array of objects and prototypes that have already been loaded */
	public static $cachedObjects = array('_OBJPrototype' => array());

	/** Checks if an OBJ or PROTO is in the cache for given className and id array.
	*	$className should NOT have leading 'OBJ'. */
	public static function get($className, $id) {
		self::$getTotal++;
		$idFix = (is_array($id) ? implode('!', $id) : $id);
		$className = strtoupper($className);
		if (empty($idFix))
			$t =& self::$cachedObjects['_OBJPrototype'];
		elseif (!isset(self::$cachedObjects[$className]))
			return false;
		else
			$t =& self::$cachedObjects[$className];
		if (!isset($t[$idFix]))
			return false;
		self::$getHits++;
		return $t[$idFix];
	}

	/** Properly sets the OBJ or PROTO into the cache. $className should NOT have leading 'OBJ'. */
	public static function set($OBJ, $className = null) {
		self::$setTotal++;
		$idFix = $OBJ->getId();
		$idFix = (is_array($idFix) ? implode('!', $idFix) : $idFix);
		
		if ($className === null) {
			if ($OBJ instanceof _OBJPrototype) {
				if (DEBUG)
					throw new Exception('$className should not be null');
				return;
			}
			$className = substr(get_class($OBJ), 3);
		}
		$className = strtoupper($className);
		
		if (empty($idFix)) {
			if ($OBJ instanceof _OBJPrototype) {
				if (DEBUG && isset($OBJ->data))
					throw new Exception('Empty _OBJPrototype must not have data if its being cached');
				self::$setHits++;
				self::$cachedObjects['_OBJPrototype'][$className] = $OBJ;
			} elseif (DEBUG) {
				throw new Exception('Dont cache HNOBJBasic with no ID');
			}
			return;
		}
		
		if (!isset(self::$cachedObjects[$className]))
			self::$cachedObjects[$className] = array();
		self::$setHits++;
		self::$cachedObjects[$className][$idFix] = $OBJ;
	}

	/** Removes an OBJ or PROTO from the cache. $className should NOT have leading 'OBJ'. */
	public static function rem($OBJ, $className = null) {
		self::$remTotal++;
		$idFix = implode('!', (array) $OBJ->getId());
		$className = strtoupper($className == null ? substr(get_class($OBJ), 3) : $className);
		if (isset(self::$cachedObjects[$className][$idFix]))
			unset(self::$cachedObjects[$className][$idFix]);
	}
}


abstract class _MOBOBJPrototype {}; // This first Prototype is for simplfying logic
class _MOBPrototype extends _MOBOBJPrototype
{
	private static $totalCreated = 0;
	private static $totalUpgraded = 0;
	private static $totalDestroyed = 0;
	public static function getObjectCounts() {
		return array(self::$totalCreated,self::$totalUpgraded,self::$totalDestroyed);
	}

	public $fieldDef;
	public function __construct(&$fieldDef) {
		$this->fieldDef =& $fieldDef;
		self::$totalCreated++;
	}
	public function __destruct() {
		self::$totalDestroyed++;
	}
	
	public function getUpgrade($parent, $loadNow) {
		self::$totalUpgraded++;
		$localFieldVal = $parent[$this->fieldDef->localField];
		$remoteField = $this->fieldDef->remoteField;
		// Hack for having an Array in the local field (eg: LDAP)
		if (is_array($localFieldVal)) $localFieldVal = $localFieldVal[0];
		return new HNMOBBasic($this->fieldDef->object, $remoteField, $localFieldVal, $parent, $loadNow);
	}
}
class _OBJPrototype extends _MOBOBJPrototype
{
	private static $totalCreated = 0;
	private static $totalUpgraded = 0;
	private static $totalDestroyed = 0;
	public static function getObjectCounts() {
		return array(self::$totalCreated,self::$totalUpgraded,self::$totalDestroyed);
	}

	public $class; // Class the OBJ should be loaded with
	public $myId; // The ID field values needed to load the OBJ
	public $data; // OPTIONAL Prefill data that the OBJ is to be loaded with
	public function __construct($class, $myId, $data = null) {
		$this->class = $class;
		$this->myId = (array) $myId;
		if ($data !== null)
			$this->data = $data;
		self::$totalCreated++;
	}
	public function __destruct() {
		self::$totalDestroyed++;
	}
	
	public function getUpgrade($parentIdField, $parentObj, $loadNow) {
		self::$totalUpgraded++;
		$object = HNOBJBasic::loadObject($this->class, $this->myId, $loadNow);
		if ($parentIdField != null && $parentObj != null)
			$object->set_reverse_link($parentIdField, $parentObj);
		if (isset($this->data))
			$object->_internal_set_data($this->data);
		return $object;
	}
	
	/**
	* @see HNOBJBasic::getId()
	*/
	public function getId() {
		if (count($this->myId) == 1)
			return $this->myId[0];
		return $this->myId;
	}
}

