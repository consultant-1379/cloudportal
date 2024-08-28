#!/bin/bash

### Some variables
THISDIRSHORT=`dirname "${BASH_SOURCE[0]}"`
THISDIR="$( cd "$THISDIRSHORT" && pwd )"
THISSCRIPTNAME=`basename $0`
THISSCRIPTPATH=$THISDIR/$THISSCRIPTNAME
cd $THISDIR
cd ../../
APPDIR=`pwd`
LOCKFILE=$THISDIR/.running_lock
SCRIPT_LOGFILE=$APPDIR/tmp/logs/pooling_queue_manager.log

# Colors
black='\E[30;40m'
red='\E[31;40m'
green='\E[32;40m'
yellow='\E[33;40m'
blue='\E[34;40m'
magenta='\E[35;40m'
cyan='\E[36;40m'
white='\E[37;40m'

function message ()
{
        local MESSAGE="$1"
        local TYPE=$2

        COLOR=$white
        if [[ "$TYPE" == "ERROR" ]]
        then
                COLOR=$red
        fi
        if [[ "$TYPE" == "LINE" ]]
        then
                COLOR=$magenta
        fi
        if [[ "$TYPE" == "WARNING" ]]
        then
                COLOR=$yellow
        fi
        if [[ "$TYPE" == "SUMMARY" ]]
        then
                COLOR=$green
        fi
        if [[ "$TYPE" == "SCRIPT" ]]
        then
                COLOR=$cyan
        fi
        if [[ `echo "$MESSAGE" | egrep "^INFO:|^ERROR:|^WARNING:"` ]]
        then
                local FORMATTED_DATE="`date | awk '{print $2 "_" $3}'`"
                local FORMATTED_TIME="`date | awk '{print $4}'`"
                MESSAGE="[$FORMATTED_DATE $FORMATTED_TIME] $MESSAGE"
        fi
        echo -en $COLOR
        echo -en "$MESSAGE"
        echo -en $white
}

function cleanup ()
{
	message "INFO: Cleaning up\n" INFO
	message "INFO: Removing lock file\n" INFO
	rm -rf $LOCKFILE
	rm $npipe > /dev/null 2>&1
	exit 0
}

### Some checks about the way the script is run. Lets make sure its run with a full path so our checks below will be more robust
if [[ "$THISDIR" != "$THISDIRSHORT" ]]
then
        message "ERROR: You ran the script from $THISDIRSHORT but you must run the script with a full path\n" ERROR
        exit 1
fi

### Make sure this script can only ever run once

# Try to get an atomic lock
if ( set -C; echo "$$" > "$LOCKFILE") 2> /dev/null;
then
	# We got the lock, so lets setup the cleanup function to be run when we exit, to remove the lock
	trap "cleanup" INT
	trap "cleanup" EXIT
	trap "cleanup" TERM
	trap "cleanup" KILL
	trap "cleanup" HUP
	message "INFO: The script isn't already running, starting now\n" INFO
else
	# We couldn't get the lock, lets double check that its not a stale lock
	OTHERPID=`cat ${LOCKFILE}`
	message "INFO: The scripts lock file exists, so its supposed to be already running with PID of $OTHERPID\n" INFO

	# Lets list the running processes with the same full script path as this
	RUNNING_PROCESSES="`ps -ef | grep $THISSCRIPTPATH | grep -v grep | awk '{print $2}'`"
	message "INFO: Running pids to check are as follows $RUNNING_PROCESSES\n" INFO

	# Check each of those processes to see if its the one stored in the lock file
	if [[ `echo "$RUNNING_PROCESSES" | grep "^$OTHERPID$"` ]]
	then
		message "INFO: Found the process running, exiting\n" INFO
	else
		# It looks like the procss isn't running but the lock exists so lets cleanup the lock
		message "ERROR: I couldn't find the process running, so it must be a stale lockfile, removing it, run me again to start from fresh\n" ERROR
		rm -rf $LOCKFILE
	fi
	exit 1
fi

######### Start of main script
### Setup logging to standard output and to log file
npipe=/tmp/$$.tmp
mknod $npipe p
tee -a <$npipe $SCRIPT_LOGFILE &
exec 1>&- 2>&-
exec 1>$npipe 2>$npipe
disown %-
message "INFO: Logging output to $SCRIPT_LOGFILE\n" INFO

### Infinite loop to manage the queue
while true
do
    OUTPUT=`curl --max-time 30 -s --insecure https://localhost/Bookings/process_queue_api/.xml 2>&1`
    if [[ ! `echo "$OUTPUT" | grep processed_queue_items_count` || ! `echo "$OUTPUT" | grep 'count>0<'` ]]
    then
        message "$OUTPUT"
    fi
    sleep 1
done
