<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* HN Template Page
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.1
* @package HNWebCore
*/

require_once TEMPLATE_PATH. '/mainTypes.php';

/**
* This is to be used to setup all pages containing any HNTPL stuff.
*/
class HNTPLPage extends HNTPLMainTypes
{

	/**
	* Contains the page title.
	* @var string
	*/
	private $title = SITE_TITLE;

	/**
	* Contains the current user name.
	* @var string
	*/
	private $userName = '';

	/**
	* Contains the current users unread message count.
	* @var integer
	*/
	private $userUMessageCount = 0;

	/**
	* Contains a uri path to the location of the help page for this page.
	* @var string
	*/
	private $helpLoc;

	/**
	* Contains the debug text.
	* @var string
	*/
	private static $debug = '';
	
	/**
	* Defines which subtemplate to use, if any.
	* eg: to use the output_ECPortal method, set this to ECPortal.
	*/
	private $subTemplate = '';

	/**
	* Sets the title for the page after the constructor.
	*
	* @param string $title The title string.
	* @uses $title
	*/
	public function set_title($title) {
		$this->title = $title;
	}
	
	/**
	* Sets the heading for the page after the constructor.
	*
	* @param string $heading The heading string.
	* @uses $heading
	*/
	public function set_page_heading( $heading )
	{
		$this->heading = $heading;
	}
	
	/**
	* Sets the instructions for the page after the constructor.
	*
	* @param string $instructions The instructions string.
	* @uses $instructions
	*/
	public function set_page_instructions( $instructions )
	{
		$this->instructions = $instructions;
	}

	/**
	* Sets the user name of the current user.
	*
	* @param string $text
	* @uses $userName
	*/
	public function set_user_name($text) {
		$this->userName = htmlspecialchars($text, ENT_NOQUOTES);
	}

	/**
	* Sets the users unread message count.
	*
	* @param integer $count
	* @uses $userUMessageCount
	*/
	public function set_user_umessage_count($count) {
		$this->userUMessageCount = intval($count);
	}

	/**
	* Sets the helpLoc variable to the parameter.
	*
	* @param string $uri
	* @uses $helpLoc
	*/
	public function set_helpLoc($uri) {
		$this->helpLoc = (string) $uri;
	}

	/**
	* Appends more text to the debug string.
	*
	* @param string $text The debug string.
	* @uses self::$debug
	*/
	public static function set_debug($text) {
		self::$debug .= htmlspecialchars($text, ENT_NOQUOTES). "\n";
	}

	/**
	* Appends more text to the debug string.
	* This one does not escape the new text.
	*
	* @param string $text The debug text.
	* @uses self::$debug
	*/
	public static function set_debug_raw($text) {
		self::$debug .= $text. "\n";
	}

	/**
	* Clears all debug text.
	* @uses self::$debug
	*/
	public static function clear_debug() {
		self::$debug = '';
	}
	
	/**
	* Set the $subTemplate property of this page.
	*/
	public function set_subTemplate($st) {
		$this->subTemplate = $st;
	}

