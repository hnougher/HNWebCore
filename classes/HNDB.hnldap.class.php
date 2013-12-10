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
HNDB::MDB2()->loadClass('MDB2_Driver_Datatype_Common');

//
// PLEASE USE HNDB::factory(), HNDB::connect() or HNDB::singleton() to make an instance.
// This is phptype 'hnldap' once this file have been included.
//

/** HNWC Wrapper for MDB2_Driver_ldap class WHICH IS LATER IN THIS FILE!! */
class MDB2_Driver_hnldap extends MDB2_Driver_ldap
{
    public static $runStats;

    /** @see MDB2_Driver_mysqli::__construct */
    function __construct() {
        if (!isset(self::$runStats)) {
            HNDB::$runStats['ldap'] = array(
                'total_instances' => 0,
                'current_instances' => 0,
                'query_count' => 0,
                'connect_time' => 0,
                'query_time' => 0);
            self::$runStats =& HNDB::$runStats['ldap'];
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
			$dsn['phptype'] = 'hnldap';
		return $dsn;
	 }

    /** @see MDB2_Driver_mysqli::connect */
    function connect() {
        $startTime = microtime(true);
		$result = parent::connect();
        self::$runStats['connect_time'] += microtime(true) - $startTime;
        if (HNDB::MDB2()->isError($result)) {
            require_once 'PEAR/Exception.php';
            throw new PEAR_Exception(DEBUG ? $result->getUserInfo() : $result->getMessage(), $result);
        }
        return $result;
	}

    /** @see MDB2_Driver_mysqli::_doQuery */
    function &_doQuery($query, $is_manip = false, $connection = null, $database_name = null) {
        self::$runStats['query_count']++;
        $startTime = microtime(true);
		$result =& parent::_doQuery($query, $is_manip, $connection, $database_name);
        self::$runStats['query_time'] += microtime(true) - $startTime;
        if (HNDB::MDB2()->isError($result)) {
            require_once 'PEAR/Exception.php';
            throw new PEAR_Exception(DEBUG ? $result->getUserInfo() : $result->getMessage(), $result);
        }
        return $result;
	}

    /**
    * This function prepares SQL statements using the table definitions created by OBJs.
    * 
    * @param $type can be DELETE, INSERT, SELECT or UPDATE.
    */
    public function prepareOBJQuery($type, $tableDef) {
        if (!($tableDef instanceOf _DefinitionTable))
            throw new Exception('The table definition passed does not inherit _DefinitionTable');
        $replaceTypes = array();
        $returnTypes = array();
        
        if ($type == 'SELECT') {
            $attributes = array();
            foreach ($tableDef->getReadableFields() as $fieldName => $fieldDef) {
                $attributes[] = $fieldDef->SQLWithTable();
                $returnTypes[] = $fieldDef->type;
            }
            
            $idFields = array();
            foreach ($tableDef->keys as $fieldName => $fieldDef) {
                $idFields[] = $fieldDef->SQLWithTable(). '=:' .$fieldName;
                $replaceTypes[] = $fieldDef->type;
            }
            
            // Make the fake query for this LDAP fakery
            $query = 'SELECT `' .implode('`,`', $attributes). '` FROM `' .$tableDef->table. '` WHERE `(' .implode(';', $idFields). ')`';
            return $this->prepare($query, $replaceTypes, $returnTypes);
        }
        
        #var_dump($filter, $replaceTypes);
        throw new Exception('Not implemented');
    }
}





//////////////////////////// IMPLEMENATION OF MD2 LDAP ////////////////////////////////////

class MDB2_Driver_ldap extends MDB2_Driver_Common
{
    var $sql_comments = array();
    var $wildcards = array('*');

    function __construct() {
        parent::__construct();

        $this->phptype = 'ldap';
        $this->dbsyntax = 'ldap';

        $this->options['default_table_type'] = '';
        $this->options['multi_query'] = false;
        $this->options['result_buffering'] = false;
    }

    function escape($text, $escape_wildcards = false) {
        // see:
        // RFC2254
        // http://msdn.microsoft.com/en-us/library/ms675768(VS.85).aspx
        // http://www-03.ibm.com/systems/i/software/ldap/underdn.html       
           
        if  ($for_dn)
            $metaChars = array(',','=', '+', '<','>',';', '\\', '"', '#');
        else
            $metaChars = array('*', '(', ')', '\\', chr(0));

        $quotedMetaChars = array();
        foreach ($metaChars as $key => $value)
            $quotedMetaChars[$key] = '\\'.str_pad(dechex(ord($value)), 2, '0');
        $text = str_replace($metaChars, $quotedMetaChars, $text); //replace them
        return $text;
    }

    function connect() {
        if (is_object($this->connection)) {
            if (count(array_diff($this->connected_dsn, $this->dsn)) == 0) {
                return MDB2_OK;
            }
            $this->connection = 0;
        }
        
        $connection = ldap_connect($this->dsn['hostspec'], empty($this->dsn['port']) ? 389 : $this->dsn['port']);
        if ($connection == false) {
            return $this->raiseError(MDB2_ERROR_CONNECT_FAILED, null, null,
                'Cannot Connect to LDAP Host: ' .$this->dsn['hostspec'], __FUNCTION__);
        }
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, $this->dsn['version']);
        
