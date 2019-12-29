#!/usr/local/bin/php
<?php


/**
 * DrPacm - this is the main (and only) entry point for the DrPacm (Derive Records for PACific Masters) script.
 *
 */

// are we running on our production server or dev server?
$server = "Production";
$currentUser = get_current_user();
if( $currentUser == "pacdev" ) {
	$server = "Development";
}

set_include_path( get_include_path() . PATH_SEPARATOR . "/usr/home/$currentUser/Library");
 
require_once 'pacminc.php';
require_once 'pacmfncn.php';
require_once 'Callbacks.php';
require_once 'RecordCollection.php';
require_once 'PMSRecord.php';
require_once 'EmailException.php';
require_once 'EmailGeneration.php';

/*
 * UpdateDB - set to 1 if DrPacm is allowed to update the PAC database if new records are discovered, or
 * 			set to 0 if DrPacm must not update the PAC database.  If set to anything else we'll abort!
 */
$UpdateDB = 1;


//error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
// Report all PHP errors
error_reporting(E_ALL);

// this is the set of courses we'll process
$myCourses = array(
	"SCY"	=>	1,
	"LCM"	=>	2,
	"SCM"	=>	3,
);

// this is the set of genders we'll care about
$myGenders = array(
	"Women",
	"Men",
);

// this is the list of age groups we'll care about
$myAgeGroups = SimpleArrayCopy( $agegroups );

$todaysDateFormat = "l, F j, Y  g:i:s a e";
$todaysStartingDate = date( $todaysDateFormat );

$programSimpleName = pathinfo( __FILE__, PATHINFO_FILENAME );

// CURRENT_FASTEST_PMS_RECORD is the 'ftime' of a current PMS Record, as defined in the spec
// "Maintenance, Storage, and Export of Pacific Masters Pool Records" by Caroline Lambert.
define( "CURRENT_FASTEST_PMS_RECORD", 1 );
////////////////////////////////////////////  Logging  //////////////////////////////////////////////////////////


/**
 * The following are used for logging:
 **/
$titleSituations = array(
	"",				// no situation #0 - we start at 1
	"The fastest time distance is < 25 y/m.",
	"The fastest time is 0.00.",																			// 2
	"A fastest time exists for a specific gender, age group, distance, and stroke\n  where a" .
		"corresponding PMS record doesn't exist.",															// 3
	"The time, date, gender, age group, distance, and stroke of the swim matches\n  an existing PAC " .		// 4
		"Record of any status (ftime),",
	"The Fastest time is a New Record!",																	// 5
	"The Fastest time is a Tie with an existing record.",													// 6
	"The Fastest time is the same as a PMS record, and is older or same splash id",							// 7
	"ERROR:  The PMS Record is FASTER than 'fastest time'!  (USMS should fix this.)",						// 8
	"ERROR:  We have PMS Records where there is no 'fastest time'  (USMS should fix these.)",				// 9
);
/*
 * $printSituations is used to control printing of situations found when comparing fastest times
 * 	with current PMS records.  $printSituations[n]
 * 	is set to 1 if we print out all such situations, and 0 if not.  For example:
 * 			$printSituations = array( 0,1,1,1,1,1,1,1,1,1 );
 * 	will cause all 9 situations to be printed when found.  But this:
 * 			$printSituations = array( 0,1,0,0,0,0,0,0,1,1 );
 * 	will only cause situations 1, 8, and 9 to be printed.
 * 	Note that there is no situation #0.
 */
	$printSituations = array( 0,1,1,1,1,1,1,1,1, 1 );	// print them all
/**
 * End of Logging controls...
 **/


////////////////////////////////////////////  Arguments  //////////////////////////////////////////////////////////

// get the program arguments
$coursesToProcess = array();
$productionFlag = 0;			// set to 1 if we are passed the -p flag
$realFlag = 0;					// set to 1 if we are passed the -r flag
$emailComment = "";				// all emails with contain this comment, but default value is "" which
								// means leave out the comment.
$logFetchedWebPages = "";		// if non-empty it gives the root name of each log file containing the contents
								// of fetched web pages.  By default it's empty and GETs are not logged.

$options = getopt( "yYsSlLaAhHrRpPc:g:" );
if( ($options !== false) && (sizeof( $options ) > 0) ) {
	// we've got some options
	foreach( $options as $option => $value ) {
		switch( strtolower( $option ) ) {
			case 'y':
				if( !in_array( "SCY", $coursesToProcess ) ) $coursesToProcess[] = "SCY";
				break;
			case 's':
				if( !in_array( "SCM", $coursesToProcess ) ) $coursesToProcess[] = "SCM";
				break;
			case 'l':
				if( !in_array( "LCM", $coursesToProcess ) ) $coursesToProcess[] = "LCM";
				break;
			case 'a':
				$coursesToProcess = array( "SCY", "SCM", "LCM", );
				break;
			case 'c':
				$emailComment = $value;
				break;
			case 'g':
				$logFetchedWebPages = $value;
				break;
			case 'r':		//  "real"
				$realFlag = 1;
				break;
			case 'p':
				$productionFlag = 1;
				break;
			case 'h':
				usage();
				exit (0);
			default:
				echo "Illegal option: '$option' - ignored.\n";
				usage();
				break;
		}
	}
} else {
	if( $argc >= 2 ) {
		unset( $argv[0] );
		$args = implode( " ", $argv );
		echo "Illegal option(s) passed to $programSimpleName:  $args    Abort!\n";
		exit(1);
	}
}


if( sizeof( $coursesToProcess ) == 0 ) {
	// default...
	$coursesToProcess = array( "SCY", "SCM", "LCM", );
}
$coursesStr = implode( ",", $coursesToProcess );
$realFlagStr = ($realFlag == 1 ? "In 'real' mode." : "Not in 'real' mode.");

// if we're on production then require to -p flag:
if( $server == "Production" ) {
	if( $productionFlag ) {
		echo "We are running on PRODUCTION!  $realFlagStr\n";
	} else {
		echo "We are running on PRODUTION but not told to run in prodution mode.  Abort!\n";
		exit(1);
	}
} else {
	// we're running on development
	if( $productionFlag ) {
		echo "We are running on DEVELOPMENT but executed with the prodution flag.  Abort!\n";
		exit(1);
	} else {
		echo "We are running on DEVELOPMENT.  $realFlagStr\n";
	}
}

echo "We are processing $coursesStr\n";

