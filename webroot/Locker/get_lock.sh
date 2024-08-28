#!/bin/bash

usage_msg()
{
        echo "Usage: $0 -f LOCKFILE -p FILE_PID -t TIMEOUT_VALUE -r REMOVE_ON_TIMEOUT (yes/no)"
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
	if [[ -z "$TIMEOUT_VALUE" ]]
        then
                echo "ERROR: You must say what the timeout value is using -t"
                exit 1
        fi
	if [[ -z "$REMOVE_ON_TIMEOUT" ]]
        then
                echo "ERROR: You must say whether to remove the lock on timeout or not using -r"
                exit 1
        fi
}

while getopts "f:p:t:r:" arg
do
    case $arg in
	f) LOCKFILE="$OPTARG"
	;;
	p) FILE_PID="$OPTARG"
	;;
	t) TIMEOUT_VALUE="$OPTARG"
        ;;
	r) REMOVE_ON_TIMEOUT="$OPTARG"
	;;

        \?) usage_msg
            exit 1
            ;;
    esac
done

check_args
#. $MOUNTPOINT/expect/expect_functions

function get_lock ()
{
        TRY_NO=1
	# Wait for 6 hours, 10800 * 2
        TRY_ACQUIRE_LOCK=$TIMEOUT_VALUE
        DONE=0

        echo -n "INFO: Waiting for lockfile $LOCKFILE on `hostname`: "
        while [[ $DONE -ne 1 && $TRY_NO -lt $TRY_ACQUIRE_LOCK ]]
        do
                if ( set -C; echo "$FILE_PID" > "$LOCKFILE") 2> /dev/null;
                then
                        echo "OK"
                        exit 0
                else
                        #echo "INFO: Waiting for lockfile..$lockfile"
                        TRY_NO=$(( $TRY_NO+1 ))
                        sleep 1
                fi
        done
}
get_lock

# Try again if we force remove the lockfile on timeout
if [[ "$REMOVE_ON_TIMEOUT" == "yes" ]]
then
	echo "Removing the lock ourselves now after $TIMEOUT_VALUE seconds, something must have gone wrong previously to leave the lock there so long"
	cat $LOCKFILE
	rm -rf $LOCKFILE
	get_lock
fi

echo "Couldn't get the lockfile after $TIMEOUT_VALUE seconds"
exit 1
