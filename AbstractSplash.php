<?php

/**
 * AbstractSplash - this class holds the details of a splash.  It's used as a superclass for classes
 * that represent records or "fastest times".
 */

require_once 'DrPUtil.php';
 
 
abstract class AbstractSplash {
	public $gender;			// either 'F' or 'M'.  Always use case-insensitive compare
	public $ageGroup;		// xx-yy, where xx and yy are 2 or more digits
	public $distance;		// Must be one of:  50, 100, 200, 400, 500, 800, 1000, 1500, and 1650
	public $stroke;			// Must be one of:  Freestyle, Butterfly, Backstroke, Breaststroke, or Individual Medley
							//   Always use case-insensitive compare
	public $first;			// USMS registered name.  Always use case-insensitive compare
	public $mi;				// USMS registered name.  Always use case-insensitive compare
	public $last;			// USMS registered name.  Always use case-insensitive compare
	public $fullname;		// we get this from USMS, and if we get this from PMS records then we'll use it instead of
							// parsed names (above)
	public $age;			// age at time of splash
	public $club;			// club at time of splash.    Always use case-insensitive compare
	public $date;			// date at time of splash in the form YYYY-MM-DD
	public $duration;		// the duration of the swim - see below for details
	public $durationHund;	// the $duration converted to an interger number of hundredths of a second.
	public $sid;			// USMS swimmer id - a sequence of 5 capitalized alphanumeric characters.
							//   Always use case-insensitive compare
	public $splashId;		// A sequence of 7 or fewer digits specified by USMS to identify the splash
	public $course;			// one of SCY, SCM, or LCM.  Always use case-insensitive compare
	public $ftime;			// an integer in the range 0-4 assigned by PMS.
	public $key;			// a hash of this object's gender, age group, distance, and stroke.
	
	public static $maxSplashDiff = 5;	// our definition of  2 dates being close' to each other (in days)

	/*
	 * More on duration:  the duration of the swim, in the form Òh:m:s.tÕ where:
	 *	ÔhÕ is hours part of the duration.  Can be 0 or more digits.  If 0 digits the Ôh:Õ must be missing,
	 *		making the duration be in the form Ôm:s.tÕ.  Ô0Õ or Ô00Õ are valid.
	 *	ÔmÕ is the minutes part of the duration.  Must be present.  Must be 1 or 2 digits.
	 *	ÔsÕ is the seconds part of the duration.  Must be present.  Must be 1 or 2 digits.
	 *	ÔtÕ is the fractional part of a second which is part of the duration.  Must be 1 or more digits.
	 *		If only one digit it represents tenths of a second, if 2 digits it represents hundredths,
	 *		if 3 digits then thousandths, etc.
	*/

