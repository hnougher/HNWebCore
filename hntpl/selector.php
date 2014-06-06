<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template Selector Object
*
* This page contains the form template implementation.
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
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
	const TYPE_ALL		= 0x000F;	// Used as mask and selects all types in one go
	const TYPE_BASIC	= 0x0000;	// No extras
	const TYPE_PAGE		= 0x0001;	// Page controls
	const TYPE_SEARCH	= 0x0002;	// Search box
	const TYPE_FILTER	= 0x0004;	// Per field filter/search
	const TYPE_ORDER	= 0x0008;	// Column ordering

	private $type = self::TYPE_BASIC;
	private $queryCode;
	private $uri = false;
	private $fieldNames;
	private $queryCodeCounter;

	private $headDisplayMethod = 'headDisplayDefault';
	private $rowOnclickMethod = 'rowOnclickDefault';
	private $rowDisplayMethod = 'rowDisplayDefault';
	private $emptyText = 'No results to display.';
	private $noResultText = 'Nothing was found with the search term.';
	private $pageCtrlText = 'Page %curpage% of %totalpages%';
	private $pageCtrlMode = 'multipage';
	private $pageCtrlBtns = 'multipage';

	/**
	* @param $queryCode The query code returns by StoreAJAXQuery.
	*   The stored query should follow the rules of the TYPE picked.
	*   All types need to have the first return parameter as the ID field for not displaying but gets put into the URI.
	*   TYPE_BASIC should have no params.
	*   TYPE_BASICPAGE should have one param, the page number.
	*   TYPE_SEARCH should have one param, the search string.
	*   TYPE_SEARCHPAGE should have two params, first is the search string, the second is the page number.
	* @param array $fieldNames The field names for the columns defined in the SQL.
	*/
	public function __construct($queryCode, $fieldNames = array()) {
		$this->queryCode = $queryCode;
		$this->fieldNames = $fieldNames;
	}

	/**
	* @param $type One or more of the TYPE_* consts of this class OR'ed together.
	*/
	public function set_selector_type($type) {
		$this->type = $type & self::TYPE_ALL;
	}

	/**
	* @param $uri The location to send the selected ID.
	* 		Keyword --ID-- is replaced with the first field in the SQL results.
	* 		eg: /client/review?client=--ID--&course=1
	*/
	public function set_row_onclick_uri($uri) {
		$this->uri = $uri;
	}

	/**
	* @param string $jsFuncName The JS function name which takes two parameters,
	* the event object and the ID of the row clicked.
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
	* @uses $headDisplayMethod
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
	* Sets the text displayed in between the page navigation buttons.
	*
	* @param string $text Can contain the following string for replacement.
	*   %firstrow% %lastrow% %totalrows% %curpage% %perpage% %totalpages%
	* @uses $pageCtrlText
	*/
	public function set_page_control_text($text) {
		$this->pageCtrlText = $text;
	}

	/**
	* Changes the display policy of the page navigation contol.
	*
	* @param string $mode Can be always, hasrow or multipage.
	* @uses $pageCtrlMode
	*/
	public function set_page_control_mode($mode) {
		if (!in_array($mode, array('always','hasrow','multipage')))
			throw new Exception('Invalid mode given');
		$this->pageCtrlMode = $mode;
	}

	/**
	* Changes the display policy of the buttons on the page navigation contol.
	* The mode given the the entire control has priority.
	*
	* @param string $mode Can be always, hasrow or multipage.
	* @uses $pageCtrlBtns
	*/
	public function set_page_control_button_mode($mode) {
		if (!in_array($mode, array('always','hasrow','multipage')))
			throw new Exception('Invalid mode given');
		$this->pageCtrlBtns = $mode;
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
			if ($this->type & self::TYPE_SEARCH)
				echo '<div id="filterDiv">Filter: <input type="text" id="srch" autocomplete="off"/></div>';
			echo $pageChanger;
			echo '<div id="content"><i>' .$this->emptyText. '</i></div>';
			echo $pageChanger;
			
			echo '<input type="hidden" name="q" value="' .$this->queryCode. '"/>';
			if ($this->type & self::TYPE_SEARCH)
				echo '<input type="hidden" name="p[]" class="srchp" value=""/>';
			if ($this->type & self::TYPE_ORDER)
				echo '<input type="hidden" name="p[]" class="orderp" value=""/>';
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
	var rowDispInterval = null;
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
		selector_timeout = window.setTimeout(selector, 200);
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
		if (rowDispInterval != null)
			clearInterval(rowDispInterval);
		
		var $base = $selector.find("#content");
		if (data.length == 0) {
			$base.empty();
			$("<i/>").appendTo($base)
				.html(setting.noResultText);
		} else {
			var rowdata = data[0];
			if (rowdata.length == 0) {
				$base.empty();
				$("<i/>").appendTo($base)
					.html(setting.noResultText);
			} else {
				var existing = ($base.find('table').length != 0);
				if (!existing) {
					var $table = $('<table/>').appendTo($base);
					var $thead = $('<thead/>').appendTo($table);
					$('<tbody/>').appendTo($table);
					
					// Column Headings
					if (setting.fieldNames.length > 0) {
						$tablehead = $('<tr id="tablehead"/>').appendTo($thead);
						<?php echo $this->headDisplayMethod; ?>($tablehead, setting.fieldNames);
					}
					
					<?php if ($this->uri !== false || $this->rowOnclickMethod != "rowOnclickDefault") { ?>
					$table.on("click", "tbody tr", <?php echo $this->rowOnclickMethod; ?>);
					<?php } ?>
				}
				
				var i = 0;
				var $tbody = $base.find('tbody');
				var $oldRows = $tbody.children();
				for (var j = rowdata.length; j < $oldRows.length; j++)
					$oldRows.eq(j).remove();
				
				function outputRows() {
					var stopIterAt = (new Date).getTime() + 15;
					for (; i < rowdata.length; i++) {
						if ((new Date).getTime() >= stopIterAt)
							return;
						
						var $tr;
						if ($oldRows.length > i) {
							$tr = $oldRows.eq(i);
							$tr.empty();
						} else {
							$tr = $("<tr/>").appendTo($tbody);
						}
						$tr.data("rowid", rowdata[i][0]);

						// If IE6 Hacks Enabled
						if (typeof(window["IERowMouseOver"]) != "undefined" && typeof(window["IERowMouseOut"]) != "undefined"){ 
							$tr.on("mouseover", IERowMouseOver);
							$tr.on("mouseout", IERowMouseOut);
							$tr.css("cursor", "pointer");
						}

						<?php if ($this->uri !== false || $this->rowOnclickMethod != "rowOnclickDefault") { ?>
						$tr.addClass("clickable");
						<?php } ?>
						<?php echo $this->rowDisplayMethod; ?>($tr, rowdata[i]);
					}
					
					clearInterval(rowDispInterval);
					rowDispInterval = null;
				}
				rowDispInterval = setInterval(outputRows, 10);
			}
			
			if (data.length >= 2) {
				// Page Information
				$selector.find("#totalrows").val(data[1][0][0]);
			}
		}
		
		<?php if ($this->type & self::TYPE_PAGE) echo "pageCtrlUpdate();"; ?>
	};
	<?php if ($this->uri !== false && $this->rowOnclickMethod == "rowOnclickDefault") { ?>
	function rowOnclickDefault(e) {
		var rowID = $(e.target).closest("tr").data("rowid");
		document.location = setting.uri.replace("--ID--", rowID);
	};
	<?php } ?>
	<?php if ($this->headDisplayMethod == "headDisplayDefault") { ?>
	function headDisplayDefault($tr, fieldNames) {
		var curOrderIndex = $(".orderp").val();
		for (var i = 0; i < fieldNames.length; i++) {
			var $th = $("<th/>").addClass("clickable");
			$tr.append($th);
			<?php if ($this->type & self::TYPE_ORDER) { ?>
			(function(){
				var newIndex = i+2;
				var $spacer = $("<span/>").addClass("spacer");
				if (newIndex == curOrderIndex) {
					$th.addClass("orderASC").append($spacer);
				}
				if (-newIndex == curOrderIndex) {
					$th.addClass("orderDESC").append($spacer);
				}
				$th.on("click", {newIndex:newIndex}, columnOrderSet);
			})();
			<?php } ?>
			$th.prepend(fieldNames[i]);
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
		for (var i = 1; i < rowdata.length; i++)
			$("<td/>").appendTo($tr).html(rowdata[i]);
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
		var text = "<?php echo str_replace('"', '\"', $this->pageCtrlText); ?>";
		var showBar = "<?php echo $this->pageCtrlMode; ?>";
		var showNav = "<?php echo $this->pageCtrlBtns; ?>";
		var rowoffset = parseInt($selector.find("#rowoffset").val());
		var perpage = parseInt($selector.find("#perpage").val());
		var totalrows = parseInt($selector.find("#totalrows").val());
		var curpage = Math.floor(rowoffset / perpage) + 1;
		var totalpage = Math.floor((totalrows - 1) / perpage) + 1;
		
		text = text.replace(/%(firstrow|lastrow|totalrows|curpage|perpage|totalpages)%/g, function(match) {
			switch (match.slice(1,-1)) {
			case 'firstrow': return Math.min(rowoffset + 1, totalrows);
			case 'lastrow': return Math.min(rowoffset + perpage, totalrows);
			case 'totalrows': return totalrows;
			case 'curpage': return Math.min(curpage, totalpage);
			case 'perpage': return perpage;
			case 'totalpages': return totalpage;
			}});
		$selector.find(".pageCtrlInfo").html(text);
		
		if (showBar != 'always' && (totalpage == 0 || (showBar != 'hasrow' && totalpage == 1)))
			$selector.find(".pageCtrl").hide();
		else
			$selector.find(".pageCtrl").show();
		if (showNav != 'always' && (totalpage == 0 || (showNav != 'hasrow' && totalpage == 1)))
			$selector.find(".pageCtrlFirst,.pageCtrlPrev,.pageCtrlNext,.pageCtrlLast").hide();
		else
			$selector.find(".pageCtrlFirst,.pageCtrlPrev,.pageCtrlNext,.pageCtrlLast").show();
		if (curpage == 1)
			$selector.find(".pageCtrlFirst,.pageCtrlPrev").attr("disabled", "disabled");
		else
			$selector.find(".pageCtrlFirst,.pageCtrlPrev").removeAttr("disabled");
		if (curpage == totalpage || totalpage <= 1)
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
