<?php
header('Access-Control-Allow-Origin: *');
if (strlen($_GET['addr']) == 42) {
	$addr = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['addr']));
}else{
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
 
$query = "SELECT hash from trans_by_addr WHERE addr CONTAINS ?";
$options = array('arguments' => array($addr));
$counter_tr=0;
foreach ($session->execute(new Cassandra\SimpleStatement($query), $options) as $row) {
  $string[$counter_tr] = implode(",",$row);
  $counter_tr++;
}
isset($string) or exit("[]");
$hashes = json_encode($string);
$hashes = str_replace("[", "(", $hashes);
$hashes = str_replace("]", ")", $hashes);
$hashes = str_replace("\"", "'", $hashes);
$counter = min($offset + $limit, $counter_tr);
$query = "select * from tokentransactions WHERE hash IN $hashes ORDER BY time DESC limit $counter;";

$counter_res = 0;
foreach ($session->execute(new Cassandra\SimpleStatement($query)) as $row) {
   $jstring[$counter_res] = json_encode($row);
   $counter_res++;
}
$number_rec = $counter_res - $offset;
if ($number_rec>0){
    $jstring = array_slice($jstring, -$number_rec);
} else {
    $jstring = null;
}

if ($jstring != null){ 
    echo json_encode($jstring);
}else{
    echo "[]";
}
?>
