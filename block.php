<?php
header('Access-Control-Allow-Origin: *');
include './checkAdmin.php';
include './Webhook.php';
require_once 'libs/jsonRPCClient.php';

$gethRPC = new jsonRPCClient('http://127.0.0.1:8545');

if(isset($_REQUEST['hash'])){
    header('Content-Type: application/json');
    echo getTransaction($_REQUEST['hash'],$gethRPC);
} else if(isset($_REQUEST['block']) && isset($_REQUEST['index'])){
    header('Content-Type: application/json');
    echo getTransactionInBlock($_REQUEST['block'],$_REQUEST['index'],$gethRPC);
} else if(isset($_REQUEST['block'])){
    header('Content-Type: application/json');
    echo blockByNumber($gethRPC,$_REQUEST['block']);
} else {
    header('Content-Type: application/json');
    echo blockNumber($gethRPC);
}

function getRPCResponse($result){
    if(isset($result['result'])){
        return $result['result'];
    } else {
        throw new Exception($result['error']['message']);
    }
}

function getDefaultResponse(){
    $data['error'] = false;
    $data['msg'] = "";
    $data['data'] = "";
    return $data;
}

function blockNumber($gethRPC){
    $data = getDefaultResponse();
    try {
        $ret = getRPCResponse($gethRPC->eth_blockNumber());
        $data['data'] = array("blockNumber"=>hexdec($ret));
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}


function blockByNumber($gethRPC,$blcnb){
    $data = getDefaultResponse();
    try {
        $ret = getRPCResponse($gethRPC->eth_getBlockByNumber($blcnb,true));
        $data["data"]=$ret;
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
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

function getTransactionInBlock($block,$index,$gethRPC) {
    $data = getDefaultResponse();
    try {
        $data = getRPCResponse($gethRPC->eth_getTransactionByBlockNumberAndIndex($block, $index),"pending");
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}

