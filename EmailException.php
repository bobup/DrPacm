<?php

/**
 * EmailException - handle exceptions that we deliver via email
 *
 */

 
 require_once "FastestTime.php";
 require_once "PMSRecord.php";
 
 
class EmailException {
	private static $exceptions = array();		// an array of EmailException objects
	public $fastestTime;		// a FastestTime - can be null
	public $pmsRecord;			// a PMSRecord - can be null
	public $message;			// a simple string
	public $tail;				// a simple string
	public $ignoreThisException;	// if we create an exception that we later want to remove we set this to 1.
	
	
	/**
	 * __construct -
	 *
	 * PASSED:
	 * 	$ftSplash - can be NULL
	 * 	$pmsRecord - can be NULL
	 * 	$message - this is the message the preceeds this exception when displayed
	 * 	$tail - this is the message that follows this exception when displayed
	 *
	 */
	function __construct( $ftSplash, $pmsRecord, $message, $tail=null ) {
		$this->fastestTime = $ftSplash;
		$this->pmsRecord = $pmsRecord;
		$this->message = $message;
		$this->tail = $tail;
		$this->ignoreThisException = 0;			// we think this is a real exception, but that might change
		self::$exceptions[] = $this;
	} // end of __construct()
	
	
	/**
	 * TossAllExceptions - discard all of the exceptions
	 *
	 */
	static function TossAllExceptions() {
		self::$exceptions = array();
	}
	
	
	/**
	 * NumExceptions - return the number of exceptions that are not to be ignored.
	 *
	 * RETURNED:
	 * 	$result - the number of not ignored exceptions
	 *
	 */
	static function NumExceptions() {
		$result = 0;
		foreach( self::$exceptions as $key => $emailException ) {
			if( $emailException->ignoreThisException ) continue;
			$result++;
		}
		return $result;
	} // end of NumExceptions()
	
	
	
	/**
	 * GenerateExceptions - generate all of the not ignored exceptions as a string.
	 *
	 * RETURNED:
	 * 	$result - a string containing all of the exceptions that we're not supposed to ignore.
	 *
	 */
	static function GenerateExceptions() {
		$result = "";
		$count = 0;
		
		foreach( self::$exceptions as $key => $emailException ) {
			if( $emailException->ignoreThisException ) continue;
			$count++;
			$result .= "#$count:  " . $emailException->message;
			$pmsRecord = $emailException->pmsRecord;
			$fastestTime = $emailException->fastestTime;
			$result .= "\n";
			if( $pmsRecord != NULL ) {
				$result .= "Current ";
				$result .= $pmsRecord->dump();
				$result .= "The corresponding ";
			}
			if( $fastestTime != NULL ) {
				// ugh!  what a hack!  we need dump() so say something different in this one stupid case!
				if( preg_match( "/Please ask Meet Operations Coordinator/", $emailException->message ) === 1 ) {
					// special case for 0.00 time
					$result .= $fastestTime->dump( 0 );
				} else {
					$result .= $fastestTime->dump();
				}
			}
			$result .= "    $emailException->tail\n";
			$result .= "\n";
		}
		return $result;
	} // end of GenerateExceptions()
	
	
	/**
	 * RemoveExceptionForFastestTime - remove all exceptions related to the passed FastestTime object.
	 *
	 * PASSED:
	 * 	$ftSplash - a FastestTime object for which one or more exceptions may have been raised.  If there
	 * 		are any exceptions for the passed FastestTime object they will be set to "ignore".
	 *
	 */
	static function RemoveExceptionForFastestTime( FastestTime $ftSplash ) {
		foreach( self::$exceptions as $key => $emailException ) {
			if( $emailException->fastestTime == $ftSplash ) {
				$emailException->ignoreThisException = 1;
			}
		}
	} // end of RemoveExceptionForFastestTime()
	
} // end of EmailException class


?>