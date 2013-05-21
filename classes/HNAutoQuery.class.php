<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Auto Query Class
* 
* This is designed to be able to create the teadiously large sql
* queries that can be associated with a project.
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

/**
* This is a class that creates some quite large queries that would take a
* programmer quite a while to create themselves.
* 
* @todo Add in a few more of the SQL parts to give the programmer even more
* 		freedom when using this method of creating queries.
*/
class HNAutoQuery
{

	/**
	* Contains the root table that the entire auto query is based off.
	* @var HNAutoQuery_Linker
	*/
	private $baseTable = null;

	/**
	* Contains the custom SQL if this is just a filler thingy.
	* @var string
	*/
	private $sql;

	/**
	 * Contains the unique names of tables used in this query.
	 */
	private $uniqueTableNames = array();

	/**
	 * Contains groupings for this query.
	 */
	private $group = array();

	/**
	 * Contains orderings for this query.
	 */
	private $order = array();

	/**
	*
	*/
	public function __construct( $sql = null )
	{
		$this->sql = $sql;
	}

	/**
	* Used to add the first table to this query starting the recursive
	* nature of this query building.
	* 
	* @param string $table The table name to be used as root.
	* @param string $unique The unique string that will be used in the
	* 		actual sql query without escaping.
	* @return HNAutoQuery_Linker The first linker object that can be used
	* 		to add fields and more tables as well as operations like order,
	* 		group and selection restriction.
	*/
	public function new_table($table, $unique) {
		$this->check_table_unique($unique);
		$this->baseTable = new HNAutoQuery_Linker((string) $table, $unique, $this);
		return $this->baseTable;
	}

	/**
	* This function runs the query created by these classes and returns a
	* mysqli query result object.
	* 
	* @param integer $count How many results for the result to contain at most.
	* @param integer $page Which page to start at.
	* 		(eg: page 3 with count 30 means rows 61-90 are selected)
	* @return mysqli_result
	*/
	public function get_result( $count = 30, $page = 1 )
	{
		$result = HNMySQL::query( $this->get_sql( $count, $page ) );
		return $result;
	}

	/**
	* This function puts together the final sql statement that can be
	* used to do the database search.
	* 
	* @param integer $count How many results for the result to contain at most.
	* @param integer $page Which page to start at.
	* 		(eg: page 3 with count 30 means rows 61-90 are selected)
	* @return string The SQL statement.
	*/
	public function get_sql( $count = 30, $page = 1 )
	{
		if( $this->baseTable == null )
			return $this->sql;

		$out = 'SELECT ';
		$out .= substr( $this->baseTable->print_fields(), 0, -2 );
		$out .= ' FROM ';
		$out .= $this->baseTable->print_from();
		$where = $this->baseTable->print_where();
		if( !empty( $where ) )
			$out .= 'WHERE ' .substr( $where, 0, -4 );

		// Group
		#$out .= $this->baseTable->print_group();
		if( count( $this->group ) )
		{
			ksort( $this->group );
			$out .= 'GROUP BY ';
			foreach( $this->group AS $group )
				$out .= $group. ', ';
			$out = substr( $out, 0, -2 ). ' ';
		}

		// Order
		#$out .= $this->baseTable->print_order();
		if( count( $this->order ) )
		{
			ksort( $this->order );
			$out .= 'ORDER BY ';
			foreach( $this->order AS $order )
				$out .= $order. ', ';
			$out = substr( $out, 0, -2 ). ' ';
		}

		// Set the limit
		if( $count != PHP_INT_MAX )
		{
			if( $page < 1 )
				$page = 1;
			if( $count < 1 )
				$count = 1;
			$out .= 'LIMIT ' .( ( $page - 1 ) * $count ). ',' .$count. ' ';
		}

		return $out;
	}


	/**
	* Add a new field to go in the GROUP BY clause of the SQL query.
	* 
	* @param string $groupPart The sql used for the group condition.
	* @param integer $groupIndex The Location of in the list to place the grouping.
	* 		If this is not set then the grouping will be appended to the end on the list
	* 		instead of replacing an existing index.
	* 		NOTE: this function will replace anything currently in the selected index!
	* 			It also includes any other tables the are using the grouping section.
	*/
	public function new_group( $groupPart, $groupIndex = false )
	{
		if( $groupIndex === false )
			$this->group[] = $groupPart;
		else
			$this->group[ $groupIndex ] = $groupPart;
	}

