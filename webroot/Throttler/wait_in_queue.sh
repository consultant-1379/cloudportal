#!/bin/bash

# Variables
#THISDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
#QUEUE_STORAGE_ROOT=$THISDIR/queue/
QUEUE_STORAGE_ROOT=Throttler/queue/
TASK_TYPE=""
TASK_NUMBER=$$
TASK_FILE=$QUEUE_STORAGE_ROOT/$TASK_NUMBER

SPP_HOSTNAME=`hostname`
SPP_IP=`host $SPP_HOSTNAME | awk '{print $4}'`
SPP_URL=""

function check_args()
{
        if [[ -z "$SPP_URL" ]]
        then
                echo "ERROR: You must specify the spp url"
                exit 1
        fi
}

while getopts :t:u: OPT
do
        case ${OPT} in
                u) SPP_URL="${OPTARG}"
                   ;;
                t) TASK_TYPE="${OPTARG}"
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
                OUTPUT=`curl -s --insecure --noproxy $SPP_IP ${SPP_URL}/Throttlers/email_administrators.xml --data-urlencode "message=${MESSAGE}"`
        fi
}

message "INFO: wait_in_queue($TASK_NUMBER): I'm a $TASK_TYPE task about to be added to the queue\n" INFO
# Put me in the queue 
echo "$TASK_TYPE" > $TASK_FILE
if [[ $? -ne 0 ]]
then
	message "ERROR: wait_in_queue($TASK_NUMBER) There was an error writing myself to the queue\n" ERROR
	exit 1
fi
message "INFO: wait_in_queue($TASK_NUMBER): I'm in the queue now\n" INFO

# Wait until im no longer in the queue
while [[ -f $TASK_FILE ]]
do
	#echo "INFO: I'm not allowed run yet, I'm still in the queue"
	sleep 1
done

message "INFO: wait_in_queue($TASK_NUMBER): I'm allowed run now so returning\n" INFO
