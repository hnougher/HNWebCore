<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template Form Object
*
* This page contains the form template implementation.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

require_once TEMPLATE_PATH. '/core.php';
require_once TEMPLATE_PATH. '/lists.php';

/**
* This class gives the functionality of creating dynamic forms easily.
* 
* @see HNTPLCore
*/
class HNTPLForm extends HNTPLCore
{

	/**
	* The title of the form.
	*/
	private $title = '';

	/**
	* The uri that the form is to be submitted to.
	*/
	private $submitTo = false;

	/**
	* The method the form will use to submit the data.
	*/
	private $formMethod = 'post';

	/**
	* The text on the submit button of the form.
	*/
	private $submitText = 'Submit';

	/**
	* The text on the reset button of the form.
	*/
	private $resetText = 'Reset';

	/**
	* The text on the back button of the form.
	*/
	private $backText = 'Back';

	/**
	* Holds the hidden fields for output
	*/
	private $hidden = array();

	/**
	* @see HNTPLCore::$content
	* 
	* Extra parts used in this version of this are
	* 'label' (the label used to associalt the fields with) and
	* 'id' (sets the tag's id and name attributes)
	*/
	//protected $content = array();

	/**
	* @param string $title The title of the form.
	* @param string|false $submitTo The uri that the form is to be submitted to. If its set to false then the form will be submitted back to the current uri.
	* @param get|post $submitMethod The method the form should use to submit the data.
	*
	* @uses $title
	* @uses $submitTo
	* @uses $formMethod
	*/
	public function __construct( $title = '', $submitTo = false, $formMethod = false )
	{
		$this->title = $title;
		$this->submitTo = $submitTo;
		if( $formMethod !== false )
		{
			$formMethod = strtolower( $formMethod );
			if( $formMethod == 'post' || $formMethod == 'get' )
				$this->formMethod = $formMethod;
		}
	}

	/**
	* Sets the submit button text.
	* 
	* @param string $text
	* @uses $submitText
	*/
	public function set_submit_text( $text )
	{
		$this->submitText = $text;
	}

	/**
	* Sets the reset button text.
	*
	* @param string $text
	* @uses $resetText
	*/
	public function set_reset_text( $text )
	{
		$this->resetText = $text;
	}

	/**
	* Sets the back button text.
	*
	* @param string $text
	* @uses $backText
	*/
	public function set_back_text( $text )
	{
		$this->backText = $text;
	}

	/**
	* Adds a new label field to the form. Use for displaying static values in the form layout.
	* 
	* @param string $label The field label.
	* @param string $text The text to be printed as the value of this field.
	* @param boolean $useRaw If set to true then the text will be used raw
	*		instead of escaping like normal.
	* 
	* @uses $content Appends a new record.
	*/
	public function new_label( $label, $text, $useRaw = false )
	{
		$this->content[] = array(
			'label' => $label,
			'content' => $useRaw ? $text : $this->escape_text( $text ),
			);
	}

	/**
	* Adds a new text field to the form.
	* 
	* @param string $label The field label.
	* @param string $id The row's name and id value.
	* @param string $text The text to be printed as the default value of this field.
	* 
	* @uses $content Appends a new record.
	*/
	public function new_text( $label, $id, $text = '' )
	{
		$this->content[] = array(
			'id' => $id,
			'label' => $label,
			'tag' => 'input',
			'attrib' => array( 'type' => 'text', 'value' => $text ),
			);
	}

	/**
	* Adds a new password field to the form.
	* 
	* @param string $label The field label.
	* @param string $id The row's name and id value.
	*
	* @uses $content Appends a new record.
	*/
	public function new_password( $label, $id )
	{
		$this->content[] = array(
			'id' => $id,
			'label' => $label,
			'tag' => 'input',
			'attrib' => array( 'type' => 'password' ),
			);
	}

	/**
	* Adds a new hidden field to the form.
	* 
	* @param string $id The field's name value.
	* @param string $value The value of this field.
	* 
	* @uses $hidden
	*/
	public function new_hidden( $id, $value )
	{
		$this->hidden[ $id ] = $value;
	}

