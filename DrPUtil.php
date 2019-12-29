<?php

/**
 * DrPUtil - Utilities for DrPacm
 *
 */


/**
 * GenerateCanonicalGender - return the one letter gender designation (M or F) for the
 *	passed gender.  Return an error string (> 1 chars) if the passed gender isn't recognized.
 */
function GenerateCanonicalGender( $gender ) {
	$passedGender = strtoupper( $gender );
	$result = substr( $passedGender, 0, 1 );	// default is first letter of gender term (e.g. 'W' for 'Women')
	if( $result == 'W') $result = 'F';
	if( $result == 'G') $result = 'F';
	if( $result == 'B') $result = 'M';
	if( ($result != 'M') && ($result != 'F') ) {
		$result = "GenerateCononicalGender: generated illegal value '$result' when passed '$passedGender - " .
			"gender not returned.";
	}
	return $result;
}  // end of GenerateCononicalGender()



/**
 * GenerateNumericDuration - convert the passed text representation of a time duration into
 *	an integer representing the duration in hundredths of a second.
 *
 * PASSED:
 *	$passedDuration - the duration in text form, e.g. 1:03:33.09 (1 hour, 3 minutes, 33 seconds, 9 hundredths
 *		of a second).  At the very least it MUST have the seconds and hundredths (e.g. 32.04)
 *
 * RETURNED:
 *	$returnedDuration - the equivalent duration as an integer in hundredths of a second.
 *		Return an error string if there is an error.
 *
 * NOTES:
 *	It's common for the duration in a result file to be formatted wrong, so we try to handle durations
 *	that do not match the above specification.	
 * 
 * 	Possible formats:
 *	- THE CORRECT FORMAT:  hh:mm:ss.tt (e.g. 0:19:51.50) - 19*60*100 + 51*100 + 50  MUST HAVE AT LEAST the
 *		seconds and the hundredths (e.g. 51.50).
 * 	- . (dot) or comma or semicolons or colons are all allowed as separaters EXCEPT the hundredths MUST be
 * 		preceeded with a dot.  Each separator must be preceeded and followed by digits (e.g. ":34.44" is not
 * 		allowed.)
 *	- mm:ss.tt - assume 0:mm:ss.tt
 *
 */
function GenerateNumericDuration( $passedDuration ) {
	$returnedDuration = 0;
	$matches = preg_split( '/[.,;:]/', $passedDuration, -1, PREG_SPLIT_NO_EMPTY );
	switch( count( $matches ) ) {
		case 2:
			$returnedDuration = $matches[0]*100 + $matches[1];
			break;
		case 3:
			$returnedDuration = $matches[0]*60*100 + $matches[1]*100 + $matches[2];
			break;
		case 4:
			$returnedDuration = $matches[0]*60*60*100 + $matches[1]*60*100 + $matches[2]*100 + $matches[3];
			break;
		default:
			$returnedDuration = "GenerateNumericDuration(): Illegal duration: '$passedDuration'\n";
			break;
	} // end of switch
	return $returnedDuration;
}  // end of GenerateNumericDuration()




/**
 * GenerateFullStringDuration - convert the passed duration (in hundredths of a second) into an 11 character
 * 	string in the form "hh:mm:ss.tt"
 *
 */
function GenerateFullStringDuration( $intDuration ) {
	$hundredths = $intDuration;
	$duration = "";
	$hr = intval($hundredths / (60*60*100));
	$hundredths -= $hr*(60*60*100);
	$min = intval($hundredths / (60*100));
	$hundredths -= $min*(60*100);
	$sec = intval($hundredths / 100);
	$hundredths -= $sec*100;
	$duration = sprintf( "%02.2d:%02.2d:%02.2d.%02.02d", $hr, $min, $sec, $hundredths );
	return $duration;	

} // end of GenerateFullStringDuration()



/*
 * GenerateCanonicalStroke - turn the passsed swim stroke into the string we recognize for that stroke
 * 
 * Passed:
 *	stroke - something representing a swim stroke (free, I.M., etc)
 *
 * Returned:
 *	stroke = the canonical version of the stroke, e.g. Freestyle, IM, etc.)  If we can't figure
 *		it out we'll return an error message with a length > 20 characters.
 *
 *
 */
