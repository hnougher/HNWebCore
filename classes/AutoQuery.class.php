<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */

class AutoQuery
{
	protected $connection;
	
	/* A set of AutoQueryTable in order of linking */
	protected $tableList = array();
	
	/* A set of AutoQueryTable that are bases of join trees */
	protected $tableListUnique = array();
	
	public function __construct() {
	}
	
	/// Helper function
	public function _CreateAutoQueryTable($AQ, $object, $alias = false) {
		return new AutoQueryTable($AQ, $object, $alias);
	}
	
	/// Adds first table to the query
	public function table($object, $alias = false, $_IsJoined = false) {
		if (empty($alias))
			$alias = $object;
		if (isset($this->tableList[$alias]))
			throw new Exception('There cannot be two tables using the same alias in AutoQuery');
		$AQT = $this->_CreateAutoQueryTable($this, $object, $alias);
		if (empty($this->connection))
			$this->connection = $AQT->getTableDef()->connection;
		elseif ($AQT->getTableDef()->connection != $this->connection)
			throw new Exception('AutoQuery cannot overcome connection boundaries');
		$this->tableList[$alias] = $AQT;
		if (!$_IsJoined)
			$this->tableListUnique[$alias] = $AQT;
		return $AQT;
	}
	
	/// @TODO remove
	public function addTable($object, $alias = false, $_IsJoined = false) {
		return $this->table($object, $alias, $_IsJoined);
	}
	
	public function getTableList() {
		return $this->tableList;
	}
	public function getTableListUnique() {
		return $this->tableListUnique;
	}
	
	public function getSQL($noLimit = false) {
		return HNDB::_DEFAULT()->makeAutoQuery($this, !$noLimit);
	}
	
	public function getAJAXCode($noLimit = false, $paramTypes = array()) {
		$DB =& HNDB::singleton(constant($this->connection));
		$sql = $DB->makeAutoQuery($this, !$noLimit);
		if (!$noLimit) {
			$paramTypes[] = 'integer';
			$paramTypes[] = 'integer';
		}
		return StoreAJAXQuery(substr($this->connection,5), $sql, $paramTypes);
	}
	
	public function prepareSQL($noLimit = false) {
		$DB =& HNDB::singleton(constant($this->connection));
		return $DB->prepareAutoQuery($this, !$noLimit);
	}
}

class AutoQueryTable
{
	// Link back to parent AutoQuery
	protected $AQ;
	
	// Related tableDef
	protected $tableDef;
	
	// Table Alias
	protected $tableAlias;
	
	// Link Definitions
	// array(array($joinType, $onClause, $remoteAQT), ...)
	protected $linkDefs = array();
	
	// A FieldList object
	protected $fieldList;
	
	// A WhereList object
	protected $whereList;
	
	// An OrderList object
	protected $orderList;
	
	// An OrderList object
	protected $groupList;
	
	public function __construct($AQ, $object, $alias = false) {
		if (!($AQ instanceof AutoQuery))
			throw new Exception('AQ is not an AutoQuery object');
		$this->AQ = $AQ;
		$this->tableAlias = $alias;
		$this->tableDef =& HNOBJBasic::getTableDefFor($object);
		$this->fieldList = new FieldList();
		$this->whereList = new WhereList();
		$this->orderList = new OrderList();
		$this->groupList = new OrderList();
	}
	
