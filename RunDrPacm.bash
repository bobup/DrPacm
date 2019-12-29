#!/bin/bash

# RunDrPacm.bash - this script will execute DrPacm with the passed arguments.
# See   DrPacm.php -h     for details.
#
# ASSUMPTION:  The cwd is the directory containg DrPacm.php, which is the parent of
#  the ATTIC-DrPacm directory

RUNTYPE=""
#OPTERR=0		# OPTARG doesn't seem to work, so let bash report errors
while getopts "yYsSlLaAhHrRpPc:g:" opt; do
	case $opt in
		r|R)
			if [ .$RUNTYPE = .p ] ; then RUNTYPE=rp
			else RUNTYPE=r
			fi
			;;
		p|P)
			if [ .$RUNTYPE = .r ] ; then RUNTYPE=rp
			else RUNTYPE=p
			fi
			;;
		\?)
			echo "$0: illegal argument: $OPTARG - ABORT!!"
			exit 1
			;;
	esac


			

done
if [ -z $RUNTYPE ] ; then RUNTYPE=t; fi

# set up logging:
LOGDIR=./ATTIC-DrPacm
LOG_FILENAME_ROOT=$LOGDIR/DrPacmLog-$RUNTYPE-`date +"%FT%I:%M:%S%P%Z"`
# this is the main log file created by DrPacm:
LOGFILE=$LOG_FILENAME_ROOT.txt

/usr/local/bin/php -d display_errors ./DrPacm.php "$@" -g "$LOG_FILENAME_ROOT" | tee $LOGFILE
tar czf $LOG_FILENAME_ROOT.tgz $LOGFILE ${LOG_FILENAME_ROOT}-*
rm -f $LOGFILE ${LOG_FILENAME_ROOT}-*

# clean up old log files
#find $LOGDIR -mtime +60 -exec rm -rf {} \;
# clean up by keeping only the most recent 60
cd $LOGDIR >/dev/null
ls -tp | grep -v '/$' | tail -n +61 | xargs -I {} rm -- {}

