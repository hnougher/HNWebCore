<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template Core
*
* This page contains the basic core class for all the HNTPL classes.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNwebCore
*/

/**
* This is the class that should be used as the base for all HNTPL.
*/
class HNTPLCore
{

	/**
	* Used to store the data tags to be outputed later.
	* 
	* @var array
	* @see output(), print_tag()
	*/
	protected $content = array();

	/**
	* A very basic tag adding function.
	* 
	* @param string $tag The name of the HTML tag.
	* @param array $attrib The attributes for the HTML tag.
	* @param string|object $content The content of the tag.
	* @see print_tag()
	*/
	public function new_tag( $tag, $attrib = array(), $content = false )
	{
		$this->content[] = array(
			'tag' => $tag,
			'attrib' => $attrib,
			'content' => $content,
			);
	}

	/**
	* A function to add escaped text to the page.
	* 
	* @param string $text The string to be escaped and sent to output.
	* 
	* @uses escape_text()
	*/
	public function new_plaintext( $text )
	{
		$text = $this->escape_text( (string) $text );
		$text = str_replace( "\n", '<br/>', $text );
		$this->content[] = array( 'content' => $text );
	}

	/**
	* A function to add raw code to the page.
	* 
	* @param string|object $raw The raw string to send to the output. If its an object then the objects output() method will be called.
	*/
	public function new_raw( $raw )
	{
		$this->content[] = array( 'content' => $raw );
	}

	/**
	* Effects the most recent tag added to the content of this object.
	* This function does 4 distinct operations depending on what you give it.
	* 
	* Get Value: if only the 1st param is given it returns the current value of the attrib.
	* 	If $attrib is style/class and 2nd param is false then the array of current styles/classes is returned.
	* Get Style Value: if 1st param is style and 3rd param is not set then return current style value.
	* Set Value: if 1st param is not style/class and 2nd is set then atrib is set to value.
	* Set Style Value: if 1st param is style and 2nd + 3rd param set then style value is set.
	* Toggle Class: if 1st param is class and 3nd value is set then the class name in the 2nd value
	* 	is toggled, meaning it is set if it doesnt already exist and removed if it does.
	* 
	* @param string $attrib The attribute name that is being dealt with.
	* @param string|false $value The Value of the attribute being set OR the style attribute name if $attrib is 'style' OR the class name to toggle if $attrib is 'class'.
	* @param string|false $subvalue The Value to set the style attribute to.
	* @return string|false The Value of the attribute on get. The previous value on set. FALSE on no value set.
	*/
	public function attrib( $attrib, $value = false, $subvalue = false )
	{
		$latest = &$this->content[ count( $this->content ) - 1 ]['attrib'];
		if( !is_array( $latest ) )
		{
			$this->content[ count( $this->content ) - 1 ]['attrib'] = array();
			$latest = &$this->content[ count( $this->content ) - 1 ]['attrib'];
		}

		if( $value === false )
			// Return value
			return isset( $latest[ $attrib ] ) ? $latest[ $attrib ] : false;

		if( $attrib == 'style' )
		{
			if( !isset( $latest[ 'style' ] ) || !is_array( $latest[ 'style' ] ) )
				$latest[ 'style' ] = array();

			$prev = isset( $latest[ 'style' ][ $value ] ) ? $latest[ 'style' ][ $value ] : false;
			if( $subvalue === false )
				// Return style value
				return $prev;

			// Set Style Value
			$latest[ 'style' ][ $value ] = '' . $subvalue;
		}
		elseif( $attrib == 'class' )
		{
			if( !isset( $latest[ 'class' ] ) )
				$latest[ 'class' ] = array();
			if( !is_array( $latest[ 'class' ] ) )
				$latest[ 'class' ] = explode( ' ', $latest[ 'class' ] );

			if( isset( $latest[ 'class' ][ $value ] ) )
				unset( $latest[ 'class' ][ $value ] );
			else
				$latest[ 'class' ][ $value ] = true;

			return !isset( $latest[ 'class' ][ $value ] );
		}
		else
		{
			// Set attribute (and make certain that its a string)
			$prev = isset( $latest[ $attrib ] ) ? $latest[ $attrib ] : false;
			$latest[ $attrib ] = '' . $value;
		}

		return $prev;
	}

