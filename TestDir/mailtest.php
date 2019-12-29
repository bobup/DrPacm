#!/usr/local/bin/php
<?php
error_reporting(E_ALL);

$to      = 'New PAC Records <newrecords@pacificmasters.org>';
$subject = 'Test of new records #1';
$message = 'this is only a test';
$headers = 'From: DrPacm <drpacm@pacificmasters.org>' . "\r\n" .
    'Reply-To: PAC Webmaster <webmaster@pacificmasters.org>' . "\r\n" .
    'X-Mailer: PHP/' . phpversion();

mail($to, $subject, $message, $headers);
?> 

