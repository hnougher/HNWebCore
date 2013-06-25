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

require_once TEMPLATE_PATH. '/core.php';

/**
* This class gives the functionality of creating a list.
* @see HNTPLCore
*/
class HNTPLMainTypes extends HNTPLCore
{
	/**
	* Adds a new heading tag.
	* 
	* @param string $level The level of the heading.
	* @param string $text The text inside the heading.
	* 
	* @uses escape_text()
	* @uses new_tag()
	*/
	public function new_heading( $level, $text )
	{
		if( $level < 1 || $level > 6 )
			$level = 4;
		$text = $this->escape_text( $text );
		$this->new_tag( 'h' .$level, null, $text );
	}

	/**
	* Adds a new line break.
	* 
	* @param boolean $pageBreak Set to true if you want a page break.
	*
	* @uses new_tag()
	*/
	public function new_br( $pageBreak = false )
	{
		$this->new_tag( 'br', $pageBreak ? array( 'class' => '.newpage' ) : null );
	}

	/**
	* Adds a new paragraph.
	* 
	* @param string|object $content Set the content to raw string or an object.
	* @uses new_tag()
	*/
	public function new_paragraph( $content )
	{
		$this->new_tag( 'p', null, $content );
	}

	/**
	* Adds a new link.
	* 
	* @param string $link The URI for the link to go.
	* @param string|object $content Set the content to raw string or an object.
	* @uses new_tag()
	*/
	public function new_link( $link, $content = false )
	{
		$attrib = array( 'href' => $link );
		$this->new_tag( 'a', $attrib, ( $content === false ? $link : $content ) );
	}

	/**
	* Adds a new image.
	* 
	* @param string $src The URI of the img to be displayed.
	* @param integer $width Set the width of the image.
	* @param integer $height Set the height of the image..
	* @uses new_tag()
	*/
	public function new_image($src, $width = 0, $height = 0) {
		$style = array();
		if ($width != 0)
			$style['width'] = $width. 'px';
		if ($height != 0)
			$style['height'] = $height. 'px';
		$attrib = array('src' => $src, 'style' => $style);
		$this->new_tag('img', $attrib);
		
	}

	/**
	* Adds a new list.
	* 
	* @param boolean $isOrdered If True then the list is an ordered one.
	* @return HNTPLListStatic
	* @uses HNTPLListStatic
	* @uses new_raw()
	*/
	public function new_list($isOrdered = false) {
		$content = new HNTPLListStatic($isOrdered);
		$this->new_raw($content);
		return $content;
	}

	/**
	* Adds a new form.
	* 
	* @param string $title The title of the form.
	* @param string|false $submitTo The uri that the form is to be submitted to. If its set to false then the form will be submitted back to the current uri.
	* @return HNTPLForm
	* @uses HNTPLForm
	* @uses new_raw()
	*/
	public function new_form($title = '', $submitTo = false, $formMethod = false) {
		require_once TEMPLATE_PATH. '/form.php';
		$content = new HNTPLForm($title, $submitTo, $formMethod);
		$this->new_raw($content);
		return $content;
	}

	/**
	* Adds a new graph.
	* 
	* @see HNTPLGraph::__construct()
	*/
	public function new_graph($graphType, $rawData, $lineNames, $groupNames) {
		require_once TEMPLATE_PATH. '/graph.php';
		$content = new HNTPLGraph($graphType, $rawData, $lineNames, $groupNames);
		$this->new_raw($content);
		return $content;
	}

	/**
	* Adds a new selector.
	* 
	* @see HNTPLSelector::__construct()
	*/
	public function new_selector($type, $queryCode, $uri, $fieldNames = array()) {
		require_once TEMPLATE_PATH. '/selector.php';
		$content = new HNTPLSelector($type, $queryCode, $uri, $fieldNames);
		$this->new_raw($content);
		return $content;
	}

	/**
	* Adds a new table to the page.
	* 
	* @return HNTPLTable
	* @uses HNTPLTable
	* @uses new_raw()
	*/
	public function new_table() {
		require_once TEMPLATE_PATH. '/table.php';
		$content = new HNTPLTable();
		$this->new_raw($content);
		return $content;
	}
	
	/**
	* Adds a new checkbox.
	* 
	* @param string $id The checkbox name and id value.
	* @param boolean $checked If set to true the checkbox will be checked by default.
	*/
	public function new_checkbox($id, $checked = false) {
		$attrib = array(
			'type' => 'checkbox',
			'id' => $id,
			'name' => $id,
			);
		if ($checked)
			$attrib['checked'] = 'checked';

		$this->new_tag('input', $attrib);
	}
}
