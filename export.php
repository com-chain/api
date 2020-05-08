<?php
header('Access-Control-Allow-Origin: *');
if (strlen($_GET['addr']) == 42) {
	$addr = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['addr']));
}else{
	echo "Bye!";
}

if (empty($_GET['start'])) {
	$_GET['start'] = 0;
}

if (empty($_GET['end'])) {
	$_GET['end'] = 1999999999;
}

if (is_numeric($_GET['start'])){
	$start = $_GET['start'];
} else {
	$start = 0;
}

if (is_numeric($_GET['end'])){
	$end = $_GET['end'];
} else {
	$end = 1999999999;
}
 

$cluster  = Cassandra::cluster('127.0.0.1') 
		->withCredentials("transactions_ro", "Public_transactions") 
		-> withDefaultPageSize(null)
                ->build();
$keyspace  = 'comchain';
$session  = $cluster->connect($keyspace);

// commited trans
$query = "select * from testtransactions WHERE add1 = ? AND status = 0 AND time >= $start AND time <= $end ORDER by time";
$options = array('arguments' => array($addr));
$full_set_row_com = $session->execute(new Cassandra\SimpleStatement($query), $options);

$full_set_row = [];
foreach ($full_set_row_com as $row) {
    array_push($full_set_row, $row);
}
/*
// Pending trans
$query = "select * from testtransactions WHERE add1 = ? AND status = 1 AND time >= $start AND time <= $end ORDER by time";
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
usort($full_set_row, function($a, $b) { 
        $va=$a['time']->value(); 
        $vb=$b['time']->value();
        if ($va==$vb){
            return 0;
        } else if ($va<$vb) {
            return -1;
        } else {
            return 1;
        }
    });
*/

$line_ct = 0;
$jstring=[];
foreach ($full_set_row as $row) {
    if ($row['direction']==1){
        $row['addr_from'] = $row['add1'];
        $row['addr_to'] = $row['add2'];
    } else {
        $row['addr_from'] = $row['add2'];
        $row['addr_to'] = $row['add1'];
    }
    
    $row['time'] = $row['time']->value();
    
    $jstring[$line_ct] = json_encode($row);
    $line_ct++;
}

echo json_encode($jstring);

?>
