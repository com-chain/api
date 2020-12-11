#!/bin/bash
cd /home/ethereum
while true
do
  # if geth is not running, wait, checking every min
  while [ ! -e .ethereum/geth.ipc ]
  do
    sleep 60 # or less like 0.2
  done
lastBlock=`geth --exec "eth.blockNumber" attach`
firstBlock=`cat block.txt`
if [ $lastBlock -ge $firstBlock ] 
then
	echo "Parsing $firstBlock - $lastBlock"
	cat logtodb.TMPL | sed s/LBLOCK/$lastBlock/g | sed s/FBLOCK/$firstBlock/g > logtodb
	geth --exec 'loadScript("logtodb")' attach | ./parser.py
	nextBlock=`expr $lastBlock + 1`
	echo $nextBlock > block.txt
fi
#sleep 2
done
