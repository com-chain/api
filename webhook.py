
#!/bin/sh
# -*- mode: Python -*-

# Licensed to the Apache Software Foundation (ASF) under one
# or more contributor license agreements.  See the NOTICE file
# distributed with this work for additional information
# regarding copyright ownership.  The ASF licenses this file
# to you under the Apache License, Version 2.0 (the
# "License"); you may not use this file except in compliance
# with the License.  You may obtain a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

""":"
# bash code here; finds a suitable python interpreter and execs this file.
# prefer unqualified "python" if suitable:
python -c 'import sys; sys.exit(not (0x020700b0 < sys.hexversion < 0x03000000))' 2>/dev/null \
    && exec python "$0" "$@"
for pyver in 2.7; do
    which python$pyver > /dev/null 2>&1 && exec python$pyver "$0" "$@"
done
echo "No appropriate python interpreter found." >&2
exit 1
":"""

from __future__ import with_statement

import cmd
import codecs
import ConfigParser
import csv
import getpass
import optparse
import os
import platform
import sys
import traceback
import warnings
import webbrowser
from StringIO import StringIO
from contextlib import contextmanager
from glob import glob
from uuid import UUID

if sys.version_info[0] != 2 or sys.version_info[1] != 7:
    sys.exit("\nCQL Shell supports only Python 2.7\n")

UTF8 = 'utf-8'
CP65001 = 'cp65001'  # Win utf-8 variant

description = "CQL Shell for Apache Cassandra"
version = "5.0.1"

readline = None
try:
    # check if tty first, cause readline doesn't check, and only cares
    # about $TERM. we don't want the funky escape code stuff to be
    # output if not a tty.
    if sys.stdin.isatty():
        import readline
except ImportError:
    pass

CQL_LIB_PREFIX = 'cassandra-driver-internal-only-'

CASSANDRA_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..')
CASSANDRA_CQL_HTML_FALLBACK = 'https://cassandra.apache.org/doc/cql3/CQL-2.2.html'

if os.path.exists(CASSANDRA_PATH + '/doc/cql3/CQL.html'):
    # default location of local CQL.html
    CASSANDRA_CQL_HTML = 'file://' + CASSANDRA_PATH + '/doc/cql3/CQL.html'
elif os.path.exists('/usr/share/doc/cassandra/CQL.html'):
    # fallback to package file
    CASSANDRA_CQL_HTML = 'file:///usr/share/doc/cassandra/CQL.html'
else:
    # fallback to online version
    CASSANDRA_CQL_HTML = CASSANDRA_CQL_HTML_FALLBACK

# On Linux, the Python webbrowser module uses the 'xdg-open' executable
# to open a file/URL. But that only works, if the current session has been
# opened from _within_ a desktop environment. I.e. 'xdg-open' will fail,
# if the session's been opened via ssh to a remote box.
#
# Use 'python' to get some information about the detected browsers.
# >>> import webbrowser
# >>> webbrowser._tryorder
# >>> webbrowser._browser
#
if len(webbrowser._tryorder) == 0:
    CASSANDRA_CQL_HTML = CASSANDRA_CQL_HTML_FALLBACK
elif webbrowser._tryorder[0] == 'xdg-open' and os.environ.get('XDG_DATA_DIRS', '') == '':
    # only on Linux (some OS with xdg-open)
    webbrowser._tryorder.remove('xdg-open')
    webbrowser._tryorder.append('xdg-open')

# use bundled libs for python-cql and thrift, if available. if there
# is a ../lib dir, use bundled libs there preferentially.
ZIPLIB_DIRS = [os.path.join(CASSANDRA_PATH, 'lib')]
myplatform = platform.system()
is_win = myplatform == 'Windows'

# Workaround for supporting CP65001 encoding on python < 3.3 (https://bugs.python.org/issue13216)
if is_win and sys.version_info < (3, 3):
    codecs.register(lambda name: codecs.lookup(UTF8) if name == CP65001 else None)

if myplatform == 'Linux':
    ZIPLIB_DIRS.append('/usr/share/cassandra/lib')

if os.environ.get('CQLSH_NO_BUNDLED', ''):
    ZIPLIB_DIRS = ()


