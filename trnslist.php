<?php
header('Access-Control-Allow-Origin: *');
if (strlen($_GET['addr']) == 42) {
	$addr = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['addr']));
} else {
	echo "Bye!";
}

if (empty($_GET['count'])) {
	$_GET['count'] = 5;
}

if (empty($_GET['offset'])) {
	$_GET['offset'] = 0;
}

if (is_numeric($_GET['count'])){
	$limit = $_GET['count'];
} else {
	$limit = 5;
}

if (is_numeric($_GET['offset'])){
	$offset = $_GET['offset'];
} else {
	$offset = 0;
}
 
$cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_ro", "Public_transactions")
                ->build();
$keyspace  = 'comchain';
$session  = $cluster->connect($keyspace);

$counter = $offset + $limit;

// commited trans
$query = "select * from testtransactions WHERE add1 = ? AND status = 0 ORDER BY time DESC limit $counter;";
$options = array('arguments' => array($addr));
$full_set_row_com = $session->execute(new Cassandra\SimpleStatement($query), $options);

$full_set_row = [];
foreach ($full_set_row_com as $row) {
    array_push($full_set_row, $row);
}

// Pending trans
$query = "select * from testtransactions WHERE add1 = ? AND status = 1 ORDER BY time DESC limit $counter;";
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
    
    $row['dbg'] = sizeof($full_set_row_pending);

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