function GenerateCanonicalStroke( $stroke ) {
	if( preg_match( '/(?i)fly/', $stroke ) == 1 ) {
		// could be "fly" or "butter fly", etc.
		$stroke = "Butterfly";
	}
	elseif( preg_match( '/(?i)^f/', $stroke ) == 1 ) {
		// could be free, freestyle, etc
		$stroke = "Freestyle";
	}
	elseif( preg_match( '/(?i)back/', $stroke ) == 1 ) {
		// could be "back" or "Back Stroke", etc.
		$stroke = "Backstroke";
	}
	elseif( preg_match( '/(?i)breast/', $stroke ) == 1 ) {
		// could be "Breast" or "breast stroke", etc.
		$stroke = "Breaststroke";
	}
	elseif( preg_match( '/(?i)^i.*m/', $stroke ) == 1 ) {
		// could be "individual Medley" or "IM", etc.
		$stroke = "Individual Medley";
	}
	elseif( preg_match( '/(?i)medley/', $stroke ) == 1 ) {
		// could be "medley" or "medley relay", but not "Individual Medley" since we already caught that.
		$stroke = "Medley";
	}
	else {
		$stroke = "GenerateCanonicalStroke():  Invalid stroke: '$stroke'";
	}

	return $stroke;	
	
}  // end of GenerateCanonicalStroke()




/**
 * GetSwimmerNames - get the first, middle, and last names for a swimmer.
 *
 * PASSED:
 * 	$swimmerId - the USMS swimmer id of the swimmer
 * 	$fullName - the full name of the swimmer
 *
 * RETURNED:
 *	$first - will be non-empty.  It will contain an error message if the middle and last names are empty.
 *	$middle - may be empty if no middle initial or an error occurs
 *	$last - will only be empty if there is an error.
 *
 * NOTES:
 *  THIS IS A HACK!  This routine will NOT always work correctly - it's just cheap and dirty.  The problem of
 *  converting a full name into it's components is difficult, and determining the names of a swimmer from their
 *  swimmer id isn't possible (or I haven't found it yet).  So we'll just do something quick here and fix it
 *  later...
 *  Examples:
 *  	"Ann Michelle Ongerth" has two words in her first name  (WCM, sid=MB511)
 *		"Katie Bracco Comfort" has two words in her last name  (M is her middle initial) (MELO, sid=03US8)
 *
 */
function GetSwimmerNames_notused( $swimmerId, $fullName ) {
	$first = $middle = $last = "";
	// for now just bust the full name into pieces
	$names = array();
	$names = explode( " ", $fullName );
	$numNames = count( $names );
	if( $numNames <= 1 ) {
		$first = "GetSwimmerNames(): Failed to find more than one name in '$fullName'";
		$middle = $last = "";
	} elseif( $numNames == 2 ) {
		// assume no middle initial
		$first = $names[0];
		$last = $names[1];
	} else {
		// 3 or more names!
		// find the middle inital if any (hack!  sometimes the middle initial is multiple characters!)
		for( $middleIndex=0; $middleIndex < $numNames; $middleIndex++ ) {
			if( strlen( $names[$middleIndex]) == 1 ) {
				// found "the" middle initial
				break;
			}
		}
		if( $middleIndex < ($numNames - 1) ) {
			// found a middle name
			for( $i=0; $i<$middleIndex; $i++ ) {
				$first .= $names[$i];
			}
			$middle = $names[$middleIndex];
			for( $i=$middleIndex+1; $i<$numNames; $i++ ) {
				$last .= $names[$i];
			}
		} else {
			// no obvious middle initial..we're going to punt...
			$first = $names[0];
			for( $i = 1; $i < $numNames; $i++ ) {
				$last .= $names[$i];
			}
		}
	}
	
	return array( $first, $middle, $last );	
	
}  // end of GetSwimmerNames()



// 		$key = PerfectHash( strtolower($obj->gender), $obj->ageGroup, $obj->distance, strtolower($obj->stroke) );
/**
 * PerfectHash - construct a hash of the passed parameters.  Guaranteed to be perfect (no other combination of
 * 	parameters will generate the same hash)
 *
 * PASSED:
 * 	$gender - either 'm' or 'f'
 * 	$ageGroup - in the form xx-yy, where xx is no longer than 3 dights and is not part of any other age group
 * 	$distance - an integer <= 1650
 * 	$stroke - one of the canonical strokes (see GenerateCanonicalStroke()) but in lower case
 *
 * RETURNED:
 * 	$key - a 9 to 12 character string, e.g.     m-55-1000-2
 *
 */
