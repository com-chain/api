<?php
require_once './Keccak.php';
require_once './ecrecover_helper.php';
use kornrunner\Keccak;

header('Access-Control-Allow-Origin: *');
/// Expected useage:
// GET with 'addr' and 'private' (optional)
// Post with 'data' a json containing the sender 'address', the 'public_message_key' and the ciphered 'private_message_key'
//       and 'sign' the signature of the data with the private key associated to the address.



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST case
    
    // Check signature
    $input_data = $_POST['data'];
    $input_obj = json_decode($_POST['data']);
    
    $input_sign = $_POST['sign'];
    $ec_recover_result = personal_ecRecoverPublic($input_data, $input_sign);
    if ($ec_recover_result[0] !== $input_obj->{'address'}){
        // wrong signature
        exit("Bye!");
    }
    
    // insert data
    $address = ec_recover_result[0];
    $main_public_key = ec_recover_result[1];
    $public_message_key = preg_replace("/[^a-zA-Z0-9]+/", "", $input_obj->{'public_message_key'});
    $private_message_key = str_replace("'","",$input_obj->{'private_message_key'});
    
    $query = "INSERT INTO keyStore (address, public_key, public_message_key, private_message_key) VALUES ('$address', '$main_public_key','$public_message_key', '$private_message_key')";
    $session->execute(new Cassandra\SimpleStatement($query));
    echo '{"data":{"result":"OK"}}';
    
    
} else {
    // GET case
    
    // Check inputs
    $addr = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['addr']));
    if (strlen($addr) != 42) {
	    exit("Bye!");
    }

    if (empty($_GET['private']) or $_GET['private'] != 1) {
	    $_GET['private'] = 0;
    }
    
    // get the data from the DB 
    $session = getDBSession();
     
    $query = "SELECT public_key, public_message_key FROM keyStore WHERE address = '$addr'"; 
    if ($_GET['private']==1){
        $query = "SELECT public_key, public_message_key, private_message_key FROM keyStore WHERE address = '$addr'";
    }

    // the address is a primary key it should be only 0 or 1 row
    $counter=0;
    foreach ($session->execute(new Cassandra\SimpleStatement($query)) as $row) {
        $string[$counter] = json_encode($row);
        $counter++;
    }

    // Return empty object if address not found
    isset($string) or exit('{"data":[]}');

    // return the keys
    echo '{"data":'+$string[0]+'}';
}




/*
FUNCTIONS
*/

function getDBSession() {
    $cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("keystore_rw", "Private_access_keystore")->build();
    $keyspace  = 'comchain';
    return $cluster->connect($keyspace);
}

?>