	/**
	* Add an ordering field to the ORDER BY clause of the SQL query.
	* 
	* @param string $orderPart The sql for this order by condition.
	* @param integer $orderIndex The Location of in the list to place the ordering.
	* 		If this is not set then the ordering will be appended to the end on the list
	* 		instead of replacing an existing index.
	* 		NOTE: this function will replace anything currently in the selected index!
	* 			It also includes any other tables the are using the ordering section.
	*/
	public function new_order( $orderPart, $orderIndex = false )
	{
		if( $orderIndex === false )
			$this->order[] = $orderPart;
		else
			$this->order[ $orderIndex ] = $orderPart;
	}

	/**
	 * This function checks to see if the given new unique ID
	 * is really unique and saves it as being used if it is
	 * unique.
	 *
	 * @param string $unique The new unique name for the table to check.
	 * @return boolean True is it is valid, False otherwise.
	 */
	public function check_table_unique( $unique )
	{
		if( in_array( $unique, $this->uniqueTableNames ) )
			return false;
		$this->uniqueTableNames[] = $unique;
		return true;
	}

	/**
	 * Clean up all links in this object ready for removal.
	 * Should be called before unsetting the last reference in the main script.
	 */
	public function clean()
	{
		$this->baseTable->clean();
		$this->baseTable = null;
	}
}


/**
* This class is used by {@link HNAutoQuery} to do the recursive creation
* of the query while giving the programmer a quite powerful interface to
* work from.
*
* @todo Make these classes able to be reused. Currently the static variables
* 		in the {@link HNAutoQuery_Linker} class will join al queries done into
* 		just one for some parts.
*/
class HNAutoQuery_Linker
{
	//private static $usedUniques = array();
	//private static $group = array();
	//private static $order = array();

	/**
	* This function simply resets the static properties of this class.
	*
	* WARNING: This should ONLY be done when the previous users of this object are
	* finished being used. It would be recommened to unset the old users of this
	* before calling this.
	*
	* NOTE: This is not needed any more.
	*/
	public static function clear_static()
	{
		//self::$usedUniques = array();
		//self::$group = array();
		//self::$order = array();
	}

	/*############## Member Stuff #################*/

	private $autoQuery;
	private $fieldList;

	private $table;
	private $unique;
	private $fields = array();
	private $where = array();
	private $subtables = array();


	/**
	* This contructor saves the table name that this instance is to be
	* associated with.
	* @todo Make it check that the table name is valid and throw an exception
	* 		if it is not valid.
	*
	* @param string $table The table name to be used for this instance.
	* @param string $unique The unique string that will be used in the
	* 		actual sql query without escaping.
	*/
	public function __construct($table, $unique, $aq) {
		$this->fieldList = FieldList::loadFieldList($table);
		$this->table = $table;
		$this->unique = $unique;
		$this->autoQuery = $aq;
	}

	/**
	* This function is used to check that the field is both valid and readable.
	* If the field is not valid or not readable then this function throws an
	* exception.
	* 
	* @param string $field The field name to be checked.
	* @return A field from FieldList::getReadable()
	* @throws HNAutoException
	*/
	private function get_field_props($field) {
		// Check that its a valid field
		$readableFields = $this->fieldList->getReadable();
		if (!isset($readableFields[$field])) {
			// Oh Nose! Its not a readable table!
			throw new HNAutoException('The chosen field (' .$field. ') is not readable or does not exist', HNAutoException::FIELD_NOT_READABLE);
		}
		return $readableFields[$field];
	}

	/**
	* Adds a new field to the set of fields to return when the query is run.
	* It also has a custom mode that can be used in place of a direct
	* field request. Eg: COUNT( unique.`field` ) AS "field_count"
	*
	* @param string $field The field name or the custom replacement.
	* @param boolean $custom If the field parameter was a custom replacement
	* 		then you must set this parameter to true to disable strict checks.
	* @throws HNAutoException
	*/
	public function new_field( $field, $custom = false )
	{
		if( !$custom )
		{
			$props = $this->get_field_props( $field );

			if( $props['type'] == 'rev_object' )
			{
				// Oh Nose! Its not a normal field!
				throw new HNAutoException( 'The chosen field (' .$field. ') is not a standard type', HNAutoException::FIELD_IS_LINK );
			}

			$field = '`' .$this->unique. '`.`' .$field. '` AS "' .$this->unique. '_' .$field. '"';
		}

		$this->fields[] = $field;
	}

