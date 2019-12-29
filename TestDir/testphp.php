#!/usr/local/bin/php
<?php

$total = 0;


	// create curl resource
	$ch = curl_init();

	// set url
	curl_setopt($ch, CURLOPT_URL, "http://www.usms.org/comp/meets/lmsc_fastest_times.php?CourseID=1&LMSCID=38" );

	//return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
//	curl_setopt($ch, CURLOPT_WRITEFUNCTION, "curlRequestCallback");
	

	// $output contains the output string
	$output = curl_exec($ch);

	echo "length of output:  " . strlen($output) . "\n";

echo "errors: " . curl_error($ch) . "\n";

	// close curl resource to free up system resources
	curl_close($ch);
		
		
echo "Bye world\n";

function curlRequestCallback( $ch, $str ) {
	global $total;
	$len = strlen( $str );
	$total += $len;
	echo "length of partial output:  $len, total so far: $total\n";
	return $len;
}
?>