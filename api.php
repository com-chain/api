<?php
header('Access-Control-Allow-Origin: *');
include './checkAdmin.php';
include './Webhook.php';
require_once 'libs/jsonRPCClient.php';

$gethRPC = new jsonRPCClient('http://127.0.0.1:8545');
if(isset($_REQUEST['balance'])){
    header('Content-Type: application/json');
    echo getEthBalance($_REQUEST['balance'],$gethRPC);
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
        $query = "SELECT * FROM testtransactions WHERE hash = ?"; 
        $options = array('arguments' => array($hash));

	    foreach ($session->execute(new Cassandra\SimpleStatement($query), $options) as $row) {
	        if ($row['direction']==1){
	            if ( $row['status']==0 || !isset($arr)) {
	                $arr = $row;
	                $arr['addr_from'] = $arr['add1'];
                    $arr['addr_to'] = $arr['add2'];
                    $arr['time'] = $arr['time']->value();
                }
	        }
	        
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
function getEthBalance($addr, $gethRPC)
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

function storeTransaction($is_valid_shop, $transaction_ash, $web_hook_status, $amount, $from, $to ,$trans_type) {
    $shop_id=$_REQUEST['shopId'];
    $shop_ref=$_REQUEST['txId'];
    $delegate=$_REQUEST['delegate'];
    $parent_hash=$_REQUEST['parent_hash'];
    $memo_from=$_REQUEST['memo_from'];
    $memo_to=$_REQUEST['memo_to'];
    
    $do_insert=false;
    $fields =array();
    $val =array();
    
    $fields['add1'] = $from;
    $val[]='?'; 
    $fields['add2'] = $to;
    $val[]='?'; 
    $fields['direction'] = 1;
    $val[]='?'; 
    
    $fields['status'] = 1;
    $val[]='?'; 
    
    $fields['hash'] = $transaction_ash;
    $val[]='?'; 
    
   
    $fields['sent'] = $amount;
    $val[]='?';
    $fields['recieved'] = $amount;
    $val[]='?';
    $fields['tax'] = 0;
    $val[]='?';
    $fields['type'] = $trans_type;
    $val[]='?'; 
    
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
    
    if (isset($parent_hash)) {
        $fields['linked_hash'] = $parent_hash;
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
    
    /* incompatibiliy between the number of bytes=> set in the text
    $fields['time'] = ''.time();
    $val[]='?';
    */

    // build the query
    $query = "INSERT INTO testtransactions (".join(', ',array_keys($fields));
    $query = $query.',time) VALUES ('.join(', ',$val).','.time().')';
    
    $cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_rw", "Private_access_transactions")->build();
    $keyspace  = 'comchain';
    $session1  = $cluster->connect($keyspace);

    $session1->execute(new Cassandra\SimpleStatement($query), array('arguments' => $fields));
 
    $cluster2  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_rw", "Private_access_transactions")->build();
    $session2  = $cluster2->connect($keyspace);
    $fields['add1'] = $to;
    $fields['add2'] = $from;
    $fields['direction'] = 2; 
    $session2->execute(new Cassandra\SimpleStatement($query), array('arguments' => $fields));
    
    
}

function getContract1($contract) { 
    $contract = '0x'.$contract;
    // legacy cases: Lemanopolis, Monnaie-Leman
    if (strtolower($contract)==strtolower("0xE616BE14B489c33c8CC3D5974A5DeAad6E4A33c8")){
        $contract="0x85291865Ac4b11b086EAf901E6116eba014244cE";
    } else if (strtolower($contract)==strtolower("0xF765D6608c9B4640BB0af860771793c41C4b8eF8")){
        $contract="0xB86C066396A6f21F17301E9acfec2a0Fc5c76116";
    }  
    
    return $contract;  

}


function in_my_array($needle, $array){
   
    foreach ($array as $elem) {
        if ($needle==$elem){
            return true;
        }
    }
    return false; 
}

function sendRawTransaction($rawtx,$gethRPC){
    $data = getDefaultResponse();
                            //   Direct      From    On Behalf   Accept Request
    $transfert_NA_functions = ['a5f7c148','58258353','1b6b1ee5','132019f4'];
    $transfert_CM_functions = ['60ca9c4c','2ef9ade2','74c421fe','1415707c'];
    $transfert_functs =  array_merge($transfert_NA_functions,$transfert_CM_functions);
     
    try {
        $tr_info = substr($rawtx,-316,182);
        //get the type of transfert
        $funct_address = strtolower(substr($tr_info,46,8));
        // get the sender
        $sender = TransactionEcRecover($rawtx)[0];
        // get the contract for the balances
        $contract = getContract1(substr($tr_info,0,40));
  
        $need_pending = false;
        $amount = 0;
        $trans_type = '';
        if (in_my_array($funct_address, $transfert_functs)) {

            if ($funct_address==$transfert_NA_functions[0] || $funct_address==$transfert_CM_functions[0]) {
                // Direct Transfert
                 
                // get the dest
                $dest = '0x'.substr($tr_info,78,40);
                // get the amount
                $amount = hexdec(substr($tr_info,-64));
                //get the type of transfert
                $funct_address = substr($tr_info,46,8);
                
                // get the infos 
                $from_status = getAccStatus($sender, $contract);
                $from_Nt_bal = getNTBalance($sender, $contract);
                $from_Cm_bal = getCMBalance($sender, $contract);
                $from_Cm_lim_m = getCMLimitM($sender, $contract);
                
                $to_status = getAccStatus($dest, $contract);
                $to_Cm_bal = getCMBalance($dest, $contract);
                $to_Cm_lim_p = getCMLimitP($dest, $contract);
                
                $check_passed = $from_status==1 && $to_status==1;
                
                // Check transaction is possible
                if ($funct_address==$transfert_NA_functions[0]) {  // Nanti
                    $check_passed &= $from_Nt_bal>=$amount;
                    $trans_type = 'Transfer';
                } else if ($funct_address==$transfert_CM_functions[0]) { // Mutual Credit
                    // dest can accept amount
                    $check_passed &= $to_Cm_bal + $amount < $to_Cm_lim_p;
                    // sender has credit 
                    $check_passed &=$from_Cm_bal-$amount > $from_Cm_lim_m;
                    $trans_type = 'TransferCredit';
                } else {
                    $check_passed = false;
                }
                $need_pending = $check_passed;
            } else if ($funct_address==$transfert_NA_functions[1] || $funct_address==$transfert_CM_functions[1]) {
                // Transfert from 
                // TODO
            } else if ($funct_address==$transfert_NA_functions[2] || $funct_address==$transfert_CM_functions[2]) {
                // Transfert On Behalf 
                // TODO
            } else if ($funct_address==$transfert_NA_functions[3] || $funct_address==$transfert_CM_functions[3]) {
                // Accept reqest
                // TODO
            }
        
        }
          
        $data['data'] = getRPCResponse($gethRPC->eth_sendRawTransaction($rawtx));
        
        $shop_url = validateShopData();
        $wh_status = 0;
        if (strlen($shop_url)>0 && $amount > 0) {
            $wh_status = 1;
        }
        
        if (strlen($shop_url)>0 && $amount > 0) {
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
        storeAdditionalData(strlen($shop_url)>0, $data['data'], $wh_status);
        
        if ($need_pending) {
            storeTransaction(strlen($shop_url)>0, $data['data'], $wh_status, $amount, $sender, $dest, $trans_type); 
        }
       
    } catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
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
