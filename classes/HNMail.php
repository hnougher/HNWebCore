<?php
/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/. */
/**
* A wrapper for using the PEAR::Mail classes.
*
* @author Hugh Nougher <hughnougher@gmail.com>
* @version 2.3
* @package HNWebCore
*/

class HNMail
{
	private static $Mail;
	private $MailMime;
	private $headers;
	private $listTo = array();
	private $listCc = array();
	private $listBcc = array();
	
	public function __construct() {
		if (!defined('MAIL_BACKEND'))
			throw new Exception('MAIL_BACKEND constant is not defined!');
		if (!defined('SERVER_EMAIL'))
			throw new Exception('SERVER_EMAIL constant is not defined!');
		
		if (!isset($Mail)) {
			require_once 'Mail.php';
			require_once 'Mail/mime.php';
			
			// See: http://pear.php.net/manual/en/package.mail.mail.factory.php
			$params = array();
			if (defined('MAIL_SENDMAIL_PATH')) $params['sendmail_path'] = MAIL_SENDMAIL_PATH;
			if (defined('MAIL_SENDMAIL_ARGS')) $params['sendmail_args'] = MAIL_SENDMAIL_ARGS;
			if (defined('MAIL_SMTP_HOST')) {
				$HOST = explode(':', MAIL_SMTP_HOST);
				$params['host'] = $HOST[0];
				if (isset($HOST[1]))
					$params['port'] = $HOST[1];
			}
			if (defined('MAIL_SMTP_AUTH')) {
				$AUTH = explode(':', MAIL_SMTP_AUTH);
				$params['auth'] = true;
				$params['username'] = $AUTH[0];
				if (isset($AUTH[1]))
					$params['password'] = $AUTH[1];
			}
			if (defined('SERVER_ADDRESS')
				&& preg_match('|^(?:(?:http:)?//)?([^/]+).*$|', SERVER_ADDRESS, $MATCHES)) {
					$params['localhost'] = $MATCHES[1];
			}
			if (defined('MAIL_SMTP_TIMEOUT')) $params['timeout'] = MAIL_SMTP_TIMEOUT;
			#if (defined('MAIL_SMTP_VERP')) $params['verp'] = MAIL_SMTP_VERP;
			if (defined('MAIL_SMTP_DEBUG')) $params['debug'] = MAIL_SMTP_DEBUG;
			#if (defined('MAIL_SMTP_PIPELINEING')) $params['pipelining'] = MAIL_SMTP_PIPELINEING;
			
			// PERSIST causes problems with the error cancelling so we handle it differently
			// Mostly we force disconnect() after sending emails via send() or sendSingle().
			if (defined('MAIL_SMTP_PERSIST')) $params['persist'] = MAIL_SMTP_PERSIST;
			
			ErrorHandler::$processErrors = false;
			self::$Mail = new Mail();
			self::$Mail = self::$Mail->factory(MAIL_BACKEND, $params);
			ErrorHandler::$processErrors = true;
		}
		
		ErrorHandler::$processErrors = false;
		$this->MailMime = new Mail_Mime(array('eol' => "\n"));
		ErrorHandler::$processErrors = true;
		
		$this->headers = array(
			'From'		=> SERVER_EMAIL,
			'Subject'	=> 'Message from ' . SERVER_IDENT,
			);
	}
	
	/**
	* Adds a To address to the final list.
	* @param $address the email address to add to the list.
	*/
	public function TO($address) {
		$this->listTo[] = $address;
	}
	
	/**
	* Adds a CC address to the final list.
	* @param $address the email address to add to the list.
	*/
	public function CC($address) {
		$this->listCc[] = $address;
	}
	
	/**
	* Adds a BCC address to the final list.
	* @param $address the email address to add to the list.
	*/
	public function BCC($address) {
		$this->listBcc[] = $address;
	}
	
	/**
	* Sets the email subject to the given string.
	* @param $text
	*/
	public function subject($text) {
		$this->headers['Subject'] = $text;
	}
	
	/**
	* Sets the text version of the email body to the given text.
	* @param $text
	*/
	public function bodyText($text) {
		ErrorHandler::$processErrors = false;
		$this->MailMime->setTXTBody($text);
		ErrorHandler::$processErrors = true;
	}
	
	/**
	* Sets the HTML version of the email body to the given HTML.
	* @param $html
	*/
	public function bodyHTML($html) {
		ErrorHandler::$processErrors = false;
		$this->MailMime->setHTMLBody($html);
		ErrorHandler::$processErrors = true;
	}
	
	/**
	* Attached a file from the filesystem to the email.
	* @param $path The path to the file to attach. Recommend absolute path to file.
	* @param $type The content-type of the file being attached.
	* @param $enc The encoding to use for this data in the email. Text data could use quoted-printable.
	*/
	public function attachFile($path, $type = 'application/octet-stream', $enc = 'base64') {
		ErrorHandler::$processErrors = false;
		$this->MailMime->addAttachment($path, $type, '', true, $enc);
		ErrorHandler::$processErrors = true;
	}
	
	/**
	* Attached a file from variable data to the email.
	* @param $name The name for the file being attached.
	* @param $data The data content of the file getting attached.
	* @param $type The content-type of the file being attached.
	* @param $enc The encoding to use for this data in the email. Text data could use quoted-printable.
	*/
	public function attachData($name, $data, $type = 'application/octet-stream', $enc = 'base64') {
		ErrorHandler::$processErrors = false;
		$this->MailMime->addAttachment($data, $type, $name, false, $enc);
		ErrorHandler::$processErrors = true;
	}
	
	/**
	* Gets the headers which will be used to send the email.
	*/
	private function getHeaders() {
		unset($this->headers['To'], $this->headers['CC'], $this->headers['BCC']);
		if (count($this->listTo))
			$this->headers['To'] = implode(', ', $this->listTo);
		if (count($this->listCc))
			$this->headers['Cc'] = implode(', ', $this->listCc);
		if (count($this->listBcc))
			$this->headers['Bcc'] = implode(', ', $this->listBcc);
		return $this->MailMime->headers($this->headers);
	}
	
	/**
	* Send the email to all addresses in the To, Cc and Bcc lists.
	*/
	public function send() {
		ErrorHandler::$processErrors = false;
		$headers = $this->getHeaders();
		$body = $this->MailMime->get();
		
		// Send to all
		foreach ($this->listTo as $to)
			self::$Mail->send($to, $headers, $body);
		foreach ($this->listCc as $to)
			self::$Mail->send($to, $headers, $body);
		foreach ($this->listBcc as $to)
			self::$Mail->send($to, $headers, $body);
		
		if (method_exists(self::$Mail, 'disconnect'))
			self::$Mail->disconnect();
		ErrorHandler::$processErrors = true;
	}
	
	/**
	* Send the email to just the given address.
	*/
	public function sendSingle($to) {
		ErrorHandler::$processErrors = false;
		$headers = $this->getHeaders();
		$body = $this->MailMime->get();
		
		self::$Mail->send($to, $headers, $body);
		
		if (method_exists(self::$Mail, 'disconnect'))
			self::$Mail->disconnect();
		ErrorHandler::$processErrors = true;
	}
}
