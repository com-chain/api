#!/bin/bash

# Loading Environment
LEM_SCRIPTS_HOME=/home/ethereum/
. ${LEM_SCRIPTS_HOME}/lemSetEnv.sh

geth --datadir $LEM_ETHEREUM_DATA_DIR --identity $LEM_ETHEREUM_IDENTITY $LEM_ETHEREUM_PORT_OPTIONS  $LEM_ETHEREUM_RPC_OPTIONS --nodiscover --ipcpath $LEM_GETH_IPC --networkid $LEM_ETHEREUM_NETWORKID --etherbase $LEM_ETHEREUM_ETHERBASE --miner.threads $LEM_ETHEREUM_MINERTHREADS --mine --gcmode archive --syncmode full >> /home/ethereum/.ethereum/geth.log 2>&1 &

nohup /home/ethereum/watchdog.sh &
