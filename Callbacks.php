<?php

/**
 * Callbacks.php - this file contains all of our callback routines.  They are easier to unit test if not
 * part of the main program file.
 */

 require 'FastestTime.php';
 require_once 'DrPUtil.php';
 
 class Callbacks {
	
	/*
	 * We will use curl to read web pages.  Whenever we read a web page we're not going to assume that the length
	 * of the page is something that can fit in memory, so we're going to read it in chunks.  This requires we
	 * use the callback method for curl.  To use this we'll need to keep state between calls to the callback.
	 * Here is where we save state.
	 */
	var $numCallbackCalls;			// used for informational purposes when errors discovered
	var $numLines;
	var $numRecords;				// number of individual records found
	var $state;						// see state machine
	var $leftoverLine;				// used to handle chunks with partial lines
	var $genders;					// list of genders we're interested in - set at run time
	var $course;					// the course we're processing - set at run time
	var $currentGender;				// the gender we're processing - set at run time
	var $currentAgeGroup;			// the age group we're processing - set at run time
	var $logFetchedWebPages;		// if non empty this is the root of a log file created and populated with
									// the full contents of the web page read.
	var $logFP;						// file pointer for the log file used by this class, if any.
	
	function __construct() {
		$this->numCallbackCalls = 0;
		$this->numLines = 0;
		$this->numRecords = 0;
		$this->state = "LookingForContent";
		$this->leftoverLine = "";
		$this->genders = "";
		$this->course = "";
		$this->currentGender = "";
		$this->currentAgeGroup = "";
		$this->logFetchedWebPages = "";
		$this->logFP = 0;
	} // end of __construct()
	
	function __destruct() {
		if( $this->logFP ) {
			fclose( $this->logFP );
		}
	} // end of __destruct()
 
 
 // 		$callBack->SetLogging( $logFetchedWebPages );
 // the passed $logName must not be empty
	function SetLogging( $logName ) {
		$this->logFetchedWebPages = $logName;
		$this->logFP = fopen( $logName, "w" );
		if( ! $this->logFP ) {
			// open NOT successful
			$this->logFP = 0;		// turn off logging
			echo "Callbacks::SetLogging(): ERROR: Unable to open/create log file '$this->logFetchedWebPages'\n";
		}
	}
 
	/**
	 * curlRequestCallback - this routine is called by curl_exec() for every chunk of the fastest times web page fetched.  See
	 * 	the php curl_exec() manual.
	 *
	 * PASSED:
	 * 	$curlHandle - the curl connection
	 * 	$chunk - the chunk of web page we need to process
	 * 	$recordCollection - reference to a RecordCollection object into which we store the fastest times that we find.
	 *
	 * RETURNED:
	 *  $len - the number of bytes in the $chunk.  If we return anything else the transfer will be terminated.
	 *
	 */
	function curlRequestCallback( $curlHandle, $chunk, RecordCollection &$recordCollection ) {
	   $len = strlen( $chunk );
	   $this->numCallbackCalls++;
	   $state = $this->state;
	   $partialLastLine = 0;		// set to 1 if the chunk we were passed ends with a partial line
	   $leftoverLine = $this->leftoverLine;
	   $course = $this->course;
   
		// log this chunk if requested:
		if( $this->logFP ) {
			fwrite( $this->logFP, $chunk );
		}
		
	   if( substr( $chunk, -1 ) != "\n" ) {
		   # the content ends with a partial line - remember this fact:
		   $partialLastLine = 1;
	   }
	   
	   // break our chunk into an array of lines (line is a string terminated by a \n)
	   $lines = explode( "\n", $chunk );
	   $numLines = count($lines);
	   if( $leftoverLine != "" ) {
		   // we have part of a line left over from the previous chunk - prepend it to the first line of
		   // the current chunk
		   $lines[0] = $leftoverLine . $lines[0];
	   }
   
	   if( $partialLastLine ) {
		   // the last line of our chunk is a partial line - save it for the next chunk
		   $numLines--;
		   $this->leftoverLine = $lines[$numLines];
		   unset( $lines[$numLines] );
	   } else {
			// our chunk was an integral number of lines - no extra text to add to the front of the next chunk
			$this->leftoverLine = "";
	   }
	   
	   // now we have an array of complete lines to process - (re-)start our state machine
	   foreach( $lines as $line ) {
		   $this->numLines++;
		   if( $state == "LookingForContent") {
			   // we're still looking for the start of the interesting content
			   if( strpos( $line, "<!-- CONTENT START -->" ) !== false ) {
				   // found the start of content
				   $state = "LookingForGenderOrContentEnd";
				   continue;
			   }
		   } elseif( $state == "LookingForGenderOrContentEnd" ) {
			   $genderFound = array();
			   if( preg_match( $this->genders, $line, $genderFound ) == 1 ) {
				   // we found the gender - remember it and also the age group
				   $regexp = "@<h4>$genderFound[1]\s+([\d-]+)\s+$course</@";
				   $agegroupFound = array();
				   $result = preg_match( $regexp, $line, $agegroupFound );
				   if( $result !== 1 ) {
					   // error!  our pattern didn't match
					   echo "curlRequestCallback(): ERROR 1: (state=$state) '$line' didn't match '$regexp'\n";
					   $len = 0;			// force an error
					   break;
				   } else {
					   // we have a new gender/age group for the course we're processing
					   $this->currentGender = $genderFound[1];
					   $this->currentAgeGroup = $agegroupFound[1];
					   $state = "LookingForTopTimeOREndOfTable";
					   continue;
				   }
			   } elseif( strpos( $line, "<!-- CONTENT END -->" ) !== false ) {
				   // we're done with this web page
				   break;
			   }
		   } elseif( $state == "LookingForTopTimeOREndOfTable" ) {
			   if( strpos( $line, 'align="left">&nbsp;<a href=' ) !== false ) {
				   // MAYBE found a line with a record
				   $recordRegexp = '@' .
					   'valign="top"><td align="left">&nbsp;' .
					   '(\d+)\s*([^&]+)' .			// distance and stroke
					   '&nbsp;</td><td align="left">&nbsp;<a href="/people/' .
					   '([^"]+)' .					// USMS swimmer id
					   '">' .
					   '([^<]+)' .					// swimmer's full name
					   '</a>&nbsp;</td><td>&nbsp;' .
					   '(\d+)' .					// age
					   '&nbsp;</td><td>&nbsp;' .
					   '([\d/]+)' .				// date of swim
					   '&nbsp;</td><td>&nbsp;' .
					   '([^&]+)' .					// team abbrev.  "Sharks & Minnows" could be a problem...
					   '&nbsp;</td><td>&nbsp;<a href="/comp/meets/swim\.php\?s=' .
					   '(\d+)' .					// splash id
					   '">' .
					   '([\d:.]+)' .				// duration of the swim
					   '</a>@';
				   $fieldsFound = array();
				   $result = preg_match( $recordRegexp, $line, $fieldsFound );
				   if( $result !== 1 ) {
					   // the line isn't a record line - it is probably a header line, so we'll check for that
					   $hdrStr = '"left">&nbsp;Name&nbsp;';
					   if( strpos( $line, $hdrStr ) !== false ) {
						   // found a header line - ignore it
						   continue;
					   } else {
						   // neither a record line nor a header line - ERROR
						   echo "curlRequestCallback(): ERROR: (state=$state) '$line'\n**didn't match**\n" .
							   "  '$recordRegexp'\n" .
							   "** NOR did it match**\n" .
							   "'$hdrStr'\n";
						   $len = 0;			// force an error
						   break;
					   }
				   }
				   // we've got the basic details of the record
				   $this->numRecords++;
				   $results = array();
				   foreach ($fieldsFound as $key=>$value) {
						if( $key == 0 ) continue;
						switch( $key ) {
							case 0: continue;
							case 1:
								$results["distance"] = $value;
								break;
							case 2:
								$results["stroke"] = $value;
								break;
							case 3:
								$results["sid"] = $value;
								break;
							case 4:
								$results["fullname"] = $value;
								break;
							case 5:
								$results["age"] = $value;
								break;
							case 6:
								$results["date"] = $value;
								break;
							case 7:
								$results["club"] = $value;
								break;
							case 8:
								$results["splashId"] = $value;
								break;
							case 9:
								$results["duration"] = $value;
								break;
						} // end of switch
				   } // end of foreach
				   $results["gender"] = $this->currentGender;
				   $results["ageGroup"] = $this->currentAgeGroup;
				   $results["course"] = $this->course;

/*  don't use split names yet....
				   // now get the swimmer's name as first, middle, last (full name isn't useful...)
				   list($results["first"],$results["mi"],$results["last"]) =
						GetSwimmerNames( $results["sid"], $results["fullname"] );
*/
				   $recordCollection->add( (new FastestTime( $results ))->PutIntoCanonicalForm() );
			   } elseif( strpos( $line, '</table>' ) !== false ) {
				   // found the end of records for the current gender and age group
				   $this->currentGender = "";
				   $this->currentAgeGroup = "";
				   $state = "LookingForGenderOrContentEnd";
			   }
		   } else {
			   // invalid state!!!
			   echo "curlRequestCallback(): ERROR: invalid state ('$state')\n";
		   }
	   } // end of foreach()
   
	   // when we call this callback again (if we do) this is the state our machine will be in:
	   $this->state = $state;
	   
	   return $len;
   } // end of curlRequestCallback()


} // end of class Callbacks

?>