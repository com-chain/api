#!/bin/bash
cd /home/ethereum
while true
do
lastBlock=`geth --exec "eth.blockNumber" attach`
firstBlock=`cat block.txt`
if [ $lastBlock -gt $firstBlock ] 
then
	echo "Parsing $firstBlock - $lastBlock"
	cat logtodb.TMPL | sed s/LBLOCK/$lastBlock/g | sed s/FBLOCK/$firstBlock/g > logtodb
	geth --exec 'loadScript("logtodb")' attach | ./parser.py
	nextBlock=`expr $lastBlock + 1`
	echo $nextBlock > block.txt
fi
#sleep 2
done
