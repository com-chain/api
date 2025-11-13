<?php


// Include the phpseclib library for elliptic curve operations
require_once 'vendor/autoload.php'; // Assuming phpseclib is installed using Composer

use Elliptic\EC;

function DecryptMessage(string $privateKeyHex, string $encryptedHex): string {
    // strip 0x if present
    if (strpos($privateKeyHex, '0x') === 0) $privateKeyHex = substr($privateKeyHex, 2);
    if (strpos($encryptedHex, '0x') === 0) $encryptedHex = substr($encryptedHex, 2);

    $encrypted = hex2bin($encryptedHex);
    if ($encrypted === false) {
        throw new \InvalidArgumentException('Invalid encrypted hex');
    }

    // parse components (match the python offsets)
    // iv = encrypted[:16]
    // ephemPubKeyEncoded = encrypted[17:81]  (so byte 16 is the 0x04 prefix)
    // mac = encrypted[81:113]
    // ciphertext = encrypted[113:]
    if (strlen($encrypted) < 113) {
        throw new \InvalidArgumentException('Encrypted buffer too short '.strlen($encryptedHex).' '.strlen($encrypted) );
    }

    $iv = substr($encrypted, 0, 16);
    $prefixByte = ord(substr($encrypted, 16, 1));              // should be 0x04
    $ephemPubKeyEncoded = substr($encrypted, 17, 64);         // 64 bytes: X||Y
    $mac = substr($encrypted, 81, 32);                        // 32 bytes
    $ciphertext = substr($encrypted, 113);

    if ($prefixByte !== 0x04) {
        // the Python code expected an uncompressed pubkey marker (0x04)
        // If it's not present you may need to adapt for compressed keys.
        throw new \RuntimeException('Unexpected ephemeral public key prefix (expected 0x04)');
    }

    // build full uncompressed public key (0x04 + X + Y) in hex
    $ephemPubKeyFull = "\x04" . $ephemPubKeyEncoded;
    $ephemPubKeyHex = bin2hex($ephemPubKeyFull);

    // create curve object
    $ec = new EC('secp256k1');

    // load private key and ephemeral public key
    $privKey = $ec->keyFromPrivate($privateKeyHex, 'hex');
    $ephemPub = $ec->keyFromPublic($ephemPubKeyHex, 'hex');

    // ECDH: derive shared secret (returns a BN-ish object in this lib)
    // -> $z will be a big integer-like object; convert to hex then to binary
    $z = $privKey->derive($ephemPub->getPublic()); // BN
    $sharedHex = $z->toString(16);
    // ensure even length hex
    if (strlen($sharedHex) % 2 !== 0) $sharedHex = '0' . $sharedHex;
    $px = hex2bin($sharedHex);

    // Derive keys: SHA512(px) => encryptionKey (first 32), macKey (last 32)
    $hashPx = hash('sha512', $px, true);
    $encryptionKey = substr($hashPx, 0, 32);
    $macKey = substr($hashPx, 32, 32);

    // Reconstruct dataToMac: iv + bytearray([4]) + ephemPubKeyEncoded + ciphertext
    $dataToMac = $iv . chr(4) . $ephemPubKeyEncoded . $ciphertext;

    // compute HMAC-SHA256 and compare
    $computedMac = hash_hmac('sha256', $dataToMac, $macKey, true);
    if (!hash_equals($computedMac, $mac)) {
        throw new \RuntimeException('MAC mismatch');
    }

    // decrypt AES-256-CBC (raw data)
    $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);
    if ($plaintext === false) {
        throw new \RuntimeException('AES decryption failed');
    }

    return $plaintext;
}




header('Access-Control-Allow-Origin: *');
if (strlen($_POST['addr']) == 42) {
	$addr = strtolower(preg_replace("/[^a-zA-Z0-9]+/", "", $_POST['addr']));
} else {
	echo "Format of the Address not valid!";
	exit;
}

$limit = 5;
if (is_numeric($_POST['max_count'])){
	$limit = $_POST['max_count'];
} 


if (empty($_POST['msg_key'])) {
	echo "missing message key";
	exit;
}

$msg_key=$_POST['msg_key'];
 
$cluster  = Cassandra::cluster('127.0.0.1') ->withCredentials("transactions_ro", "Public_transactions")
                ->build();
$keyspace  = 'comchain';
$session  = $cluster->connect($keyspace);

$counter = $limit;


$query = "select * from testtransactions WHERE add1 = ? AND status = 0 ORDER BY time DESC limit $counter;";
$options = array('arguments' => array($addr));
$full_set_row = $session->execute(new Cassandra\SimpleStatement($query), $options);

$line_ct = 0;
$jstring = [];
foreach ($full_set_row as $row) {
   
    if ($row['direction']==2){
        $row['time'] = $row['time']->value();
       
        $clear = Null;
        if (strlen($row["message_to"])>113){
            $clear=DecryptMessage( $msg_key, $row["message_to"]);
        }
        $result = array(
        	"hash"=> $row['hash'], 
  		"time"=> intval($row['time']), 
   		"amount"=> floatval($row['recieved'])/100.0, 
    		"msg"=> $clear
		);

        $jstring[$line_ct] = $result;
        $line_ct++;
    }
}



echo json_encode($jstring);  


?>
