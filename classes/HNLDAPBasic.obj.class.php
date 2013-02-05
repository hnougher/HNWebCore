<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN LDAP Object Class
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

class HNLDAPBasic extends HNOBJBasic
{
	/**
	* This function loads the data from the database.
	*
	* @return Boolean TRUE on success, otherwise FALSE.
	*/
	public function load() {
		// Reset the local data store
		$this->isLoaded = false;
		$this->noRecord = false;
		$oldData = $this->myData;
		$this->myData = array();

		// Make the SQL statement
		$fields = array();
		foreach ($this->fieldList->getReadable() as $name => $fieldInfo) {
			if ($fieldInfo['type'] != 'rev_object')
				$fields[] = $name;
		}
		
		if (!count($fields)) {
			// This is hopefully safe.
			// It occurs when the user has no permission to view the record
			return false;
		}
		
		// Create filter
		/*$filter = array();
		foreach ((array) $this->fieldList->getIdField() AS $key => $idField)
			$filter[] = '(' .$idField. '=' .HNLDAP::escape($this->myId[$key]). ')';
		$filter = (count($filter) > 1 ? '(&' .implode('', $filter). ')' : implode('', $filter));*/
		$filter = explode(',', $this->myId[0], 2);
		$filter = '(' .$filter[0]. ')';
		#var_dump($filter);

		// Do the Query
		$result = HNLDAP::search($this->fieldList->getLDAPBase(), $filter, $fields);
		#var_dump($result);
		if (!$result) {
			// The query has failed for some reason
			// NOTE: HNMySQL logs and displays the error before return.
			return false;
		}

		// Check if the record does exist
		if ($result["count"] == 0)
			$this->noRecord = true;

		// Process the Data into the correct data types and locally store it
		#var_dump($result);
		$this->storeArrayToLocal($this->noRecord ? array() : $result[0]);

		// YAY! We have loaded successfully
		$this->isLoaded = true;
		return true;
	}

	/**
	* Saves the data to the database.
	* If the id is zero or the record doesnt exist, the record is inserted into
	* the database following database rules.
	*
	* @return boolean TRUE on success, otherwise FALSE.
	*/
	public function save() {
	}

	/**
	* Removes the current record from the database.
	*
	* @return boolean TRUE on success, otherwise FALSE.
	*/
	public function remove() {
		die("LDAP OBJ ERMOVE NOT IMPLEMENTED");
	}
}


