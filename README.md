# DrPacm - Derive Records for PAC Masters

This project runs on our production server and is responsible for suggesting PAC records.  It
does this by analyzing the USMS "fastest" times web pages.  When a possible new record is
found our database is updated to show the possible record and an email is sent to our
administrator who is responsible for approving or reject records.

This project requires PHP, MySQL, and a PHP library used to interface with the PAC database.