	/**
	* Adds a new textarea field to the form.
	* 
	* @param string $label The field label.
	* @param string $id The row's name and id value.
	* @param string $text The text to be printed as the default value of this field.
	* 
	* @uses $content Appends a new record.
	*/
	public function new_separator()
	{
		$this->content[] = array(
			'tag' => 'hr',
			);
	}

	/**
	* Adds a new textarea field to the form.
	* 
	* @param string $label The field label.
	* @param string $id The row's name and id value.
	* @param string $text The text to be printed as the default value of this field.
	* 
	* @uses $content Appends a new record.
	*/
	public function new_textarea( $label, $id, $text = '' )
	{
		$this->content[] = array(
			'id' => $id,
			'label' => $label,
			'tag' => 'textarea',
			'content' => $this->escape_text( $text ),
			);
	}

	/**
	* Adds a new checkbox field to the form.
	* 
	* @param string $label The field label.
	* @param string $id The row's name and id value.
	* @param boolean $checked If set to true the checkbox will be checked by default.
	* 
	* @uses $content Appends a new record.
	*/
	public function new_checkbox( $label, $id, $checked = false )
	{
		$attrib = array( 'type' => 'checkbox' );
		if( $checked )
			$attrib['checked'] = 'checked';

		$this->content[] = array(
			'id' => $id,
			'label' => $label,
			'tag' => 'input',
			'attrib' => $attrib,
			);
	}

	/**
	* Adds a new select box to the form.
	* 
	* @param string $label The field label.
	* @param string $id The row's name and id value.
	* @param integer $size Set the size of the select box in visible rows.
	* @param boolean $multiselect Make it possible to select multiple values in the list.
	* @return HNTPLListSelect
	* 
	* @uses HNTPLListSelect
	* @uses $content
	*/
	public function new_select( $label, $id, $size = 1, $multiselect = false )
	{
		$attrib = array( 'size' => $size );
		if( $multiselect )
			$attrib['multiple'] = 'multiple';

		$newList = new HNTPLListSelect();

		$this->content[] = array(
			'id' => $id,
			'label' => $label,
			'tag' => 'select',
			'attrib' => $attrib,
			'content' => $newList,
			);

		return $newList;
	}

	/**
	* Adds a new radio select set to the form.
	* 
	* @param string $label The field label.
	* @param string $id The row's name and id value.
	* @return HNTPLListRadio
	* 
	* @uses HNTPLListRadio
	* @uses $content
	*/
	public function new_radio( $label, $id )
	{
		$newList = new HNTPLListRadio( $id );

		$this->content[] = array(
			'id' => $id,
			'label' => $label,
			'content' => $newList,
			);

		return $newList;
	}

	/**
	* Ouputs a form field from the database with correct type checking.
	*
	* @param string $label The label of the form row.
	* @param string $field The name of the field that we are dealing with.
	* @param HNOBJBasic $object The object that the field is to be collected from.
	* @param array $ignore Used for enum and set fields. Values listed in here
	*		will not be displayed for the user to use.
	*/
	public function output_form_field( $label, $field, $object, $ignore = array() )
	{
		$param = $object->getValParam( $field );
		$value = $object[ $field ];
		switch( $param['type'] )
		{
			case 'bool':
				$this->new_checkbox( $label, $field, $value );
				break;
			case 'str':
				if( !empty( $param['enum'] ) )
				{
					$enum = explode( '|', $param['enum'] );
					$select = $this->new_select( $label, $field );
					foreach( $enum AS $tmp )
					{
						if( !in_array( $tmp, $ignore ) )
							$select->new_option( $tmp, null, ( $value == $tmp ) );
					}
					break;
				}
				if( !empty( $param['set'] ) )
				{
					$value = explode( ',', $value );
					$enum = explode( '|', $param['set'] );
					$select = $this->new_select( $label, $field. '[]', 5, true );
					foreach( $enum AS $tmp )
					{
						if( !in_array( $tmp, $ignore ) )
							$select->new_option( $tmp, null, in_array( $tmp, $value ) );
					}
					break;
				}
				if( !empty( $param['password'] ) )
				{
					$this->new_password( $label, $field );
					break;
				}
			case 'int':
			case 'float':
				$this->new_text( $label, $field, $value );
				break;
			case 'object':
				$this->new_label( $label, $object->replaceFields( '{' .$field. '}' ) );
		}
	}

