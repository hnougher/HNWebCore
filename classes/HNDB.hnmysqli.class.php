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
HNDB::MDB2()->loadClass('MDB2_Driver_mysqli');

//
// PLEASE USE HNDB::factory(), HNDB::connect() or HNDB::singleton() to make an instance.
// This is phptype 'hnmysqli' once this file have been included.
//

/** HNWC Wrapper for MDB2_Driver_mysqli class */
class MDB2_Driver_hnmysqli extends MDB2_Driver_mysqli
{
	public static $runStats;
	private static $validWhereSigns = array('=','!=','<','>','<=','>=','LIKE');

	/** @see MDB2_Driver_mysqli::__construct */
	function __construct() {
		if (!isset(self::$runStats)) {
			HNDB::$runStats['mysqli'] = array(
				'total_instances' => 0,
				'current_instances' => 0,
				'query_count' => 0,
				'stmt_prep_count' => 0,
				'stmt_exec_count' => 0,
				'connect_time' => 0,
				'query_time' => 0,
				'stmt_prep_time' => 0,
				'stmt_exec_time' => 0);
			self::$runStats =& HNDB::$runStats['mysqli'];
		}
		self::$runStats['total_instances']++;
		self::$runStats['current_instances']++;
		
		parent::__construct();
	}

	/** @see MDB2_Driver_mysqli::__construct */
	function __destruct() {
		self::$runStats['current_instances']--;
		parent::__destruct();
	}

	/** @see MDB2_Driver_mysqli::getDSN */
	 function getDSN($type = 'string', $hidepw = false) {
		$dsn = parent::getDSN($type, $hidepw);
		if ($type == 'array')
			$dsn['phptype'] = 'hnmysqli';
		return $dsn;
	 }

	/** @see MDB2_Driver_mysqli::connect */
	function connect() {
		$startTime = microtime(true);
		$oldType = $this->phptype;
		$this->phptype = 'mysqli';
		$result = parent::connect();
		$this->phptype = $oldType;
		self::$runStats['connect_time'] += microtime(true) - $startTime;
		if (HNDB::MDB2()->isError($result))
			throw new Exception(DEBUG ? $result->getUserInfo() : $result->getMessage());
		return $result;
	}

	/** @see MDB2_Driver_mysqli::_doQuery */
	function &_doQuery($query, $is_manip = false, $connection = null, $database_name = null) {
		self::$runStats['query_count']++;
		$startTime = microtime(true);
		$result =& parent::_doQuery($query, $is_manip, $connection, $database_name);
		self::$runStats['query_time'] += microtime(true) - $startTime;
		return $result;
	}

	/** @see MDB2_Driver_mysqli::prepare */
	// NOTE: phptype must be overridden to make MDB2 use our Statement class.
	function &prepare($query, $types = null, $result_types = null, $lobs = array()) {
		$oldType = $this->phptype;
		$this->phptype = 'hnmysqli';
		
		self::$runStats['stmt_prep_count']++;
		$startTime = microtime(true);
		$stmt =& parent::prepare($query, $types, $result_types, $lobs);
		self::$runStats['stmt_prep_time'] += microtime(true) - $startTime;
		if (HNDB::MDB2()->isError($stmt))
			throw new Exception(DEBUG ? $stmt->getUserInfo() : $stmt->getMessage());
		
		#if ($this->phptype != 'hnoci8') throw new Exception('MDB2 phptype has been unexpectedly changed to ' .$this->phptype);
		$this->phptype = $oldType;
		return $stmt;
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
					$SQL .= ' "' .$fieldName. '"';
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
			
			$SQL = sprintf('SELECT %s FROM %s WHERE %s LIMIT 1',
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
			
			$SQL = sprintf('UPDATE %s SET %s WHERE %s LIMIT 1',
				$tableDef->table, implode(',', $sqlFields), implode(' AND ', $idFields));
			$returnTypes = MDB2_PREPARE_MANIP;
			break;
		
		case 'INSERT':
			if (empty($fields))
				throw new Exception('No fields given for INSERT. Cannot continue.');
			foreach ($tableDef->getInsertableFields() as $fieldName => $fieldDef) {
				if (($fieldsIndex = array_search($fieldName, $fields)) === false) {
					if (isset($fieldDef->default))
						$sqlFields[$fieldDef->SQLWithTable()] = $fieldDef->default;
					continue;
				}
				unset($fields[$fieldsIndex]);
				$sqlFields[$fieldDef->SQLWithTable()] = ':'.$fieldName;
			}
			if (!empty($fields))
				throw new Exception('Following fields not insertable: ' .implode(', ', $fields));
			if (empty($sqlFields))
				throw new Exception('No fields to set in INSERT. Cannot continue.');
			
			$SQL = sprintf('INSERT INTO %s (%s) VALUES (%s)',
				$tableDef->table, implode(',', array_keys($sqlFields)), implode(',', array_values($sqlFields)));
			$returnTypes = MDB2_PREPARE_MANIP;
			break;
		
		case 'DELETE':
			foreach ($tableDef->keys as $fieldName => $fieldDef) {
				$sqlFields[] = $fieldDef->SQLWithTable(). '=:' .$fieldName;
				$replaceTypes[] = $fieldDef->type;
			}
			if (empty($sqlFields))
				throw new Exception('No ID fields in DELETE. Cannot continue.');
			
			$SQL = sprintf('DELETE FROM %s WHERE %s LIMIT 1',
				$tableDef->table, implode(' AND ', $sqlFields));
			$returnTypes = MDB2_PREPARE_MANIP;
			break;
		
		default:
			throw new Exception('Unknown type');
		}
		
		#var_dump($SQL, $replaceTypes, $returnTypes);
		return $this->prepare($SQL, $replaceTypes, $returnTypes);
	}
	
