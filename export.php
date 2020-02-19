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


$query = "select * from testtransactions WHERE part CONTAINS ? AND time >= '$start' AND time <= '$end' ORDER by time";
$options = array('arguments' => array($addr));


$line_ct = 0;
$hashes = [];
foreach ($session->execute(new Cassandra\SimpleStatement($query), $options) as $row) {
    if ($row['wh_status'] == 0) { // confirmed trans
        $hashes[] = $row['hash'];
    } else if (in_array($row['hash'], $hashes)){ // pending trans already found as completed
        continue;
    }
    $jstring[$line_ct] = json_encode($row);
    $line_ct++;
}


if ($jstring != null){
echo json_encode($jstring);
}else{
echo "[]";
}
 
?>
