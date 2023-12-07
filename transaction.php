<?php
header('Access-Control-Allow-Origin: *');
include './checkAdmin.php';

/* request format 

 PUT (to hide the payload and prevent replay attack) 
 Json formated data:
 
 $_PUT['req'] => {"data":{
                            "caller" : "<caller 0x....>",   <= mandatory
                            "addr" :   "<caller 0x....>",   <= mandatory
                            "start" : 0 ,                   <= optional
                            "end" : 1660338149 ,            <= optional
                            "add_pending" : 1 ,             <= optional
                            "only_failed" : 0 ,             <= optional
                            "part_add" : "<part 0x....>" ,  <= optional
                            "currency" : "<currencyname>" , <= mandatory if caller != address    
                            },
                  "sign"="<signature>"}
                  
*/

/* Check payload format */
if (!array_key_exists('req', $_REQUEST)) {
   echo json_encode(array("error"=>True, "msg"=>"Missing payload!"));
   exit();
}

$payload = json_decode($_REQUEST['req']);
if (!array_key_exists('data', $payload) || !array_key_exists('sign', $payload)) {
    echo json_encode(array("error"=>True, "msg"=>"Wrong payload format!"));
    exit();
}

/* Check data format */
if (!array_key_exists('caller', $payload['data']) || !array_key_exists('addr', $payload['data']) ) {
    echo json_encode(array("error"=>True, "msg"=>"Wrong data format!"));
    exit();
}

/* reject call with wrong caller length */
if (strlen($payload['data']['caller']) == 42) {
	$caller = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $payload['data']['caller']));
} else {
    echo json_encode(array("error"=>True, "msg"=>"Wrong caller format!"));
	exit();
}

if (strlen($payload['data']['addr']) == 42) {
	$addr = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $payload['data']['addr']));
} else {
    echo json_encode(array("error"=>True, "msg"=>"Wrong address format!"));
	exit();
}

/* Case Admin (if addr!=caller it must be admin)*/
if ($caller!=$addr){
    if (array_key_exists('currency', $payload['data'])){
        if (!checkLegitimateAdmin($payload['data'], $payload['sign'], $caller, $payload['data']['currency'])) {
            echo json_encode(array("error"=>True, "msg"=>"Not valid Admin!"));
	        exit();
        }  
    } else {
    echo json_encode(array("error"=>True, "msg"=>"missing currency"));
	      exit();
    }    
} else {
    if ( !checkSign($payload['data'], $payload['sign'], $caller)) {
    echo json_encode(array("error"=>True, "msg"=>"Bad signature or no right!"));
	    exit();
    }
}



require_once 'libs/jsonRPCClient.php';

function getRPCResponse($result){
    if(isset($result['result'])){
        return $result['result'];
    } else {
        throw new Exception($result['error']['message']);
    }
}

$start = 0;
if (!empty($payload['data']['start']) && is_numeric($payload['data']['start'])){
	$start = $payload['data']['start'];
} 

$end = time();
if (!empty($payload['data']['end']) && is_numeric($payload['data']['end'])){
	$end = $payload['data']['end'];
} 

$part_add='';
if (!empty($payload['data']['partnair_addr']) && strlen($payload['data']['partnair_addr']) == 42) {
	$part_add = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $payload['data']['partnair_addr']));
}

$add_pending = true;
if (!empty($payload['data']['add_pending']) && $payload['data']['add_pending'] == 0) {
    $add_pending = false;
}

$only_failed = false;
if (!empty($payload['data']['only_failed']) && $payload['data']['only_failed'] == 1) {
    $only_failed = true;
    $add_pending = false;
}

$currency ='';
if (array_key_exists('currency', $payload['data'])  && !empty($payload['data']['currency']) ) {
    $currency = $payload['data']['currency'];
}


 
$cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_ro", "Public_transactions")
                ->build();
$keyspace  = 'comchain';
$session  = $cluster->connect($keyspace);



// commited trans
$query = "select * from testtransactions WHERE add1 = ? AND status = 0 AND time >= $start AND time <= $end ORDER BY time DESC;";
$options = array('arguments' => array($addr));
$full_set_row_com = $session->execute(new Cassandra\SimpleStatement($query), $options);

$full_set_row = [];
$commited_hash = [];
foreach ($full_set_row_com as $row) {
    if (!only_failed) {
        array_push($full_set_row, $row);
    }
    array_push($commited_hash, $row['hash']);
}

// Pending trans
$full_set_row_pending  = [];
$query = "select * from testtransactions WHERE add1 = ? AND status = 1 and  AND time >= $start AND time <= $end  ORDER BY time DESC;";

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
	    if ($add_pending || $row['status']==2) {
	        array_push($full_set_row, $row);
	    }
	}
}


// remouve unvanted address in case of filter on the other address
if ($part_add!='') {
    $new_row_set = [];
    foreach ($full_set_row as $row_match){ 
        if ($row_match['add2']==$part_add){
            array_push($new_row_set, $row_match);
        }
    }
    $full_set_row = $new_row_set;
}

// filter over  the currency 
if ($currency!=''){
    $new_row_set = [];
    foreach ($full_set_row as $row_match){ 
        if (!array_key_exists('currency',$row_match) || $row_match['currency']=='' || $row_match['currency']==$currency){
            array_push($new_row_set, $row_match);
        }
    }
    $full_set_row = $new_row_set;
}

// order by time
usort($full_set_row, function($a, $b) { 
        $va=$a['time']->value(); 
        $vb=$b['time']->value();
        if ($va==$vb){
            return 0;
        } else if ($va>$vb) {
            return -1;
        } else {
            return 1;
        }
    });
    
    

    
$line_ct = 0;
$jstring = [];
foreach ($full_set_row as $row) {
    if ($row['direction']==1){
        $row['addr_from'] = $row['add1'];
        $row['addr_to'] = $row['add2'];
    } else {
        $row['addr_from'] = $row['add2'];
        $row['addr_to'] = $row['add1'];
    }
    
    $row['time'] = $row['time']->value();
    if(!is_null($row['receivedat'])) {
        $row['receivedat'] = $row['receivedat']->value();
    } else {
        $row['receivedat'] =  $row['time'];    // for old transaction without receivedAt
    }
    

    $jstring[$line_ct] = json_encode($row);
    $line_ct++;
}


    
  

    echo json_encode($jstring);  


?>
