<?php
/**
 * A simple selector page for global use.
 * @author Hugh Nougher <hughnougher@gmail.com>
 */

function checkRequiredReadable($object, $requiredFields = array()) {
	require_once CLASS_PATH.'/OBJ.'.$object.'.class.php';
	$tableDef = call_user_func(array('OBJ'.$object, 'getTableDef'));
	$readable = array_keys($tableDef->getReadableFields());
	return (count(array_diff($requiredFields, $readable)) == 0);
}

switch ($query[0]) {
case 'user':
	if (empty($_REQUEST['after']))
		$_REQUEST['after'] = 'user/modify';
	$table = 'user';
	$requiredFields = array('userid', 'username', 'first_name', 'last_name', 'gender');
	$fieldNames = array('Username', 'Full Name', 'Gender');
	$queryRows = StoreAJAXQuery('DEFAULT', 'SELECT
		`userid`, `username`, CONCAT_WS(", ",`last_name`,`first_name`), `gender`
		FROM `user`
		WHERE
			CONCAT_WS(" ",`username`,`last_name`,`first_name`,`last_name`,`gender`) LIKE CONCAT("%", IFNULL(?,""),"%")
		ORDER BY
			ELT(?,`userid`, `username`, CONCAT_WS(", ",`last_name`,`first_name`), `gender`) ASC,
			ELT(?*-1,`userid`, `username`, CONCAT_WS(", ",`last_name`,`first_name`), `gender`) DESC,
			`username`
		LIMIT ?,?', array('text','integer','integer','integer','integer'));
	$queryCount = StoreAJAXQuery('DEFAULT', 'SELECT COUNT(*)
		FROM `user`
		WHERE CONCAT_WS(" ",`username`,`last_name`,`first_name`,`last_name`,`gender`) LIKE CONCAT("%", IFNULL(?,""),"%")
		', array('text'));
	break;

default:
	error('Invalid select page');
	return;
}

if (!checkRequiredReadable($table, $requiredFields)) {
	error('You do not have enough permission to view this data');
	return;
}

$after = SERVER_ADDRESS . (isset($_REQUEST['after']) ? '/' .$_REQUEST['after'] : '/select_plan');
$after .= (strpos( $after, '?' ) === false ? '?' : '&').$query[0]. '=--ID--';

$selector = $HNTPL->new_selector($queryRows, $fieldNames);
$selector->set_selector_type(HNTPLSelector::TYPE_PAGE | HNTPLSelector::TYPE_SEARCH | HNTPLSelector::TYPE_ORDER);
$selector->set_query_code_counter($queryCount);
