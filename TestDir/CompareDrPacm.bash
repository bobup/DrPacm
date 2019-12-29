#!/bin/bash
#
# CompareDrPacm.bash - Compare the source files for DrPacm with those installed on production.
#
# Usage:
#   CompareDrPacm.bash  ArchivedVersion
# where
#   ArchivedVersion is the simple file name of a tar of a previously pushed version of DrPacm found in
#   DrPacm/PushedVersions/ .
#

#set -x

function Usage {
    echo "Usage: $0 ArchivedVersion"
    echo "    (e.g. CompareDrPacm.bash  PushDrPacm_28Apr2019.tar )"
}

ARCHIVED_VERSION=$1
TEMP_DIR=/tmp/CompareDrPacmDir
SIMPLE_SCRIPT_NAME=`basename $0`
SCRIPT_DIR=$(dirname $0)
cd $SCRIPT_DIR
SCRIPT_DIR=`pwd -P`
cd .. 
DRPACM_DIR=`pwd -P`

if [ .$1 == . ] ; then
    Usage
    exit
fi

rm -rf $TEMP_DIR
mkdir $TEMP_DIR
cp PushedVersions/$ARCHIVED_VERSION $TEMP_DIR
pushd $TEMP_DIR >/dev/null
tar xf $ARCHIVED_VERSION
popd >/dev/null
for FILE in `ls *.bash *.php` ; do
    echo "Compare $FILE:"
    diff $FILE $TEMP_DIR/DrPacm
done
