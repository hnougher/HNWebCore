<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template Graphing Object
*
* @requires JQuery, Flot.
*
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
class HNTPLGraph extends HNTPLCore
{
	const BAR_WIDTH_MAX = 0.9;
	
	private static $graphCounter = 0;
	private static $includedScripts = array();

	private $graphHeight = '300px';
	private $graphNumber = 0;
	private $graphType = 'bar'; // bar, fbar, hbar, line, fline, stack, fstack, pie
	private $graphWidth = '400px';
	
	private $legendPosition = 'nw';
	private $legendText = '%lineName%';
	
	private $groupNames = array();
	private $lineNames = array();
	private $rawData = array();
	private $barWidth = self::BAR_WIDTH_MAX;

	/**
	* @param $graphType The graph type. Can be bar, fbar, hbar, line, fline, stack, ftack or pie.
	* @param $rawData The data which is to be displayed.
	*   Should be of the JSON-like form [{0:1,1:2,5:3},{0:3,1:2,5:1},{1:2},{0:3,5:3}].
	*   Which is four lines containing up to 3 points.
	* @param $lineNames This gives the line names which will be used in the graph legend.
	*   Should be of the JSON-like form ["First Line","Second Line","Third Line","Fourth Line"].
	* @param $groupNames This gives the value names for the line keys.
	*   Should be of the JSON-like form {0:"First Column",1:"Second Column",5:"Third Column"}.
	*/
	public function __construct($graphType, $rawData, $lineNames, $groupNames) {
		if (!preg_match('/^(?:bar|fbar|hbar|line|fline|stack|fstack|pie)$/', $graphType))
			throw new Exception('Invalid graph type "' .$graphType. '"');
		if (!is_array($rawData) || !is_array($lineNames) || !is_array($groupNames))
			throw new Exception('$rawData, $lineNames and $groupNames must be arrays');
		if (count($rawData) != count($lineNames))
			throw new Exception('Invalid Data Seen');
		if ($graphType == 'pie' && count($groupNames) != 1)
			throw new Exception('Graphing with pie cannot handle multiple values in first field');
		
		if ($graphType == 'bar' || $graphType == 'fbar')
			$this->barWidth = self::BAR_WIDTH_MAX / count($lineNames);
		
		// Rearrange data to be better for Flot
		foreach ($rawData as $lineID => $lineSeries) {
			$rawData[$lineID] = array('label' => $lineNames[$lineID], 'data' => $lineSeries);
			$lineSeries =& $rawData[$lineID];
			$lineData =& $lineSeries['data'];
			
			foreach ($groupNames as $groupID => $groupName) {
				if (!isset($lineData[$groupID]))
					$lineData[$groupID] = array($groupID, 0);
				
				if ($graphType == 'bar' || $graphType == 'fbar')
					$lineData[$groupID][0] += $this->barWidth * $lineID;
				
				if (count($lineData[$groupID]) != 2)
					throw new Exception('Invalid Data Seen');
			}
			
			if (count($lineData) != count($groupNames))
				throw new Exception('Invalid Data Seen');
			
			// Sort the data by key so the JSON does not make objects when its meant to be arrays
			ksort($lineData);
			
			// Clean up references
			unset($lineSeries, $lineData);
		}
		
		print_r($rawData);
		
		$this->graphNumber = ++self::$graphCounter;
		$this->graphType = $graphType;
		$this->groupNames = $groupNames;
		$this->lineNames = $lineNames;
		$this->rawData = $rawData;
	}
	
	/**
	* Can contain %lineName% which is the name of the line.
	*/
	public function setLegendPosition($pos) {
		if (!preg_match('/^(?:nw|ne|sw|se)$/', $pos))
			throw new Exception('Invalid legend position "' .$pos. '"');
		$this->legendPosition = $pos;
	}
	
	/**
	* Can contain %lineName% which is the name of the line.
	*/
	public function setLegendText($text) {
		$this->legendText = $text;
	}
	
	/**
	* Any valid CSS height.
	*/
	public function setHeight($height) {
		$this->graphHeight = $height;
	}
	
	/**
	* Any valid CSS width.
	*/
	public function setWidth($width) {
		$this->graphWidth = $width;
	}
	