	/**
	* This function adds the redirect field to the givien form for
	* user to select what they want to do after the current form is
	* submitted.
	* 
	* @param array $myWhereTo The hash array of places to goto.
	*/
	public function do_redirect_select($myWhereTo) {
		$select = $this->new_select('After you Press Submit', 'where_to');
		$counter = 0;
		foreach ($myWhereTo AS $text) {
			$selected = false;
			if ($text[0] == '^') {
				$text = substr($text, 1);
				$selected = true;
			}
			$select->new_option('id' .$counter, $text, $selected);
			$counter++;
		}
	}

	/**
	* Default output function. Outputs all the tags that have been added in default format.
	* 
	* @param boolean $capture If set to true this function will return the content to output instead of writing it to STDOUT.
	* @param array|false $attrib If the calling template had some attributes that need to be printed in this, thay are sent in through this.
	* @return string|none
	* 
	* @uses $submitTo Printed directly into the form's action attribute.
	* @uses $submitText The submit button text.
	* @uses $resetText The reset button text.
	* @uses $title Printed directly to the form header area.
	* @uses $content Each value processed and outputed into the form style.
	* @uses print_tag() Used for the tag processing.
	* @uses $hidden
	* @uses $formMethod
	*/
	public function output( $capture = false, $attrib = false )
	{
		if( $capture )
			ob_start();

		echo '<form method="' .$this->formMethod. '"' .( empty( $this->submitTo ) ? '' : ' action="' .$this->submitTo. '"' ) . self::attributes( $attrib ) . '>';

		// print out the hidden fields
		foreach( $this->hidden As $key => $val )
			$this->print_tag( 'input', array( 'type' => 'hidden', 'name' => $key, 'value' => $val ) );

		echo '<table>';

		if( !empty( $this->title ) )
			echo '<thead><tr><td colspan="2" style="font-weight: bold; text-align: center">' .$this->title. '</td></tr></thead>';

		echo "<tbody>\n";
		$seenIds = array();
		foreach( $this->content AS $block )
		{
			echo "<tr><td";
				if( !empty( $block['label'] ) )
				{
					echo '>';
					if( isset( $block['id'] ) )
					{
						if( in_array( $block['id'], $seenIds ) && substr( $block['id'], -2 ) != '[]' )
							echo '<span class="error">&lt;Error: Duplicate ID&gt;</span><br/>';
						else
							$seenIds[] = $block['id'];
						echo '<label for="form_' .$block['id']. '">';
					}
					echo '<b>' .$block['label']. ':</b> ';
					if( isset( $block['id'] ) )
						echo '</label>';
					echo '</td><td';
				}
				else
					echo ' colspan="2"';
			echo '>';

			// Print the tag
			$tag = empty( $block['tag'] ) ? 'span' : $block['tag'];
			$attrib = !isset( $block['attrib'] ) || !is_array( $block['attrib'] ) ? array() : $block['attrib'];
			$content = !isset( $block['content'] ) ? false : $block['content'];
			if( isset( $block['id'] ) )
			{
				$attrib['id'] = 'form_' .$block['id'];
				$attrib['name'] = $block['id'];
			}
			$this->print_tag( $tag, $attrib, $content );

			echo "</td></tr>\n";
		}

		// Print out the submit and reset buttons
		echo '<tr><td colspan="2" style="text-align: center">';
			if( !empty( $this->submitText ) )
				$this->print_tag( 'input', array( 'type' => 'submit', 'value' => $this->submitText ) );
			if( !empty( $this->resetText ) )
				$this->print_tag( 'input', array( 'type' => 'reset', 'value' => $this->resetText ) );
			if( !empty( $this->backText ) )
				$this->print_tag( 'input', array( 'type' => 'button', 'value' => $this->backText, 'onclick' => 'history.go(-1)' ) );
		echo '</td></tr>';

		echo '</tbody>';

		echo '</table>';
		echo '</form>';

		if( $capture )
			return ob_get_clean();
	}
}
