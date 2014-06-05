<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Object Class
*
* This page contains the basic class for all multi objects.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

/**
* This is a basic class that loads objects from the database.
*/
class HNMOBBasic implements IteratorAggregate, ArrayAccess, Countable
{
	/**
	* Keeps a count of created MOB Objects.
	* @var array
	*/
	private static $totalCreated = array();

	/**
	* Keeps a count of destroyed MOB Objects.
	* @var array
	*/
	private static $totalDestroyed = array();

	/**
	* Contains an array of HNOBJBasic objects that this class found.
	* Can also contain _OBJPrototype objects which define how to load an HNOBJBasic.
	* @var array
	*/
	protected $objectList = array();

	/**
	* The OBJ class name this MOB is for.
	* @var string
	*/
	private $OBJName;

	/**
	* The tableDef of the OBJ this MOB is for.
	* @var _DefinitionTable
	*/
	private $tableDef;

	/**
	* This tells if this object has loaded the sub objects.
	* Will be set to true when successful loading is complete.
	* @var boolean
	*/
	private $isLoaded = false;

	/**
	* The parent object.
	* @var HNOBJBasic
	*/
	private $parentObj;

	/**
	* The id field of the parent object.
	* @var string
	*/
	private $parentIdField;

	/**
	* The id of the parent object.
	* @var integer
	*/
	private $parentId;

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
	* @param string $table The table that these new objects are to come from.
	* @param string $field The id field name of the $table. Use 'NONE' if you just want everything in the table.
	* @param integer $id The id value that the search is based around in the $field.
	* @param HNOBJBasic $parent The parent object if there is one. Used for saving some of the object loading repetition.
	* @param boolean $loadNow If you set this to true then the objects will be loaded now.
	*/
	public function __construct($object, $field, $id, $parent = null, $loadNow = false) {
		$this->OBJName = $object;
		$this->tableDef =& HNOBJBasic::getTableDefFor($object);
		$readableFields = $this->tableDef->getReadableFields();
		
		if (is_object($id) || is_resource($id))
			throw new Exception('ID cannot be an object or resource');
		if ($field != 'NONE' && empty($readableFields[$field]))
			throw new Exception('Field does not exist or is unreadable');
		if (!empty($parent) && (empty($readableFields[$field]->object) || strtolower(get_class($parent)) != strtolower('OBJ'.$readableFields[$field]->object)))
			throw new Exception('Parent is not of correct type for the field being linked to');
		$this->parentIdField = $field;
		$this->parentId = $id;
		$this->parentObj = $parent;
		
		// Object Count Statistic Update
		if (STATS) {
			if (!isset(self::$totalCreated[$object])) {
				self::$totalCreated[$object] = 0;
				self::$totalDestroyed[$object] = 0;
			}
			self::$totalCreated[$object]++;
		}
		
		if ($loadNow)
			$this->load();
	}

	/**
	 * Used to decrement the count of currently loaded.
	 */
	public function __destruct() {
		if (STATS)
			self::$totalDestroyed[$this->OBJName]++;
	}

	/**
	* Checks if this mob object is loaded.
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
	* Makes a new object for the parent object.
	*
	* @return HNOBJBasic The new object that at least has the ansestor HNOBJBasic.
	*/
	public function addNew() {
		if (!$this->checkLoaded(true))
			return false;
		
		$newObj = HNOBJBasic::loadObject($this->OBJName, 0);
		
		if ($this->parentIdField != 'NONE') {
			$newObj[$this->parentIdField] = $this->parentObj; // So it saves in HNOBJBasic
			$newObj->set_reverse_link($this->parentIdField, $this->parentObj);
		}
		
		$this->objectList[] = $newObj;
		return $newObj;
	}

	/**
	* @param $fields An array of fields which defines the output we want.
	*    $fields can also contain a string, in which case we call replaceFields on it.
	* @return array The results of the population.
	* @example $OBJ->collect(array('first_name'=>'','last_name'=>''));
	*/
	public function collect($fields) {
		$JRet = array();
		foreach($this AS $DBOBJ)
			$JRet[] = $DBOBJ->collect($fields);
		return $JRet;
	}

	/**
	* Loads all the matching records
	*
	* @param array $orderParts Contains an array which is used to order the objects that are selected. The key is the field name and the value is the direction (true=ASC, false=DESC).
	* @param array $whereList A WhereList object.
	* @param boolean $loadChildren If this is true then all children will get their data in one go, false is normal.
	*/
	public function load($orderParts = false, $whereList = false, $loadChildren = false) {
		// Reset the local data store
		$this->isLoaded = false;
		$this->objectList = array();
		
		if ($this->parentIdField != 'NONE') {
			if ($whereList === false)
				$whereList = new WhereList();
			if ($whereList->count())
				$whereList->append(WhereList::WAND, new WherePart($this->parentIdField, '=', $this->parentId));
			else
				$whereList->append(new WherePart($this->parentIdField, '=', $this->parentId));
		}
		
		$DB =& HNDB::singleton(constant($this->tableDef->connection));
		$stmt = $DB->prepareMOBQuery($this->tableDef, $loadChildren, $whereList, $orderParts);
		if (HNDB::MDB2()->isError($stmt))
			throw new Exception(DEBUG ? $stmt->userinfo : 'Statement is invalid!');
		$result = $stmt->execute();
		if (HNDB::MDB2()->isError($result))
			throw new Exception(DEBUG ? $result->userinfo : 'Result is invalid!');
		
		while (($row = $result->fetchRow(MDB2_FETCHMODE_ASSOC)) && !HNDB::MDB2()->isError($row)) {
			$idSet = array();
			foreach ($this->tableDef->keys as $fieldName => $fieldDef) {
				$idSet[] = $row[$fieldName];
			}
			if ($loadChildren)
				$this->objectList[] = new _OBJPrototype($this->OBJName, $idSet, $row);
			else
				$this->objectList[] = new _OBJPrototype($this->OBJName, $idSet);
		}
		
		$result->free();
		$stmt->free();
		$this->isLoaded = true;
		return true;
	}

