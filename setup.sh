#!/bin/bash

#
#This script is used to set up permissions of directories and scripts.
#@author Sean O Finneadha esenofi
#

#check if the current diretory contains webroot otherwise you are in the incorrect location

if [[ -d webroot ]]
then
	# Cakephp tmp files
	mkdir -p tmp/cache/
	mkdir -p tmp/sessions/
	mkdir -p tmp/tests/
	chmod -R 777 tmp

	# Throttler files
	chmod +x webroot/Throttler/queue_manager.sh
	chmod +x webroot/Throttler/wait_in_queue.sh
	chmod -R 777 webroot/Throttler/queue/

	# Lock related files
	chmod +x webroot/Locker/*.sh

	# Webroot files in general
	chmod 777 -R webroot/files/
	echo "Project permissions are set up"
else
        echo "ERROR: Please make sure it is in the correct directory (e.g sean0)"
        exit 1
fi
