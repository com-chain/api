<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

include './checkAdmin.php';
include './Webhook.php';
require_once 'libs/jsonRPCClient.php';

$gethRPC = new jsonRPCClient('http://127.0.0.1:8545');

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
function getPoolContent($gethRPC){
    $data = getDefaultResponse();
    try {
        $data['data'] = getRPCResponse($gethRPC->txpool_content());
    }
    catch (exception $e) {
        $data['error'] = true;
        $data['msg'] = $e->getMessage();
    }
    return json_encode($data);
}
echo getPoolContent($gethRPC);

?>
