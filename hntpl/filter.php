<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template Filter Object
*
* This page contains the filter template implementation.
* @author Daniel Scott <dysfunctional16@gmail.com>
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

define('WITH_WHERE', true);
define('WITHOUT_WHERE', false);

/**
* This class can be used to make a user filter interface.
* 
* @example 
$filter = $HNTPL->new_filter('studentFilter');
$filter->add_field(1, 'School', 'school', array(
	array('School Alpha', 'SchoolID = 1'),
	array('School Beta', 'SchoolID = 2'),
	));
$filter->add_field(2, 'Year', 'year', array(
	array('Year 11 or 12', 'ClassYear = 11 OR ClassYear = 12'),
	array('Less than year 11', 'ClassYear < 11'),
	));
$SQL = "SELECT * FROM students " . $filter->outputSQL(WITH_WHERE);
*/
class HNTPLFilter extends HNTPLCore {
	private static $cookieExpire = 2592000; // 30 days
	private static $cookiePath = '/';
	private static $cookieDomain = '';
	private static $shownOnce = false;

	private $name;
	private $fields = array();

	/**
	* @param string $name Unique name for this filter over the whole domain. Used to label the cookie.
	*/
	public function __construct($name) {
		$this->name = $name;
		$this->save_filter_settings();
	}

	/** Adds a field for filtering with.
	* @param int $column The column to use on the filter selection.
	* @param string $display The text to display in the filter for the field.
	* @param string $field A short unique name with will be used in the SQL by default.
	* @param array $values An array of values the field can be filtered using.
	*     Values are arrays of two part, 0 = display text, 1 = SQL.
	* @param string $SQL OPTIONAL string containing the SQL to use. Key of values array to go into %s.
	*/
	public function add_field($column, $display, $field, $values, $SQL = null) {
		$this->fields[$field] = array(
			'column' => $column,
			'display' => $display,
			'field' => $field,
			'values' => $values,
			'SQL' => $SQL,
			);
	}

	private function save_filter_settings() {
		if (isset($_REQUEST[$this->name.'/CHANGED'])) {
			$data = $this->get_cookie();
			if (empty($_REQUEST['filterData'])) {
				$data = array();
			} else {
				$filter = $_REQUEST['filterData'];
				foreach ($filter AS $key => $val)
					$data[$key] = $val;
				
				// Remove keys that have no values sent
				foreach ($data as $key => $val) {
					if (!isset($filter[$key]))
						unset($data[$key]);
				}
			}
			$this->set_cookie($data);
		}
	}

	/** Gets the selected items */
	public function get_selected() {
		$data = $this->get_cookie();
		$selected = array();
		foreach ($this->fields as $field) {
			$fieldID = $field['field'];
			if (!empty($data[$fieldID])) {
				$selected[$fieldID] = array();
				foreach ($data[$fieldID] as $key => $val)
					$selected[$fieldID][$val] = $field['values'][$val];
			}
		}
		return $selected;
	}

	/** Collects the saved cookie data as an array */
	public function get_cookie() {
		if (empty($_COOKIE[$this->name.'/DATA']))
			return array();
		$data = $_COOKIE[$this->name.'/DATA'];
		return json_decode($data, true);
	}

	/** Set cookies values.
	* @param array $data Filter data to be stored in the cookie.
	*/
	public function set_cookie($data = array()) {
		////////// LEGACY CLEANUP ////////////
		// Old style gets cleaned up and re-saved in the new format
		if (!empty($_COOKIE[$this->name]) && is_array($_COOKIE[$this->name])) {
			$oldData = $_COOKIE[$this->name];
			foreach ($_COOKIE[$this->name] as $key => $val)
				setcookie($this->name.'['.$key.']', '', 1, self::$cookiePath, self::$cookieDomain);
			foreach ($data as $key => $val)
				$oldData[$key] = $val;
			$data = $oldData;
			unset($oldData, $key, $val);
		}
		////////// END LEGACY CLEANUP ////////
		
		// remove anything we don't want to start because it empty
		// cast all numbers to proper numerals to lose quotes
		foreach ($data as $key => &$val) {
			if (empty($val)) unset($data[$key]);
			foreach ($val as $key2 => &$val2)
				if (intval($val2) == $val2) $val2 = intval($val2);
		}
		unset($val, $val2);
		
		// Save the cookie
		$data = json_encode($data);
		$_COOKIE[$this->name.'/DATA'] = $data;
		setcookie($this->name.'/DATA',
			$data,
			time() + self::$cookieExpire,
			self::$cookiePath,
			self::$cookieDomain);
	}

