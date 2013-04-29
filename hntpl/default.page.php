<?php

// HEAD SECTION
if ($SECTION == 'head') {
	echo '<link type="text/css" href="' .SERVER_ADDRESS. '/style/HNWebCore.css" rel="stylesheet">';
	echo '<link rel="icon" href="' .SERVER_ADDRESS. '/style/favicon.ico" type="image/x-icon">';
	echo '<link rel="shortcut icon" href="' .SERVER_ADDRESS. '/style/favicon.ico" type="image/x-icon">';
	
	// JQuery Setup
	echo '<link type="text/css" href="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.0/themes/smoothness/jquery-ui.css" rel="stylesheet">';
	echo '<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>';
	echo '<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.0/jquery-ui.min.js"></script>';
	
	// Put the cursor in the first input box using JS
	echo '<script type="text/javascript">$(function(){$("input[type=\'text\']:first").focus();});</script>';
	
	// Extra JS Files & Classes
	echo '<script type="text/javascript" src="' .SERVER_ADDRESS. '/style/HNToolTips.js"></script>';
	return;
}
// REST IS CONTENT


echo '<table width="100%" class="screenOnly header" style="margin: 0 auto">
	<tr>
		<td style="text-align:right;">
			<span class="headerText" style="font-weight: bold; padding-left:30px; color: #000;">Logged in as: '.$username. '</span><br/>
			<a href="' .SERVER_ADDRESS. '/staff/password">My Details</a> | <a href="' .SERVER_ADDRESS. '?logout' .(isset($css) ? '&css='.$css : ''). '">Sign Out</a>
		</td>
	</table>';

if (!empty($_SESSION['confirm'])) {
	echo '<div id="confirmBox" class="ui-corner-all">';
		echo implode('<br/>', (array) $_SESSION['confirm']);
	echo '</div>';
	unset($_SESSION['confirm']);
}
if (!empty($_SESSION['error'])) {
	echo '<div id="errorBox" class="ui-corner-all">';
		echo implode('<br/>', (array) $_SESSION['error']);
	echo '</div>';
	unset($_SESSION['error']);
}

// Continue making page
echo '<div id="pageContent" class="ui-corner-all">';
echo $CONTENT;
echo '</div>';
