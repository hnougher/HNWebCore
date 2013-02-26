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
	// Base level bitfield, can be OR'd together as a type
	const TYPE_BASIC	= 0x0000;	// No extras
	const TYPE_PAGE		= 0x0001;	// Page controls
	const TYPE_SEARCH	= 0x0002;	// Search box
	const TYPE_FILTER	= 0x0004;	// Per field filter/search
	const TYPE_ORDER	= 0x0008;	// Column ordering

	private $type;
	private $queryCode;
	private $uri;
	private $fieldNames;
	private $queryCodeCounter;

	private $headDisplayMethod = 'headDisplayDefault';
	private $rowOnclickMethod = 'rowOnclickDefault';
	private $rowDisplayMethod = 'rowDisplayDefault';
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
	* @param string $jsFuncName The JS function name which takes one parameter, the ID of the row clicked.
	* @uses $rowOnclickMethod
	*/
	public function set_row_onclick_method($jsFuncName) {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.\-]*$/', $jsFuncName))
			throw new Exception("Invalid characters in row onclick method");
		$this->rowOnclickMethod = $jsFuncName;
	}

	/**
	* @param string $jsFuncName The JS function name which takes two parameters, the parent tr object
	*    wrapped in jQuery, and the JSON data for the row.
	* @uses $rowDisplayMethod
	*/
	public function set_head_display_method($jsFuncName) {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.\-]*$/', $jsFuncName))
			throw new Exception("Invalid characters in head display method");
		$this->headDisplayMethod = $jsFuncName;
	}

	/**
	* @param string $jsFuncName The JS function name which takes two parameters, the parent tr object
	*    wrapped in jQuery, and the JSON data for the row.
	* @uses $rowDisplayMethod
	*/
	public function set_row_display_method($jsFuncName) {
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.\-]*$/', $jsFuncName))
			throw new Exception("Invalid characters in row display method");
		$this->rowDisplayMethod = $jsFuncName;
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
 
		echo '<form id="selector' .$this->queryCode. '" class="selector" method="post" ' .self::attributes($attrib). '>';
			if ($this->type & self::TYPE_SEARCH) {
				echo '<table class="selector_searchbox"><tr>';
					echo '<td>Enter some search text:</td>';
					echo '<td><input type="text" id="srch" autocomplete="off"/></td>';
					echo '<td><input type="submit" value="Search"/></td>';
				echo '</tr></table>';
			}
			echo $pageChanger;
			echo '<div id="content"><i>' .$this->emptyText. '</i></div>';
			echo $pageChanger;
			
			echo '<input type="hidden" name="q" value="' .$this->queryCode. '"/>';
			if ($this->type & self::TYPE_SEARCH)
				echo '<input type="hidden" name="p[]" class="srchp" value=""/>';
			if ($this->type & self::TYPE_ORDER) {
				echo '<input type="hidden" name="p[]" class="orderp" value=""/>';
				echo '<input type="hidden" name="p[]" class="orderp" value=""/>';
			}
			if ($this->type & self::TYPE_PAGE) {
				echo '<input type="hidden" name="p[]" id="rowoffset" value="0"/>';
				echo '<input type="hidden" name="p[]" id="perpage" value="30"/>';
				echo '<input type="hidden" id="totalrows" value="0"/>';
				echo '<input type="hidden" name="q1" value="' .$this->queryCodeCounter. '"/>';
				if ($this->type & self::TYPE_SEARCH)
					echo '<input type="hidden" name="p1[]" class="srchp" value=""/>';
			}
		echo '</form>';
?>

<script type="text/javascript">
(function(){
	var setting = <?php echo json_encode(array(
		'fieldNames' => $this->fieldNames,
		'noResultText' => $this->noResultText,
		'uri' => $this->uri,
		)); ?>;
	var $selector = $("#selector<?php echo $this->queryCode; ?>");
	var $tablehead = false;
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
		var $base = $('<div id="content"/>');
		if (data.length == 0) {
			var $i = $("<i/>");
			$i.html(setting.noResultText);
			$base.html($i);
		} else {
			var rowdata = data[0];
			if (rowdata.length == 0) {
				var $i = $("<i/>");
				$i.html(setting.noResultText);
				$base.html($i);
			} else {
				var $table = $("<table/>");
				$base.append($table);
				
				// Column Headings
				if (setting.fieldNames.length > 0) {
					$tablehead = $('<tr id="tablehead"/>');
					<?php echo $this->headDisplayMethod; ?>($tablehead, setting.fieldNames);
					$table.append($tablehead);
				}
				
				for (var i = 0; i < rowdata.length; i++) {
					var $tr = $("<tr/>");
					$table.append($tr);

					// If IE6 Hacks Enabled
					if (typeof(window["IERowMouseOver"]) != "undefined" && typeof(window["IERowMouseOut"]) != "undefined"){ 
						$tr.on("mouseover", IERowMouseOver);
						$tr.on("mouseout", IERowMouseOut);
						$tr.css("cursor", "pointer");
					}

					(function(){
						// To copy rowID for each row
						var rowID = rowdata[i][0];
						$tr.on("click", function(){<?php echo $this->rowOnclickMethod; ?>(rowID)});
					})();
					$tr.addClass("clickable");
					<?php echo $this->rowDisplayMethod; ?>($tr, rowdata[i]);
				}
			}
			
			if (data.length >= 2) {
				// Page Information
				$selector.find("#totalrows").val(data[1][0][0]);
			}
		}
		
		<?php if ($this->type & self::TYPE_PAGE) echo "pageCtrlUpdate();"; ?>
		$selector.find("#content").replaceWith($base);
	};
	<?php if ($this->rowOnclickMethod == "rowOnclickDefault") { ?>
	function rowOnclickDefault(rowID) {
		document.location = setting.uri.replace("--ID--", rowID);
	};
	<?php } ?>
	<?php if ($this->headDisplayMethod == "headDisplayDefault") { ?>
	function headDisplayDefault($tr, fieldNames) {
		var curOrderIndex = $(".orderp").val();
		for (var i = 0; i < fieldNames.length; i++) {
			var $th = $("<th/>");
			$tr.append($th);
			<?php if ($this->type & self::TYPE_ORDER) { ?>
			(function(){
				var newIndex = i+2;
				$th.addClass("clickable");
				if (newIndex == curOrderIndex)
					$th.addClass("orderASC");
				if (-newIndex == curOrderIndex)
					$th.addClass("orderDESC");
				$th.on("click", {newIndex:newIndex}, columnOrderSet);
			})();
			<?php } ?>
			$th.html(fieldNames[i]);
		}
	};
	<?php } ?>
	<?php if ($this->type & self::TYPE_ORDER) { ?>
	function columnOrderSet(e) {
		var event = jQuery.Event("orderchange");
		event.newIndex = e.data.newIndex;
		event.oldIndex = $(".orderp").val();
		if (event.oldIndex == event.newIndex)
			event.newIndex = -event.newIndex;
		$(".orderp").val(event.newIndex);
		$tablehead.trigger(event);
		selector();
	}
	<?php } ?>
	<?php if ($this->rowDisplayMethod == "rowDisplayDefault") { ?>
	function rowDisplayDefault($tr, rowdata) {
		for (var i = 1; i < rowdata.length; i++) {
			var $td = $("<td/>");
			$tr.append($td);
			$td.html(rowdata[i]);
		}
	};
	<?php } ?>
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

		if ($selector.find("#srch").val() == "") {
			if (totalpage == 1)
				$selector.find(".pageCtrl").hide();
			else
				$selector.find(".pageCtrl").show();
		}
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
