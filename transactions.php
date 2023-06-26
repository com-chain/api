<?php
header('Access-Control-Allow-Origin: *');
include './checkAdmin.php';

/* request format 

 PUT (to hide the payload and prevent replay attack) 
 Json formated data:
 
 $_PUT['req'] => {"data":{
                            "caller" : "<caller 0x....>",   <= mandatory
                            "addr" :   "<caller 0x....>",   <= mandatory
                            "count" : 500 ,                 <= optional
                            "offset" : 0 ,                  <= optional
                            "start" : 0 ,                   <= optional
                            "end" : 1660338149 ,            <= optional
                            "add_pending" : 1 ,             <= optional
                            "part_add" : "<part 0x....>" ,  <= optional
                            "admin" : "<currencyname>" ,    <= optional    
                  "sign"="<signature>"}

*/

/* Check payload format */
$payload = json_decode($_PUT['req']);
if (!array_key_exists('data', $payload) || !array_key_exists('sign', $payload)) {
    echo "Wrong payload format!";
    exit();
}

/* Check data format */
if (!array_key_exists('caller', $payload['data']) || !array_key_exists('caller', $payload['addr']) ) {
    echo "Wrong data format!";
    exit();
}

/* reject call with wrong caller length */
if (strlen($payload['data']['caller']) == 42) {
	$caller = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $payload['data']['caller']));
} else {
	echo "Wrong caller format!";
	exit();
}

if (strlen($payload['data']['addr']) == 42) {
	$addr = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $payload['data']['addr']));
} else {
	echo "Wrong address format!";
	exit();
}


/* Case Admin */
if (array_key_exists('admin', $payload['data'])){
    if (!checkLegitimateAdmin($payload['data'], $payload['sign'], $caller, $payload['data']['admin'])) {
        echo "Not valid Admin!";
	    exit();
    }  
} else {
    if ($caller!=$addr || !checkSign($payload['data'], $payload['sign'], $caller)) {
     	echo "Bad signature or no right!";
	    exit();
    }
}


$limit = 500;
if (!empty($payload['data']['count']) && is_numeric($payload['data']['count'])){
	$limit = $payload['data']['count'];
}

$offset = 0;
if (!empty($payload['data']['offset']) && is_numeric($payload['data']['offset'])){
	$offset = $payload['data']['offset'];
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
if (!empty($payload['data']['add_p']) && $payload['data']['add_p'] == 0) {
    $add_pending = false;
}

 
$cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_ro", "Public_transactions")
                ->build();
$keyspace  = 'comchain';
$session  = $cluster->connect($keyspace);

$counter = $offset + $limit;
if ($part_add!=''){  
    $counter = $offset + 500;
}


// commited trans
$query = "select * from testtransactions WHERE add1 = ? AND status = 0 AND time >= $start AND time <= $end ORDER BY time DESC limit $counter;";
$options = array('arguments' => array($addr));
$full_set_row_com = $session->execute(new Cassandra\SimpleStatement($query), $options);

$full_set_row = [];
foreach ($full_set_row_com as $row) {
    array_push($full_set_row, $row);
}

// Pending trans
$full_set_row_pending  = [];
if ($add_pending) {
    $query = "select * from testtransactions WHERE add1 = ? AND status = 1 and  AND time >= $start AND time <= $end  ORDER BY time DESC limit $counter;";

    $options = array('arguments' => array($addr));
    $full_set_row_pending = $session->execute(new Cassandra\SimpleStatement($query), $options);

    foreach ($full_set_row_pending as $row) {
        $hash = $row['hash'];
        $duplicate = false;
        foreach ($full_set_row as $row_match){
            if ($row_match['status']==0 && $row_match['hash']==$hash){
                $duplicate = true;
                continue;
            }
        }
        if (!$duplicate) {
            array_unshift($full_set_row, $row);
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

if (sizeof($jstring)<$offset) {
    echo '[]';
} else {
    $jstring = array_slice($jstring, $offset);
    if (sizeof($jstring)>$limit){
       $jstring = array_slice($jstring, 0, $limit); 
    }

    echo json_encode($jstring);  
}

?>
