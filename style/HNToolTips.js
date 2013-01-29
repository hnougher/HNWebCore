/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN's JS code to make a tooltip appear on anything with a tooltip attribute.
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

var HNToolTips = {

	// Properties
	_visible: false,
	_timer: null,

	// Called at page Init
	init_page: function()
	{
		// Anything with a tooltip attribute will have a tooltip
		$(document)
			.on("mouseenter", "*[tooltip]", HNToolTips.mouse_enter)
			.on("mousemove", "*[tooltip]", HNToolTips.mouse_move);
// 		$("body").append('<span id="ToolTip" class="ui-state-highlight ui-corner-all">Im A Tooltip</span>');
		$("body").append('<table id="ToolTip" class="ui-state-highlight ui-corner-all"><tr><td><span class="ui-icon ui-icon-info"/></td><td id="ToolTipContent">Im A Tooltip</td></tr></table>');
	},

	// On mouse entry event on tooltip parent
	mouse_enter: function( e )
	{
		var target = $(e.target);
		target.bind( "keypress", HNToolTips.hide );
		target.one( "mouseleave", HNToolTips.mouse_leave );

		$("#ToolTip").stop( true, true );

		// ToolTip Text
		var lines = target.attr("tooltip").split("\t");
		if( lines.length < 2 )
			$("#ToolTipContent").html( target.attr("tooltip") );
		else
			$("#ToolTipContent").html( "<ul><li>" + lines.join("</li><li>") + "</li></ul>" );

		HNToolTips.mouse_move(e);
	},

	// On mouse move event on tooltip parent
	mouse_move: function( e )
	{
		var x = e.pageX + 10;
		var y = e.pageY + 10;

		$("#ToolTip")
			.css( "left", x + "px" )
			.css( "top", y + "px" );
		HNToolTips.do_show();
	},

	// On mouse exit event on tooltip parent
	mouse_leave: function( e )
	{
		var target = $(e.target);
		target.unbind( "keypress", HNToolTips.hide );

		if( HNToolTips._timer )
		{
			window.clearTimeout( HNToolTips._timer );
			HNToolTips._timer = null;
		}
		HNToolTips.hide();
	},

	// Delays the tooltip showing for half a second
	do_show: function( e )
	{
		if( HNToolTips._timer )
			return;
		HNToolTips._timer = window.setTimeout( HNToolTips.show, 500 );
	},

	// Display the tooltip
	show: function()
	{
		// Dont run if already shown
		if( HNToolTips._visible )
			return;

		HNToolTips._visible = true;
		$("#ToolTip")
			.stop( true, true )
			.fadeIn( "slow" );
	},

	// Hide the tooltip
	hide: function()
	{
		// Dont run if already hidden
		if( !HNToolTips._visible )
			return;

		HNToolTips._visible = false;
		$("#ToolTip")
			.stop( true, true )
			.fadeOut( "fast" );
	}

};

// Onload Event
$( HNToolTips.init_page );
