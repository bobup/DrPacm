#!/bin/bash
#
# RepeatRun.bash - Run DrPacm a bunch of times.
#

#set -x

TEMP_FILE=/tmp/RepeatRun
SIMPLE_SCRIPT_NAME=`basename $0`
# compute the full path name of the directory holding this script.  We'll find
# other files we need using this path:
SCRIPT_DIR=$(dirname $0)
cd $SCRIPT_DIR
SCRIPT_DIR=`pwd -P`

function Usage {
    echo "Usage: $0 "
    echo "    (e.g. RepeatRun.bash   )"
}

# set up logging:
LOGDIR=$SCRIPT_DIR
LOG_FILENAME_ROOT=$LOGDIR/DrPacmLog-$RUNTYPE-debugging
echo "See '$LOG_FILENAME_ROOT' for log of fastest times read"

# set STOP_ON_DISCOVERED_ERROR to >0 to stop once error is found so we can analyze logs
STOP_ON_DISCOVERED_ERROR=0

#    refresh_records_ind_apr21.sh \
#    refresh_records_ind_apr23.sh \
#    refresh_records_ind_apr24.sh \
#    refresh_records_ind_apr25.sh \
#    refresh_records_ind_apr26.sh \
#    refresh_records_ind_apr27.sh \


for REFRESH_DB in \
    refresh_records_ind_apr21.sh \
    refresh_records_ind_apr21.sh \
    refresh_records_ind_apr21.sh \
    refresh_records_ind_apr21.sh \
    refresh_records_ind_apr21.sh \
    ; do

    echo "Testing using the DB: $REFRESH_DB"
    SUM=0
    for COUNT in {1..100} ; do
        /usr/home/pacdev/misc/$REFRESH_DB 2>/dev/null
    #    /usr/local/bin/php -d display_errors /usr/home/pacdev/Automation/DrPacm/DrPacm.php -s -g "$LOG_FILENAME_ROOT" >$TEMP_FILE
        /usr/local/bin/php -d display_errors /usr/home/pacdev/Automation/DrPacm/DrPacm.php -s  >$TEMP_FILE
        NUM=`grep -c "New Record \[\[5\]#143\]:  New PMS Record:" $TEMP_FILE`
        if [ $NUM -gt 0 ] ; then
            echo -n "  $COUNT: FOUND THE ERROR"
            SUM=$((SUM + 1))
        else
            echo -n "  $COUNT: no error"
        fi
        if [ $NUM -gt 0 ] && [ $STOP_ON_DISCOVERED_ERROR -gt 0 ] ; then
            echo ""
            exit;
        fi
    done
    echo ""
    echo "Total errors found with $REFRESH_DB: $SUM"
done

exit;
