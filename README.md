# Com-chain API

This API allows Biletujo (Com-Chain multi-currency wallet) and tha admin pages
to communicate with the Com-Chain node back-end. 

It was created and is maintained by Florian and Dominique (see com-chain.org)

## Features 
The files in this api have the following purpose:

Check the node is up:

├── ping.php

├── dbcheck.php

Authentication and authorization:

├── auth.php

├── checkAdmin.php

Access the Blockchain Node:

├── api.php

Access the DB node:

├── enroll.php       - used by the administration

├── getadd.php       - used by the administration

├── getuid.php       - used by the administration

├── trnslist.php     - to retrieve an index-based set of transactions

├── export.php       - to retrieve a time-based set of transaction 


Access to the IPFS node:

├── ipfsadd.php

├── ipfscat.php


Scripts:

├── parser.py         - for filling the DB from Blckchain Logs

├── update.sh         - for filling the DB from Blckchain Logs

├── webhook.py        - for sending whebhook

├── ReSendFailedWebhook.py


Others files are helpers or library

## Our Philosophy

- Empower the people: Give people the ability to interact with the Ethereum blockchain easily, without having to run a full node.

- Make it easy & free: Everyone should be able to create a wallet and send Tokens without additional cost.
People are the Priority: People are the most important.

- If it can be hacked, it will be hacked: Never save, store, or transmit secret info, like passwords or keys. Open source & auditable.

## Contact

If you can think of any other features or run into bugs, let us know. You can drop a line at it {at} monnaie {-} leman dot org.
