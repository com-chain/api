#!/bin/sh

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
from webhook import processWebhookTransaction, openCassandraSession, openCassandraSessionStagingTransaction

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
except ImportError, e:
    sys.exit("\nPython Cassandra driver not installed, or not on PYTHONPATH.\n"
             'You might try "pip install cassandra-driver".\n\n'
             'Python: %s\n'
             'Module load path: %r\n\n'
             'Error: %s\n' % (sys.executable, sys.path, e))

from cassandra.auth import PlainTextAuthProvider
from cassandra.cluster import Cluster
import json

has_some_shop_tx = False
session = openCassandraSession()
sessioStaging = openCassandraSessionStagingTransaction()
for line in sys.stdin:
	if line == "true\n":
		break
	data = json.loads(line)
	transaction = data['args']
	transTime = transaction['time']
	try:
		transFrom = transaction['from']
	except:
		transFrom = "Admin"
	transTo = transaction['to']
	try:
		transRecieved = transaction['recieved']
	except:
		transRecieved = transaction['value']
		print("Error recieved:")
		print(transaction)


	try:
		transSent = transaction['sent']
	except:
		transSent = transaction['recieved']
		#print("Error sent:")
		#print(transaction)
		
	try:
		transTax = transaction['tax']
	except:
		transTax = 0
	transEvent = data['event']
	transHash = data['transactionHash']
	transBlock = str(data['blockNumber'])
	
	print transTime + " - Added transaction " + transHash + " from block " + transBlock
	
	# Check if the transaction is in the pending transaction table (webshop_transactions)
	cqlcommand = "SELECT hash, store_id, store_ref, wh_status, delegate , message_from, message_to, toTimestamp(now()) AS stamp FROM webshop_transactions WHERE hash='{}'".format(transHash)
	rows = sessioStaging.execute(cqlcommand)
	additional_fields = []
	additional_values = []
	shop_tx = False
	
	for row in rows:	    
	    # message
	    if hasattr(row, 'message_from')  and row.message_from is not None:
	         additional_fields.append('message_from')
	         additional_values.append("'{}'".format(row.message_from)) 
	    
	    if hasattr(row, 'message_to')  and row.message_to is not None:
	         additional_fields.append('message_to')
	         additional_values.append("'{}'".format(row.message_to)) 
	         
	    # delegate
	    if hasattr(row, 'delegate')  and row.delegate is not None:
	         additional_fields.append('delegate')
	         additional_values.append("'{}'".format(row.delegate)) 
	         
	    # webshop
	    if hasattr(row, 'store_id')  and row.store_id is not None: # this is a webshop transaction
	        shop_tx = True
	        has_some_shop_tx = True
	        additional_fields.append('store_id')
	        additional_values.append("'{}'".format(row.store_id)) 
	        additional_fields.append('store_ref')
	        additional_values.append("'{}'".format(row.store_ref))  
	        
	        wh_status = row.wh_status
	        nb_attempt ='0'
	        if wh_status>1: # 2 failed attempt / 3 success
	            nb_attempt ='1'
	        additional_fields.append('wh_status')
	        additional_values.append(status) # New shop transction 
	        additional_fields.append('tr_attempt_nb')
	        additional_values.append(nb_attempt) 
	        additional_fields.append('tr_attempt_date')
	        additional_values.append("'{}'".format(row.stamp-10800000)) 
        
	if not shop_tx:
	    additional_fields.append('wh_status')
            additional_values.append('0') # Not a shop transction

	add_fields = ', '.join(additional_fields)
	add_val =  ', '.join(additional_values)
	cqlcommand = "INSERT INTO testtransactions (add1, add2, status, hash, time, direction, recieved, sent, tax, type, block, {}) VALUES ('{}', '{}',     {},  '{}',  {},        {},       {},   {},  {}, '{}',    '{}', {}) IF NOT EXISTS"
	cqlcommand_1 = cqlcommand.format(add_fields, transFrom, transTo, 0, transHash, transTime, 1, transRecieved, transSent, transTax, transEvent, transBlock, add_val )
	cqlcommand_2 = cqlcommand.format(add_fields, transTo, transFrom, 0, transHash, transTime, 2, transRecieved, transSent, transTax, transEvent, transBlock, add_val )
	
	try:
		session.execute(cqlcommand_1)
	#print(cqlcommand_1)
	except:
		print("Error Executing:" + cqlcommand_1)
    
	try:
		session.execute(cqlcommand_2)
	#print(cqlcommand_2)
	except:
		print("Error Executing:" + cqlcommand_2)

	
# send webhook for the newly inserted transactions	
#if has_some_shop_tx:
#    processWebhookTransaction(True)
	
	