	/**
	* @see HNTPLCore::output()
	*/
	public function output($capture = false, $attrib = false) {
		if($capture)
			ob_start();

if (!in_array('flot', self::$includedScripts)) {
	self::$includedScripts[] = 'flot';
	echo '<!--[if lte IE 8]><script type="text/javascript" src="' .SERVER_ADDRESS. '/style/flot/excanvas.min.js"></script><![endif]-->';
	echo '<script type="text/javascript" src="' .SERVER_ADDRESS. '/style/flot/jquery.flot.js"></script>';
}
if ($this->graphType == 'pie' && !in_array('pie', self::$includedScripts)) {
	self::$includedScripts[] = 'pie';
	echo '<script type="text/javascript" src="' .SERVER_ADDRESS. '/style/flot/jquery.flot.pie.js"></script>';
}
if (in_array($this->graphType, array('stack','fstack')) && !in_array('stack', self::$includedScripts)) {
	self::$includedScripts[] = 'stack';
	echo '<script type="text/javascript" src="' .SERVER_ADDRESS. '/style/flot/jquery.flot.stack.js"></script>';
}
echo '<div id="HNTPLGraph' .$this->graphNumber. '" class="graph graph-' .$this->graphType. '" style="height:' .$this->graphHeight. '; width:' .$this->graphWidth. '"></div>';


?>
<script type="text/javascript">
$(function(){
	var graphID = <?php echo $this->graphNumber; ?>;
	var graphType = "<?php echo $this->graphType; ?>";
	var groupNames = <?php echo json_encode($this->groupNames); ?>;
	var lineNames = <?php echo json_encode($this->lineNames); ?>;
	var rawData = <?php echo json_encode($this->rawData); ?>;
	
	var ticksX = [];
	for (var i = 0; i < groupNames.length; i++)
		ticksX.push([i, groupNames[i]]);
	
	/*console.dir(ticksX);
	console.dir(lineNames);
	console.dir(rawData);*/
	
	$.plot("#HNTPLGraph" + graphID, rawData, {
		grid: {
			hoverable: (graphType != "pie"),
			clickable: false
		},
		legend: {
			labelFormatter: function(label, series) {
				return "<?php echo $this->legendText; ?>".replace(/%lineName%/g, label);
			},
			position: "<?php echo $this->legendPosition; ?>",
			show: (graphType != "pie"),
		},
		series: {
			stack: (["stack","fstack"].indexOf(graphType) != -1),
			pie: {
				show: (graphType == "pie"),
				label: {
					radius: 1/2,
					formatter: function(label, series) {
						return "<div style='font-size:8pt; text-align:center; padding:2px; color:black;'>"
							+ label + "<br/>" + Math.round(series.percent) + "%</div>";
						}
					}
				},
			points: {
				show: (["line","fline","stack","fstack"].indexOf(graphType) != -1)
				},
			lines: {
				show: (["line","fline","stack","fstack"].indexOf(graphType) != -1),
				fill: (["fline","fstack"].indexOf(graphType) != -1),
				steps: false
				},
			bars: {
				show: (["bar","fbar","hbar"].indexOf(graphType) != -1),
				fill: (["fbar"].indexOf(graphType) != -1),
				barWidth: <?php echo $this->barWidth; ?>,
				horizontal: (graphType == "hbar")
				}
			},
		xaxes: [{ticks: ticksX}]
	});
	
	var previousPoint = null;
	$("#HNTPLGraph" + graphID).bind("plothover", function (event, pos, item) {
		if (item) {
			if (previousPoint == null || previousPoint[0] != item.seriesIndex || previousPoint[1] != item.dataIndex) {
				previousPoint = [item.seriesIndex, item.dataIndex];
				$("#graphtooltip").remove();
				showTooltip(item);
			}
		} else {
			$("#graphtooltip").remove();
			previousPoint = null;            
		}
	});
	
	function showTooltip(item) {
		var $outer = $("<div id='graphtooltip'/>").appendTo("body");
		var $inner = $("<div/>").appendTo($outer);
		$outer.css({
			//border: "1px solid #fdd",
			display: "none",
			left: item.pageX,
			position: "absolute",
			top: item.pageY + 10
			});
		/*$inner.css({
			"background-color": "#fee",
			border: "1px solid #fdd",
			"margin-left": "-50%",
			opacity: 0.80,
			padding: "2px",
			"text-align": "center",
			width: "100%"
			});*/
		$inner.append(item.datapoint[1] + " entries of " + groupNames[item.datapoint[0]] + "<br/>" + item.series.label);
		$outer.fadeIn(200);
	}
});
</script>

<?php
		parent::output();
		if ($capture)
			return ob_get_clean();
	}
}