	/**
	* Adds a new restriction to the SQL query.
	* 
	* @param string $field The field the restriction is to relate to.
	* @param string $sign The sign the restriction will use (ie: =, <=, >=, LIKE).
	* @param string $compare Either a string or a SQL code segment.
	* @param boolean $dontEscape If $compare is an SQL code segment then set this to true.
	*/
	public function new_where( $field, $sign, $compare, $dontEscape = false )
	{
		$props = $this->get_field_props( $field );

		if( !$dontEscape )
			$compare = '"' .HNMySQL::escape( $compare ). '"';
		$where = '`' .$this->unique. '`.`' .$field. '` ' .$sign. ' ' .$compare;
		$this->where[] = $where;
	}

	/**
	* Adds a new section to the SQL query in raw format.
	* 
	* @param string $sql The SQL to AND into the WHERE section of the final query produced.
	*/
	public function new_where_raw($sql) {
		$this->where[] = $sql;
	}

	/**
	* Add a new field to go in the GROUP BY clause of the SQL query.
	* 
	* @param string $groupField The field to group by.
	* @param integer $groupIndex The Location of in the list to place the grouping.
	* 		If this is not set then the grouping will be appended to the end on the list
	* 		instead of replacing an existing index.
	* 		NOTE: this function will replace anything currently in the selected index!
	* 			It also includes any other tables the are using the grouping section.
	* @param boolean $rawField Use the $groupField as-is in the grouping location. Added 091214.
	*/
	public function new_group( $groupField, $groupIndex = false, $rawField = false )
	{
		if( $rawField === false )
		{
			// This checks that the field is valid and readable. See get_field_props().
			$props = $this->get_field_props( $groupField );
			$groupPart = '`' .$this->unique. '`.`' .$groupField. '`';
		}
		else
			$groupPart = $groupField;

		$this->autoQuery->new_group( $groupPart, $groupIndex );
		/*if( $groupIndex === false )
			self::$group[] = $groupPart;
		else
			self::$group[ $groupIndex ] = $groupPart;*/
	}

	/**
	* Add an ordering field to the ORDER BY clause of the SQL query.
	* 
	* @param string $orderField The field to use in the ordering.
	* @param boolean $orderDesc If set to true the order will be in descending order,
	* 		otherwise it will be in ascending order. Default is false.
	* @param integer $orderIndex The Location of in the list to place the ordering.
	* 		If this is not set then the ordering will be appended to the end on the list
	* 		instead of replacing an existing index.
	* 		NOTE: this function will replace anything currently in the selected index!
	* 			It also includes any other tables the are using the ordering section.
	* @param boolean $rawField Use the $orderField as-is in the order location. Added 091214.
	*/
	public function new_order( $orderField, $orderDesc = false, $orderIndex = false, $rawField = false )
	{
		if( $rawField === false )
		{
			// This checks that the field is valid and readable. See get_field_props().
			$props = $this->get_field_props( $orderField );
			$orderPart = '`' .$this->unique. '`.`' .$orderField. '`';
		}
		else
			$orderPart = $orderField;

		$orderPart .= $orderDesc ? ' DESC' : ' ASC';
		$this->autoQuery->new_order( $orderPart, $orderIndex );
		/*if( $orderIndex === false )
			self::$order[] = $orderPart;
		else
			self::$order[ $orderIndex ] = $orderPart;*/
	}