def find_zip(libprefix):
    for ziplibdir in ZIPLIB_DIRS:
        zips = glob(os.path.join(ziplibdir, libprefix + '*.zip'))
        if zips:
            return max(zips)   # probably the highest version, if multiple

cql_zip = find_zip(CQL_LIB_PREFIX)
if cql_zip:
    ver = os.path.splitext(os.path.basename(cql_zip))[0][len(CQL_LIB_PREFIX):]
    sys.path.insert(0, os.path.join(cql_zip, 'cassandra-driver-' + ver))

third_parties = ('futures-', 'six-')

for lib in third_parties:
    lib_zip = find_zip(lib)
    if lib_zip:
        sys.path.insert(0, lib_zip)

warnings.filterwarnings("ignore", r".*blist.*")




try:
    import cassandra
except ImportError:
    sys.exit("\nPython Cassandra driver not installed, or not on PYTHONPATH.\n"
             'You might try "pip install cassandra-driver".\n\n'
             'Python: %s\n'
             'Module load path: %r\n'% (sys.executable, sys.path))
             
from cassandra.auth import PlainTextAuthProvider
from cassandra.cluster import Cluster
import json
import urllib2
import binascii # for crc32
import os
import Crypto
import platform # for the host name
import time
from Crypto.PublicKey import RSA    
from Crypto.Signature import PKCS1_v1_5 
from base64 import b64encode, b64decode 

NANT_TRANSFERT = "0xa5f7c148"
CM_TRANSFERT = "0x60ca9c4c"


def openCassandraSession():
    auth_provider = PlainTextAuthProvider(
        username='transactions_rw', password='Private_access_transactions')
    cluster = Cluster(auth_provider=auth_provider)
    session = cluster.connect('comchain')
    return session
    
def openCassandraSessionStagingTransaction():
    auth_provider = PlainTextAuthProvider(
        username='webhook_rw', password='Private_access_transactions')
    cluster = Cluster(auth_provider=auth_provider)
    session = cluster.connect('comchain')
    return session
    
    

def transmissionSucceeded(transaction_hash):
    session = openCassandraSession()
    cqlcommand = "INSERT INTO transactions (hash, status) VALUES ('{}', 3)".format(transaction_hash)
    session.execute(cqlcommand)
   
def transmissionFailed(transaction_hash, attempt_number, max_attempt):
    status=2
    if attempt_number==max_attempt:
        status = 4
    else:
        attempt_number+=1
        
    session = openCassandraSession()
    cqlcommand = "INSERT INTO transactions (hash, status, tr_attempt_nb) VALUES ('{}', {}, {})".format(transaction_hash, status, attempt_number)
    session.execute(cqlcommand)
    
def getWebhookInfo(store_id):
    session = openCassandraSession()
    rows = session.execute("SELECT webhook_url, server_name FROM sellers WHERE store_id ='{}'".format(store_id))
    for store_row in rows:
       return store_row['webhook_url'], store_row['server_name'] 
    return '',''

def buildWebhookMessage(transaction, server_name):

   h = platform.uname()[1]

   data = {}
   data['id']=transaction['hash']
   data['create_time']=transaction['time']
   data['resource_type']='sale'
   data['event_type']='PAYMENT.SALE.COMPLETED'
   data['summary']='A sale has been completed. The payement has been processed.'
   
   # Link to the transaction data through the API
   link={}
   link['href']= '{}/api.php?hash={}'.format(h, transaction['hash'])
   link['method']='GET'
   
   resource={}
   resource['id']=transaction['hash']
   resource['create_time']=transaction['time']
   resource['state']="completed"
   resource['store_id']=transaction['store_id']
   resource['reference']=transaction['store_ref']
   resource['links']=[link]
   resource['addr_from']=transaction['addr_from']
   resource['addr_to']=transaction['addr_to']
   
   amount={}
   amount['sent']=transaction['sent']
   amount['recieved']=transaction['recieved']
   amount['tax']=transaction['tax']
   amount['type']=transaction['type']
   amount['currency']=server_name
   
   
   resource['amount']=amount
   data['resource']=resource
   data['links']=[link]
   
   return data 
   
   
