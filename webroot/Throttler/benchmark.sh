#/bin/bash

while read seq
do
/opt/bitnami/apache2/htdocs/mark0/webroot/Throttler/wait_in_queue.sh "vappDeploy" > /dev/null 2>&1 &
done < <(seq 100);time wait