function PerfectHash( $gender, $ageGroup, $distance, $stroke ) {
	$ageGroupKey = preg_replace( '/-.*$/', '', $ageGroup );
	$strokeKey = 0;
	switch( $stroke ) {
		case "butterfly":  $strokeKey = 1; break;
		case "freestyle":  $strokeKey = 2; break;
		case "backstroke":  $strokeKey = 3; break;
		case "breaststroke":  $strokeKey = 4; break;
		case "individual medley":  $strokeKey = 5; break;
		case "medley":  $strokeKey = 6; break;
		default:  echo "PerfectHash didn't understand the passed stroke: '$stroke'\n";
	}
	
	// assemble the key:	
	$key = "$gender-$ageGroupKey-$distance-$strokeKey";
	return $key;	
} // end of PerfectHash()



/**
 * BaseKey - return the base part of the passed key.
 *
 * PASSED:
 * 	$key - a string created by PerfectHash() (above), or a collision key, in one of these forms:
 * 		m-55-1000-2				(key created by PerfectHash)
 * 		or
 * 		m-55-1000-2--1			(key created due to a key collision)
 *
 * RETURNED:
 * 	the base key, i.e. the part of the key preceeding the '--'.  The full $key if it didn't contain a '--'
 *
 */
function BaseKey( $key ) {
	// if this key is the key to a collision then remove the extra part of the key
	if( ($pos = strpos( $key, "--" )) > 0 ) {
		// got a collision key - convert to normal key
		$key = substr( $key, 0, $pos );
		// this fastest time is a tie with another
		// fastest time.  If the other fastest time is a record so should this one be.
	}
	return $key;
} // end of BaseKey()


/**
 * CollisionIndex - return the collision index of the passed key
 *
 * PASSED:
 * 	$key - a string created by PerfectHash() (above), or a collision key, in one of these forms:
 * 		m-55-1000-2				(key created by PerfectHash)
 * 		or
 * 		m-55-1000-2--1			(key created due to a key collision)
 *
 * RETURNED:
 * 	$collisionIndex - the part of the key following the '--'.  0 if the key didn't contain a '--'
 *
 */
function CollisionIndex( $key ) {
	$collisionIndex = 0;
	// if this key is the key to a collision then get the extra part of the key
	if( ($pos = strpos( $key, "--" )) > 0 ) {
		// got a collision key - get the collision index
		$pos += 2;		// point to first (left-most) digit of collision index
		$collisionIndex = substr( $key, $pos );
	}
	return $collisionIndex;
} // end of CollisionIndex()

 
 
/**
 * SpecialCases - look for "special cases" when comparing a record to a fastest time.
 *
 * PASSED:
 * 	$fastestTime - a FastestTime object
 * 	$pmsRecord - a PMSRecord object
 *
 * RETURNED:
 * 	$result - a string that describes special cases when the passed fastest time is compared
 * 		to the passed pms record, or an empty string if there are no special cases found.
 *
 */
function SpecialCases( FastestTime $fastestTime, PMSRecord $pmsRecord ) {
	$result = "";
	$fastDate = $fastestTime->date;
	$recordDate = $pmsRecord->date;

	// are the two dates "the same" (close to each other)
	if( $fastestTime->CloseDates( $pmsRecord ) ) {
		$result .= "  NOTE: the two splashes are " . FastestTime::$maxSplashDiff . " or less days apart!\n";
	}
	
	// first, the case where the PMS record date is YYYY-12-31 (bogus date!)
	$fastYear = array();
	$recYear = array();
	$xxx = preg_match( '/(....)-12-31/', $recordDate, $recYear );
	$yyy = preg_match( '/^(....)/', $fastDate, $fastYear );
	if( ($xxx == 1) && ($yyy == 1) && ($recYear[1] != $fastYear[1]) ) {
		// both swims are in the different years, and the record year is BOGUS!
		$result .= "  NOTE: the two splashes are in different years, and the " .
			"record year is BOGUS!\n";
	}
	return $result;					
} // end of SpecialCases()



/**
 * SplashOlderThan1Year - return true if the passed splash is older than one year from now.
 *
 */
function SplashOlderThan1Year( AbstractSplash $splash ) {
	$splashTime = strtotime( $splash->date );
	$result = ($splashTime + 365*24*60*60) < strtotime("now");
	return $result;
} // end of SplashOlderThan1Year()



/**
 * SimpleArrayCopy - helper routine used to copy an array into another array
 *
 * PASSED:
 * 	$array - the source of the copy
 *
 * RETURNED:
 * 	$result - a copy of the passed $array
 *
 */
function SimpleArrayCopy( array $array ) {
	$result = array();
	foreach( $array as $key => $value ) {
		$result[$key] = $value;
	}
	return $result;
} // end of SimpleArrayCopy()


?>