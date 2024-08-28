#!/bin/bash

usage_msg()
{
        echo "Usage: $0 -c CONFIG -f LOCKFILE -p FILE_PID"
        exit 1
}
check_args()
{
	if [[ -z "$LOCKFILE" ]]
        then
                echo "ERROR: You must say where the lockfile is using -f"
                exit 1
        fi
	if [[ -z "$FILE_PID" ]]
        then
                echo "ERROR: You must say what the pid in the lockfile should be"
                exit 1
        fi
}

while getopts "f:p:" arg
do
    case $arg in
	f) LOCKFILE="$OPTARG"
	;;
	p) FILE_PID="$OPTARG"
	;;
        \?) usage_msg
            exit 1
            ;;
    esac
done

check_args
#. $MOUNTPOINT/expect/expect_functions

if [[ -f $LOCKFILE ]]
then
	LOCK_ID=`cat $LOCKFILE`
	if [[ "$LOCK_ID" == "$FILE_PID" ]]
	then
		echo "INFO: Deleting lockfile $LOCKFILE on `hostname` as it was created by me"
		rm -rf $LOCKFILE
	fi
fi
