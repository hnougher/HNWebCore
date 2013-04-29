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
	* Contains the page heading.
	* @var string
	*/
	private $heading = SITE_TITLE;

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
			$templatePath = TEMPLATE_PATH. '/' .(empty($this->subTemplate) ? 'default' : $this->subTemplate). '.page.php';
			$templateVars = array(
				'username' => $this->userName,
				'helpLocation' => $this->helpLoc,
				'heading' => $this->heading,
				'title' => $this->title,
				'username' => $this->userName,
				);

			echo '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">';
			echo '<html><head>' .$EOL;
				echo '<meta charset="UTF-8"/>' .$EOL;
				echo '<title>' .$this->title. '</title>' .$EOL;
				echo '<meta name="description" content="' .SITE_DESCRIPTION. '">' .$EOL;
				echo '<meta name="keywords" content="' .SITE_KEYWORDS. '">' .$EOL;

				// Show a sub template head
				$templateVars['SECTION'] = 'head';
				Loader::run_script($templatePath, null, null, $templateVars);
			echo '</head>';
			
			// Send what we have made so far so the browser can get going
			if (!$capture)
				flush();

			// Continue creating page
			echo '<body>';

			if (!empty(self::$debug)) {
				echo '<div id="debugDiv" style="display: none"><pre>' .self::$debug. '</pre></div>';
				echo '<span id="debugSpan" onclick="d=document.getElementById(\'debugDiv\');d.style.display=d.style.display==\'none\'?\'\':\'none\'">DEBUG TEXT</span>';
			}
			
			// Send what we have made so far so the browser can get going
			if (!$capture)
				flush();
			
			// Show a sub template body
			$templateVars['SECTION'] = 'body';
			$templateVars['CONTENT'] = parent::output(true);
			Loader::run_script($templatePath, null, null, $templateVars);
			
			echo '</body></html>';
		}

		if ($capture)
			return ob_get_clean();
	}
}