	/**
	* @param $type can be one of the following:
	*   - ALL: Loads all fields in the object (for pre-loading children).
	*   - STD: Just loads the ID fields.
	*   - COUNT: Just counts the number of objects in the query result.
	*/
	public function prepareMOBQuery($tableDef, $type, $whereList = false, $orderParts = false) {
		// Fields to collect
		$sqlFields = array();
		$returnTypes = array();
		
		if ($type == 'COUNT') {
			$sqlFields[] = 'COUNT(*)';
			$returnTypes[] = 'integer';
		} else {
			$sqlFieldDefs = ($type == 'ALL' ? $tableDef->getReadableFields() : $tableDef->keys);
			foreach ($sqlFieldDefs as $fieldName => $fieldDef) {
				$SQL = $fieldDef->SQLWithTable();
				if ($fieldName != $SQL)
					$SQL .= ' AS "' .$fieldName. '"';
				$sqlFields[] = $SQL;
				$returnTypes[] = $fieldDef->type;
			}
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
		if ($type == 'COUNT') {
			$order = '';
		} else {
			$orderFields = array();
			if (is_array($orderParts)) {
				foreach ($tableDef->getReadableFields() as $fieldName => $fieldDef) {
					if (isset($orderParts[$fieldName])) {
						$orderFields[] = $fieldDef->SQLWithTable() .($orderParts[$fieldName] ? '' : ' DESC');
						unset($orderParts[$fieldName]);
					}
				}
				if (!empty($orderParts))
					throw new Exception('Order field "' .implode('","', array_keys($orderParts)). '" is not readable in object "' .$tableDef->table. '".');
			} elseif (isset($orderParts) && $orderParts == 'RAND()') {
				$orderFields[] = 'RAND()';
			} else { // Default ordering is by keys
				foreach ($tableDef->keys as $fieldName => $fieldDef)
					$orderFields[] = $fieldDef->SQLWithTable();
			}
			$order = ' ORDER BY ' .implode(',', $orderFields);
		}
		
		$SQL = sprintf('SELECT %s FROM %s %s%s',
			implode(',', $sqlFields), $tableDef->table, $where, $order);
		
		#var_dump($SQL, $returnTypes);
		return $this->prepare($SQL, $returnTypes);
	}
	
	public function makeAutoQuery($autoQuery, $hasLimit) {
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
			$wherePart = $this->wherePrinter($AQT->getTableDef(), $AQT->getWhereList()->get(), $AQT->getTableAlias());
			if (!empty($wherePart))
				$where[] = $wherePart;
		}
		
		// Group By and Order By
		$groups = $this->AQOrderPrinter($autoQuery, 'group');
		$orders = $this->AQOrderPrinter($autoQuery, 'order');
		
		$SQL = sprintf('SELECT %s FROM %s%s%s%s%s',
			$selectedFields,
			implode(',', $fromClauseBlocks),
			(empty($where) ? '' : ' WHERE ' .implode(' AND ', $where)),
			(empty($groups) ? '' : ' GROUP BY ' .$groups),
			(empty($orders) ? '' : ' ORDER BY ' .$orders),
			($hasLimit ? ' LIMIT :limitstart,:limitcount' : '')
			);
		
		return $SQL;
	}
	
