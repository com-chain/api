#!/usr/bin/env python3

from cassandra.auth import PlainTextAuthProvider
from cassandra.cluster import Cluster
import json
import sys
 
def openCassandraSessionStagingTransaction():
    auth_provider = PlainTextAuthProvider(
        username='webhook_rw', password='Private_access_transactions')
    cluster = Cluster(auth_provider=auth_provider)
    session = cluster.connect('comchain')
    return session
    
def openCassandraSession():
    auth_provider = PlainTextAuthProvider(
        username='transactions_rw', password='Private_access_transactions')
    cluster = Cluster(auth_provider=auth_provider)
    session = cluster.connect('comchain')
    return session    


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

	try:
		transSent = transaction['sent']
	except:
		transSent = transaction['recieved']
		
	try:
		transTax = transaction['tax']
	except:
		transTax = 0
		
	transEvent = data['event']
	transHash = data['transactionHash']
	transBlock = str(data['blockNumber'])
	
	print(str(transTime) + " - Added transaction " + transHash + " from block " + transBlock)
	
	# Check if the transaction is in the pending transaction table (webshop_transactions)
	cqlcommand = "SELECT hash, store_id, store_ref, wh_status, delegate, linked_hash, message_from, message_to, toTimestamp(now()) AS stamp FROM webshop_transactions WHERE hash='{}'".format(transHash)
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
	         
	    
	    # parent transaction
	    if hasattr(row, 'linked_hash')  and row.linked_hash is not None:
	         additional_fields.append('linked_hash')
	         additional_values.append("'{}'".format(row.linked_hash)) 
	         
	    # webshop
	    if hasattr(row, 'store_id')  and row.store_id is not None: # this is a webshop transaction
	        shop_tx = True
	        additional_fields.append('store_id')
	        additional_values.append("'{}'".format(row.store_id)) 
	        additional_fields.append('store_ref')
	        additional_values.append("'{}'".format(row.store_ref))  
	        
	        wh_status = row.wh_status
	        nb_attempt ='0'
	        if wh_status>1: # 2 failed attempt / 3 success
	            nb_attempt ='1'
	        additional_fields.append('wh_status')
	        additional_values.append(str(wh_status)) # New shop transction 
	        additional_fields.append('tr_attempt_nb')
	        additional_values.append(nb_attempt) 
	        additional_fields.append('tr_attempt_date')
	        diff = row.stamp - datetime.datetime(1970, 1, 1)
	        timestamp = int(diff.total_seconds())
	        additional_values.append("'{}'".format(str(timestamp-10800000))) 
        
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

	

	
