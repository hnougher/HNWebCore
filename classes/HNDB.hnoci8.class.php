<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

require_once CLASS_PATH. '/HNDB.class.php';
HNDB::MDB2()->loadClass('MDB2_Driver_oci8');

//
// PLEASE USE HNDB::factory(), HNDB::connect() or HNDB::singleton() to make an instance.
// This is phptype 'hnoci8' once this file have been included.
//

/** HNWC Wrapper for MDB2_Driver_oci8 class */
class MDB2_Driver_hnoci8 extends MDB2_Driver_oci8
{
	public static $runStats;
	private static $validWhereSigns = array('=','!=','<','>','<=','>=','LIKE');

	/** @see MDB2_Driver_oci8::__construct */
	function __construct() {
		if (!isset(self::$runStats)) {
			HNDB::$runStats['oci8'] = array(
				'total_instances' => 0,
				'current_instances' => 0,
				'query_count' => 0,
				'connect_time' => 0,
				'query_time' => 0);
			self::$runStats =& HNDB::$runStats['oci8'];
		}
		self::$runStats['total_instances']++;
		self::$runStats['current_instances']++;
		
		parent::__construct();
	}

	/** @see MDB2_Driver_oci8::__construct */
	function __destruct() {
		self::$runStats['current_instances']--;
		parent::__destruct();
	}

    /** @see MDB2_Driver_oci8::getDSN */
	 function getDSN($type = 'string', $hidepw = false) {
		$dsn = parent::getDSN($type, $hidepw);
		if ($type == 'array')
			$dsn['phptype'] = 'hnoci8';
		return $dsn;
	 }

    /** @see MDB2_Driver_oci8::connect */
    function connect() {
        $startTime = microtime(true);
		$result = parent::connect();
        self::$runStats['connect_time'] += microtime(true) - $startTime;
        if (HNDB::MDB2()->isError($result))
            throw new Exception(DEBUG ? $result->getUserInfo() : $result->getMessage());
        return $result;
	}

    /** @see MDB2_Driver_oci8::_doQuery */
    function &_doQuery($query, $is_manip = false, $connection = null, $database_name = null) {
        self::$runStats['query_count']++;
        $startTime = microtime(true);
		$result =& parent::_doQuery($query, $is_manip, $connection, $database_name);
        self::$runStats['query_time'] += microtime(true) - $startTime;
        if (HNDB::MDB2()->isError($result))
            throw new Exception(DEBUG ? $result->getUserInfo() : $result->getMessage());
        return $result;
	}