	public function prepareAutoQuery($autoQuery, $hasLimit) {
		$SQL = $this->makeAutoQuery($autoQuery, $hasLimit);
		return $this->prepare($SQL);
	}
	
	private function AQFieldPrinter($autoQuery) {
		$sqlFields = array();
		foreach ($autoQuery->getTableList() as $AQT) {
			foreach ($AQT->getFieldList()->get() as $fieldPart) {
				if ($fieldPart->field instanceof _DefinitionField) {
					$SQL = $fieldPart->field->SQLWithTable($AQT->getTableAlias());
					if ($fieldPart->field->virtName != substr($SQL, 1, -1))
						$SQL .= ' "' .$fieldPart->field->virtName. '"';
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
				if ($part->dontEscapeField) {
					$ret .= $part->field;
				} else {
					if (!isset($tableDef->fields[$part->field]))
						throw new Exception('Invalid field "' .$part->field. '" in where part');
					$ret .= $tableDef->fields[$part->field]->SQLWithTable($tableAlias);
				}
				
				if (!in_array($part->sign, self::$validWhereSigns))
					throw new Exception('Invalid sign "' .$part->sign. '" in where part');
				$ret .= ' ' .$part->sign. ' ';
				
				if ($part->dontEscapeValue) {
					$ret .= $part->value;
				} else {
					if (isset($tableDef->fields[$part->value]))
						$ret .= $tableDef->fields[$part->value]->SQLWithTable($tableAlias);
					else
						$ret .= $this->quote($part->value);
				}
			} elseif (is_array($part)) {
				$ret .= '(' .$this->wherePrinter($part). ')';
			} elseif ($part == WHERE_AND) {
				$ret .= ' AND ';
			} elseif ($part == WHERE_OR) {
				$ret .= ' OR ';
			} else {
				throw new Exception ('This cannot be happening! Its not possible!');
			}
		}
		return $ret;
	}
}

class MDB2_Statement_hnmysqli extends MDB2_Statement_mysqli
{
	/** @see MDB2_Statement_mysqli::_execute */
	function &_execute($result_class = true, $result_wrap_class = false) {
		MDB2_Driver_hnmysqli::$runStats['stmt_exec_count']++;
		$startTime = microtime(true);
		#$result =& parent::_execute($result_class, $result_wrap_class);
		$result =& self::_executeX($result_class, $result_wrap_class);
		if (HNDB::MDB2()->isError($result)) {
			var_dump($result->getUserInfo());
			throw new Exception(DEBUG ? $result->getUserInfo() : $result->getMessage());
		}
		MDB2_Driver_hnmysqli::$runStats['stmt_exec_time'] += microtime(true) - $startTime;
		return $result;
	}
	
