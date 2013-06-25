<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Object Class
*
* This page contains the basic class for all multi objects.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
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
	* The fieldlist object instance for this object.
	* @var FieldList
	*/
	private $fieldList;

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
	* @param string $field The id field name of calling table. Use 'NONE' if you just want everything in the table.
	* @param integer $id The id value that the search is based around.
	* @param HNOBJBasic $parent The parent object if there is one. Used for saving some of the object loading repetition.
	* @param boolean $loadNow If you set this to true then the objects will be loaded now.
	*/
	public function __construct($table, $field, $id, $parent = null, $loadNow = false) {
		#$this->fieldList = FieldList::loadFieldList($table);
		$this->fieldList = $table;
		$this->parentIdField = $field;
		$this->parentId = $id;
		$this->parentObj = $parent;
		
		// Object Count Statistic Update
		if (STATS) {
			if (!isset(self::$totalCreated[$table])) {
				self::$totalCreated[$table] = 0;
				self::$totalDestroyed[$table] = 0;
			}
			self::$totalCreated[$table]++;
		}
		
		if ($loadNow)
			$this->load();
	}

	/**
	 * Used to decrement the count of currently loaded.
	 */
	public function __destruct() {
		if (STATS) {
			if ($this->fieldList instanceof FieldList)
				self::$totalDestroyed[$this->fieldList->getTable()]++;
			else
				self::$totalDestroyed[$this->fieldList]++;
		}
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
		
		$newObj = HNOBJBasic::loadObject($this->fieldList->getTable(), 0, false, $this->fieldList);
		
		if ($this->parentIdField != 'NONE') {
			$newObj[$this->parentIdField] = $this->parentObj;
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
	* @param array $whereParts Contains an array which is used to restrict the records
	* 		that are selected. It contains arrays that consist of 3 items each. The first
	* 		item is the field, the second is the sign and the third is the value.
	* @param boolean $loadChildren If this is true then all children will get their data in one go, false is normal.
	*/
	public function load($orderParts = false, $whereParts = false, $loadChildren = false) {
		// Reset the local data store
		$this->isLoaded = false;
		$this->objectList = array();
		
		// Prepair the field list
		if (!($this->fieldList instanceof FieldList)) {
			// Convert fieldlist from the table value to a real object since it now needed
			$this->fieldList = FieldList::loadFieldList($this->fieldList);
		}

		// Get list of readable fields
		$readableFields = $this->fieldList->getReadable();

		// Check for problems
		if ($this->parentIdField != 'NONE' && !isset($readableFields[$this->parentIdField]))
			throw new Exception('Parent field is not readable');

		// Clean up the where parts
		if (is_array($whereParts)) {
			$cleanWhereParts = array();
			foreach ($whereParts AS $part) {
				// Check that the where part looks valid
				if (!is_array($part) || count($part) != 3) {
					trigger_error('Bad where part (Needs exactly 3 items)', E_USER_WARNING);
					continue;
				}

				// Check that the field name is valid
				if (!isset($readableFields[$part[0]])) {
					trigger_error('Bad field name "' .$part[0]. '" in where part', E_USER_WARNING);
					continue;
				}

				$cleanWhereParts[] = $part;
			}
			$whereParts = $cleanWhereParts;
			unset($cleanWhereParts);
		}

		$LDAPBase = $this->fieldList->getLDAPBase();
		if (!empty($LDAPBase))
			$this->load_ldap($readableFields, $orderParts, $whereParts, $loadChildren);
		else
			$this->load_mysqli($readableFields, $orderParts, $whereParts, $loadChildren);
		
		$this->isLoaded = true;
		return true;
	}

	/**
	* Special loader for ldap tables.
	*/
	private function load_ldap($readableFields, $orderParts, $whereParts, $loadChildren) {
		// Gather fields to fetch
		$fields = (array) $this->fieldList->getIdField();
		if ($loadChildren) {
			foreach ($readableFields as $name => $fieldInfo) {
				if ($fieldInfo['type'] != 'rev_object') {
					/*$sqlFields[] = '`' .$name. '` AS "0' .$name. '"';*/
					$fields[] = $name;
				}
			}
			unset($name, $fieldInfo);
			$fields = array_unique($fields);
		}
		
		// Create LDAP filter
		$filter = array();
		if ($this->parentIdField != 'NONE')
			$filter[] = '(' .$this->parentIdField. '=' .HNLDAP::escape($this->parentId). ')';
		if (is_array($whereParts)) {
			$validSigns = array('=','<','>','<=','>=');
			foreach ($whereParts AS $part) {
				// Check that the sign is one of the valid ones
				if (!in_array($part[1], $validSigns)) {
					trigger_error('Bad sign "' .$part[1]. '" in where part', E_USER_WARNING);
					continue;
				}

				$filter[] = '(' .$part[0].$part[1].HNLDAP::escape($part[2]). ')';
			}
		}
		$filter = (count($filter) > 1 ? '(&' .implode('', $filter). ')' : implode('', $filter));

		// Do the Query
		$result = HNLDAP::search($this->fieldList->getLDAPBase(), $filter, $fields);
		if (!$result) {
			// The query has failed for some reason
			return false;
		}

		// Check over the order list and process it
		if (is_array($orderParts)) {
			foreach ($readableFields as $name => $f) {
				if (isset($orderParts[$name])) {
					$result = HNLDAP::sort($result, $name);
					if (!$orderParts[$name])
						$result = array_reverse($result);
				}
			}
			unset($name, $f);
		}

		// Load all the objects
		unset($result['count']); // Dont want LDAP count
		$table = $this->fieldList->getTable();
		if ($loadChildren) {
			$idFields = $this->fieldList->getIdField();
			foreach ($result as $row) {
				unset($row['count']);
				$idSet = array();
				foreach ((array) $idFields AS $idField)
					$idSet[] = (isset($row[$idField]) ? $row[$idField] : 0);
				
				$this->objectList[] = new _OBJPrototype($table, $idSet, $row);
			}
		}
		else {
			foreach ($result as $row) {
				unset($row['count']); // Done want LDAP count
				$this->objectList[] = new _OBJPrototype($table, array_values($row));
			}
		}
	}

	/**
	* Special loader for mysql tables.
	*/
	private function load_mysqli($readableFields, $orderParts, $whereParts, $loadChildren) {
		// Make the SQL statement
		$sql = 'SELECT ';
		$sqlFields = (array) $this->fieldList->getIdField();
		if ($loadChildren) {
			foreach ($readableFields as $name => $fieldInfo) {
				if ($fieldInfo['type'] != 'rev_object')
					$sqlFields[] = $name;
			}
			unset($name, $fieldInfo);
			$sqlFields = array_unique($sqlFields);
		}
		$sql .= '`' .implode('`,`', $sqlFields). '`';
		unset($sqlFields);
		
		$sql .= ' FROM `' .$this->fieldList->getTable(). '` ';

		// Check over the where list and process it
		$whereFields = array();
		if ($this->parentIdField != 'NONE')
			$whereFields[] = '`' .$this->parentIdField. '`="' .HNMySQL::escape($this->parentId). '"';
		if (is_array($whereParts)) {
			$validSigns = array('=','!=','<','>','<=','>=','LIKE');
			foreach ($whereParts AS $part) {
				// Check that the sign is one of the valid ones
				if (!in_array($part[1], $validSigns)) {
					trigger_error('Bad sign "' .$part[1]. '" in where part', E_USER_WARNING);
					continue;
				}

				// Escape the value
				$value = HNMySQL::escape($part[2]);

				// Add to the where clause
				$whereFields[] = '`' .$part[0]. '`' .$part[1]. '"' .$value. '"';
			}
		}
		if (count($whereFields))
			$sql .= 'WHERE ' .implode(' AND ', $whereFields);
		unset($whereFields, $value, $part, $validSigns);

		// Check over the order list and process it
		if (is_array($orderParts)) {
			$orderShown = false;
			foreach ($readableFields as $name => $f) {
				if (isset($orderParts[$name])) {
					$sql .= ($orderShown ? ', ' : ' ORDER BY '). '`' .$name. '`';
					if (!$orderParts[$name])
						$sql .= ' DESC';
					$orderShown = true;
				}
			}
			unset($orderShown, $name, $f);
		}
		elseif (isset($orderParts) && $orderParts == 'RAND()') {
			$sql .= ' ORDER BY RAND()';
		}

		// Do the Query
		#echo "$sql\n";
		$result = HNMySQL::query($sql, ($loadChildren ? MYSQLI_USE_RESULT : MYSQLI_STORE_RESULT));
		if (!$result) {
			// The query has failed for some reason
			// NOTE: HNMySQL logs and displays the error before return.
			return false;
		}

		// Load all the objects
		$table = $this->fieldList->getTable();
		if ($loadChildren) {
			$idFields = $this->fieldList->getIdField();
			while ($row = $result->fetch_assoc()) {
				$idSet = array();
				foreach ((array) $idFields AS $idField)
					$idSet[] = (isset($row[$idField]) ? $row[$idField] : 0);
				
				/*$obj = HNOBJBasic::loadObject($this->fieldList->getTable(), $idSet, false, $this->fieldList);
				if ($this->parentIdField != 'NONE')
					$obj->set_reverse_link($this->parentIdField, $this->parentObj);
				$this->objectList[] = $obj;*/
				$this->objectList[] = new _OBJPrototype($table, $idSet, $row);
			}
		}
		else {
			while ($row = $result->fetch_row()) {
				$this->objectList[] = new _OBJPrototype($table, $row);
			}
		}

		// Close the result set
		$result->free();
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
		$this->isLoaded = false;
		foreach ($this->objectList AS $obj) {
			if ($obj instanceof HNOBJBasic)
				$obj->clean();
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
			$return = strtoupper($this->fieldList->getTable()). " {\n";
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
			$object = $object->getUpgrade(false, $this->fieldList);
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