	/**
	* This function links to another object using a join.
	* @param $field This is a string field or subtable which contains link information to another object.
	* @param $alias The object alias to use in query. If not given it uses the new object name.
	* @param $onClause To join the tables in a custom way you can include what goes in the ON() clause.
	*      eg: $onClause = 'tablealias1.rawfield = tablealias2.rawfield'.
	* @param $joinType You can use different JOIN types as long as they support the ON clause.
	*      eg: JOIN, INNER JOIN, CROSS JOIN, LEFT [OUTER] JOIN, RIGHT [OUTER] JOIN.
	*/
	public function link($field, $alias = false, $onClause = false, $joinType = 'LEFT OUTER JOIN') {
		// Check readable link
		$readableFields = $this->tableDef->getReadableFieldsAndSubtables();
		if (!isset($readableFields[$field]))
			throw new Exception('Field or Subtable "' .$field. '" is not readable or does not exist');
		$fieldDef = $readableFields[$field];
		
		// Load other object
		$RAQT = $this->AQ->table($fieldDef->object, $alias, true);
		$RTableDef = $RAQT->getTableDef();
		
		// Create ON clause if not given
		if (empty($onClause)) {
			$remoteFields = $RTableDef->getReadableFields();
			if (!isset($readableFields[$fieldDef->remoteField]))
				throw new Exception('Field "' .$fieldDef->remoteField. '" is not readable or does not exist');
			$remoteFieldDef = $readableFields[$fieldDef->remoteField];
			
			$localField = $fieldDef->SQLWithTable($this->tableAlias);
			$remoteField = $remoteFieldDef->SQLWithTable($RAQT->getTableAlias());
			
			$onClause = $localField.'='.$remoteField;
		}
		
		// Record link definitions
		$this->linkDefs[] = array($joinType, $onClause, $RAQT);
		
		return $RAQT;
	}
	
	/// @TODO remove
	public function addLink($field, $alias = false, $onClause = false, $joinType = 'LEFT OUTER JOIN') {
		return $this->link($field, $alias, $onClause, $joinType);
	}
	
	private function getFieldDef($field) {
		$readableFields = $this->tableDef->getReadableFields();
		if (!isset($readableFields[$field]))
			throw new Exception('Field "' .$field. '" is not readable or does not exist');
		return $readableFields[$field];
	}
	
	/// Adds a field to return in the query
	public function field($field, $fieldIndex = false, $customField = false) {
		if (!$customField)
			$field = $this->getFieldDef($field);
		if ($fieldIndex !== false)
			$this->fieldList->insertAt($field, $fieldIndex);
		else
			$this->fieldList->append($field);
	}
	
	/// @TODO remove
	public function addField($field, $fieldIndex = false, $customField = false) {
		return $this->field($field, $fieldIndex, $customField);
	}
	
	/**
	* Appends a WherePart to the WhereList.
	* @see WhereList::append($proto)
	* @see WherePart::__construct($field, $sign, $value, $dontEscape = false)
	* @example where(new WherePart(...), WHERE_AND, new WherePart(...))
	*/
	public function where($proto) {
		if (!is_array($proto))
			$proto = func_get_args();
		$this->whereList->append($proto);
	}
	
	/// @TODO remove
	public function addWhere($proto) {
		if (!is_array($proto))
			$proto = func_get_args();
		return $this->where($proto);
	}
	
	public function order($field, $order = ORDER_ASC, $orderIndex = false, $customField = false) {
		$this->addOrderToList($this->orderList, $field, $order, $orderIndex, $customField);
	}
	public function group($field, $order = ORDER_ASC, $groupIndex = false, $customField = false) {
		$this->addOrderToList($this->groupList, $field, $order, $groupIndex, $customField);
	}
	private function addOrderToList($orderList, $field, $order = ORDER_ASC, $orderIndex = false, $customField = false) {
		if (!$customField)
			$field = $this->getFieldDef($field);
		if ($orderIndex !== false)
			$orderList->insertAt(array($field, $order), $orderIndex);
		else
			$orderList->append(array($field, $order));
	}
	
	/// @TODO remove
	public function addOrder($field, $order = ORDER_ASC, $orderIndex = false, $customField = false) {
		return order($field, $order = ORDER_ASC, $orderIndex, $customField);
	}
	
	/// @TODO remove
	public function addGroup($field, $order = ORDER_ASC, $groupIndex = false, $customField = false) {
		return group($field, $order = ORDER_ASC, $groupIndex, $customField);
	}
	
	public function getTableDef() {
		return $this->tableDef;
	}
	public function getTableAlias() {
		return $this->tableAlias;
	}
	public function getLinkDefs() {
		return $this->linkDefs;
	}
	
	public function getFieldList() {
		return $this->fieldList;
	}
	public function getWhereList() {
		return $this->whereList;
	}
	public function getOrderList() {
		return $this->orderList;
	}
	public function getGroupList() {
		return $this->groupList;
	}
}
