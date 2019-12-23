<?php
require_once './Keccak.php';
require_once './ecrecover_helper.php';
use kornrunner\Keccak;

header('Access-Control-Allow-Origin: *');
/*

UseCases:
Add a reference:

    POST with:
         data = {'add_req'='0x123...', 'add_to'='0x123...', 'ref_to'='0x123...', 'ref_req'='0x123...'}
         sign = 0x123..  
         
Read a reference :        
    GET with:
        add_req = 0x123.. 
        add_to = 0x123.. 
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST case
    
    // Check signature
    $input_data = $_POST['data'];
    $input_obj = json_decode($_POST['data']);
    
    $input_sign = $_POST['sign'];
    $ec_recover_result = personal_ecRecover($input_data, $input_sign);
    if ($ec_recover_result !== $input_obj->{'add_req'}){
        // wrong signature
        exit("Bye!");
    }
    
    // insert data
    $add_from = $ec_recover_result;
    $add_to = preg_replace("/[^a-zA-Z0-9]+/", "", $input_obj->{'add_cli'});
    $ref_from = preg_replace("/[^a-zA-Z0-9]+/", "", $input_obj->{'ref_req'});
    $ref_to = preg_replace("/[^a-zA-Z0-9]+/", "", $input_obj->{'ref_cli'});


    $session = getDBSession();
    $query = "INSERT INTO request_reference (add_from, add_to, ref_from, ref_to) VALUES (?,?,?,?)";
    $options = array('arguments' => array($add_from, $add_to, $ref_from, $ref_to));
    
    $session->execute(new Cassandra\SimpleStatement($query), $options);
    echo '{"result":"OK"}';
    
    
} else {
    // GET case
    
    // Check inputs
    $addr_from = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['add_req']));
    if (strlen($addr_from) != 42) {
	    exit("Bye!");
    }
    $addr_cli = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['add_cli']));
    if (strlen($addr_cli) != 42) {
	    exit("Bye!");
    }

    // get the data from the DB 
    $session = getDBSession();
     
    $query = "SELECT ref_from, ref_to FROM request_reference WHERE add_from = '$addr_from' and add_to = '$addr_cli'"; 
   

    // the address is a primary key it should be only 0 or 1 row
    $counter=0;
    foreach ($session->execute(new Cassandra\SimpleStatement($query)) as $row) {
        $string[$counter] = json_encode($row);
        $counter++;
    }

    // Return empty object if address pait is not found
    isset($string) or exit("[]");

    // return the keys
    echo $string[0];
}




/*
FUNCTIONS
*/

function getDBSession() {
    $cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("webhook_rw", "Private_access_transactions")->build();
    $keyspace  = 'comchain';
    return $cluster->connect($keyspace);
}

?>