	/**
	* WARNING: THIS FUNCTION WILL NOT CHANGE PRIMARY ID!
	* (Field Permissions Should Not Allow Users To Change Primary Id Fields)
	*
	* Calls {@link HNOBJBasic::save()} on every child object that this class
	* currently has references to. To be used in situations like when the
	* parent id is changed and needs to be changed in all forein key loactions.
	*/
	public function save_all() {
		foreach ($this->objectList As $object) {
			if ($object instanceof HNOBJBasic)
				$object->save();
		}
	}

	/**
	 * Clean on this and all children.
	 */
	public function clean() {
		if (!$this->isLoaded)
			return; // No need to clean if not loaded
		$this->isLoaded = false;
		foreach ($this->objectList AS $obj) {
			if ($obj instanceof HNOBJBasic)
				$obj->clean($this->parentObj);
		}
		$this->objectList = array();
	}

	/**
	 * Gets the index of the given object or -1 on not found.
	 *
	 * @param $find The object/id we are trying to locate in this list.
	 * @return integer The index of the object that was found or -1 if not found.
	 */
	public function indexOf($find) {
		// Check that the find object is from the same table
		if (is_object($find)) {
			if (!($find instanceof HNOBJBasic) || $find->getTable() != $this->theTable)
				return -1;
			$find = $find->getId();
		}

		// Search List
		$find = (array) $find;
		foreach ($this AS $index => $item) {
			if ((array) $item->getId() == $find)
				return $index;
		}

		// Not Found
		return -1;
	}


	// ############################################
	// #####   Implementation of Interfaces   #####
	// ############################################

	/**
	* Prints this object as a string.
	*
	* @return string
	*/
	public function __toString() {
		if (!$this->checkLoaded(false))
			return '[MOB NOT LOADED]';
		
		try {
			$return = "MOB" .$this->tableDef->table. " {\n";
			foreach ($this AS $num => $value)
				$return .= "\t[" .$num. '] => ID ' .$value->getId(). "\n";
			$return .= "\t}";
		}
		catch (Exception $e) {
			$return = 'EXCEPTION! "' .$e->getMessage(). '"';
		}
		return $return;
	}

	/**
	* Required definition of interface IteratorAggregate
	*/
	public function getIterator() {
		$this->checkLoaded(true);
		return new HNMOBBasicIterator($this);
	}

	/**
	* Required definition of interface ArrayAccess
	*/
	public function offsetExists($offset) {
		if (!$this->checkLoaded(true))
			return false;
		return ($offset < count($this->objectList));
	}

	/**
	* Required definition of interface ArrayAccess
	*/
	public function offsetGet($offset) {
		// Check that this MOB is currently loaded
		// Check that its a valid offset
		if (!$this->checkLoaded(true) || $offset >= count($this->objectList))
			return null;
		
		// Check if progressive creation of HNOBJ for offset is needed
		$object = $this->objectList[$offset];
		if ($object instanceof _OBJPrototype) {
			$object = $object->getUpgrade(false);
			if (!empty($this->parentObj) && $this->parentIdField != 'NONE')
				$object->set_reverse_link($this->parentIdField, $this->parentObj);
			$this->objectList[$offset] = $object;
		}
		
		// Return the HNOBJ instance at the offset
		return $object;
	}

	/**
	* Required definition of interface ArrayAccess
	*/
	public function offsetSet($offset, $value) {
		throw new Exception('offsetSet is not implemented in HNMOBBasic');
	}

	/**
	* Required definition of interface ArrayAccess
	*/
	public function offsetUnset($offset) {
		throw new Exception('offsetUnset is not implemented in HNMOBBasic');
	}

	/**
	* Required definition of interface Countable
	*/
	public function count() {
		if (!$this->checkLoaded(true))
			return 0;
		return count($this->objectList);
	}
}

class HNMOBBasicIterator implements Iterator
{
	private $MOB;
	private $cur = 0;

	public function __construct($MOB) {
		$this->MOB = $MOB;
	}

	public function current() {
		return $this->MOB->offsetGet($this->cur);
	}

	public function key() {
		return $this->cur;
	}

	public function next() {
		$this->cur++;
	}

	public function rewind() {
		$this->cur = 0;
	}

	public function valid() {
		return $this->MOB->offsetExists($this->cur);
	}
}
