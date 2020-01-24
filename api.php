<?php
header('Access-Control-Allow-Origin: *');
include './checkAdmin.php';
include './Webhook.php';
require_once 'libs/jsonRPCClient.php';

$gethRPC = new jsonRPCClient('http://127.0.0.1:8545');
if(isset($_REQUEST['balance'])){
    header('Content-Type: application/json');
    echo getBalance($_REQUEST['balance'],$gethRPC);
} else if(isset($_REQUEST['rawtx'])){
    header('Content-Type: application/json');
    echo sendRawTransaction($_REQUEST['rawtx'],$gethRPC);
} else if(isset($_REQUEST['txdata'])){
    header('Content-Type: application/json');
    echo getTransactionData($_REQUEST['txdata'],$gethRPC);
} else if(isset($_REQUEST['estimatedGas'])){
    header('Content-Type: application/json');
    echo getEstimatedGas($_REQUEST['estimatedGas'],$gethRPC);
} else if(isset($_REQUEST['ethCall'])){
    header('Content-Type: application/json');
    echo getEthCall($_REQUEST['ethCall'],$gethRPC);
}else if(isset($_REQUEST['ethCallAt']) && isset($_REQUEST['blockNb'])){
    header('Content-Type: application/json');
    echo getEthCallAt($_REQUEST['ethCallAt'],$_REQUEST['blockNb'],$gethRPC);
} else if(isset($_REQUEST['hash'])){
    header('Content-Type: application/json');
    echo getTransaction($_REQUEST['hash'],$gethRPC);
} else {
    header('Content-Type: application/json');
    echo blockNumber($gethRPC);
}
function blockNumber($gethRPC){
    $data = getDefaultResponse();
    try {
        $ret = getRPCResponse($gethRPC->eth_blockNumber());
        $data['data'] = $ret;
        $block=hexdec($ret);
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return $block;
}
function getTransaction($hash, $gethRPC){
    $data = getDefaultResponse();
    try {
        $ret = getRPCResponse($gethRPC->eth_getTransactionByHash($hash),
        "pending");
        $cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_ro", "Public_transactions")
                ->build();
        $keyspace  = 'comchain';
        $session  = $cluster->connect($keyspace);
        $query = "SELECT * FROM transactions WHERE hash = ?"; 
        $options = array('arguments' => array($hash));

	    foreach ($session->execute(new Cassandra\SimpleStatement($query), $options) as $row) {
	        $arr = $row;
	    }
	    
        $arr['transaction'] = $ret;
        $ret = json_encode($arr);
        $data = $ret;
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}
function getEstimatedGas($txobj, $gethRPC){
    $data = getDefaultResponse();
    try {
        $data['data'] = getRPCResponse($gethRPC->eth_estimateGas($txobj));
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}



function getEthCall($txobj, $gethRPC){
    $data = getDefaultResponse();
    try {
        $data['data'] = getRPCResponse($gethRPC->eth_call($txobj,
        "pending"));
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}


function getEthCallAt($txobj, $blockNb, $gethRPC){
    $data = getDefaultResponse();
    try {
        $data['data'] = getRPCResponse($gethRPC->eth_call($txobj,
        $blockNb));
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}


function getTransactionData($addr, $gethRPC){
    $data = getDefaultResponse();
    try {
        $addr = formatAddress($addr);
        $balance = getRPCResponse($gethRPC->eth_getBalance($addr,
            "pending"));
        $nonce = getRPCResponse($gethRPC->eth_getTransactionCount($addr,
            "pending"));
        $gasprice = getRPCResponse($gethRPC->eth_gasPrice());
        $balance=bchexdec($balance);
        $tarr['address'] = $addr;
        $tarr['balance'] = $balance;
        $tarr['nonce'] = $nonce;
        $tarr['gasprice'] = $gasprice;
        $data['data'] = $tarr;
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}
function getBalance($addr, $gethRPC)
{
    $data = getDefaultResponse();
    try {
        $addr = formatAddress($addr);
        $balancehex = getRPCResponse($gethRPC->eth_getBalance($addr,
            "pending"));
        $balance=bchexdec($balancehex);
        $tarr['address'] = $addr;
        $tarr['balance'] = $balance;
        $tarr['balancehex'] = $balancehex;
        $data['data'] = $tarr;
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}
function getRPCResponse($result){
    if(isset($result['result'])){
        return $result['result'];
    } else {
        throw new Exception($result['error']['message']);
    }
}

// Lookup into the DB for a valid shop from the REQUEST parameters

function validateShop($shop_id, $server_name){
   $cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_ro", "Public_transactions")->build();
   $keyspace  = 'comchain';
   $session  = $cluster->connect($keyspace);
   $query = "SELECT webhook_url FROM sellers WHERE store_id = ? AND server_name = ? "; 
   $options = array('arguments' => array($shop_id, $server_name)); 
   foreach ($session->execute(new Cassandra\SimpleStatement($query), $options) as $row) {
	 return $row['webhook_url'];
   }
   
   return "";
}



function validateShopData() {
    $shop_id=$_REQUEST['shopId'];
    $shop_ref=$_REQUEST['txId'];
    $server_name=$_REQUEST['serverName'];
    if (isset($shop_id) && isset($server_name) && isset($shop_ref)) {
        return validateShop( $shop_id, $server_name);
    } else {
        return "";
    }
}

function storeAdditionalData($is_valid_shop, $transaction_ash, $web_hook_status) {
    $shop_id=$_REQUEST['shopId'];
    $shop_ref=$_REQUEST['txId'];
    $delegate=$_REQUEST['delegate'];
    $memo_from=$_REQUEST['memo_from'];
    $memo_to=$_REQUEST['memo_to'];
    
    $do_insert=false;
    $fields =array();
    $val =array();
   
    $fields['wh_status'] = $web_hook_status;
    $val[]='?';
    
    if ($is_valid_shop) {
        $fields['store_id'] = $shop_id;
        $fields['store_ref'] = $shop_ref; 
        $val[]='?';  
        $val[]='?';      
    }
    
    if (isset($delegate)) {
        $fields['delegate'] = $delegate;
        $val[]='?';    
    }
    
    if (isset($memo_from) && $memo_from!="") {
        $fields['message_from'] = $memo_from;
        $val[]='?';    
    }
    
    if (isset($memo_to) && $memo_to!="") {
        $fields['message_to'] = $memo_to;
        $val[]='?';    
    }
    
    // Add if not only the status
    if (sizeof($fields)>1) {
        $fields['hash'] = $transaction_ash;
        $val[]='?';    
        // build the query
        $query = "INSERT INTO webshop_transactions (".join(', ',array_keys($fields));
        $query = $query.') VALUES ('.join(', ',$val).')';
        
        $cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("webhook_rw", "Private_access_transactions")->build();
        $keyspace  = 'comchain';
        $session  = $cluster->connect($keyspace);
        $session->execute(new Cassandra\SimpleStatement($query), array('arguments' => $fields));
    }
}


function sendRawTransaction($rawtx,$gethRPC){
    $data = getDefaultResponse();
    
    $shop_url = validateShopData();
    
  
    
    $contract = '';
    $dest = '';
    $amount = 0;
    $to_bal = 0;
    $wh_status = 0;
    try {
        if (strlen($shop_url)>0) {
            $config = getServerConfig($_REQUEST['serverName']);
            $contract = $config->{'contract_1'};
            // if so get the dest
            $dest = '0x'.$rawtx.substr(110,40);
            // get the sender
            $sender = TransactionEcRecover($rawtx)[0];

            // get the amount
            $amount = hexdec($rawtx.substr(150,64));
            // get the balances 
            $to_bal = getBalance($dest, $contract);
            $from_bal = getBalance($sender, $contract);
            $wh_status = 1;
        }
        
        $data['data'] = getRPCResponse($gethRPC->eth_sendRawTransaction($rawtx));
        
        if (strlen($shop_url)>0 && $amount > 0) {
            // get the balances check if changes compatible the the amount 
            $to_bal_after = getBalance($dest, $contract);
            $from_bal_after = getBalance($sender, $contract);
            if (($to_bal_after - $to_bal >= $amount)  && ($from_bal - $from_bal_after >= $amount)) {
                // if so : send the webhook 
                $message = createWebhookMessage($data['data'], $_REQUEST['serverName'], 
                                                $_REQUEST['shopId'], $_REQUEST['txId'], 
                                                $sender, $rawtx); 
                $res = sendWebhook($shop_url, $message);
                if ($res) {
                    $wh_status = 3;
                } else {
                    $wh_status = 2;
                }
            }
        }   
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = 'E1'.$e->getMessage();
    }
    try {
        storeAdditionalData(strlen($shop_url)>0, $data['data'], $wh_status);
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = 'E2'.$e->getMessage();
    }
    return json_encode($data);
}


function formatAddress($addr){
    if (substr($addr, 0, 2) == "0x")
        return $addr;
    else
        return "0x".$addr;
}
function getDefaultResponse(){
    $data['error'] = false;
    $data['msg'] = "";
    $data['data'] = "";
    return $data;
}
function bchexdec($hex)
{
    $dec = 0;
    $len = strlen($hex);
    for ($i = 1; $i <= $len; $i++) {
        $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
    }
    return $dec;
}
?>