	function __construct( $splashDetails ) {
		$this->ftime = 1;		// this is only defined for PMS Records, so we'll initialize it here and
								// that way it will be initialized regardless of the type of object
								// this AbstractSplash really is.
		foreach( $splashDetails as $key=>$value ) {
			$this->$key = $value;
		}
	} // end of __construct()
	
	
	/**
	 * PutIntoCanonicalForm - modify some fields of $this to put them into a standard form.
	 *
	 * PASSED:
	 *  n/a
	 *
	 * RETURNED:
	 * 	$this - return the object acted upon
	 *
	 */
	function PutIntoCanonicalForm() {
		$errStr = "";
		$this->gender = GenerateCanonicalGender( $this->gender );
		if( count($this->gender) > 1 ) {
			echo "$this->gender\n";
			$this->gender = "?";
		}
		$this->durationHund = GenerateNumericDuration( $this->duration );
		if( ! is_numeric( $this->durationHund  ) ) {
			echo "$this->durationHund\n";
			$this->durationHund = 9999999.99;
		}
		$fixedStroke = GenerateCanonicalStroke( $this->stroke );
		if( count( $fixedStroke ) > 20 ) {
			echo "$fixedStroke\n";
		} else {
			$this->stroke = $fixedStroke;
		}
		
		return $this;
	} // end of PutIntoCanonicalForm()
	
	
	/**
	 * SameSplash - return TRUE if the passed splash identifies the same splash as $this.
	 *
	 * PASSED:
	 * 	$splash - the splash we will compare $this to.
	 *
	 * RETURNED:
	 * 	$result - true if they are the same splash, false otherwise.
	 *
	 * NOTES:
	 * 	By definition they are the same splash if:
	 * 		- both have a splashId and they are the same, OR
	 * 		- both have the same $key, AND
	 * 			- same course
	 * 			- same duration
	 * 			- same swimmer
	 * 			- same date
	 *
	 */
	function SameSplash( AbstractSplash $splash ) {
		$result = false;
		
		// first check the splashId.  If they are the same then we're done.  But if either is not
		// set then we keep checking.
		if( (isset( $this->splashId) && ($this->splashId != "") ) &&
			(isset( $splash->splashId) && ($splash->splashId != "") ) ) {
				if( $this->splashId == $splash->splashId ) {
					$result = true;
				}
			}
		
		// next check the keys - the base of each key (hash of Gender, age group, distance, and stroke)
		// have to be the same if they are the same splash.
		if( !result ) {
			$baseThisKey = BaseKey( $this->key );
			$baseSplashKey = BaseKey( $splash->key );
			if( $baseThisKey == $baseSplashKey ) {
				
				// Just because the keys are the same doesn't mean they are the same splash.
				// Now check a few other things before we commit...
				if( ($this->course == $splash->course) &&					// same course
					($this->durationHund == $splash->durationHund) &&		// same duration
					(! $this->$DifferentSwimmer( $splash ) ) &&				// same swimmer
					($this->SameDateAs( $splash ) ) ) {						// same date
					// looks the same to me!
					$result = true;											// same splash!
				}
			}
		}
		
		return $result;
	} // end of SameSplash()
	
	
	
	/**
	 * CloseDates - look at the dates of two splashes and return 1 if they are "close" to each other,
	 * 	0 otherwise.
	 *
	 * PASSED:
	 * 	$splash - the splash we will compare $this to.
	 *
	 * RETURNED:
	 * 	$result - 1 if they are the same splash, 0 otherwise.
	 *
	 */
	function CloseDates( AbstractSplash $splash ) {
		$result = 0;				// assume they are not close
		$days = 0;
		$thisDate = new DateTime( $this->date );
		$splashDate = new DateTime( $splash->date );
		$interval = $thisDate->diff( $splashDate, true );
		if( $interval === false ) {
			echo "AbstractSplash.php::CloseDates(): failed to compute date diff between thisDate ($this->date) " .
				"and splashDate ($splash->date)\n  -- this DateTime object:\n";
			var_dump( $thisDate );
			echo " --splash DateTime object:\n";
			var_dump( $splashDate );
		} else {
			$days = $interval->days;
			if( ($days <= self::$maxSplashDiff) && ($days >= 0) ) {
				$result = 1;
			}
		}
		return $result;
	} // end of CloseDates()
	
	
	/**
	 * NewerThan - return true if the date of $this is newer than the date of $splash.
	 *
	 * PASSED:
	 * 	$splash - the splash we will compare $this to.
	 *
	 * RETURNED:
	 * 	$result - true if the date of $this is newer than the date of $splash, false otherwise.
	 *
	 * NOTES:
	 * 	"newer than" means that $this->date is actually newer than (numerically greater than) $splash->date + $maxSplashDiff days.
	 *
	 */
	function NewerThan( AbstractSplash $splash ) {
		$splashTime = strtotime( $splash->date );
		$thisTime = strtotime( $this->date );
		$maxSplashDiffSeconds = self::$maxSplashDiff * 24*60*60;
		$result = ($splashTime + $maxSplashDiffSeconds) < $thisTime;
		return $result;
	} // end of NewerThan()
	
	
	
