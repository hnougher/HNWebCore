<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template List Set
*
* This page has classes that are used to create lists including
* select and radio form types.
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

require_once TEMPLATE_PATH. '/core.php';

/**
* This class gives the functionality of creating a list.
* 
* @see HNTPLCore
*/
abstract class HNTPLList extends HNTPLCore
{
	/**
	* Adds a new option to the option list.
	* 
	* @param string $value The value of the option.
	* @param string $text The text label of the option.
	*/
	public abstract function new_option( $value, $text = false );
}

/**
* This class gives the functionality of creating a select option list.
* 
* @see HNTPLList
*/
class HNTPLListSelect extends HNTPLList
{
	/**
	* Adds a new option to the option list.
	* 
	* @param string $value The value of the option.
	* @param string $text The text label of the option.
	* @param boolean $selected If true the option will attempt to be selected by default.
	* @uses escape_text()
	* @uses new_tag()
	*/
	public function new_option( $value, $text = false, $selected = false )
	{
		$attrib = array();
		if( $text != false )
		{
			$attrib[ 'value' ] = $value;
			$content = $this->escape_text( $text );
		}
		else
			$content = $this->escape_text( $value );
		if( $selected )
			$attrib[ 'selected' ] = 'selected';
		$this->new_tag( 'option', $attrib, $content );
	}

	/**
	* Adds an option group to the list.
	* 
	* @param string $label The label of the option group.
	* @return HNTPLListSelect
	* 
	* @uses new_tag()
	* @uses $type Sets the type for the new option list.
	* @uses HNTPLListSelect Creates a new instance of the object.
	*/
	public function new_group( $label )
	{
		$newList = new HNTPLListSelect();
		$this->new_tag( 'optgroup', array( 'label' => $label ), $newList );
		return $newList;
	}

}

/**
* This class gives the functionality of creating a radio option list.
* 
* @see HNTPLList
*/
class HNTPLListRadio extends HNTPLList
{
	/**
	* This is used to set the name and id's of the radio buttons.
	* 
	* @var string
	*/
	protected $myId;

	/**
	* This is used to keep the radio buton id's different.
	* 
	* @var string
	*/
	protected $radioCounter = 0;

	/**
	* Constructor for the radio option list type.
	* 
	* @param string $id A value that is used as the radio lists name and id.
	* 
	* @uses $myId Sets it to the id and name we are to use when using radio type.
	*/
	public function __construct( $id )
	{
		$this->myId = 'form_' .$id;
	}

	/**
	* Adds a new option to the option list.
	* 
	* @param string $value The value of the option.
	* @param string $text The text label of the option.
	* 
	* @uses $content Adds new record it the array.
	* @uses $radioCounter Increments before using it make unique radio names.
	*/
	public function new_option( $value, $text = false )
	{
		$this->radioCounter++;
		$this->content[] = array(
			'tag' => 'input',
			'attrib' => array(
				'id' => $this->myId. '_' .$this->radioCounter,
				'name' => $this->myId,
				'type' => 'radio',
				'value' => $value,
				),
			'content' => $content,
			);
		if( $text !== false )
			$content = $this->escape_text( $text );
		else
			$content = $this->escape_text( $value );
		$this->content[] = array(
			'tag' => 'label',
			'attrib' => array( 'for' => $this->myId. '_' .$this->radioCounter ),
			'content' => $content,
			);
		$this->content[] = array( 'tag' => 'br' );
	}
}

/**
* This class gives the functionality of creating ordered and unordered lists.
* 
* @see HNTPLList
*/
class HNTPLListStatic extends HNTPLList
{
	/**
	* Remembers is the list we are making is an ordered one.
	* @var boolean
	*/
	protected $isOrdered;

	/**
	* @param boolean $isOrdered Sets if this list is to be of the ordered type.
	* 
	* @uses $isOrdered Sets the variable.
	*/
	public function __construct( $isOrdered = false )
	{
		$this->isOrdered = ( $isOrdered == true );
	}

	/**
	* Adds a new record to the list.
	* 
	* @param string $value The value of the list item.
	* @param string $text The text label of the list item.
	* 
	* @uses new_tag()
	* @uses escape_text()
	*/
	public function new_option( $value, $text = false )
	{
		$this->new_tag( 'li', null, $this->escape_text( $value ) );
	}

	/**
	* Adds a new sub-list to the list.
	* 
	* @param boolean $isOrdered If true the sub-list will be an ordered one.
	* @return HNTPLListStatic
	* 
	* @uses new_raw()
	* @uses HNTPLListStatic Makes a new instance of the object.
	*/
	public function new_list( $isOrdered = false )
	{
		$newList = new HNTPLListStatic( $isOrdered );
		$this->new_raw( $newList );
		return $newList;
	}

	/**
	* Output function for the static lists.
	* @param boolean $capture
	* 
	* @see HNTPLCore::output()
	* @uses $isOrdered
	*/
	public function output( $capture = false )
	{
		if( $capture )
			ob_start();

		echo '<' .( $this->isOrdered ? 'o' : 'u' ). 'l>';
		parent::output();
		echo '</' .( $this->isOrdered ? 'o' : 'u' ). 'l>';

		if( $capture )
			return ob_get_clean();
	}
}