if( ($UpdateDB != 0) && ($UpdateDB != 1) ) {
	echo 'The internal flag $UpdateDB is set incorrectly! ' . "($UpdateDB) - abort!!\n";
	exit(1);
}
$updateFlagStr = "UpdateDB is set (update the database if necessary.)";
if( $UpdateDB != 1 ) {
	$updateFlagStr = "UpdateDB is CLEAR (DO NOT UPDATE the database.)";
}
echo "NOTE: $updateFlagStr\n";

if( $logFetchedWebPages != "" ) {
	echo "We are logging every page we GET.  Root log file name: '$logFetchedWebPages'\n";
}

////////////////////////////////////////////  Begin Processing  ///////////////////////////////////////////////////

echo "Begin " . __FILE__ . "\n   on $todaysStartingDate ($server)\n";

// this array will hold a RecordCollection for each course
$FastestTimes = array();
// this array will hold a RecordCollection for each course
$PmsRecords = array();

$grandTotalNumRecords = 0;			// total number of records found over all courses processed
$grandTotalNumFatal = 0;			// total number of records we failed to insert over all courses processed
$grandTotalNumExceptions = 0;		// total number of exceptions generated over all courses processed

$emailsSentToGroup = 0;				// set to 1 if we send any emails to the group

// For each course:
//		Get all the fastest times for the course
//		Get all the current PMS records for the course
//		Compare them to generate prospective records
//		Insert the new records into our database
//		Log statistics
//		Generate email
foreach( $coursesToProcess as $course ) {
	// clear out our list of exceptions:
	EmailException::TossAllExceptions();
	
	// here is where we store our collection of fastest times for this course:
	$FastestTimes[$course] = new RecordCollection( $course );
	// here is where we store our collection of PMS records for this course:
	$PmsRecords[$course] = new RecordCollection( $course );

	echo "\n/////////// Begin $course //////////////////////////////////////////////////////////////////////\n";
	// Get the PAC fastest times according to USMS for a specific course, all genders, and age groups:
	GetFastestTimes( $course, $myCourses[$course], $myGenders, $myAgeGroups, $FastestTimes[$course] );
//	$FastestTimes[$course]->SanityCheck( );
	
	// Get the current PMS records for a specific course
	GetPMSRecords( $course, $PmsRecords[$course] );
//	$PmsRecords[$course]->SanityCheck( );
	
	// we're going to track the different "situations" we see to make sure that we cover all cases correctly and
	// also be able to explain every decision we make.
	$numSituations = array(0,0,0,0,0,0,0,0,0,0);
	$logSituations = array("", "", "", "", "", "", "", "", "", "");

	// Now compare what USMS thinks are our fastest times with what we have for records:
	CompareTimes( $course, $FastestTimes[$course], $PmsRecords[$course] );
	
	// if we have any records we need to insert them into the database and send an email
	list( $numTotalRecords, $numFatal ) = InsertRecords( $course, $FastestTimes[$course], $UpdateDB );
	
	// All done - log some statistics:
	LogStats( $course );
	
	// done inserting new records (or failing to do so)	- send email(s)
	$numExceptions = EmailException::NumExceptions();
	$grandTotalNumExceptions += $numExceptions;
	if( $numTotalRecords || $numExceptions ) {
		// we've got exceptions and/or records to tell people about...
		GenerateEmails( $course, $FastestTimes[$course], $numExceptions, $numTotalRecords, $numFatal, $UpdateDB );
		$emailsSentToGroup = 1;
	} else {
		echo "There are no emails sent to the group for this course\n";
	} // end of if( $numTotalRecords || $num....  (we had records and/or exceptions to email about)
	
	echo "\n/////////// End $course //////////////////////////////////////////////////////////////////////\n";

} // end of foreach( $coursesToProcess ....

if( $emailsSentToGroup == 0 ) {
	echo "\nNo emails sent to the group for any of the courses processed.\n";
}
if( $coursesStr != $course ) {
	echo "\n\n/////////// End $coursesStr //////////////////////////////////////////////////////////////////////\n";
}

////////////////////////////////////////////  End Processing  ///////////////////////////////////////////////////

	
////////////////////////////////////////////  Clean up and Finish  ///////////////////////////////////////////////////

$todaysEndingDate = date( $todaysDateFormat );

// email telling us that DrPacm ran...
// (this doesn't go to the New PAC Records list, but just the drpacm list which is just watching to make sure DrPacm runs.)
$email = new EmailGeneration( $emailComment );
if( $emailComment != "" ) {
	$email->AddToContent( "\n\n" );
}
$email->AddToContent( "DrPacm ran on $server, beginning on $todaysStartingDate\n    and " .
						 "ending on $todaysEndingDate\n" );
$email->AddToContent( $realFlagStr . "\n" );
$email->AddToContent( $updateFlagStr . "\n" );
$email->AddToContent( "Number of records: $grandTotalNumRecords ($grandTotalNumFatal fatal errors.)  " );
$email->AddToContent( "Number of exceptions: $grandTotalNumExceptions.\n" );
$email->AddToContent( "Processed course(s): " . $coursesStr . "\n" );
if( $emailsSentToGroup ) {
	$email->AddToContent( "There were emails sent to the group\n\n" );
} else {
	$email->AddToContent( "There were NO emails sent to the group\n\n" );
}

foreach( $coursesToProcess as $course ) {
	if( $FastestTimes[$course]->numNotSane > 0 ) {
		$email->AddToContent( "!! We found " . $FastestTimes[$course]->numNotSane . " SANITY ERRORS in " .
							 "the $course Fastest Times.\n" );
	}
	if( $PmsRecords[$course]->numNotSane > 0 ) {
		$email->AddToContent( "!! We found " . $PmsRecords[$course]->numNotSane . " SANITY ERRORS in " .
							 "the $course PMS Records.\n" );
	}
}


$email->ChangeTo( "drpacm@pacificmasters.org" );
$email->ChangeSubject( "DrPacm ran on $server ($coursesStr)" );
$email->SendEmail();


// All done!
echo "Finished " . __FILE__ . " on $todaysEndingDate\n";


////////////////////////////////////////////  Support Functions  ///////////////////////////////////////////////////

/**
 * usage() - print out usage information explaining arguments.
 */