	/**
	 * NewerThanOrEqual - return true if the date of $this is newer than OR THE SAME AS the date of $splash.
	 *
	 * PASSED:
	 * 	$splash - the splash we will compare $this to.
	 *
	 * RETURNED:
	 * 	$result - true if the date of $this is newer than OR THE SAME AS the date of $splash, false otherwise.
	 *
	 * NOTES:
	 * 	"newer or the same as" means that $this->date is actually newer than (numerically greater than)
	 * 		or equal to $splash->date + $maxSplashDiff days.
	 *
	 */
	function NewerThanOrEqual( AbstractSplash $splash ) {
		$splashTime = strtotime( $splash->date );
		$thisTime = strtotime( $this->date );
		$maxSplashDiffSeconds = self::$maxSplashDiff * 24*60*60;
		$result = ($splashTime + $maxSplashDiffSeconds) <= $thisTime;
		return $result;
	} // end of NewerThanOrEqual()
	
	
	
	/**
	 * OlderThan - return true if the date of $this is older than the date of $splash
	 *
	 * PASSED:
	 * 	$splash - the splash we will compare $this to.
	 *
	 * RETURNED:
	 * 	$result - true if the date of $this is older than the date of $splash, false otherwise.
	 *
	 * NOTES:
	 * 	"older than" means that $this->date is older than (numerically less than) $splash->date + $maxSplashDiff days.
	 *
	 */
	function OlderThan( AbstractSplash $splash ) {
		$splashTime = strtotime( $splash->date );
		$thisTime = strtotime( $this->date );
		$maxSplashDiffSeconds = self::$maxSplashDiff * 24*60*60;
		$result = $thisTime < ($splashTime - $maxSplashDiffSeconds);
	return $result;

	} // end of OlderThan()

	
	
	/**
	 * OlderThanOrEqualTo - return true if the date of $this is older than or equal to the date of $splash
	 *
	 * PASSED:
	 * 	$splash - the splash we will compare $this to.
	 *
	 * RETURNED:
	 * 	$result - true if the date of $this is older than or equal to the date of $splash, false otherwise.
	 *
	 * NOTES:
	 * 	"older than or equal to" means that $this->date is older than or equal to (numerically less
	 * 		than or equal to) $splash->date + $maxSplashDiff days.
	 *
	 */
	function OlderThanOrEqualTo( AbstractSplash $splash ) {
		$splashTime = strtotime( $splash->date );
		$thisTime = strtotime( $this->date );
		$maxSplashDiffSeconds = self::$maxSplashDiff * 24*60*60;
		$result = $thisTime <= ($splashTime + $maxSplashDiffSeconds);
	return $result;

	} // end of OlderThanOrEqualTo()

	
	
