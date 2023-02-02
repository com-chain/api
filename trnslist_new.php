<?php
include './checkAdmin.php';
header('Access-Control-Allow-Origin: *');

/*
*  Expected format:
*  $_POST['signature'] = < 0x+134 char string>    
*  $_POST['data'] = {caller=<address>,address=<address>,offset=<int>,count=<int>,server=<string(currency name)>}
*  
*  Verification: checkSign($dat, $signature, $caller)
*  3 cases:
*     - caller==address asking for his own transaction: ok
*     - server is set and caller is an admin of the currency: checkLegitimateAdmin($dat, $signature, $caller, $server)
*     - caller is the address of the public_message_key of the wallet (check is done into cassandra 'keyStore' table)
*/



///////////////////////////////////////////////////////////////////////////////////////////
/////     Get data from Cassandra
///////////////////////////////////////////////////////////////////////////////////////////
function getTransactions($addr,$offset,$limit) {

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
    $query = "select * from testtransactions WHERE add1 = ? AND status = 1 and time>=".(time()-3600)." ORDER BY time DESC limit $counter;";

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
}

function isValidMessageKey($address, $message_address) {
    $cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("keystore_rw", "Private_access_keystore")->build();
    $keyspace  = 'comchain';
    $session = $cluster->connect($keyspace);
    
    $query = "SELECT public_message_key FROM keyStore WHERE address = '$address'"; 
   
    $counter=0;
    foreach ($session->execute(new Cassandra\SimpleStatement($query)) as $row) {
        $string[$counter] = keyToAddress($row['public_message_key']);
        $counter++;
    }
    
    isset($string) or exit(False);
    return $string[0] == $message_address;
}







///////////////////////////////////////////////////////////////////////////////////////////
/////     Handle input
///////////////////////////////////////////////////////////////////////////////////////////

$signature = $_POST['signature'];
$data = $_POST['data'];
$decoded_data = json_decode($data);

// caller is mandatoy
$caller = '';
if (!array_key_exists('caller', $decoded_data) || strlen($decoded_data['caller']) == 42) {
    $caller = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $decoded_data['caller']));
}  else {
	echo "Bye!";
	exit();
}

// address is mandatory
$address = '';
if (!array_key_exists('address', $decoded_data) || strlen($decoded_data['address']) == 42) {
    $address = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $decoded_data['address']));
}  else {
	echo "Bye!";
    exit();
}

// count is optional: by default 5
$limit = 5;
if (!empty($decoded_data['count']) && is_numeric($decoded_data['count'])) {
	$limit = $decoded_data['count'];
}

// offset is optional: by default 0
$offset = 0;
if (!empty($decoded_data['offset']) && is_numeric($decoded_data['offset'])) {
	$limit = $decoded_data['offset'];
}

// server is optional by default ''
$server=''
if (!array_key_exists('server', $decoded_data)) {
    $server = filter_var($decoded_data['server'], FILTER_SANITIZE_STRING);
}

///////////////////////////////////////////////////////////////////////////////////////////
/////     Check signature and right
///////////////////////////////////////////////////////////////////////////////////////////
if (!checkSign($data, $signature, $caller)) {
    exit();
}

// signature is valid:
if ($caller==$address || checkLegitimateAdmin($data, $signature, $caller, $server) || isValidMessageKey($address, $caller)) {
    getTransactions($address,$offset,$limit)
}

?>