function usage() {
	echo "usage:  php -d display_errors DrPacm.php -x ...  where '-x' is one or more of:\n";
	echo "  -y or -Y  - generate records for SCY\n";
	echo "  -s or -S  - generate records for SCM\n";
	echo "  -l or -L  - generate records for LCM\n";
	echo "  -a or -A  - generate records for all courses and ignore all other course options.  This is the default.\n";
	echo "  -c<value> - the <value> string is a comment added to the beginning of every email sent.\n";
	echo "  -g<value> - the <value> string is used as root name of each log file containing the contents\n";
	echo "				of fetched web pages.  If empty or not supplied then GETs are not logged.\n";
	echo "  -h or -H  - generate this message and then terminate\n";
	echo "  -r or -R  - THIS IS REAL!  update the database, send email to the team, etc.  Abort if on production.\n";
	echo "  -p or -P  - THIS IS ON PRODUCTION!  The only way to run DrPacm on production.  Supply -R too to run in REAL mode.\n";
	echo "\n";
} // end of usage()



/**
 * InsertRecords - Pass over the passed set of fastest times and, for every one marked as a new record, insert
 * 	the new record into the PMS database.
 *
 * PASSED:
 * 	$course - the course being processed (SCY, etc.)
 * 	$FastestTimes - a RecordCollection object holding the set of fastest times to process.
 * 	$UpdateDB - 1 if we're really supposed to update the DB, 0 if not
 *
 * RETURNED:
 * 	$numTotalRecords - number of fastest times that we think are records
 * 	$numFatal - number of attempts to insert a record that failed.
 *
 */
function InsertRecords( $course, $FastestTimes, $UpdateDB ) {
	global $grandTotalNumRecords, $grandTotalNumFatal;
	$numFatal = 0;				// number of records we FAILED to insert
	$numTotalRecords = 0;				// number of records total we tried to insert (including those that failed.)
	// pass over all the fastest times looking for the ones we've marked as a new record:
	foreach( $FastestTimes->recordCollection as $key => $fastestTime ) {
		if( ($stat = $fastestTime->GetOutputAsNewRecord()) == 1 ) {
			// we have a new record!!!
			$numTotalRecords++;
			if( $UpdateDB == 1 ) {
				// yep - really update the database
				$messageArr = add_ind_record($course, $fastestTime->gender, $fastestTime->ageGroup, $fastestTime->distance,
										  $fastestTime->ConvertStrokeName(), $fastestTime->fullname, $fastestTime->sid, $fastestTime->club,
										  $fastestTime->date, $fastestTime->Get11Duration(), $fastestTime->splashId, "DrPacM");
				$messageStr = "";
				// process the returned array from add_ind_record looking for a string that contains "Fatal".  If we find
				// such a string we'll report it.  Otherwise we ignore the result strings.
				foreach( $messageArr as $message ) {
					if( $messageStr != "" ) {
						$messageStr .= "\n";
					}
					$fatalPos = strpos( $message, "Fatal:" );
					if( $fatalPos !== false) {
						// we failed to insert this record so we'll add this to our email message:
						$messageStr .= "    $message";
					}
				} // end of foreach( $messageArr ...
				$fastestTime->message = $messageStr;
				$fatalPos = strpos( $messageStr, "Fatal:" );
				if( $fatalPos !== false) {
					// we failed to insert this record!
					$fastestTime->recordStatus = 2;			// failure...
					$numFatal++;
				}
			}
		} // end of if( ($stat = $fastestTime->....
	} // end of foreach( $FastestTimes[$cou...
	$grandTotalNumRecords += $numTotalRecords;
	$grandTotalNumFatal += $numFatal;
	return array( $numTotalRecords, $numFatal );
} // end of InsertRecords()



/**
 * GenerateEmails - construct and send an email to the New PAC Records list telling them of
 * 	new records and/or exceptions.
 *
 * PASSED:
 * 	$FastestTimes - a RecordCollection object holding the set of fastest times to process.
 * 	$numExceptions - number of exceptions to report
 * 	$numTotalRecords - number of fastest times that we think are records
 * 	$numFatal - number of attempts to insert a record that failed.
 * 	$UpdateDB - 1 if we're really supposed to update the DB, 0 if not
 * 	
 * RETURNED:
 * 	n/a
 *
 * NOTES:
 * 	The email will be sent.
 *
 */
function GenerateEmails( $course, $FastestTimes, $numExceptions, $numTotalRecords, $numFatal, $UpdateDB ) {
	global $todaysStartingDate, $realFlag, $server, $emailComment   ;
	$email = new EmailGeneration( $emailComment );
	if( $emailComment != "" ) {
		$email->AddToContent( "\n\n" );
	}
	$email->AddToContent( "DrPacm began on $todaysStartingDate and here's what happened:\n\n" );
	if( $realFlag == 0 ) {
		// not 'real' mode - don't send email to the team
		$email->ChangeTo( "drpacm@pacificmasters.org" );
		$email->AddToContent( "NOTE  This is running in TEST mode.  The production database was NOT changed:\n" );
		$email->AddToContent( "    This email was NOT sent to the group.\n\n" );
	}
	$email->ChangeSubject( "DrPacm processed $course on $server: " );

	if( $numExceptions ) {
		$email->AddToContent( "DrPacm generated $numExceptions exceptions for your review:\n\n" );
		$email->AddToContent( EmailException::GenerateExceptions() );
		$email->AddToSubject( "$numExceptions exceptions" );

	} //  end of if( $numExceptions... (we had exceptions to email about)
	
	if( $numTotalRecords ) {
		if( $numExceptions ) {
			$email->AddToContent( "\n---------------------------------------------------------------------------\n\n" );
			$email->AddToSubject( " and " );
		}
		$email->AddToSubject( "$numTotalRecords records" );
		// we inserted (or tried to insert) new records:
		if( $numFatal ) {
			$email->AddToContent("DrPacm attempted to insert $numTotalRecords new records, but $numFatal failed due to fatal errors.\n\n");
		} else {
			if( $UpdateDB == 1 ) {
				$email->AddToContent("DrPacm SUCCESSFULLY inserted $numTotalRecords new records.\n\n");
			} else {
				$email->AddToContent("Although DrPacm found $numTotalRecords new records the database was not " .
									 "updated because DB updates were disabled.\n\n");
			}
		}
		$count = 0;
		foreach( $FastestTimes->recordCollection as $key => $fastestTime ) {
			if( ($stat = $fastestTime->GetOutputAsNewRecord()) == 1 ) {
				// this is a new record we should have inserted
				$count++;
				$email->AddToContent( "#$count:  " );
				$email->AddToContent( $fastestTime->dump() );
				$email->AddToContent( "    Situation " . $fastestTime->recordSituation );
				$email->AddToContent( "\n" );
				$pmsRecord = $fastestTime->splash;				// if non-null this is the record being replaced or tied
		
				if( $fastestTime->tieOrRecord == 1 ) {
					// this is a true record (not a tie of existing record)
					if( $pmsRecord == null ) {
						$email->AddToContent( "This is a brand new record!" );
					} else {
						$email->AddToContent( "Breaking the record of " . $pmsRecord->duration . " set on " .
											 $pmsRecord->date );
					}
				} else {
					// this is a tie
					$email->AddToContent( "Tying the existing record of " . $pmsRecord->duration );
				}
				if( ($pmsRecord != null ) && ($pmsRecord->splashId != "") ) {
					$email->AddToContent( ", splash=http://www.usms.org/comp/meets/swim.php?s=$pmsRecord->splashId\n" );
				} else {
					$email->AddToContent( "\n" );
				}
				
				if( $UpdateDB == 1 ) {
					if( $fastestTime->message != "" ) {
						$email->AddToContent( "Result of inserting this new record:\n" );
						$email->AddToContent( $fastestTime->message );
					}
					if( $fastestTime->recordStatus == 2 ) {
						$email->AddToContent( "    NOTICE!! This record FAILED to be inserted as a new record!!\n" );
					}
				} else {
					$email->AddToContent( "(NOTE: This new record was NOT inserted into the database because DB " .
										 "updates was disabled.\n" );
				}
				$email->AddToContent( "\n" );
			}
		}
	} // end of if( $numTotalRecords ... (we had records to email about)
	$email->AddToContent("\n\nQuestions?  Concerns?  Reply to this email.\n");
	$email->SendEmail();
} // end of GenerateEmails()