	/**
	 * SameDateAs - return true if the date of $this is the same as the date of $splash
	 *
	 * PASSED:
	 * 	$splash - the splash we will compare $this to.
	 *
	 * RETURNED:
	 * 	$result - true if the date of $this is the same as the date of $splash, false otherwise.
	 *
	 * NOTES:
	 * 	"same as" means that $this->date is the same as $splash->date + or - $maxSplashDiff days.
	 *
	 */
	function SameDateAs( AbstractSplash $splash ) {
		$splashTime = strtotime( $splash->date );
		$thisTime = strtotime( $this->date );
		$maxSplashDiffSeconds = self::$maxSplashDiff * 24*60*60;
		$result = ($thisTime >= ($splashTime - $maxSplashDiffSeconds)) &&
				  ($thisTime <= ($splashTime + $maxSplashDiffSeconds));
	return $result;

	} // end of SameDateAs()

	
	/**
	 * DifferentYear - return true if the passed  splash occurred in a different year from $this splash.
	 *
	 * PASSED:
	 * 	$splash - the splash we will compare $this to.
	 *
	 * RETURNED:
	 * 	$result - true if the passed  splash occurred in a different year from $this splash, false otherwise.
	 *
	 */
	function DifferentYear( AbstractSplash $splash ) {
		$thisYear = preg_replace( "/-.*$/", "", $this->date );
		$splashYear = preg_replace( "/-.*$/", "", $splash->date );
		$result = $thisYear != $splashYear;
		return $result;
	} // end of DifferentYear()
	
	
	
	
	/**
	 * GetLowerAgeGroup - return the lowest age of someone in the age group of $this
	 *
	 * PASSED:
	 * 	n/a
	 *
	 * RETURNED:
	 * 	$result - the lowest age (in years) of a person in the age group of $this.
	 * 		For example, if the age group is "18-24" then the lowest age is "18"
	 *
	 */
	function GetLowerAgeGroup() {
		$result = $this->ageGroup;
		$result = preg_replace( "/-.*$/", "", $result );
		return $result;
	} // end of GetLowerAgeGroup()
	
	
	/**
	 * Get11Duration - return the duration of $this as an 11 character string (exactly).
	 *
	 * RETURNED:
	 * 	time (duration) of $this as hh:mm:ss.tt, must be 11 characters, only digits and Ò:Ó and Ò.Ó
	 *
	 */
	function Get11Duration() {
		$result = GenerateFullStringDuration( $this->durationHund );
		return $result;
	} // end of Get11Duration()
	
	
	/**
	 * ConvertStrokeName - convert $this->stroke into a name that is accptable to add_ind_record().
	 *
	 * RETURNED:
	 * 	$result - the stroke name acceptable to add_ind_record()
	 *
	 */
	function ConvertStrokeName() {
		$result = "$this->stroke";
		switch( $result ) {
			case 'Freestyle':
				$result = "Free";
				break;
			case 'Butterfly':
				$result = "Fly";
				break;
			case 'Backstroke':
				$result = "Back";
				break;
			case 'Breaststroke':
				$result = "Breast";
				break;
			case 'Individual Medley':
				$result = "I.M.";
				break;
			default:
				echo "ConvertStrokeName: illegal stroke: '$result' - dump of splash:\n";
				$this->dump();
		}
		return $result;
	} // end of ConvertStrokeName()
	
	
	
	
	
	
	/**
	 * DifferentSwimmer - compare the swimmers of two splashes and return true if they are different,
	 * 	false otherwise.
	 *
	 * NOTES:
	 * 	Since we can't use names in our logic (as of Jan 31, 2018) the only way we can compare swimmers is
	 * 	by their swimmer id.  And since we may not get a swimmer id with a PMS record this means that this
	 * 	function isn't all that useful.  We're keeping it for hopefull use later.  For not it's not used.
	 * 	
	 */
	function DifferentSwimmer_NOTUSED( AbstractSplash $splash ) {
		$result = true;
		
		// first, if two swimmers have the same USMS swimmer id then they are the same person
		if( (isset( $this->sid) && ($this->sid != "") ) &&
		    (isset( $splash->sid) && ($splash->sid != "") ) ) {
			if( strcasecmp( $this->sid, $splash->sid ) == 0 ) {
				// the two swimmer id's are the same - they are not different people
				$result = false;
			} else {
				// the two swimmer id's are different - they are (probably) different people
				// (caveat:  they got a vanity id, thus the same person has two USMS ids.  We should
				// handle this case but we don't do it yet!)
				$result = true;
			}
		}
		
		/*
		 * As of Jan 31, 2018 it was decided that I MUST NOT use names to compare splashes, or for any other
		 * reason, other than for logging.  So this part of the code is removed.
		 */
		/*********************************************************************************************
		else {
			// one or both swimmers don't have a sid - we will have to use their names.
			// Note that this isn't great, since two people can have exactly the same name.
			// But we don't have a better way yet...
			// NOTE: if we have $fullname for $this and for $splash then we're going to use that instead
			// of the parsed names.  Otherwise we'll use the parsed names.
			if( (isset( $this->fullname ) && ($this->fullname != "")) &&
				(isset( $splash->fullname ) && ($splash->fullname != "")) ) {
				if( strcasecmp( $this->fullname, $splash->fullname ) == 0 ) {
					$result = false;
				} else {
					$result = true;
				}
			} else {
				// we don't have the full name in both objects - use the parsed name:
				if( (strcasecmp( $this->first, $splash->first ) == 0) &&
					(strcasecmp( $this->mi, $splash->mi ) == 0) &&
					(strcasecmp( $this->last, $splash->last ) == 0) ) {
					// the two swimmers have the same name
					$result = false;
				} else {
					$result = true;
				}
			}
		}
		**********************************************************************************************/

		return $result;
	} // end of DifferentSwimmer()
	
	
	// child classes must implement this function on their own
	abstract function dump();
	
} // end of class AbstractSplash



?>