	/**
	* This function prepares SQL statements using the table definitions created by OBJs.
	* 
	* @param $type can be DELETE, INSERT, SELECT or UPDATE.
	* @param $tableDef is the table definitions created for the requesting table.
	* @param $fields holds the fields that are to be updated/inserted.
	*/
	public function prepareOBJQuery($type, $tableDef, $fields = array()) {
		if (!($tableDef instanceOf _DefinitionTable))
			throw new Exception('The table definition passed does not inherit _DefinitionTable');
		$sqlFields = array();
		$replaceTypes = array();
		$returnTypes = array();
		
		if (count($tableDef->keys) == 0)
			throw new Exception('Cannot make OBJ queries for object that has no keys!');
		
		switch ($type) {
		case 'SELECT':
			foreach ($tableDef->getReadableFields() as $fieldName => $fieldDef) {
				$SQL = $fieldDef->SQLWithTable();
				if ($fieldName != $SQL)
					$SQL .= ' AS ' .$fieldName. '';
				$sqlFields[] = $SQL;
				$returnTypes[] = $fieldDef->type;
			}
			if (empty($sqlFields))
				throw new Exception('No fields to get in SELECT. Cannot continue.');
			
			$idFields = array();
			foreach ($tableDef->keys as $fieldName => $fieldDef) {
				$idFields[] = $fieldDef->SQLWithTable(). '=:' .$fieldName;
				$replaceTypes[] = $fieldDef->type;
			}
			if (empty($idFields))
				throw new Exception('No ID fields in SELECT. Cannot continue.');
			
			$idFields[] = 'ROWNUM=1'; // LIMIT 1
			$SQL = sprintf('SELECT %s FROM %s WHERE %s',
				implode(',', $sqlFields), $tableDef->table, implode(' AND ', $idFields));
			break;
		
		case 'UPDATE':
			if (empty($fields))
				throw new Exception('No fields given for UPDATE. Cannot continue.');
			foreach ($tableDef->getWriteableFields() as $fieldName => $fieldDef) {
				if (($fieldsIndex = array_search($fieldName, $fields)) === false)
					continue;
				unset($fields[$fieldsIndex]);
				$sqlFields[] = $fieldDef->SQLWithTable(). '=:' .$fieldName;
				$replaceTypes[] = $fieldDef->type;
			}
			if (!empty($fields))
				throw new Exception('Following fields not writeable: ' .implode(',', $fields));
			if (empty($sqlFields))
				throw new Exception('No fields to set in UPDATE. Cannot continue.');
			
			$idFields = array();
			foreach ($tableDef->keys as $fieldName => $fieldDef) {
				$idFields[] = $fieldDef->SQLWithTable(). '=:' .$fieldName;
				$replaceTypes[] = $fieldDef->type;
			}
			if (empty($idFields))
				throw new Exception('No ID fields in UPDATE. Cannot continue.');
			
			$idFields[] = 'ROWNUM=1'; // LIMIT 1
			$SQL = sprintf('UPDATE %s SET %s WHERE %s',
				$tableDef->table, implode(',', $sqlFields), implode(' AND ', $idFields));
			$returnTypes = MDB2_PREPARE_MANIP;
			break;
		
		case 'INSERT':
			if (empty($fields))
				throw new Exception('No fields given for INSERT. Cannot continue.');
			foreach ($tableDef->getInsertableFields() as $fieldName => $fieldDef) {
				if (($fieldsIndex = array_search($fieldName, $fields)) === false)
					continue;
				unset($fields[$fieldsIndex]);
				$sqlFields[] = $fieldDef->SQLWithTable(). '=:' .$fieldName;
				$returnTypes[] = $fieldDef->type;
			}
			if (!empty($fields))
				throw new Exception('Following fields not insertable: ' .implode(', ', $fields));
			if (empty($sqlFields))
				throw new Exception('No fields to set in INSERT. Cannot continue.');
			
			$SQL = sprintf('INSERT INTO %s SET %s',
				$tableDef->table, implode(',', $sqlFields));
			$returnTypes = MDB2_PREPARE_MANIP;
			break;
		
		case 'DELETE':
			foreach ($tableDef->keys as $fieldName => $fieldDef) {
				$sqlFields[] = $fieldDef->SQLWithTable(). '=:' .$fieldName;
				$replaceTypes[] = $fieldDef->type;
			}
			if (empty($sqlFields))
				throw new Exception('No ID fields in DELETE. Cannot continue.');
			
			$sqlFields[] = 'ROWNUM=1'; // LIMIT 1
			$SQL = sprintf('DELETE FROM %s WHERE %s',
				$tableDef->table, implode(' AND ', $sqlFields));
			$returnTypes = MDB2_PREPARE_MANIP;
			break;
		
		default:
			throw new Exception('Unknown type');
		}
		
		#var_dump($SQL, $replaceTypes, $returnTypes);
		$stmt = $this->prepare($SQL, $replaceTypes, $returnTypes);
		if (HNDB::MDB2()->isError($stmt))
			throw new Exception('Invalid statement ' . (DEBUG ? $SQL : 'was created'));
		return $stmt;
	}
	
	public function prepareMOBQuery($tableDef, $allFields, $whereList = false, $orderParts = false) {
		// Fields to collect
		$sqlFields = array();
		$sqlFieldDefs = ($allFields ? $tableDef->getReadableFields() : $tableDef->keys);
		foreach ($sqlFieldDefs as $fieldName => $fieldDef) {
			$SQL = $fieldDef->SQLWithTable();
			if ($fieldName != $SQL)
				$SQL .= ' AS ' .$fieldName. '';
			$sqlFields[] = $SQL;
			$returnTypes[] = $fieldDef->type;
		}
		
		// Where filter
		if ($whereList !== false) {
			if (!($whereList instanceof WhereList))
				throw new Exception('That is not a WhereList!');
			$where = 'WHERE ' .$this->wherePrinter($tableDef, $whereList->get());
		} else {
			$where = '';
		}
		
		// Result order
		$orderFields = array();
		if (is_array($orderParts)) {
			foreach ($tableDef->getReadableFields() as $fieldName => $fieldDef) {
				if (isset($orderParts[$fieldName])) {
					$orderFields[] = $fieldDef->SQLWithTable() .($orderParts[$fieldName] ? '' : ' DESC');
					unset($orderParts[$fieldName]);
				}
			}
			if (!empty($orderParts))
				throw new Exception('Field "' .implode('","', array_keys($orderParts)). '" is not readable in object "' .$tableDef->table. '".');
		} elseif (isset($orderParts) && $orderParts == 'RAND()') {
			$orderFields[] = 'RAND()';
		} else { // Default ordering is by keys
			foreach ($tableDef->keys as $fieldName => $fieldDef)
				$orderFields[] = $fieldDef->SQLWithTable();
		}
		
		$SQL = sprintf('SELECT %s FROM %s %s ORDER BY %s',
			implode(',', $sqlFields), $tableDef->table, $where, implode(',', $orderFields));
		
		var_dump($SQL, $replaceTypes, $returnTypes);
		return $this->prepare($SQL, $replaceTypes, $returnTypes);
	}
	
