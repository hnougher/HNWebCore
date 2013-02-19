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
	const TYPE_BASIC = 0x0000;
	// Just displays the query with pagination
	const TYPE_PAGE = 0x0001;
	const TYPE_BASICPAGE = 0x0001;
	// Displays a search box which sends one parameter to the query via AJAX
	const TYPE_SEARCH = 0x0002;
	// Same as TYPE_SEARCH with pagination
	const TYPE_SEARCHPAGE = 0x0003;
	#const TYPE_FILTER = 'filter';

	private $type;
	private $queryCode;
	private $uri;
	private $fieldNames;
	private $queryCodeCounter;

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
	* Sets the query code used for getting the total number of pages in the set.
	*
	* @param string $queryCodeCounter
	*    The query should have the same number of parameters in it as the main one minus the two for LIMIT.
	* @uses $queryCodeCounter
	*/
	public function set_query_code_counter($code) {
		$this->queryCodeCounter = $code;
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

		$pageChanger = '';
		if ($this->type & self::TYPE_PAGE) {
			if (empty($this->queryCodeCounter)) {
				echo "Selector cannot display because there is no query set for getting page count.";
				if ($capture)
					return ob_get_clean();
				return;
			}
			
			$pageChanger .= '<div class="pageCtrl" style="display:none">';
			$pageChanger .= '<button class="pageCtrlFirst">First</button>';
			$pageChanger .= '<button class="pageCtrlPrev">Previous</button>';
			$pageChanger .= '<span class="pageCtrlInfo"></span>';
			$pageChanger .= '<button class="pageCtrlNext">Next</button>';
			$pageChanger .= '<button class="pageCtrlLast">Last</button>';
			$pageChanger .= '</div>';
		}
?>


<form id="selector<?php echo $this->queryCode; ?>" class="selector" method="post" <?php echo self::attributes($attrib); ?>>
	<?php if ($this->type & self::TYPE_SEARCH) { ?>
		<table class="selector_searchbox"><tr>
			<td>Enter some search text:</td>
			<td><input type="text" id="srch" autocomplete="off"/></td>
			<td><input type="submit" value="Search"/></td>
		</tr></table>
	<?php }
	echo $pageChanger; ?>
	<table>
		<tr><td id="content" colspan="3">
			<i><?php echo $this->emptyText; ?></i>
		</td></tr>
	</table>
	<?php echo $pageChanger; ?>
	
	<input type="hidden" name="q" value="<?php echo $this->queryCode; ?>"/>
	<input type="hidden" name="p[]" class="srchp" value=""/>
	<?php if ($this->type & self::TYPE_PAGE) { ?>
		<input type="hidden" name="p[]" id="rowoffset" value="0"/>
		<input type="hidden" name="p[]" id="perpage" value="30"/>
		<input type="hidden" id="totalrows" value="0"/>
		<input type="hidden" name="q1" value="<?php echo $this->queryCodeCounter; ?>"/>
		<input type="hidden" name="p1[]" class="srchp" value=""/>
	<?php } ?>
</form>

<script type="text/javascript">
(function(){
	var $selector = $("#selector<?php echo $this->queryCode; ?>");
	var selector_timeout = null;
	var selector_delay = function() {
		if (selector_timeout)
			window.clearTimeout(selector_timeout);
		selector_timeout = window.setTimeout(selector, 250);
	};
	var selector = function(e) {
		window.clearTimeout(selector_timeout);
		if ($selector.find(".srchp").val() != $selector.find("#srch").val()) {
			$selector.find(".srchp").val($selector.find("#srch").val());
			$selector.find("#rowoffset").val(0);
		}
		$.post("<?php echo SERVER_ADDRESS; ?>/ajax.php", $selector.serialize(), selector2, "json");
		if (e) e.preventDefault();
		return false;
	};
	var selector2 = function(data) {
		var out = "";
		if (data.length == 0) {
			out += "<i><?php echo $this->noResultText; ?></i>";
		} else {
			var rowdata = data[0];
			if (rowdata.length == 0) {
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
				for (i = 0; i < rowdata.length; i++) {
					var pointTo = "<?php
						$tmp = explode('--ID--', $this->uri, 2);
						echo $tmp[0];
						echo '" + rowdata[i][0] + "';
						if (isset($tmp[1]))
							echo $tmp[1];
						?>";

					// If IE6 Hacks Enabled
					var ieHack = "";
					if (typeof(window["IERowMouseOver"]) != "undefined" && typeof(window["IERowMouseOut"]) != "undefined")
						ieHack = " onmouseover='IERowMouseOver()' onmouseout='IERowMouseOut()' style='cursor: pointer'";

					out += "<tr onclick='document.location = \"" + pointTo + "\"'" + ieHack + ">";
					for (j = 1; j < rowdata[i].length; j++) {
						if (rowdata[i][j] == null)
							rowdata[i][j] = "";
						out += "<td style='text-align: center'>" + rowdata[i][j] + "</td>";
					}
					out += "</tr>";
				}
				out += "</table>";
			}
			
			if (data.length >= 2) {
				// Page Information
				$selector.find("#totalrows").val(data[1][0][0]);
			}
		}
		
		<?php if ($this->type & self::TYPE_PAGE) echo "pageCtrlUpdate();"; ?>
		$selector.find("#content").html(out);
	};
	<?php if ($this->type & self::TYPE_PAGE) { ?>
	function pageCtrl(type) {
		var $rowoffset = $selector.find("#rowoffset");
		var rowoffset = parseInt($rowoffset.val());
		var $perpage = $selector.find("#perpage");
		var perpage = parseInt($perpage.val());
		var $totalrows = $selector.find("#totalrows");
		var totalrows = parseInt($totalrows.val());
		
		switch (type) {
		case "first":
			if (rowoffset == 0)
				return; // Nothing to do
			rowoffset = 0;
			break;
		case "prev":
			if (rowoffset == 0)
				return; // Nothing to do
			rowoffset = rowoffset - perpage;
			break;
		case "next":
			rowoffset = rowoffset + perpage;
			break;
		case "last":
			var tmp = Math.floor((totalrows - 1) / perpage) * perpage;
			if (tmp == rowoffset)
				return; // Nothing to do
			rowoffset = tmp;
			break;
		default:
			return; // Nothing to do
		}
		if (rowoffset < 0)
			rowoffset = 0;
		if (rowoffset >= totalrows)
			return; // Cant go here
		
		$rowoffset.val(rowoffset);
		selector();
	};
	function pageCtrlUpdate() {
		var rowoffset = parseInt($selector.find("#rowoffset").val());
		var perpage = parseInt($selector.find("#perpage").val());
		var totalrows = parseInt($selector.find("#totalrows").val());
		var curpage = Math.floor(rowoffset / perpage) + 1;
		var totalpage = Math.floor((totalrows - 1) / perpage) + 1;
		
		$selector.find(".pageCtrlInfo").html("Page " + curpage + " of " + totalpage);

		if (totalpage == 1 && $selector.find("#srch").val() == "")
			$selector.find(".pageCtrl").hide();
		else
			$selector.find(".pageCtrl").show();
		if (curpage == 1)
			$selector.find(".pageCtrlFirst,.pageCtrlPrev").attr("disabled", "disabled");
		else
			$selector.find(".pageCtrlFirst,.pageCtrlPrev").removeAttr("disabled");
		if (curpage == totalpage)
			$selector.find(".pageCtrlNext,.pageCtrlLast").attr("disabled", "disabled");
		else
			$selector.find(".pageCtrlNext,.pageCtrlLast").removeAttr("disabled");
	};
	<?php } ?>

	$(document).ready(function() {
		$selector.on("submit", selector);
		$selector.on("keyup", "#srch", selector_delay);
		<?php if ($this->type & self::TYPE_PAGE) { ?>
		$selector.on("click", ".pageCtrlFirst", function(){pageCtrl("first");return false});
		$selector.on("click", ".pageCtrlPrev", function(){pageCtrl("prev");return false});
		$selector.on("click", ".pageCtrlNext", function(){pageCtrl("next");return false});
		$selector.on("click", ".pageCtrlLast", function(){pageCtrl("last");return false});
		<?php } ?>

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
