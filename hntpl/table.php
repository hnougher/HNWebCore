<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template Table Set
*
* This page has classes that are used to create tables and its contents.
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

require_once TEMPLATE_PATH. '/mainTypes.php';

/**
* HN Template Table Class
*/
class HNTPLTable extends HNTPLCore
{

	/**
	* Creates and returns a new table row in this table.
	* 
	* @param string $mouseClick If this row is to do something when clicked then put the javascript in here.
	* @return HNTPLTRow
	*/
	public function new_row( $mouseClick = false )
	{
		$content = new HNTPLTRow( $mouseClick );
		$this->new_raw( $content );
		return $content;
	}

	/**
	* @param array|false $attrib If the calling template had some attributes that need to be printed in this, thay are sent in through this.
	*
	* @see HNTPLCore::output()
	*/
	public function output( $capture = false, $attrib = false )
	{
		if( $capture )
			ob_start();

		echo '<table' .self::attributes( $attrib ). '>';
		parent::output();
		echo '</table>';

		if( $capture )
			return ob_get_clean();
	}
}

/**
* HN Template Table Row Class
*/
class HNTPLTRow extends HNTPLCore
{

	/**
	* This contains the javascript to be run when the table row is clicked.
	* @var string|false
	*/
	private $mouseClick = false;

	/**
	* @param string $mouseClick If this row is to do something when clicked then put the javascript in here.
	* @uses $mouseClick
	*/
	public function __construct( $mouseClick = false )
	{
		$this->mouseClick = $mouseClick;
	}

	/**
	* Creates and returns a new table row in this table.
	* 
	* @param integer $colspan How many columns the cell is to cover.
	* @param integer $rowspan How many rows the cell is to cover.
	* @return HNTPLMainTypes
	*/
	public function new_cell( $colspan = 1, $rowspan = 1 )
	{
		$attrib = array();
		if( $colspan != 1 )
			$attrib['colspan'] = $colspan;
		if( $rowspan != 1 )
			$attrib['rowspan'] = $rowspan;

		$content = new HNTPLMainTypes();
		$this->new_tag( 'td', $attrib, $content );
		return $content;
	}

	/**
	* @param array|false $attrib If the calling template had some attributes that need to be printed in this, thay are sent in through this.
	*
	* @see HNTPLCore::output()
	* @uses $mouseClick
	*/
	public function output( $capture = false, $attrib = false )
	{
		if( $capture )
			ob_start();

		echo '<tr' .( $this->mouseClick ? ' onclick="' .$this->mouseClick. '"' : '' ) .self::attributes( $attrib ). '>';
		parent::output();
		echo '</tr>';

		if( $capture )
			return ob_get_clean();
	}
}