	/**
	* This function is used to escape the text that is going to be used in the content.
	* 
	* @param mixed $text The variable that you want to be escaped.
	* @return string
	*/
	public function escape_text( $text )
	{
		return htmlspecialchars( $text, ENT_NOQUOTES );
	}

	/**
	* Default output function. Outputs all the tags that have been added in default format.
	* 
	* @param boolean $capture If set to true this function will return the content to output instead of writing it to STDOUT.
	* @return string|none
	* @uses $content Each value cleaned up and sent to {@link print_tag} for output.
	* @uses print_tag() Used for the tag processing.
	*/
	public function output( $capture = false )
	{
		if( $capture )
			ob_start();

		foreach( $this->content AS $block )
		{
			$tag = empty( $block['tag'] ) ? false : $block['tag'];
			$attrib = isset( $block['attrib'] ) ? $block['attrib'] : false;
			$content = empty( $block['content'] ) ? false : $block['content'];
			$this->print_tag( $tag, $attrib, $content );
			echo "\n";
		}

		if( $capture )
			return ob_get_clean();
	}

	/**
	* Prints out a tag using the parameters that are sent.
	* 
	* @param string $tag The tag used for the HTML tag text.
	* @param array|false $attrib A hash table of attributes of the tag. The key is the attribute name and the value is the value of the attribute.
	* @param string|object $content The content that goes inside the double sided tag. If this is false then the HTML is a single sided tag. The raw string is printed if its a string, the output() method is called if its an object.
	*/
	protected function print_tag( $tag = false, $attrib = false, $content = false )
	{
		// Open the Tag and its attributes
		if( $tag !== false )
		{
			echo '<' .$tag;
			echo self::attributes( $attrib );
			if( $content === false )
				echo '/';
			echo '>';
		}

		// Output the Contents
		if( $content !== false )
		{
			if( is_object( $content ) )
				if( method_exists( $content, 'output' ) )
				{
					echo "\n";
					$content->output( false, $tag === false ? $attrib : false );
				}
				else
					echo '&lt;' .get_class( $content ). '::output()&gt;';
			else
				echo $content;

			// Close the tag
			if( $tag !== false )
				echo '</' .$tag. '>';
		}
	}

	/**
	* Creates the attributes part of any tag.
	* Handles the style and class attributes in special way.
	*
	* @param array|false $attrib The attributes that needs to be formatted.
	* @return string The attribute string.
	*/
	protected static function attributes( $attrib )
	{

		// Check that it is correct input
		if( !isset( $attrib ) || !is_array( $attrib ) )
			return '';

		$out = '';
		foreach( $attrib AS $attTag => $attVal )
		{
			if( is_array( $attVal ) )
			{
				$tmp = $attVal;
				if( count( $tmp ) == 0 )
					continue;

				$attVal = '';
				if( $attTag == 'style' )
				{
					// Create style array
					foreach( $tmp AS $key => $val )
						$attVal .= $key. ':' .$val. ';';
				}
				elseif( $attTag == 'class' )
				{
					// Create class array
					foreach( $tmp AS $key => $val )
						$attVal .= ' ' .$key;
					$attVal = substr( $attVal, 1 ); // Cut off the leading space
				}
			}
			$out .= ' ' .$attTag. '="' .htmlspecialchars( $attVal ). '"';
		}
		return $out;
	}

	/**
	* This function makes it possible to easily output any of the
	* HNTPL objects as a string.
	* 
	* @return string The outputed string
	* @uses output()
	*/
	public function __toString()
	{
		return (string) $this->output( true );
	}
}