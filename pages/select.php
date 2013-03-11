<?php
/**
 * A simple selector page for global use.
 * @author Hugh Nougher <hughnougher@gmail.com>
 */

$after = SERVER_ADDRESS . (isset($_REQUEST['after']) ? '/' .$_REQUEST['after'] : '/select_plan');
$after .= (strpos( $after, '?' ) === false ? '?' : '&').$query[0]. '=--ID--';

switch ($query[0]) {
case 'user':
	$fieldNames = array('Username', 'Full Name', 'Gender');
	$queryRows = StoreAJAXQuery('SELECT
		`userid`, `username`, CONCAT_WS(", ",`last_name`,`first_name`), `gender`
		FROM `user`
		WHERE
			CONCAT_WS(" ",`username`,`last_name`,`first_name`,`last_name`,`gender`) LIKE CONCAT("%",?,"%")
		ORDER BY
			ELT(?,`userid`, `username`, CONCAT_WS(", ",`last_name`,`first_name`), `gender`) ASC,
			ELT(?*-1,`userid`, `username`, CONCAT_WS(", ",`last_name`,`first_name`), `gender`) DESC,
			`username`
		LIMIT ?,?', 'siiii');
	$queryCount = StoreAJAXQuery('SELECT COUNT(*)
		FROM `user`
		WHERE CONCAT_WS(" ",`username`,`last_name`,`first_name`,`last_name`,`gender`) LIKE CONCAT("%",?,"%")
		', 's');
	break;
default:
	error('Invalid select page');
	return;
}

$selector = $HNTPL->new_selector(
	HNTPLSelector::TYPE_PAGE|HNTPLSelector::TYPE_SEARCH|HNTPLSelector::TYPE_ORDER,
	$queryRows, $after, $fieldNames);
$selector->set_query_code_counter($queryCount);