        ob_start(); // Curse the warning which cannot be suppressed
        if (!ldap_bind($connection, $this->dsn['username'], $this->dsn['password'])) {
            ob_end_clean();
            return $this->raiseError(MDB2_ERROR_CONNECT_FAILED, null, null,
                'Cannot Bind to LDAP Host: ' .$this->dsn['hostspec'], __FUNCTION__);
        }
        ob_end_clean();
        
        $this->connection = $connection;
        $this->connected_dsn = $this->dsn;
        $this->connected_database_name = $this->database_name;
        
        return MDB2_OK;
    }
    
    function disconnect($force = true) {
        if ($this->connection != false)
            ldap_close($this->connection);
        return parent::disconnect($force);
    }

    function &_doQuery($query, $is_manip = false, $connection = null, $database_name = null) {
        $this->last_query = $query;
        $result = $this->debug($query, 'query', array('is_manip' => $is_manip, 'when' => 'pre'));
        if ($result) {
            if (HNDB::MDB2()->isError($result)) {
                return $result;
            }
            $query = $result;
        }
        
        // Split fake query into our bits
        if (!preg_match('/^SELECT `((?:[^`]*(?:`,`)?)+)` FROM `([^`]+)` WHERE `([^`]*)`$/', $query, $matches)) {
            $err = $this->raiseError(null, null, null,
                'Could not split the query string: '.$query, __FUNCTION__);
            return $err;
        }
        $attributes = explode('`,`', $matches[1]);
        $base_dn = $matches[2];
        $filter = $matches[3];
        
#		var_dump($query);
        $result = ldap_search($connection, $base_dn, $filter, $attributes);

        $this->debug($query, 'query', array('is_manip' => $is_manip, 'when' => 'post', 'result' => $result));
        return $result;
    }

    // This is just to disable quoting of fields which LDAP does not need (AFAIK)
    function quote($value, $type = null, $quote = true, $escape_wildcards = false) {
        return $value;
    }
}

// Portions based of MDB2 mysqli result classes
class MDB2_Result_ldap extends MDB2_Result_Common
{
    var $result_entry_identifier = false;

    function __construct(&$db, &$result, $limit = 0, $offset = 0) {
        parent::__construct($db, $result, $limit, $offset);
    }

    function &fetchRow($fetchmode = MDB2_FETCHMODE_DEFAULT, $rownum = null) {
        if (!is_null($rownum)) {
            $seek = $this->seek($rownum);
            if (HNDB::MDB2()->isError($seek))
                return $seek;
        }
        
        if ($this->rownum == -1)
            $this->result_entry_identifier = ldap_first_entry($this->db->connection, $this->result);
        else
            $this->result_entry_identifier = ldap_next_entry($this->db->connection, $this->result_entry_identifier);
        
        $row = array();
        $attrs = ldap_get_attributes($this->db->connection, $this->result_entry_identifier);
        for ($i = 0; $i < $attrs['count']; $i++) {
            $newkey = ($fetchmode & MDB2_FETCHMODE_ASSOC ? $attrs[$i] : $i);
            $values = $attrs[$attrs[$i]];
            unset($values['count']);
			/*if (count($values) == 0)
				$value = '';
			elseif (count($values) == 1)
				$value = $value[0];*/
            $row[$attrs[$i]] = $values;
        }
        
        if (empty($row)) {
            if ($this->result === false) {
                $err =& $this->db->raiseError(MDB2_ERROR_NEED_MORE_DATA, null, null,
                    'resultset has already been freed', __FUNCTION__);
                return $err;
            }
            $null = null;
            return $null;
        }
        $mode = $this->db->options['portability'] & MDB2_PORTABILITY_EMPTY_TO_NULL;
        if ($mode) {
            $this->db->_fixResultArrayValues($row, $mode);
        }
        if (!empty($this->types)) {
            $row = $this->db->datatype->convertResultRow($this->types, $row, false);
        }
        if (!empty($this->values)) {
            $this->_assignBindColumns($row);
        }
        if ($fetchmode === MDB2_FETCHMODE_OBJECT) {
            $object_class = $this->db->options['fetch_class'];
            if ($object_class == 'stdClass') {
                $row = (object) $row;
            } else {
                $row = new $object_class($row);
            }
        }
        ++$this->rownum;
        return $row;
    }

    function numRows() {
        return ldap_count_entries($this->db->connection, $this->result);
    }

    function free() {
        if (is_object($this->result) && $this->db->connection) {
            $free = ldap_free_result($this->result);
            if ($free === false) {
                return $this->db->raiseError(null, null, null,
                    'Could not free result', __FUNCTION__);
            }
        }
        $this->result = false;
        parent::free();
    }
}

class MDB2_Driver_Datatype_ldap extends MDB2_Driver_Datatype_Common
{
    var $valid_default_values = array(
        'text' => '',
        'ldaparray' => '',
    );
    
    function _baseConvertResult($value, $type, $rtrim = true)
    {
        switch ($type) {
        case 'text':
            if ($rtrim)
                $value = rtrim($value);
            return $value;
        case 'ldaparray':
			if (empty($value)) return NULL;
			if (count($value) == 1) return $value[0];
            return (array) $value;
        }

        $db =& $this->getDBInstance();
        if (HNDB::MDB2()->isError($db)) {
            return $db;
        }

        return $db->raiseError(MDB2_ERROR_INVALID, null, null,
            'attempt to convert result value to an unknown type :' . $type, __FUNCTION__);
    }
}

class MDB2_Statement_ldap extends MDB2_Statement_Common {}
