<?php
header('Access-Control-Allow-Origin: *');
include './checkAdmin.php';
include './Webhook.php';
require_once 'libs/jsonRPCClient.php';

$gethRPC = new jsonRPCClient('http://127.0.0.1:8545');

if(isset($_REQUEST['hash'])){
    header('Content-Type: application/json');
    echo blockByHash($_REQUEST['hash'],$gethRPC);
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

function blockByHash($gethRPC,$blchash){
    $data = getDefaultResponse();
    try {
        $ret = getRPCResponse($gethRPC->eth_getBlockByHash($blcnb,true));
        $data["data"]=$ret;
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

