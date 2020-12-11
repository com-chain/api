#!/bin/bash
cd /home/ethereum
while true
do
        update_pid=`pgrep update.sh`
	if [ -z "$update_pid" ]
	then
		nohup /home/ethereum/update.sh >> /home/ethereum/parser.log &
		echo restarted update.sh
	fi
sleep 10
done