/**
 * CompareTimes - compare our records for a particular course with what USMS thinks are the
 * 	fastest times.  Log discrepancies and, if enabled, update our database with new records.
 *
 * PASSED:
 * 	$course - one of SCY, SCM, LCM
 * 	$fastestTimes - a RecordCollection of FastestTime objects, each object representing a PMS fastest time
 * 		for a specific gender/age group/distance/stroke as recorded by USMS.
 * 	$pmsRecords - a RecordCollection of PMSRecord objects, each object representing a PMS record
 * 		for a specific gender/age group/distance/stroke as recorded by PMS.
 *
 * NOTES:
 * See the class AbstractSplash.  It is used to describe EVERY splash (fastest time splash and a PMS record splash.)
 * 	Note also that the class defines some useful values, for example:
 * 		- $maxSplashDiff - the minimum difference (in days) between a fastest time date and a PMS record date that must exist
 * 			for us to consider the two splash dates to be different.  Specifically, if a fastest time date is the same as
 * 			a PMS record date, or less than or equal to 5 days following the PMS record date, the two splashes are considered
 * 			to have been made on the same date.
 * 			
 * 	Here are the situations we'll look for:
 * 	(1)  The fastest time distance is < 25 y/m.
 * 		- PMS doesn't track records <= 25 y/m.  This is NOT a PMS record.
 * 		- ignore this fastest time.
 * 	(2)  The fastest time is 0.00
 * 		- this is an error on the USMS fastest time.  This is NOT a PMS record.
 * 		- ignore this entry
 * 		NOTE:  generate an exception via email.
 * 	(3)  If a fastest time exists for a specific gender, age group, distance, and stroke where a
 * 		corresponding PMS record doesn't exist, and:
 * 		- the fastest time age group is 90-94 or greater.
 * 		Then we have a new PMS record.  No historical PMS record is created.
 * 		NOTE:  if the fastest time is older than 1 year we will also generate an exception via email.
 * 	(4)  Compare a fastest time splash to ALL record splashs with the same gender, age group, distance,
 * 			and stroke (same RecordCollection key):
 * 		- fastest time duration == record duration.
 * 		- fastest time date == record date.
 * 		In this case we silently ignore the fastest time, because we have already seen that fastest time and
 * 			have already considered it.  It's either a current record or a nullified record or historical record
 * 			or something else.  In any case we don't want to consider this fastest time again.
 * 	(5)  Compare a fastest time splash to ALL record splashs with the same gender, age group, distance,
 * 			and stroke (same RecordCollection key):
 * 		- fastest time duration < record duration.
 * 		- fastest time splash date doesn't matter.
 * 		In this case the PMS record becomes historical and fastest time is a new PMS record.
 * 		NOTE:  if the fastest time is older than 1 year we will also generate an exception via email.
 * 	(5)  Compare a fastest time splash to ALL record splashs with the same gender, age group, distance,
 * 			and stroke (same RecordCollection key):
 * 		- same gender, age group, distance, and stroke.
 * 		- fastest time duration = record duration
 * 		- fastest time splash date is newer than the current record date + $maxSplashDiff days.
 * 		Then this is a tie of an existing PMS record.  No historical PMS record is created.
 * 			(if there is more than one current record (ties) then we will match this fastest time with that
 * 			record separately.  There we may change our decision that this is a record.)
 * 		NOTE:  if the fastest time is older than 1 year we will also generate an exception via email.
 *  (6)  Compare a fastest time splash to ALL record splashs with the same gender, age group, distance,
 * 			and stroke (same RecordCollection key):
 * 		- same gender, age group, distance, and stroke.
 * 		- fastest time duration = record duration
 * 		- ONE of:
 * 			- fastest time splash id is NOT EMPTY and EQUAL to the record splash id, OR
 * 			- fastest time splash date is older than or equal to the record splash date + $maxSplashDiff days.
 * 		In this case the fastest time is NOT a PMS record - see the spec.  (Why not?? we don't have
 * 		enough information to make that statement.)
 * 		NOTE:  if two different swimmers (same gender, age group, distance, stroke) tie for a fastest time
 * 			at the same meet then this will fail to recognize both of them - only one of them.  This is due
 * 			to the fact that we can't recognize swimmers (names or swimmer id)
 * 		NOTE:  if the record splash date is YYYY-12-31 then this is suspicious.
 * 	(7)  Compare a fastest time splash to ALL record splashs with the same gender, age group, distance,
 * 			and stroke (same RecordCollection key):
 * 		- same gender, age group, distance, and stroke.
 * 		- fastest time duration > record duration (fastest time is slower than record)
 * 		- splash dates don't matter
 * 		In this case the fastest time is NOT a PMS record.  In fact, the USMS data is wrong, and
 * 		the fastest time should be updated to be the PMS record.
 * 	(8)	 If a PMS record exists for a specific gender, age group, distance, and stroke where a
 * 		corresponding fastest time doesn't exist, AND the date is 2006 or more, then we have discovered a USMS data error.
 * 		NOTE:  it would be nice to ask them to add our record as a new fastest time.  But not part of the spec.
 * 		
 */