	/**
	* Output the SQL
	*/
	public function outputSQL($needsWhere) {
		$selected = $this->get_selected();
		$sql = '';
		if (count($selected) == 0)
			return $sql;
		
		foreach ($selected as $block => $values) {
			$sql .= ($needsWhere ? ' WHERE' : ' AND');
			$needsWhere = false;
			$sql .= ' (';
			foreach (array_values($values) as $key => $val) {
				if ($key != 0) $sql .= ' OR ';
				$sql .= $val[1];
			}
			$sql .= ')';
		}
		return $sql;
	}

	/**
	* Default output function.
	*/
	public function output($capture = false, $attrib = false) {
		if ($capture)
			ob_start();
		
		if (!self::$shownOnce) {
			self::$shownOnce = true;
			echo '<link rel="stylesheet" type="text/css" href="' .SERVER_ADDRESS. '/style/filter.css">';
?>
<script type="text/javascript">
	$(document).ready(function() {
		$(document).on('click', '.filter-toggle', function(e){
			var $filterForm = $(e.target).closest('form');
			if ($filterForm.hasClass("expanded"))
				$filterForm.find('.filters-container').slideUp("fast");
			else
				$filterForm.find('.filters-container').slideDown("fast");
			$filterForm.toggleClass("expanded");
		});
		$(document).on('click', '.filter-reset', function(e){
			var $filterForm = $(e.target).closest('form');
			$filterForm.find('input:checkbox').removeAttr('checked');
		});
	});
</script>
	<?php }?>

<form id="filter-<?php echo $this->name; ?>" class="filter-form" method="post" action="?">
	<input type="hidden" name="<?php echo $this->name; ?>/CHANGED" value="1"/>
	<div class="filter-toggle">
		<?php
			$selList = $this->outputSelectedList(true);
			echo '<em>Filter:</em> ';
			echo '<span class="filter-list-content">';
			if (strlen($selList) <= 9)
				echo 'Show all';
			else
				echo $selList;
			echo '</span>';
		?>
		<div class="outerarrow">
			<div class="arrow"></div>
		</div>
	</div>
	<div class="filters-container">
	<table>
	<tr>
	<?php $this->outputCheckboxes(); ?>
	</tr>
	</table>
	<div class="boxed-content align-right">
		<button class="filter-reset submit_btn" type="button">Reset</button>
		<button class="submit_btn" type="submit">Apply filter</button>
	</div>
	</div>
</form>
<?php
		parent::output();
		if ($capture)
			return ob_get_clean();
	}

	/** Outputs an unordered list of currently selected filters */
	public function outputSelectedList($capture = false) {
		if ($capture)
			ob_start();
		
		echo '<ul>';
		foreach ($this->get_selected() as $fieldID => $valArray) {
			$field = $this->fields[$fieldID];
			echo sprintf('<li>%s: ', $field['display']);
			foreach (array_values($valArray) as $key => $val) {
				if ($key != 0) echo ', ';
				echo $val[0];
			}
			echo '</li>';
		}
		echo '</ul>';
		
		if ($capture)
			return ob_get_clean();
	}
	
	/** Make all the checkboxes for display */
	public function outputCheckboxes($capture = false) {
		if ($capture)
			ob_start();
		
		$selected = $this->get_selected();
		$colData = array();
		foreach ($this->fields as $fieldKey => $field) {
			$out = (isset($colData[$field['column']]) ? $colData[$field['column']] : '');
			$out .= '<section class="filter split">';
			$out .= '<h3>' .$field['display']. '</h3><ul>';
			
			foreach ($field['values'] as $valKey => $value) {
				$isSelected = (isset($selected[$fieldKey]) && isset($selected[$fieldKey][$valKey]));
				
				$out .= '<li>';
				$out .= sprintf('<input type="checkbox" name="filterData[%s][]" value="%s" id="%s"%s>',
					$fieldKey,
					$valKey,
					$fieldKey.'-'.$valKey,
					($isSelected ? ' checked="checked"' : ''));
				$out .= sprintf('<label for="%s">%s</label>',
					$fieldKey.'-'.$valKey,
					$value[0]);
				$out .= '</li>';
			}
			
			$out .= '</ul></section>';
			$colData[$field['column']] = $out;
		}
		
		ksort($colData);
		echo '<td valign="top" class="filter-column boxed-content">';
		echo implode('</td><td valign="top" class="filter-column boxed-content">', $colData);
		echo '</td>';
		
		if ($capture)
			return ob_get_clean();
	}
}