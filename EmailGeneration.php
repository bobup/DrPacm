<?php

/**
 * EmailGeneration - Generate and send emails
 *
 */

 
require_once "FastestTime.php";
 
 
class EmailGeneration {
	private $emailContent;			// string...
	private $to;					// to whom this email will be sent
	private $subject;				// the subject of this email
	private $headers;				// headers for the email
	
	
	/**
	 * __construct -
	 *
	 * PASSED:
	 * 	$message - the initial content of the email message
	 *
	 * NOTES:
	 * 	The '$to', the '$subject', and the '$headers" fields of this object have default
	 * 	settings but they can be changed after the object is constructed.
	 *
	 */
	function __construct( $message ) {
		$this->emailContent = $message;
		$this->to = "New PAC Records <newrecords@pacificmasters.org>";
		$this->subject = "";
		$this->headers = 'From: DrPacm <drpacm@pacificmasters.org>' . "\r\n" .
			'Reply-To: PAC Webmaster <webmaster@pacificmasters.org>' . "\r\n" .
			'X-Mailer: PHP/' . phpversion();
	} // end of __construct()
	
	
	/**
	 * AddToContent - append the passed $message to the content of this email.
	 *
	 */
	function AddToContent( $message ) {
		$this->emailContent .= $message;
	} // end of AddToContent()
	
	
	/**
	 * AddToSubject - append the passed message to the subject of this email.
	 *
	 */
	function AddToSubject( $message ) {
		$this->subject .= $message;
	} // end of AddToSubject()
	
	
	/**
	 * ChangeTo - replace the current $this->to with the passed $to
	 *
	 */
	function ChangeTo( $to ) {
		$this->to = $to;
	} // end of ChangeTo()
	
	
	/**
	 * ChangeSubject - replace the current $this->subject with the passed $subject
	 *
	 */
	function ChangeSubject( $subject ) {
		$this->subject = $subject;
	} // end of ChangeSubject()
	
	
	/**
	 * SendEmail - send the email as specified by $this.
	 *
	 */
	function SendEmail() {
		mail($this->to, $this->subject, $this->emailContent, $this->headers);
		echo "\n-------------------------- Begin Email --------------------------\n";
		echo "Mail sent to: $this->to\n";
		echo "Subject:  $this->subject\n";
		echo "Headers:  $this->headers\n";
		echo "Content: $this->emailContent\n";
		echo "-------------------------- End Email --------------------------\n";
	} // end of SendEmail()
	
} // end of EmailGeneration class


?>