function CompareTimes( $course, RecordCollection $fastestTimes, RecordCollection $pmsRecords ) {
	// Used for logging only:
	global $numSituations, $logSituations;
	$fastestTimeNum = 0;				// used to keep track of each fastest time in the logs

	// get the array of fastest times...
	$fastestTimesCollection = $fastestTimes->recordCollection;
	// ...and array of records
	$pmsRecordsCollection = $pmsRecords->recordCollection;

	// pass through our list of fastest times, and for each gender/age group/distance/stroke see
	// how the fastest time for that combination compares with our record for the same combination.
	foreach( $fastestTimesCollection as $key => $fastestTime ) {
		$fastestTimeNum++;
		
		// if this key is the key to a collision then remove the extra part of the key.  If there is a collision it
		// means we have a tie for fastest times for a specific gender, age group, distance, and stroke.  Since those
		// ties can have different dates we handle them separately.
		$key = BaseKey( $key );

		// Look for the specific situations for which we don't care about a matching PMS record:
		if( $fastestTime->distance <= 25 ) {
			// [1] - The fastest time distance is < 25 y/m.
			$fastestTime->SetAsNONRecord("[1]#$fastestTimeNum");
			$fastestTime->situation = 1;
			$numSituations[1]++;
			$logSituations[1] .= "[1]#$fastestTimeNum:  We have a fastest time distance < 25 y/m - ignored.\n";
			$logSituations[1] .= $fastestTime->dump();
			$logSituations[1] .= "\n";
			// ignore this fastest time
		} else if( $fastestTime->durationHund == 0 ) {
			// [2] - a fastest time with a time of 0.00
			$fastestTime->SetAsNONRecord("[2]#$fastestTimeNum");
			$fastestTime->situation = 2;
			$numSituations[2]++;
			$logSituations[2] .= "[2]#$fastestTimeNum:  We have a fastest time = 0.00 - ignored.\n";
			$logSituations[2] .= $fastestTime->dump();
			$logSituations[2] .= "\n";
			// Create an exception and then ignore it
			new EmailException( $fastestTime, NULL, "Please ask Meet Operations Coordinator to remove this swim from the USMS database:" );
		} else if( !isset ($pmsRecordsCollection[$key]) ) {
			// the record corresponding to this fastest time doesn't exist.
			// This MAY be a new record:
			$lowerAgeGroup = $fastestTime->GetLowerAgeGroup();
			$fastestTime->situation = 3;
			$numSituations[3]++;
			$logSituations[3] .= "[3]#$fastestTimeNum:  We have a fastest time for which there is no " .
				"corresponding PMS record. (key=$key)\n";
			if( ($lowerAgeGroup >= 90) &&
				($itsReallyANewRecord = $fastestTime->SetAsNewRecord("[3]#$fastestTimeNum", 1, null )) ) {
				// we've got a new record!
				if( SplashOlderThan1Year( $fastestTime ) ) {
					new EmailException( $fastestTime, NULL, "This candidate record swim " .
									   "took place more than one year ago.", "Situation [3]#$fastestTimeNum" );
					$logSituations[3] .=  "  NOTE:  This new record is older than one year - an email exception was generated.\n";
				}
				$logSituations[3] .= "  This is a NEW PMS record:\n";
			} else {
				$fastestTime->SetAsNONRecord("[3]#$fastestTimeNum");		// we assume this is an invalid race for records
				$logSituations[3] .= "  This is NOT a new PMS record:\n";
			}
			$logSituations[3] .= "  ";
			$logSituations[3] .= $fastestTime->dump();
			$logSituations[3] .= "\n";
		} // end of [3]
		else {
			// THE REST OF THESE SITUATIONS REQUIRE US TO COMPARE THE FASTEST TIME WITH ALL EXISTING PMS RECORDS:
			// Get the record with the same gender, age group, distance, and stroke as the fastest time
			// If there is more than one we'll iterate over all of them:
			for( $i = 0, $pmsRecord = $pmsRecords->GetTiedSplash( $key, $i );
					($pmsRecord != NULL) &&
					// if this fastest time is definately NOT a new record no reason to compare it with other records -
					// we'll just break out of this loop and go on to the next fastest time.
					($fastestTime->GetOutputAsNewRecord() != -1);
				 $i++, $pmsRecord = $pmsRecords->GetTiedSplash( $key, $i ) ) {
				// compare the fastest time splash ($fastestTime object) with this particular PMS record
				// ($pmsRecord object)
				$definatelyNotANewRecord = LookForExactSameRecord( $fastestTime, $pmsRecord, $fastestTimeNum );
				if( $definatelyNotANewRecord ) {
					// this fastest time is definately not a new record - give up on it
					break;
				}
				// Still not sure - let's see if we've got a new record:
				CompareFastestTimeWithRecord( $fastestTime, $pmsRecord, $pmsRecords, $fastestTimeNum );
			} // end of for( $i = 0, $pmsRecord = $pmsRecordsCollection->....
		} // end of handle this fastest time as necessary...
	} // end of foreach( $fastestTimesCollection...
	
	// now look for situation [9] - we have a PMS Record where the corresponding fastest time doesn't exist.  Is this
	// a USMS error?:
	$count = ord("A")-1;
	foreach( $pmsRecordsCollection as $key => $record ) {
		// use CollisionIndex() to filter out collision keys - we don't look for fastest times with the same collisions!
		if( (CollisionIndex( $key ) == 0) && !isset( $fastestTimesCollection[$key]) ) {
			// We found a PMS record where the corresponding fastest time doesn't exist.
			$count++;
			$numSituations[9]++;
			// is the date of this record > 2005?
			$recYear = array();
			$xxx = preg_match( '/^(....)/', $record->date, $recYear );
			if( ($xxx == 1) && ($recYear[1] > 2005) ) {
				// date of record is 2006 or older - this should be a fastest time
				$logSituations[9] .= "[9](a)#" . chr($count) . ":  There is a PMS Record dated 2006 or later " .
					"with no matching fastest time.\n    The ";
			} else {
				// date of record is younger than 2006 - we're going to ignore these
				$logSituations[9] .= "[9](b)#" . chr($count) . ":  There is a PMS Record dated younger than 2006 " .
					"with no matching fastest time.\n    The ";
			}
			$logSituations[9] .= $record->dump();
			$logSituations[9] .= "\n";
		}
	} // end of situation [9]
	// all done!
} // end of CompareTimes()




