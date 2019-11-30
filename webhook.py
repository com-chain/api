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
from Crypto.PublicKey import RSA    
from Crypto.Signature import PKCS1_v1_5 
from base64 import b64encode, b64decode 


def openCassandraSession():
    auth_provider = PlainTextAuthProvider(
        username='transactions_rw', password='Private_access_transactions')
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
    
 
def sendWebhook(url, message, transaction):

    private_key_path =''
    public_key_url ='https://...'
    
    json_message = json.dumps(message)

    # prepare the string to be signed
    crc = binascii.crc32(json_message)
    sign_str = "{}|{}|{}|{}".format(transaction['hash'], transaction['time'], transaction['store_id'], crc)
    
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
        
            
        lock_query = "BEGIN BATCH  INSERT INTO lock_processing (hash) VALUES ('"+transaction_row['hash']+"') IF NOT EXISTS;  INSERT INTO transactions (hash,tr_attempt_date) VALUES ('"+transaction_row['hash']+"', toTimestamp(now())) APPLY BATCH" 
        
        # if we are able to set the tr_attempt_date this lock the record and we can process it
        if session.execute(lock_query):
            try:
                webhook_url, server_name = getWebhookInfo(transaction_row['store_id'])
                message = buildWebhookMessage(transaction_row, server_name)
                if sendWebhook(webhook_url, message, transaction_row):
                    transmissionSucceeded(transaction_row['hash'])
                else:
                    transmissionFailed(transaction_row['hash'], transaction_row['tr_attempt_nb'], 24)
            except:
                	print("WARNING: An exception occures when trying to send a webhook.") 
                   
            session.execute("DELETE FROM lock_processing WHERE hash='"+transaction_row['hash']+"'")
  


    

