<?php

/**
 * PMSRecord class - used to contain a single PMS record retrieved from the PMS database.
 *
 */

require_once 'AbstractSplash.php';
 
 
 
class PMSRecord extends AbstractSplash {
	
	function __construct( $recordDetails ) {
		parent::__construct( $recordDetails );
	}
	
	/**
	 * BogusDate - determine whether or not the pms record has a "bogus" date.
	 *
	 * PASSED:
	 * 	n/a
	 *
	 * RETURN:
	 * 	$result - true if the date of $this is bogjs (has a date of 31st of December for some year),
	 * 		false otherwise.
	 *
	 */
	function BogusDate() {
		$xxx = preg_match( '/(....)-12-31/', $this->date );
		$result = $xxx == 1;
		return $result;
	} // end of BogusDate()
	
	
	
	/**
	 * dump - dump the contents of the pms record to a string
	 *
	 * PASSED:
	 * 	n/a
	 *
	 * RETURNED:
	 *  $result - a string represenation of $this
	 *
	 */
	function dump() {
		$result = "";
		$fullName = $this->fullname;
		// if we have a parsed name use that instead:
		if( (isset( $this->last )) && ($this->last != "") ) {
			// we have parsed names
			$middle = "";
			if( $this->mi != "" ) {
				$middle = " '$this->mi'";
			}
			$fullName = "$this->first'$middle '$this->last";
		}
		$result = "PMS Record: '$fullName' ($this->gender:$this->ageGroup, sid=$this->sid) [ftime=$this->ftime]\n";
		$result .= "    $this->course $this->distance $this->stroke in $this->duration ($this->durationHund ms) " .
			"on $this->date for team '$this->club',\n    ";
		if( $this->splashId != "" ) {
			$result .= "splash=http://www.usms.org/comp/meets/swim.php?s=$this->splashId";
		} else {
			$result .= "splash=(unknown)";
		}
		$result .= " , key='" . $this->key . "'\n";
			
		// look for suspicious situation:
		$recYear = array();
		$xxx = preg_match( '/(....)-12-31/', $this->date, $recYear );
		if( $xxx == 1 ) {
			// the record was set on December 31?  Ha!  This is bogus!
			$result .= "    NOTE: the record year is BOGUS!\n";
		}
		
		// is the date of this record <= 2005?
		$recordDate = $this->date;
		$recYear = array();
		$xxx = preg_match( '/^(....)/', $recordDate, $recYear );
		if( ($xxx == 1) && ($recYear[1] <= 2005) ) {
			$result .= "    NOTE: the record was set before 2006!\n";
		}
		return $result;
	}
	
} // end of class PMSRecord

?>