//				$definatelyNotANewRecord = LookForExactSameRecord( $fastestTime, $pmsRecord, $pmsRecords, $fastestTimeNum );
function LookForExactSameRecord( FastestTime $fastestTime, PMSRecord $pmsRecord,
								 $fastestTimeNum  ) {
	
	global $numSituations, $logSituations;
	// set $definatelyNotANewRecord to TRUE if we are absolutely sure the passed fastest time is not a new record
	$definatelyNotANewRecord = false;		// not sure yet...

	// get the duration and date of the fastest time swim:
	$fastTime = $fastestTime->durationHund;
	$fastDate = $fastestTime->date;
	// get the duration and date of the PMS record swim we're comparing the fastest time to:
	$recordTime = $pmsRecord->durationHund;
	$recordDate = $pmsRecord->date;
	$recordFTime = $pmsRecord->ftime;

	if( ($fastTime == $recordTime) && ($fastestTime->CloseDates( $pmsRecord )) && 1 ) {
		// [4] - we have seen this "exact" record before!
		if( $weUnSetANewRecord = $fastestTime->SetAsNONRecord("[4]#$fastestTimeNum") ) {
			// we earlier thought that this fastest time was a new (or tied) record, but now we know that it's not.
			// If we have any exceptions for this fastest time we're going to un-do them.
			EmailException::RemoveExceptionForFastestTime( $fastestTime );
		}
		$fastestTime->situation = 4;
		$numSituations[4]++;
		$logSituations[4] .= "[4]#$fastestTimeNum:  We have seen this Fastest Time before...as this\n   ";
		$logSituations[4] .= $pmsRecord->dump();
		$logSituations[4] .= "  The fastest time that is equal to this previous record:\n   ";
		$logSituations[4] .= $fastestTime->dump();
		$logSituations[4] .= "\n";
		$definatelyNotANewRecord = true;
	}
	
	return $definatelyNotANewRecord;
	
} // end of LookForExactSameRecord()
				
				
				
				
			
/**
 * CompareFastestTimeWithRecord - compare the passed fastest time object with the passed PMS record OF
 * 		THE SAME KEY.
 *
 * PASSED:
 * 	$fastestTime - an object of type FastestTime representing a fastest time from USMS
 * 	$pmsRecord - an object of type PMSRecord representing a PMS record with the same key as the passed $fastestTime.
 * 	$pmsRecordsCollection - an object of type RecordCollection representing our collection of PMS records
 * 	$fastestTimeNum - the unique number identifying the passed fastest time
 *
 * RETURNED:
 * 	n/a, but there are global arrays and scalars updated by this routine.
 *
 * NOTES:
 * 	The $fastestTime object may be updated to indicate that it is a new record to be used to update the PMS
 * 	records database.
 *
 */