	/**
	* Outputs the page. Does headers and footer for the page
	* then calls the parents to output the internal stuff.
	*
	* @param boolean $capture If set to true this function will return the content to output instead of writing it to STDOUT.
	* @return string|none
	* @uses $content Each value cleaned up and sent to {@link print_tag} for output.
	* @uses print_tag() Used for the tag processing.
	* @uses $title
	* @uses $userName
	* @uses $userUMessageCount
	* @uses $helpLoc
	* @uses self::$debug
	*/
	public function output( $capture = false ) {
		if( $capture )
			ob_start();

		if( isset( $_REQUEST['noTemplate'] ) )
		{
			if (!empty(self::$debug)) {
				echo '<div id="debugDiv" style="display: none"><pre>' .self::$debug. '</pre></div>';
				echo '<span id="debugSpan" onclick="d=document.getElementById(\'debugDiv\');d.style.display=d.style.display==\'none\'?\'\':\'none\'">DEBUG TEXT</span>';
			}
			
			// Output the page
			parent::output();
		}
		else
		{
			$EOL = "\n";

			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
			echo '<html><head>' .$EOL;
				echo '<meta charset="UTF-8"/>' .$EOL;
				echo '<title>' .$this->title. '</title>' .$EOL;
				echo '<meta name="description" content="' .SITE_DESCRIPTION. '">' .$EOL;
				echo '<meta name="keywords" content="' .SITE_KEYWORDS. '">' .$EOL;

				// JQuery Setup
				echo '<link type="text/css" href="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.0/themes/smoothness/jquery-ui.css" rel="stylesheet">';
				echo '<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.9.0/jquery.min.js"></script>';
				echo '<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jqueryui/1.10.0/jquery-ui.min.js"></script>';
				
				// Put the cursor in the first input box using JS
				echo '<script type="text/javascript">$(function(){$("input[type=\'text\']:first").focus();});</script>';
				
				// Detect IE for Hacks
				/*if( isset( $_SERVER['HTTP_USER_AGENT'] )
					&& strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE' ) !== false
					)
				{
					if( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 8' ) !== false ) // IE8
					{
						// No Hacks Needed
					}
					elseif( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 7' ) !== false ) // IE7
					{
						echo '<link rel="stylesheet" type="text/css" href="' .SERVER_ADDRESS. '/style/iexplore_7.css" media="all">';
						echo '<script type="text/javascript" src="' .SERVER_ADDRESS. '/style/iexplore_7.js"></script>';
					}
					elseif( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 6' ) !== false ) // IE6
					{
						echo '<link rel="stylesheet" type="text/css" href="' .SERVER_ADDRESS. '/style/iexplore_6.css" media="all">';
						echo '<script type="text/javascript" src="' .SERVER_ADDRESS. '/style/iexplore_6.js"></script>';
					}
				}*/

				echo '<link type="text/css" href="' .SERVER_ADDRESS. '/style/HNWebCore.css" rel="stylesheet">';
				echo '<link rel="icon" href="' .SERVER_ADDRESS. '/style/favicon.ico" type="image/x-icon">';
				echo '<link rel="shortcut icon" href="' .SERVER_ADDRESS. '/style/favicon.ico" type="image/x-icon">';

				// Extra JS Files & Classes
				echo '<script type="text/javascript" src="' .SERVER_ADDRESS. '/style/HNToolTips.js"></script>';
			echo '</head>';
			
			// Send what we have made so far so the browser can get going
			if( !$capture )
				flush();

			// Continue creating page
			echo '<body>';

			if (!empty(self::$debug)) {
				echo '<div id="debugDiv" style="display: none"><pre>' .self::$debug. '</pre></div>';
				echo '<span id="debugSpan" onclick="d=document.getElementById(\'debugDiv\');d.style.display=d.style.display==\'none\'?\'\':\'none\'">DEBUG TEXT</span>';
			}
			
			// Send what we have made so far so the browser can get going
			if( !$capture )
				flush();
			
			// Show a sub template
			// This feature was added for one project and has almost no overhead so it still exists
			if (empty($this->subTemplate))
				$subTemplateMethod = 'output_default';
			else
				$subTemplateMethod = 'output_' .$this->subTemplate;
			if (!method_exists($this, $subTemplateMethod))
				throw new Exception('Unknown page subTemplate "' .$subTemplateMethod. '"');
			$this->{$subTemplateMethod}();
			
			echo '</body></html>';
		}

		if ($capture)
			return ob_get_clean();
	}
	
	/**
	* Output subtemplate for the main site (if wanted).
	*/
	private function output_default() {
	
		echo '<table width="100%" class="screenOnly header" style="margin: 0 auto">
			<tr>
				<td style="text-align:right;">
					<span class="headerText" style="font-weight: bold; padding-left:30px; color: #000;">Logged in as: '.$this->userName. '</span><br/>
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
		parent::output();
		echo '</div>';
	}
}