	public function makeAutoQuery($autoQuery) {
		if (!($autoQuery instanceof AutoQuery))
			throw new Exception('Was not passed an AutoQuery');
		
		// Create selected fields
		$selectedFields = $this->AQFieldPrinter($autoQuery);
		if (empty($selectedFields))
			throw new Exception('No Fields Selected');
		
		// Create selected tables and joins
		$fromClauseBlocks = array();
		foreach ($autoQuery->getTableListUnique() as $AQT)
			$fromClauseBlocks[] = $this->AQFromPrinter($AQT);
		
		// Where filter
		$where = array();
		foreach ($autoQuery->getTableList() as $AQT) {
			$wherePart = $this->wherePrinter($AQT->getTableDef(), $AQT->getWhereList()->get(), '"'.$AQT->getTableAlias().'"');
			if (!empty($wherePart))
				$where[] = $wherePart;
		}
		
		// Group By and Order By
		$groups = $this->AQOrderPrinter($autoQuery, 'group');
		$orders = $this->AQOrderPrinter($autoQuery, 'order');
		
		$SQL = sprintf('SELECT * FROM (SELECT %s FROM %s%s%s%s) WHERE ROWNUM>:limitstart AND ROWNUM<=(:limitstart + :limitcount)',
			$selectedFields,
			implode(',', $fromClauseBlocks),
			(empty($where) ? '' : ' WHERE ' .implode(' AND ', $where)),
			(empty($groups) ? '' : ' GROUP BY ' .$groups),
			(empty($orders) ? '' : ' ORDER BY ' .$orders)
			);
		
		return $SQL;
	}
	
	public function prepareAutoQuery($autoQuery) {
		$SQL = $this->makeAutoQuery($autoQuery);
		return $this->prepare($SQL);
	}
	
	private function AQFieldPrinter($autoQuery) {
		$sqlFields = array();
		foreach ($autoQuery->getTableList() as $AQT) {
			foreach ($AQT->getFieldList()->get() as $fieldPart) {
				if ($fieldPart->field instanceof _DefinitionField) {
					$SQL = $fieldPart->field->SQLWithTable('"'.$AQT->getTableAlias().'"');
					if ($fieldPart->field->virtName != substr($SQL, 1, -1))
						$SQL .= ' AS "' .$fieldPart->field->virtName. '"';
				} else {
					$SQL = $fieldPart->field;
				}
				$sqlFields[] = $SQL;
			}
		}
		return implode(',', $sqlFields);
	}
	
	private function AQFromPrinter($AQT) {
		$linkDefs = $AQT->getLinkDefs();
		$block = (empty($linkDefs) ? '' : '(');
		$block .= $AQT->getTableDef()->table;
		if ($AQT->getTableDef()->table != $AQT->getTableAlias())
			$block .= ' "'.$AQT->getTableAlias().'"';
		foreach ($linkDefs as $linkDef) {
			$block .= ' '.$linkDef[0].' '; // JOIN
			$block .= $this->AQFromPrinter($linkDef[2]); // remote table and joins
			$block .= ' ON ('.$linkDef[1].')'; // ON
		}
		return $block.(empty($linkDefs) ? '' : ')');
	}
	
	private function AQOrderPrinter($autoQuery, $OrderOrGroup) {
		$fields = array();
		foreach ($autoQuery->getTableList() as $AQT) {
			$orderList = ($OrderOrGroup == 'group' ? $AQT->getGroupList() : $AQT->getOrderList());
			foreach ($orderList->get() as $orderPart) {
				if ($orderPart->field instanceof _DefinitionField)
					$tmp = $orderPart->field->SQLWithTable($AQT->getTableAlias());
				else
					$tmp = $orderPart->field;
				$fields[] = $tmp . ($orderPart->order === ORDER_ASC ? '' : ' DESC');
			}
		}
		return implode(',', $fields);
	}
	
	private function wherePrinter($tableDef, $parts, $tableAlias = false) {
		$ret = '';
		foreach ($parts as $part) {
			if ($part instanceof WherePart) {
				if (!isset($tableDef->fields[$part->field]))
					throw new Exception('Invalid field "' .$part->field. '" in where part');
				$ret .= $tableDef->fields[$part->field]->SQLWithTable($tableAlias);
				
				if (!in_array($part->sign, self::$validWhereSigns))
					throw new Exception('Invalid sign "' .$part->sign. '" in where part');
				$ret .= $part->sign;
				
				if ($part->dontEscape) {
					$ret .= $part->value;
				} else {
					if (isset($tableDef->fields[$part->value]))
						$ret .= $tableDef->fields[$part->value]->SQLWithTable($tableAlias);
					else
						$ret .= "'" .$this->escape($part->value). "'";
				}
			} elseif (is_array($part)) {
				$ret .= '(' .$this->wherePrinter($part). ')';
			} elseif ($part == WHERE_AND) {
				$ret .= ' AND ';
			} elseif ($part == WHERE_OR) {
				$ret .= ' OR ';
			} else {
				throw new Eception ('This cannot be happening! Its not possible!');
			}
		}
		return $ret;
	}
}