function CompareFastestTimeWithRecord( FastestTime $fastestTime, PMSRecord $pmsRecord,
										RecordCollection $pmsRecordsCollection, $fastestTimeNum ) {
	// Used for logging only:
	global $numSituations, $logSituations;
	$emailExceptionThrown = 0;		// set to 1 if we generate an email exception for the passed splashes

	// get the duration and date of the fastest time swim:
	$fastTime = $fastestTime->durationHund;
	$fastDate = $fastestTime->date;
	// get the duration and date of the PMS record swim we're comparing the fastest time to:
	$recordTime = $pmsRecord->durationHund;
	$recordDate = $pmsRecord->date;
	$recordFTime = $pmsRecord->ftime;

	// Now, see what situation describes the passed fastest time
	// NOTE - situations [1] through [4] HANDLED BY THE CALLER!!!

	if( ($fastTime < $recordTime) && ($recordFTime == CURRENT_FASTEST_PMS_RECORD) ) {
		// [5] - we have a new record 

		if( $itsReallyANewRecord = $fastestTime->SetAsNewRecord("[5]#$fastestTimeNum", 1, $pmsRecord) ) {
			// we set it as a new record ... is this new record older than 1 year?
			if( SplashOlderThan1Year( $fastestTime ) ) {
				new EmailException( $fastestTime, NULL, "This candidate record swim took place more than one year ago.",
								   "Situation [5]#$fastestTimeNum" );
				$emailExceptionThrown = 1;
			}
		} 
		$fastestTime->situation = 5;
		$numSituations[5]++;
		if( $itsReallyANewRecord ) {
			$logSituations[5] .= "[5]#$fastestTimeNum:  We have a new record...Current ";
		} else {
			$logSituations[5] .= "[5]#$fastestTimeNum:  It looks like a new record but we found a " .
				"different reason why it's not...\nCurrent ";
		}
		$logSituations[5] .= $pmsRecord->dump();
		if( $itsReallyANewRecord ) {
			$logSituations[5] .= "  The NEW PMS record should be this ";
		} else {
			$logSituations[5] .= "  The fastest time that is NOT a record is this ";
		}
		$logSituations[5] .= $fastestTime->dump();
		$logSituations[5] .= SpecialCases( $fastestTime, $pmsRecord );
		if( $emailExceptionThrown ) {
			$logSituations[5] .=  "  NOTE:  This new record is older than one year - an email exception was generated.\n";
		}
		$logSituations[5] .= "\n";
	} else if( ($fastTime == $recordTime) &&
			  ($fastestTime->NewerThan( $pmsRecord )) &&
			  ($recordFTime == CURRENT_FASTEST_PMS_RECORD) ) {
		// [6] - we have a tie for a record
		//	But for this to stick it must be faster than ALL tied records (if any).  We will confirm that later.
		$fastestTime->situation = 6;
		$numSituations[6]++;
		// this may or may not stick...depends on what other tied records exist
		if( $itsReallyANewRecord = $fastestTime->SetAsNewRecord("[6]#$fastestTimeNum", 2, $pmsRecord) ) {
			if( SplashOlderThan1Year( $fastestTime ) ) {
				new EmailException( $fastestTime, NULL, "This candidate record swim " .
								   "took place more than one year ago.", "Situation [6]#$fastestTimeNum" );
				$emailExceptionThrown = 1;
			}
		}
		$logSituations[6] .= "[6]#$fastestTimeNum:  We think this is a valid tied record, BUT\n" .
			"when we compare with other records we may change that.\n";
		// this fastest time needs to be added as a tie for an existing PMS record unless a newer record is discovered:
		$logSituations[6] .= "  Current ";
		$logSituations[6] .= $pmsRecord->dump();
		$logSituations[6] .= "  The NEW (possible) tie for this record is this\n  ";
		$logSituations[6] .= $fastestTime->dump();
		$logSituations[6] .= SpecialCases( $fastestTime, $pmsRecord );
		if( $emailExceptionThrown ) {
			$logSituations[6] .=  "  NOTE:  This new record is older than one year - an email exception was generated.\n";
		}
		$logSituations[6] .= "\n";
	} else if( ($fastTime == $recordTime) &&
				(	($fastestTime->splashId != "") &&
					($fastestTime->splashId == $pmsRecord->splashId)     ) ||
				($fastestTime->OlderThanOrEqualTo( $pmsRecord )) &&
				($recordFTime == CURRENT_FASTEST_PMS_RECORD) ) {
		// [7] - we have a tie for a record...but according to the spec it's NOT A TIE...WHY NOT???  (because current
		//	record is more recent.  But it's not clear that's a good reason.  Maybe we're just discovering this after the
		//	new tie was set.)
		if( $weUnSetANewRecord = $fastestTime->SetAsNONRecord("[7]#$fastestTimeNum") ) {
			// we earlier thought that this fastest time was a new (or tied) record, but now we know that it's not.
			// If we have any exceptions for this fastest time we're going to un-do them.
			EmailException::RemoveExceptionForFastestTime( $fastestTime );
		}
		$fastestTime->situation = 7;
		$numSituations[7]++;
		$logSituations[7] .= "[7]#$fastestTimeNum:  We have a tie...BUT IT'S NOT A TIE BECAUSE (according to the spec)\n" .
			"  the 'fastest time' is older than the\n  Current ";
		$logSituations[7] .= $pmsRecord->dump();
		$logSituations[7] .= "  The OLDER ";
		$logSituations[7] .=$fastestTime->dump();
		$logSituations[7] .= SpecialCases( $fastestTime, $pmsRecord );
		if( $emailExceptionThrown ) {
			$logSituations[7] .=  "  NOTE:  This situation caused an email exception to be generated.\n";
		}
		$logSituations[7] .= "\n";
	} else if( ($fastTime > $recordTime)  && ($recordFTime == CURRENT_FASTEST_PMS_RECORD) ) {
		// (8) - we found a mistake in USMS's fastest times - we found a time faster than their
		// listed fastest time.  Should be reported to USMS.
		// HOWEVER, we will ignore this situation if the PMS Record date < 2006 because that's when we think USMS started
		// 	keeping records.
		$fastestTime->SetAsNONRecord("[8]#$fastestTimeNum");
		$recordYear = preg_replace( "/-.*$/", "", $pmsRecord->date );
		$fastestTime->situation = 8;
		$numSituations[8]++;
		if( $recordYear >= 2006 ) {
			$logSituations[8] .= "[8]#$fastestTimeNum:  There is a USMS 'fastest time' that is SLOWER than our record!  " .
				"Inform USMS for correction.\n";
			$logSituations[8] .= "  ";
			$logSituations[8] .=$pmsRecord->dump();
			$logSituations[8] .= "  ";
			$logSituations[8] .=$fastestTime->dump();
			$logSituations[8] .= SpecialCases( $fastestTime, $pmsRecord );
			if( $emailExceptionThrown ) {
				$logSituations[8] .=  "  NOTE:  This situation caused an email exception to be generated.\n";
			}
			$logSituations[8] .= "\n";
		} else {
			// the PMS record was set prior to 2006
			$logSituations[8] .= "[8]#$fastestTimeNum:  There is a USMS 'fastest time' that is SLOWER than our record!\n" .
				"    But the record was set prior to 2006 (the year when USMS started keeping records.)\n";
			$logSituations[8] .= "  ";
			$logSituations[8] .=$pmsRecord->dump();
			$logSituations[8] .= "  ";
			$logSituations[8] .=$fastestTime->dump();
			$logSituations[8] .= SpecialCases( $fastestTime, $pmsRecord );
			if( $emailExceptionThrown ) {
				$logSituations[8] .=  "  NOTE:  This situation caused an email exception to be generated.\n";
			}
			$logSituations[8] .= "\n";
		}
	} else {
		// huh?  we found a case not covered above!
		// This is weird ONLY if the record we're looking at is a current record.
		if( $recordFTime == CURRENT_FASTEST_PMS_RECORD ) {
			$fastestTime->situation = "?";
			echo "[???]#$fastestTimeNum:  We found a condition we didn't expect.\n";
			echo "  ";
			echo $pmsRecord->dump();
			echo "  ";
			echo $fastestTime->dump();
			echo "\n";
		}
	}
} // end of CompareFastestTimeWithRecord()



/**
 * GetFastestTimes - get all the fastest times for a specific course, genders, and age groups
 *
 * PASSED:
 * 	$course - the course being processed (SCY, etc.)
 * 	$courseId - the integer USMS uses to represent a course.
 * 	$myGenders - the genders we'll fetch
 * 	$myAgeGroups - the age groups we'll fetch
 * 	$recordCollection - a reference to the RecordCollection object that will hold the fastest times we get.
 *
 * RETURNED:
 *  n/a
 *
 * NOTES:
 *  This routine doesn't really do much - it just sets up a fetch of a web page and then waits until
 *  the web page is read and processed by a different callback routine.  See the Callbacks class, the
 *  method curlRequestCallback().
 *
 */
