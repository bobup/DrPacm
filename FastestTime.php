<?php

/**
 * FastestTime class - used to contain a single "fastest time" scraped from the USMS fastest times page.
 * 	For example, see http://www.usms.org/comp/meets/lmsc_fastest_times.php?CourseID=1&LMSCID=38
 *
 */

require_once 'AbstractSplash.php';
 
 
 
class FastestTime extends AbstractSplash {
	
	private $outputAsNewRecord;				// initialize to 0 which means "we don't know yet".
											// Set to 1 if it looks like a new record and haven't seen anything to dispute that.
											// Set to -1 if this is definitely NOT a new record.  Once set to -1 it won't change.
	public $tieOrRecord;					// Set to 1 if this is a true record, 2 if it's a tie for a record.  Meaningless unless
											// $outputAsNewRecord is 1 so its value will be 0.
	public $splash;							// an AbstractSplash that is related to this splash, which usually means this is the
											// PMS record that has been replaced or tied by this fastest time.  Can be null.
	public $situation;						// remember the situation this fastest time falls into...there can be more than one
											// (if we compare to 2+ pms records due to a tie) so this is the last one we see.
	public $recordSituation;				// This is the situation that causes us to make our FINAL decision on whether or not
											// this fastest time is a new record or not.
	public $message;						// A string that represents 1 or more FATAL messages returned by add_ind_record() if this
											// fastest time is output as a new PMS record.  An empty string if this fastest time is
											// not a record or it is a record and output with no FATAL errors.
	public $recordStatus;					// The status of using this fastest time as a new record.  0=not a record, 1=output
											// of a new record was successful, 2=output of a new record failed.
	
	function __construct( $fastDetails ) {
		parent::__construct( $fastDetails );
		$this->outputAsNewRecord = 0;		// not determined to be a record...yet...
		$this->tieOrRecord = 0;				// 1 = true record, 2 = tie
		$this->situation = 0;				// no situation, yet
		$this->recordSituation = 0;		// no decision on a pms record or not, yet
		$this->message = "";			// not a record (yet...)
		$this->recordStatus = 0;		// not a record (yet...)
		$this->splash = null;			// related splash
	} // end of __construct()
	
	
	/**
	 * SetAsNewRecord - set $this as a record.
	 *
	 * PASSED:
	 * 	$theSituation - the situation that convinced us that this fastest time is a record.
	 * 	$recOrTie - 1 if this is a new record, 2 if this is a tie of a record
	 * 	$splash - the splash related to this fastest time, e.g. the PMSRecord being replaced
	 * 		by this fastest time.
	 * 		
	 * RETURNED:
	 * 	$result - true if this fastest time is really set as a record, false if it's not
	 * 		set as a record because something else has already happened to convince us that
	 * 		this fastest time MUST NOT be a record.
	 *
	 */
	function SetAsNewRecord( $theSituation, $recOrTie, $splash ) {
		$result = true;
		if( $this->outputAsNewRecord >= 0 ) {
			$this->outputAsNewRecord = 1;		// determined to be a record!
			$this->tieOrRecord = $recOrTie;
			$this->recordSituation = $theSituation;
			$this->splash = $splash;
		} else {
			$result = false;
		}
		return $result;
	} // end of SetAsNewRecord()
	
	
	/**
	 * SetAsNONRecord - set $this as a DEFINITE Non Record
	 *
	 * PASSED:
	 * 	$theSituation - the situation that made us decide that this fastest time is definately not a record.
	 *
	 * RETURNED:
	 * 	$result - true if this fastest time was thought to be a record and we had to "unset" it, false otherwise.
	 *
	 */
	function SetAsNONRecord( $theSituation ) {
		$result = false;
		if( $this->outputAsNewRecord == 1 ) {
			$result = true;			// we're going to "unset" this "record" making it a non record
		}
		$this->outputAsNewRecord = -1;		// definitely not a record!
		$this->tieOrRecord = 0;
		$this->recordSituation = $theSituation;
		return $result;
	} // end of SetAsNONRecord()
	
	
	/**
	 * GetOutputAsNewRecord - getter function to return the outputAsNewRecord field.
	 *
	 */
	function GetOutputAsNewRecord() {
		return $this->outputAsNewRecord;
	} // end of GetOutputAsNewRecord()
	
	
	/**
	 * PutIntoCanonicalForm - convert the fields into canonical form
	 *
	 * NOTES:
	 * 	Some data on the USMS page is in a form that we really can't use for various reasons.  So
	 * 	this routine will clean and convert that data into something we can use.
	 *
	 */
	function PutIntoCanonicalForm() {
		// date from USMS is the wrong format:  e.g. 4/30/17  =  mm/dd/yy
		// we need to convert it to the required format: yyyy-mm-dd
		$pieces = explode("/", $this->date );
		$month = $pieces[0];
		$day = $pieces[1];
		$year = $pieces[2];
		$currentYear = date('y');
		if( $year > $currentYear ) {
			// e.g. this year is 2018 and the fastest time date year is 19, we have to assume it's 1919.
			$year += 1900;
		} else {
			// e.g. this year is 2018 and the fastest time date year is 17 then we will assume (maybe incorrectly,
			// but that's what you get when using 2 digit years) that the fastest time year is 2017.  Same if
			// this year is 2018 and the fastest time date year is 18.  We don't bother considering the month and
			// day since this code will be executed throughout the year, so we don't want things changing
			// unexpentently.
			$year += 2000;
		}
		if( $day < 10 ) $day = "0$day";
		if( $month < 10 ) $month = "0$month";
		$this->date = "$year-$month-$day";
		
		// do the rest of the conversions...
		parent::PutIntoCanonicalForm();
		return $this;
	} // end of PutIntoCanonicalForm()
	
	
	/**
	 * dump - dump the contents of the fastest time to a string
	 *
	 * PASSED:
	 * 	n/a
	 *
	 * RETURNED:
	 *  $result - a string represenation of $this
	 *
	 */
	function dump( $normalDump = 1 ) {
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
		if( $normalDump ) {
			if( $this->tieOrRecord == 1 ) {
				$result = "New PMS Record: ";
			} else if( $this->tieOrRecord == 2 ) {
				$result = "Tied PMS Record: ";
			} else {
				$result = "PMS Fastest Time: ";
			}
		} else {
			// special case - 0.00 time
			$result = "USMS Database Problem: ";
		}
		$result .= " '$fullName' ($this->gender:$this->ageGroup, " .
			"sid=$this->sid)\n";
		$result .= "    $this->course $this->distance $this->stroke in $this->duration ($this->durationHund ms) " .
			"on $this->date for team '$this->club',\n    ";
		if( $this->splashId != "" ) {
			$result .= "splash=http://www.usms.org/comp/meets/swim.php?s=$this->splashId";
		} else {
			$result .= "splash=(unknown)";
		}
		$result .= " , key='" . $this->key . "'\n";
		return $result;
	} // end of dump()
	
} // end of class FastestTime

?>