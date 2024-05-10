<?php
header('Access-Control-Allow-Origin: *');
if (strlen($_GET['addr']) == 42) {
	$addr = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['addr']));
} else {
	echo "Bye!";
}

require_once 'libs/jsonRPCClient.php';

function getRPCResponse($result){
    if(isset($result['result'])){
        return $result['result'];
    } else {
        return  array ("blockNumber"=>'0x0', 'error'=>'GETH return no result for this trn hase');
    }
}

if (is_numeric($_GET['start'])){
	$start = $_GET['start'];
} else {
	$start = 0;
}

if (is_numeric($_GET['end'])){
	$end = $_GET['end'];
} else {
	$end = 2660338149;
}
/*
$currencies = ["Agnel", "COEUR", "Gemme", "LaPive", "Leman-EU", "Lemanopolis", "Lokacoin", "Monnaie-Leman", "Racine", "Tissou"];

$curr = $_GET['curr'];
if (!in_array($curr,  $currencies)) {
    echo 'Missing or not recognised currency';
    exit();
}
*/

$gethRPC = new jsonRPCClient('http://127.0.0.1:8545');

$cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_ro", "Public_transactions")
                ->build();
$keyspace  = 'comchain';
$session  = $cluster->connect($keyspace);

// commited trans
$query = "select * from testtransactions WHERE add1=? AND status = 0 AND time >= $start AND time <= $end ORDER BY time DESC;";
$options = array('arguments' => array($addr));
$full_set_row_com = $session->execute(new Cassandra\SimpleStatement($query), $options);

$commited_hash = [];
foreach ($full_set_row_com as $row) {
    array_push($commited_hash, $row['hash']);
}

// Pending trans
$Not_matched_pending  = [];
$query = "select * from testtransactions WHERE add1=? AND  status = 1 AND time >= $start AND time <= $end  ORDER BY time DESC;";
$options = array('arguments' => array($addr));
$full_set_row_pending = $session->execute(new Cassandra\SimpleStatement($query), $options);
    
    foreach ($full_set_row_pending as $row) {
        $hash = $row['hash'];
        if ( !in_array($hash,  $commited_hash)) {
            $ret = getRPCResponse($gethRPC->eth_getTransactionByHash($hash),"pending");
            $row['transaction']=$ret;
            if ($row['transaction']['blockNumber']!='0x0'){
                $row['status']=2;
            }
            array_push($Not_matched_pending, $row);
        }
    }


echo json_encode(array("date_start"=>gmdate("Y-m-d\TH:i:s\Z", $start), "date_end"=>gmdate("Y-m-d\TH:i:s\Z", $end), "number_completed"=>count($commited_hash),"number_pending_and_rejected"=>count($Not_matched_pending),  "pending_and_rejected"=>$Not_matched_pending));
?>