function GetFastestTimes( $course, $courseId, $myGenders, $myAgeGroups, &$recordCollection ) {
	global $logFetchedWebPages;
	$callBack = new CallBacks();		// this object contains the method we use as a callback
	
	// define the genders we'll look for:
	foreach( $myGenders as $gender ) {
		if( $callBack->genders == "" ) {
			$callBack->genders = "$gender";
		} else {
			$callBack->genders .= "|$gender";
		}
	}
	$callBack->genders = '/<h4>(' . $callBack->genders . ')/';
	$callBack->course = $course;

	// are we going to log the fastest time page we fetch?
	if( $logFetchedWebPages != "" ) {
		$LogName = $logFetchedWebPages . "-" . $course . ".html";
		$callBack->SetLogging( $LogName ); 
	}
	// we will use Curl to read web pages
	$curlHandle = curl_init();
	
	// set url
	curl_setopt($curlHandle, CURLOPT_URL,
		"https://www.usms.org/comp/meets/lmsc_fastest_times.php?CourseID=$courseId&LMSCID=38" );
	// return the transfer as a string
	curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
	// we'll process the returned web page a chunk at a time
	curl_setopt($curlHandle, CURLOPT_WRITEFUNCTION, function( $ch, $chunk ) use ($callBack, &$recordCollection) {
		return $callBack->curlRequestCallback( $ch, $chunk, $recordCollection ); } );
	
	// set curl off an running...
	curl_exec( $curlHandle );
	
	// once we get here we're done with this course...
	// close curl resource to free up system resources
	curl_close($curlHandle);
} // end of GetFastestTimes()



/**
 * GetPMSRecords - get all the PMS records for the passed $course
 *
 * PASSED:
 * 	$course - the course being processed (SCY, etc.)
 * 	$recordCollection - a reference to the RecordCollection object that will hold the records we get.
 *
 * RETURNED:
 * 	n/a
 * 	The passed RecordCollection object is stuffed with current PMS records.
 *
 */
function GetPMSRecords( $course, &$recordCollection ) {
	// get the records:
	$records = ind_records_extract( $course );
	// did it work?
	$type = gettype( $records );
	if( $type == "string" ) {
		echo "GetPMSRecords(): ERROR from ind_records_extract($course): '$records'\n";
		exit(1);
	}
	// it worked!  now pass over each record and store it away the way we want it:
	foreach ($records as $record) {
		// each $record is an associative array
		$results = array();
		// look at the ftime field to see if this is a record we want to consider:
//		$ftime = $record['ftime'];
//		if( ($ftime == 1) || ($ftime == 2) ) {
			// yes - use this record (it's a true record or an unverified record)
			foreach ($record as $key=>$value) {
				switch( $key ) {
					case 'ftime':
						$results["ftime"] = $value;
						break;
					case 'gender':
						$results["gender"] = $value;
						break;
					case 'age_group':
						$results["ageGroup"] = $value;
						break;
					case 'distance':
						$results["distance"] = $value;
						break;
					case 'stroke':
						$results["stroke"] = $value;
						break;
					case 'swimmer_id':
						$results["sid"] = $value;
						break;
					case 'first_name':					// may not be supplied
						$results["first"] = $value;
						break;
					case 'middle_initial':				// may not be supplied
						$results["mi"] = $value;
						break;
					case 'last_name':					// may not be supplied
						$results["last"] = $value;
						break;
					case 'name':						// may not be supplied
						$results["fullname"] = $value;
						break;
					case 'date':
						$results["date"] = $value;
						break;
					case 'course':
						$results["course"] = $value;
						break;
					case 'splash_id':
						$results["splashId"] = $value;
						break;
					case 'duration':
						$results["duration"] = $value;
						break;
				} // end of switch
			} // end of foreach record...
			$recordCollection->add( (new PMSRecord( $results ))->PutIntoCanonicalForm() );
//		} // if ftime is 1 or 2
//		else {
//			//echo "ftime=$ftime\n";
//			// ignore all records whose ftime is not 1 or 2:
//			continue;
//		}
	} // end of foreach $records
} // end of GetPMSRecords()




/**
 * LogStats - print out statistics gathered while generating new records.
 *
 * PASSED:
 * 	$course - one of SCY, SCM, LCM
 *
 * RETURNED:
 * 	n/a but a bunch of stuff is printed out.
 *
 */
function LogStats( $course ) {
	global $numSituations, $logSituations, $titleSituations, $printSituations;
	global $FastestTimes, $PmsRecords;


	foreach( $printSituations as $key => $value ) {
		if( $key == 0 ) continue;		// we number our situations starting at 1
		if( $value == 0 ) continue;		// if 0 we don't want to show them
		if( $numSituations[$key] == 0 ) continue;		// don't show situations with 0 members
		echo "*** We found " . $numSituations[$key] . " of these situations:\n$titleSituations[$key]:\n";
		echo $logSituations[$key];
		echo "\n";
	}

	// print a summary:
	echo "*****  Summary of the situations discovered for $course:\n";
	$totalNumSituations = 0;
	foreach( $numSituations as $key => $value ) {
		if( $key == 0 ) continue;
		$totalNumSituations += $value;
		echo "[$key] had $value situations:  $titleSituations[$key]\n";
	}
	echo "Total number of situations:  $totalNumSituations\n";
	echo "Total number of fastest times we considered:  " . $FastestTimes[$course]->numInCollection . "\n";
	echo "  (Number of fastest times collisions: " . $FastestTimes[$course]->numCollisions . ")\n";
	echo "Total number of PMS records we considered:  " . $PmsRecords[$course]->numInCollection . "\n";
	echo "  (Number of PMS record collisions: " . $PmsRecords[$course]->numCollisions . ")\n";
	echo "\n";

	// log the new records
	$numDefiniteRecords = 0;
	$numDefiniteNonRecords = 0;
	$numDontKnow = 0;
	
	foreach( $FastestTimes[$course]->recordCollection as $key => $fastestTime ) {
		if( ($stat = $fastestTime->GetOutputAsNewRecord()) == 1 ) {
			$theSituation = $fastestTime->recordSituation;
			$numDefiniteRecords++;
			echo "New Record [$theSituation]:  ";
			echo $fastestTime->dump();
			echo "\n";
		} elseif( $stat == -1 ) {
			$numDefiniteNonRecords++;
		} else {
			$numDontKnow++;
		}
	}
	echo "\n--- Number of new records: $numDefiniteRecords\n";
	echo "--- Number of Non-records: $numDefiniteNonRecords\n";
	echo "--- Not sure: $numDontKnow\n\n\n";
	
	// any records logged?
	if( $numDefiniteRecords == 0 ) {
		echo "There are no records that would be sent to the email list.\n";
	}
	// print email exceptions
	if( EmailException::NumExceptions() > 0 ) {
		echo "Here are the " . EmailException::NumExceptions() . " exceptions that would be sent to the email list " .
			"'newrecords@pacificmasters.org':\n";
		echo EmailException::GenerateExceptions();
	} else {
		echo "There are no exceptions that would be sent to the email list.\n";
	}

} // end of LogStats()

?>
