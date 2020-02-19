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
$query = "select * from testtransactions WHERE part CONTAINS ? ORDER BY time DESC limit $counter;";
$options = array('arguments' => array($addr));

$line_ct = 0;
foreach ($session->execute(new Cassandra\SimpleStatement($query), $options) as $row) {
    $jstring[$line_ct] = json_encode($row);
    $line_ct++;
}

$jstring = array_slice($jstring, -$limit);
if ($jstring != null){ 
    echo json_encode($jstring);
} else {
    echo "[]";
}
?>