def buildPreWebhookMessage(tr_hash, server_name, store_id, store_ref, rawtx):
   function = rawtx[78:86]
   type_tr = 'TransferCredit' if '0x'+function == CM_TRANSFERT else 'Transfer'
   
   dest = '0x'+ rawtx[110:150]
   amount = int(rawtx[150:214],16)/100.0

   h = platform.uname()[1]

   data = {}
   data['id']=tr_hash
   data['create_time']=transaction['time']
   data['resource_type']='sale'
   data['event_type']='PAYMENT.SALE.PENDING'
   data['summary']='A sale is pending. The payement has been recieved and is currently processed.'
   
   # Link to the transaction data through the API
   link={}
   link['href']= '{}/api.php?hash={}'.format(h, tr_hash)
   link['method']='GET'
   
   resource={}
   resource['id']=tr_hash
   resource['create_time']= int(time.time())
   resource['state']="pending"
   resource['store_id'] = store_id
   resource['reference'] = store_ref
   resource['links']=[link]
   resource['addr_to']=dest
   
   amount={}
   amount['sent']=amount
   amount['type']=type_tr
   amount['currency']=server_name
   
   resource['amount']=amount
   data['resource']=resource
   data['links']=[link]
   
   return data 
   
   
    
 
def sendWebhook(url, message):

    private_key_path ='../ComChain/comchainwebhook_rsa'
    public_key_url ='https://com-chain.org/comchainwebhook_rsa.pub'
    
    json_message = json.dumps(message)

    # prepare the string to be signed
    crc = binascii.crc32(json_message)
    sign_str = "{}|{}|{}|{}".format(message['id'], message['create_time'], message[resource]['store_id'], crc)
    
    #load the key
    private_key =  open(private_key_path, 'r').read() 
    rsakey = RSA.importKey(private_key)
    signer = PKCS1_v1_5.new(rsakey) 
    
    #sign and b64 encode
    sign = signer.sign(sign_str, '')
    signature = b64encode(sign)
    
    #create the request and its header
    req = urllib2.Request(url)
    req.add_header('Content-Type', 'application/json')
    req.add_header('COMCHAIN-TRANSMISSION-SIG', signature)
    req.add_header('COMCHAIN-AUTH-ALGO', 'RSA')
    req.add_header('COMCHAIN-CERT-URL', public_key_url)

    #send the webhook
    response = urllib2.urlopen(req, json_message)

    return response.getcode()>=200 and response.getcode()<300

    
def processWebhookTransaction(new_ones):
    query = "SELECT hash, block, recieved, sent, tax, time, type, addr_from, addr_to, store_id, store_ref, status, tr_attempt_nb, tr_attempt_date, toTimestamp(now()) AS stamp FROM transactions WHERE status=2 ALLOW FILTERING" 
    if new_ones:
        query = "SELECT hash, block, recieved, sent, tax, time, type, addr_from, addr_to, store_id, store_ref, status, tr_attempt_nb,  tr_attempt_date, toTimestamp(now()) AS stamp FROM transactions WHERE status=1 ALLOW FILTERING"
        
    session = openCassandraSession()
    rows = session.execute(query)
    for transaction_row in rows:
        if transaction_row['status']==2 and transaction_row['tr_attempt_date']>transaction_row['stamp'] - 10800000: # 3h en milisec delay for all
            continue
        
        lock_query = "BEGIN BATCH INSERT INTO lock_processing (hash) VALUES ('"+transaction_row['hash']+"') IF NOT EXISTS;  INSERT INTO transactions (hash,tr_attempt_date) VALUES ('"+transaction_row['hash']+"', toTimestamp(now())) APPLY BATCH" 
        
        # if we are able to set the tr_attempt_date this lock the record and we can process it
        if session.execute(lock_query):
            try:
                webhook_url, server_name = getWebhookInfo(transaction_row['store_id'])
                message = buildWebhookMessage(transaction_row, server_name)
                if sendWebhook(webhook_url, message):
                    transmissionSucceeded(transaction_row['hash'])
                else:
                    transmissionFailed(transaction_row['hash'], transaction_row['tr_attempt_nb'], 24)
            except:
                	print("WARNING: An exception occures when trying to send a webhook.") 
                	
            # remove the lock       
            session.execute("DELETE FROM lock_processing WHERE hash='"+transaction_row['hash']+"'")
  


    

