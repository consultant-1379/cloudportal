#!/bin/bash

### Some variables
THISDIRSHORT=`dirname "${BASH_SOURCE[0]}"`
THISDIR="$( cd "$THISDIRSHORT" && pwd )"
THISSCRIPTNAME=`basename $0`
THISSCRIPTPATH=$THISDIR/$THISSCRIPTNAME
QUEUE_STORAGE_ROOT=$THISDIR/queue/
cd $THISDIR
cd ../../
APPDIR=`pwd`
LOCKFILE=$THISDIR/.running_lock
SCRIPT_LOGFILE=$APPDIR/tmp/logs/queue_manager.log
SPP_URL=""

function check_args()
{
        if [[ -z "$SPP_URL" ]]
        then
                echo "ERROR: You must specify the spp url"
                exit 1
        fi
}

while getopts :u: OPT
do
        case ${OPT} in
                u) SPP_URL="${OPTARG}"
                   ;;
                \?) echo "ERROR: Invalid option specified ('${OPTARG}')"
               exit 1
                   ;;
                 :) echo "ERROR: Argument missing for option '${OPTARG}'"
               exit 1
                   ;;
        esac
done

check_args

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

	if [[ "$TYPE" == "ERROR" ]]
	then
		# Email the administrators using the throttlers controller function
		OUTPUT=`curl -x ""-s --insecure ${SPP_URL}/Throttlers/email_administrators.xml --data-urlencode "message=${MESSAGE}"`
	fi
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

message "INFO: Waiting for tasks to enter the queue\n" INFO

### Infinite loop to manage the queue
while true
do
	# Get the number of tasks queued up
	TASKS_QUEUED="`ls -tr $QUEUE_STORAGE_ROOT/`"
	TASKS_QUEUED_COUNT=`echo "$TASKS_QUEUED" | sed '/^$/d' | wc -l`

	# If theres nothing in the queue, continue next iteration of the loop after sleeping
	if [[ $TASKS_QUEUED_COUNT -eq 0 ]]
	then
		#message "INFO: Theres nothing in the queue, sleeping for 1 second\n" INFO
		sleep 1
		continue
	fi

	# There is something in the queue so lets work on it
	message "INFO: Theres $TASKS_QUEUED_COUNT tasks in the queue\n" INFO

	# Loop through tasks in the queue
	while read task_number
	do
		# Loop forever until we are allowed to remove this item from the queue
		while true
		do
			# Read the task name from the queue
			TASK_NAME=`cat $QUEUE_STORAGE_ROOT/$task_number`

			# Call the cake console to propose the new task
			message "INFO: Proposing new task to the throttler\n" INFO
			#/opt/bitnami/apache2/htdocs/lib/Cake/Console/cake -app $APPDIR throttler --username=script --function=propose_new_task --task_name=$TASK_NAME
			#PROPOSAL_RETURN_CODE=$?
			#OUTPUT=`wget --no-proxy -q -O - --no-check-certificate ${SPP_URL}/Throttlers/propose_new_task/task_name:${TASK_NAME}.xml`
			OUTPUT=`curl -x "" -s --insecure ${SPP_URL}/Throttlers/propose_new_task/task_name:${TASK_NAME}.xml`
			PROPOSAL_RETURN_CODE=`echo "$OUTPUT" | grep result | awk -F\> '{print $3}' | awk -F\< '{print $1}'`
			if [[ $PROPOSAL_RETURN_CODE != "147" ]]
			then
				# Make sure we got a clear answer back from the cake shell. It should be 146 or 147
				if [[ $PROPOSAL_RETURN_CODE != "146" ]]
	                        then
					message "ERROR: The proposal of a new task didn't come back with a clear yes (146) or no (147) answer. Please check is something wrong. The return code was $PROPOSAL_RETURN_CODE. Heres the output. $OUTPUT\n" ERROR
				fi

				# Remove the queued file
				message "INFO: Allowing task $task_number which is of type $TASK_NAME through\n" INFO
                                rm $QUEUE_STORAGE_ROOT/$task_number
				break
			else
				# We didn't get the go ahead to remove this item from the queue so lets try again in 1 second
				message "INFO: Sleeping for 1 second before retrying this task\n" INFO
				sleep 1
			fi
		done
	done < <(echo "$TASKS_QUEUED")

	message "INFO: Finished current set of tasks, waiting for more tasks to enter the queue\n" INFO
done
