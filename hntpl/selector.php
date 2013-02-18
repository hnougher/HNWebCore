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

/**
* This class gives the functionality of creating dynamic forms easily.
* AJAX parts work in with the core functionality of HNWebCore.
* 
* @see HNTPLCore
*/
class HNTPLSelector extends HNTPLCore
{
	// Just displays the query on one page
	const TYPE_BASIC = 'basic';
	// Just displays the query with pagination
	const TYPE_BASICPAGE = 'basicp';
	// Displays a search box which sends one parameter to the query via AJAX
	const TYPE_SEARCH = 'search';
	// Same as TYPE_SEARCH with pagination
	const TYPE_SEARCHPAGE = 'searchp';
	#const TYPE_FILTER = 'filter';

	private $type;
	private $queryCode;
	private $uri;
	private $fieldNames;

	private $emptyText = 'Enter some text above to start searching.';
	private $noResultText = 'Nothing was found with the search term.';

	/**
	* @param $type One of the TYPE_* consts of this class.
	* @param $queryCode The query code returns by StoreAJAXQuery.
	*   The stored query should follow the rules of the TYPE picked.
	*   All types need to have the first return parameter as the ID field for not displaying but gets put into the URI.
	*   TYPE_BASIC should have no params.
	*   TYPE_BASICPAGE should have one param, the page number.
	*   TYPE_SEARCH should have one param, the search string.
	*   TYPE_SEARCHPAGE should have two params, first is the search string, the second is the page number.
	* @param $uri The location to send the selected ID.
	* 		Do not include hostnames or leading slash.
	* 		Keyword --ID-- is replaced with the first field in the SQL results.
	* 		eg: client/review?client=--ID--&course=1
	* @param array $fieldNames The field names for the columns defined in the SQL.
	*/
	public function __construct($type, $queryCode, $uri, $fieldNames = array()) {
		$this->type = $type;
		$this->queryCode = $queryCode;
		$this->uri = $uri;
		$this->fieldNames = $fieldNames;
	}

	/**
	* Sets the text displayed in the result area when there are no rows in the result.
	* 
	* @param string $text
	* @uses $emptyText
	*/
	public function set_empty_text($text) {
		$this->emptyText = $text;
	}

	/**
	* Sets the text displayed in the result area before a search is processed.
	*
	* @param string $text
	* @uses $noResultText
	*/
	public function set_noresult_text($text) {
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
	public function output($capture = false, $attrib = false) {
		if($capture)
			ob_start();

?>


<form id="selector<?php echo $this->queryCode; ?>" class="selector" method="post" <?php echo self::attributes($attrib); ?>>
	<input type="hidden" name="q" value="<?php echo $this->queryCode; ?>"/>
	<?php if ($this->type == self::TYPE_SEARCH || $this->type == self::TYPE_SEARCHPAGE) { ?>
		<table class="selector_searchbox"><tr>
			<td>Enter some search text:</td>
			<td><input type="text" name="p[]" id="srch" autocomplete="off"/></td>
			<td><input type="submit" value="Search"/></td>
		</tr></table>
	<?php } ?>
	<table>
		<tr><td id="content" colspan="3">
			<i><?php echo $this->emptyText; ?></i>
		</td></tr>
	</table>
</form>

<script type="text/javascript">
(function(){
	var $selector = $("#selector<?php echo $this->queryCode; ?>");
	var selector_timeout = null;
	var selector_delay = function() {
		if (selector_timeout)
			window.clearTimeout(selector_timeout);
		selector_timeout = window.setTimeout(selector, 500);
	};
	var selector = function(e) {
		window.clearTimeout(selector_timeout);
		$.get("<?php echo SERVER_ADDRESS; ?>/ajax.php", $selector.serialize(), selector2, "json");
		if (e) e.preventDefault();
		return false;
	};
	var selector2 = function(data) {
		var out = "";
		if (data.length == 0) {
			out += "<i><?php echo $this->noResultText; ?></i>";
		} else {
			out += "<table>";
			<?php

			if (count($this->fieldNames) > 0) {
				echo 'out += "<tr>';
				foreach ($this->fieldNames AS $field) {
					echo '<th>' .$field. '</th>';
				}
				echo '</tr>";';
			}

			?>
			var i, j;
			for (i = 0; i < data.length; i++) {
				var pointTo = "<?php
					$tmp = explode('--ID--', $this->uri, 2);
					echo $tmp[0];
					echo '" + data[i][0] + "';
					if (isset($tmp[1]))
						echo $tmp[1];
					?>";

				// If IE6 Hacks Enabled
				var ieHack = "";
				if (typeof(window["IERowMouseOver"]) != "undefined")
					ieHack = " onmouseover='IERowMouseOver()' onmouseout='IERowMouseOut()' style='cursor: pointer'";

				out += "<tr onclick='document.location = \"" + pointTo + "\"'" + ieHack + ">";
				for (j = 1; j < data[i].length; j++) {
					if (data[i][j] == null)
						data[i][j] = "";
					out += "<td style='text-align: center'>" + data[i][j] + "</td>";
				}
				out += "</tr>";
			}
			out += "</table>";
		}

		$selector.find("#content").html(out);
	};

	$(document).ready(function() {
		$selector.find("#srch").bind("keyup", selector_delay);
		$selector.bind("submit", selector);

		// Preload
		selector();
	});
})();
</script>

<?php

		parent::output();

		if ($capture)
			return ob_get_clean();
	}
}
