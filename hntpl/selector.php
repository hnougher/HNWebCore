<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template Selector Object
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
* AJAX parts work in with the core functionality of HNWebbase.
* 
* @see HNTPLCore
*/
class HNTPLSelector extends HNTPLCore
{

	private $uri;
	private $fieldNames;

	private $emptyText = 'Enter some text above to start searching.';
	private $noResultText = 'Nothing was found with the search term.';

	/**
	* @param string $searchSQL This is the SQL query that is used for the this selector.
	* 		Use the keyword "--searchText--" which will be replaced with the search text.
	* 		SELECT return names is not used so dont both with them.
	* 		The first 'field' is used as the ID with will be sent to the after URI.
	* 			eg: SELECT `userid`, `username` FROM `user` WHERE `username` LIKE "%--searchText--%" LIMIT 30
	* @param string $uri The location to send the selected ID.
	* 		Do not include hostnames or leading slash.
	* 		Keyword --ID-- is replaced with the ID in the SQL results.
	* 		eg: client/review?client=--ID--&course=1
	* @param array $fieldNames The field names for the columns defined in the SQL.
	*/
	public function __construct( $searchSQL, $uri, $fieldNames = array() )
	{
		$_SESSION['AJAX_SQL'] = $searchSQL;
		$this->uri = $uri;
		$this->fieldNames = $fieldNames;
	}

	/**
	* Sets the text displayed in the result area when there are no rows in the result.
	* 
	* @param string $text
	* @uses $emptyText
	*/
	public function set_empty_text( $text )
	{
		$this->emptyText = $text;
	}

	/**
	* Sets the text displayed in the result area before a search is processed.
	*
	* @param string $text
	* @uses $noResultText
	*/
	public function set_noresult_text( $text )
	{
		$this->noResultText = $text;
	}

	/**
	* Default output function. Outputs all the tags that have been added in default format.
	* 
	* @param boolean $capture If set to true this function will return the content to output instead of writing it to STDOUT.
	* @param array|false $attrib If the calling template had some attributes that need to be printed in this, thay are sent in through this.
	* @return string|none
	* 
	* @uses $content Each value processed and outputed into the form style.
	* @uses print_tag() Used for the tag processing.
	*/
	public function output( $capture = false, $attrib = false )
	{
		if( $capture )
			ob_start();

?>


<form id="searcher" method="post" target="search_result" <?php echo self::attributes( $attrib ); ?>>
<table>
	<tr>
		<td>Enter some search text:</td>
		<td><input type="text" name="srch" id="srch" autocomplete="off"/></td>
		<td><input type="submit" value="Search"/></td>
	</tr>
	<tr><td id="content" colspan="3">
		<i><?php echo $this->emptyText; ?></i>
		<iframe name="search_result"></iframe>
	</td></tr>
</table>
</form>

<script type="text/javascript">
var searcher_timeout = null;
var searcher_delay = function()
{
	if( searcher_timeout )
		window.clearTimeout( searcher_timeout );
	searcher_timeout = window.setTimeout( searcher, 500 );
};
var searcher = function()
{
	window.clearTimeout( searcher_timeout );
	var tx = $("#search_text").val();

	$.get( "<?php echo $_SERVER['REQUEST_URI']; ?>", $("#searcher").serialize() + "&ajax=1", searcher2, "json" );

	return false;
};
var searcher2 = function( data )
{
	var out = "";
	if( data.length == 0 )
	{
		out += "<i><?php echo $this->noResultText; ?></i>";
	}
	else
	{
		out += "<table>";
		<?php

		if( count( $this->fieldNames ) > 0 )
		{
			echo 'out += "<tr>';
			foreach( $this->fieldNames AS $field )
			{
				echo '<th>' .$field. '</th>';
			}
			echo '</tr>";';
		}

		?>
		var i, j;
		for( i = 0; i < data.length; i++ )
		{
			var pointTo = "<?php
				$tmp = explode( '--ID--', $this->uri, 2 );
				echo $tmp[0];
				echo '" + data[i][0] + "';
				if( isset( $tmp[1] ) )
					echo $tmp[1];
				?>";

			// If IE6 Hacks Enabled
			var ieHack = "";
			if( typeof(window["IERowMouseOver"]) != "undefined" )
				ieHack = " onmouseover='IERowMouseOver()' onmouseout='IERowMouseOut()' style='cursor: pointer'";

			out += "<tr onclick='document.location = \"" + pointTo + "\"'" + ieHack + ">";
			for( j = 1; j < data[i].length; j++ )
			{
				if( data[i][j] == null )
					data[i][j] = "";
				out += "<td style='text-align: center'>" + data[i][j] + "</td>";
			}
			out += "</tr>";
		}
		out += "</table>";
	}

	$("#content").html( out );
};

$(document).ready(function() {
	$("#content").html( "<i><?php echo $this->emptyText; ?></i>" );
	$("#srch").bind( "keyup", searcher_delay );
	$("#searcher").bind( "submit", searcher );
});

// Perload
searcher();
</script>

<?php

		parent::output();

		if( $capture )
			return ob_get_clean();
	}
}