	/**
	* Adds another table to the query that relates to this one in some way.
	* 
	* @param string $field The field name that is a link to another table.
	* 		It is to be either a an object or subtable in the dbstruct.
	* @param string $unique A unique name for this table to use. It should
	* 		be valid name that contains only letters and numbers (no symbols
	* 		or white space).
	* @throws HNAutoException
	*/
	public function new_table( $field, $unique )
	{
		$props = $this->get_field_props( $field );

		// Check that its a valid link
		if( $props['type'] == 'object' )
		{
			$fields = array(
				$field,
				!empty( $props['for'] ) ? $props['for'] : $field
				);
			$type = 'obj';
		}
		elseif( $props['type'] == 'rev_object' )
		{
			$fields = $this->fieldList->getIdField();
			$type = 'rev';
		}
		else
		{
			// Oh Nose! Its not a linkable table!
			throw new HNAutoException( 'The chosen field (' .$field. ') is not a linkable table', HNAutoException::FIELD_NOT_LINK );
		}
		$table = $props['table'];

		// Check that $unique is actually unique
		if( !$this->autoQuery->check_table_unique( $unique ) )
		{
			// Oh Nose! Its already here!
			throw new HNAutoException( 'The value that is supposed to be unique (' .$unique. ') has already been used', HNAutoException::NOT_UNIQUE );
		}

		// Create the new linker
		$newLinker = new HNAutoQuery_Linker((string) $table, $unique, $this->autoQuery);
		$this->subtables[] = array(
			$newLinker,
			$type,
			$props,
			$unique
			);
		return $newLinker;
	}

	/**
	* Prints the fields section for the final query based on this tables data.
	* 
	* @return string The field list.
	*/
	public function print_fields()
	{
		// unique.`myfield` AS "unique_myfield"
		$out = '';
		foreach( $this->fields AS $field )
			$out .= $field. ', ';
		foreach( $this->subtables AS $subtable )
			$out .= $subtable[0]->print_fields();
		return $out;
	}

	/**
	* Prints the from section for the final query based on this tables data.
	*
	* @return string The from list.
	*/
	public function print_from()
	{
		// `table01` AS unique
		$out = '`' .$this->table. '` AS ' .$this->unique. ' ';
		foreach( $this->subtables AS $subtable )
		{
			if( $subtable[1] == 'obj' )
			{
				$f1 = $subtable[2]['name'];
				$f2 = !empty( $subtable[2]['field'] ) ? $subtable[2]['field'] : $f1;
			}
			else
			{
				$f1 = $this->fieldList->getIdField();
				$f2 = !empty( $subtable[2]['field'] ) ? $subtable[2]['field'] : $f1;
			}
			$out = '(' .$out. ' LEFT JOIN ' .$subtable[0]->print_from(). 'ON `' .$this->unique. '`.`' .$f1. '` = `' .$subtable[3]. '`.`' .$f2. '`) ';
		}
		return $out;
	}

	/**
	* Prints the where section for the final query based on this tables data.
	*
	* @return string The where list.
	* @uses $where
	* @uses $subtables
	* @uses HNAutoQuery_Linker::print_where()
	*/
	public function print_where()
	{
		$out = '';
		foreach( $this->where AS $where )
			$out .= $where. ' AND ';
		foreach( $this->subtables AS $subtable )
			$out .= $subtable[0]->print_where();
		return $out;
	}

	/**
	* Prints the group section for the final query based on all table data.
	*
	* @return string The group list.
	* @uses $group
	*/
	public function print_group()
	{
		// unique.`myfield`
		if( !count( self::$group ) )
			return '';

		ksort( self::$group );
		$out = 'GROUP BY ';
		foreach( self::$group AS $group )
			$out .= $group. ', ';
		$out = substr( $out, 0, -2 ). ' ';
		return $out;
	}

	/**
	* Prints the order section for the final query based on all table data.
	*
	* @return string The order list.
	* @uses $order
	*/
	public function print_order()
	{
		// unique.`myfield` ASC
		if( !count( self::$order ) )
			return '';

		ksort( self::$order );
		$out = 'ORDER BY ';
		foreach( self::$order AS $order )
			$out .= $order. ', ';
		$out = substr( $out, 0, -2 ). ' ';
		return $out;
	}

	/**
	 * Clean up all links in this object ready for removal.
	 * Should be called by the autoQuery or parent when it is cleaned.
	 */
	public function clean()
	{
		foreach( $this->subtables AS $table )
			$table->clean();
		$this->subtables = array();
		$this->autoQuery = null;
	}
}


/**
* This class is used for all the exceptions that the HNAutoQuery and HNAutoQuery_Linker
* classes throw.
*/
class HNAutoException extends Exception
{
	const FIELD_IS_LINK = 1;
	const FIELD_NOT_LINK = 2;
	const FIELD_NOT_READABLE = 3;
	const NOT_UNIQUE = 4;
}