	/**
	* Drop in replacement of parent::_execute() to fix http://pear.php.net/bugs/bug.php?id=17207
	*/
	function &_executeX($result_class = true, $result_wrap_class = false)
    {
        if (is_null($this->statement)) {
            $result =& parent::_execute($result_class, $result_wrap_class);
            return $result;
        }
        $this->db->last_query = $this->query;
        $this->db->debug($this->query, 'execute', array('is_manip' => $this->is_manip, 'when' => 'pre', 'parameters' => $this->values));
        if ($this->db->getOption('disable_query')) {
            $result = $this->is_manip ? 0 : null;
            return $result;
        }

        $connection = $this->db->getConnection();
        if (HNDB::MDB2()->isError($connection)) {
            return $connection;
        }

        if (!is_object($this->statement)) {
            $query = 'EXECUTE '.$this->statement;
        }
        if (!empty($this->positions)) {
            $parameters = array(0 => $this->statement, 1 => '');
            $lobs = array();
            $i = 0;
            foreach ($this->positions as $parameter) {
                if (!array_key_exists($parameter, $this->values)) {
                    return $this->db->raiseError(MDB2_ERROR_NOT_FOUND, null, null,
                        'Unable to bind to missing placeholder: '.$parameter, __FUNCTION__);
                }
                $value = $this->values[$parameter];
                $type = array_key_exists($parameter, $this->types) ? $this->types[$parameter] : null;
                if (!is_object($this->statement)) {
                    if (is_resource($value) || $type == 'clob' || $type == 'blob') {
                        if (!is_resource($value) && preg_match('/^(\w+:\/\/)(.*)$/', $value, $match)) {
                            if ($match[1] == 'file://') {
                                $value = $match[2];
                            }
                            $value = @fopen($value, 'r');
                            $close = true;
                        }
                        if (is_resource($value)) {
                            $data = '';
                            while (!@feof($value)) {
                                $data.= @fread($value, $this->db->options['lob_buffer_length']);
                            }
                            if ($close) {
                                @fclose($value);
                            }
                            $value = $data;
                        }
                    }
                    $quoted = $this->db->quote($value, $type);
                    if (HNDB::MDB2()->isError($quoted)) {
                        return $quoted;
                    }
                    $param_query = 'SET @'.$parameter.' = '.$quoted;
                    $result = $this->db->_doQuery($param_query, true, $connection);
                    if (HNDB::MDB2()->isError($result)) {
                        return $result;
                    }
                } else {
                    if (is_resource($value) || $type == 'clob' || $type == 'blob') {
                        $parameters[] = null;
                        $parameters[1].= 'b';
                        $lobs[$i] = $parameter;
                    } else {
                        $parameters[] = $this->db->quote($value, $type, false);
                        $parameters[1].= $this->db->datatype->mapPrepareDatatype($type);
                    }
                    ++$i;
                }
            }

            if (!is_object($this->statement)) {
                $query.= ' USING @'.implode(', @', array_values($this->positions));
            } else {
                $par_fix = array();
                foreach ($parameters as &$v)
                    $par_fix[] =& $v;
                $result = @call_user_func_array('mysqli_stmt_bind_param', $par_fix);
                if ($result === false) {
                    $err =& $this->db->raiseError(null, null, null,
                        'Unable to bind parameters', __FUNCTION__);
                    return $err;
                }

                foreach ($lobs as $i => $parameter) {
                    $value = $this->values[$parameter];
                    $close = false;
                    if (!is_resource($value)) {
                        $close = true;
                        if (preg_match('/^(\w+:\/\/)(.*)$/', $value, $match)) {
                            if ($match[1] == 'file://') {
                                $value = $match[2];
                            }
                            $value = @fopen($value, 'r');
                        } else {
                            $fp = @tmpfile();
                            @fwrite($fp, $value);
                            @rewind($fp);
                            $value = $fp;
                        }
                    }
                    while (!@feof($value)) {
                        $data = @fread($value, $this->db->options['lob_buffer_length']);
                        @mysqli_stmt_send_long_data($this->statement, $i, $data);
                    }
                    if ($close) {
                        @fclose($value);
                    }
                }
            }
        }

        if (!is_object($this->statement)) {
            $result = $this->db->_doQuery($query, $this->is_manip, $connection);
            if (HNDB::MDB2()->isError($result)) {
                return $result;
            }

            if ($this->is_manip) {
                $affected_rows = $this->db->_affectedRows($connection, $result);
                return $affected_rows;
            }

            $result =& $this->db->_wrapResult($result, $this->result_types,
                $result_class, $result_wrap_class, $this->limit, $this->offset);
        } else {
            if (!@mysqli_stmt_execute($this->statement)) {
                $err =& $this->db->raiseError(null, null, null,
                    'Unable to execute statement', __FUNCTION__);
                return $err;
            }

            if ($this->is_manip) {
                $affected_rows = @mysqli_stmt_affected_rows($this->statement);
                return $affected_rows;
            }

            if ($this->db->options['result_buffering']) {
                @mysqli_stmt_store_result($this->statement);
            }

            $result =& $this->db->_wrapResult($this->statement, $this->result_types,
                $result_class, $result_wrap_class, $this->limit, $this->offset);
        }

        $this->db->debug($this->query, 'execute', array('is_manip' => $this->is_manip, 'when' => 'post', 'result' => $result));
        return $result;
    }
}

HNDB::MDB2()->loadClass('MDB2_Driver_Datatype_mysqli');
/** phptyped class for missing some internal prepare errors */
class MDB2_Driver_Datatype_hnmysqli extends MDB2_Driver_Datatype_mysqli {}
