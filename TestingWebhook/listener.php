<?php

////////////////////////////////////////////////////////////////////////////////
// Security configuration: Public Key to check the signature
//
// the public key is available at this url
//$public_key_url ='https://com-chain.org/comchainwebhook_rsa.pub';
//The key format can be converted using:
//ssh-keygen -f comchainwebhook_rsa.pub -e -m pkcs8 >comchainwebhook_rsa.pub.pem
$public_key_file ='comchainwebhook_rsa.pub.pem';

////////////////////////////////////////////////////////////////////////////////
// Security configuration: Wallet the payment have to be sent to
$target_address = strtolower("0x9e898bc7c13ba309a412904f07aff65a13e15d32");
////////////////////////////////////////////////////////////////////////////////
 

class MyLiteDB extends SQLite3 {
      function __construct() {
         $this->open('./db/webhookMessage.db');
         
         $sql = "CREATE TABLE IF NOT EXISTS WebHookMessage (amount TEXT  NOT NULL, txId TEXT)";
         $this->query($sql);
      }
      
      function __destruct() {
         $this->close();
      }
      
      public function clearMessage() {
        $sql ="DELETE FROM WebHookMessage";
        $this->query($sql);
      }
      
      public function insertMessage($amount,$txId) {
        $sql ='INSERT INTO WebHookMessage(amount, txId) values (\''.$amount.'\', \''.$txId.'\' )';
        $this->query($sql);
      }
      
      
      public function getMessage($txId) {
        $sql ='SELECT amount, txId FROM WebHookMessage WHERE txId=\''.$txId.'\'';
        $ret = $this->query($sql);
        while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
            return $row;
        }
        
        return [];
      }
      public function getMessages() {
        $sql ='SELECT amount, txId FROM WebHookMessage';
        $ret = $this->query($sql);
        $rows = [];
        while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
            $rows[]=$row;
        }
        return $rows;
      }
}


 $db = new MyLiteDB();

if (isset($_GET['cleanMessages'])) {

    $db->clearMessage();
} else if (isset($_GET['allMessages'])) {
    foreach ($db->getMessages() as $row ){
        echo json_encode($row).'<br/>';
    }
} else if (isset($_GET['txId'])) {
    echo json_encode($db->getMessage($_GET['txId']));
} else {
  if (isset($_POST['resources'])){
  /*/////////////////////////////////////////////
    //////////         DEBUG         ////////////
    $txt = "RECIEVED WEB HOOK INFO ".PHP_EOL;        
    $txt = $txt."json:".PHP_EOL.$json_message.PHP_EOL;
    $txt = $txt."hash:".PHP_EOL.$hash.PHP_EOL;        
    $txt = $txt."sign:".PHP_EOL.$signature.PHP_EOL;
    $txt = $txt."signed:".PHP_EOL.$signed.PHP_EOL;

    $myfile = file_put_contents('dbg_r.txt', $txt , FILE_APPEND | LOCK_EX);
    ////////////////////////////////////////////  */
    
    // Minimal data cherry picking: check signature and target account, record ref and amount

    // 1) check message signature 
    // 1.a) get the hash of the message
    $json_message = json_encode($_POST);
    $hash = crc32($json_message);
    
    // 1.b) decode the signature
    $signed = $_SERVER['HTTP_COMCHAIN_TRANSMISSION_SIG'];
    $signature = base64_decode($signed);
   


     // 1.c) get the public key
    $pub_key_id = openssl_pkey_get_public(file_get_contents($public_key_file));
   
    // 1.d) check signature
    if (1 == openssl_verify ( $hash , $signature ,  $pub_key_id  )){
        // 2) Get the address the payment has been made to
        $address = $_POST['resources']['addr_to'];
        // 2.a) Check the address is the expected one: 
        if (strtolower($address)==$target_address) {
            // 3) everything OK: record the payment.
            $amount = intval($_POST['resources']['amount']['sent'])/100;
            $txId = $_POST['resources']['reference'];
            $db->insertMessage($amount, $txId);
        } else {
          throw new Exception('WARNING: Recieved a message with wrong destinary: '.$json_message);  
        }
    } else {
        throw new Exception('WARNING: Recieved a message with wrong signature: '.$_POST['json']. ' Signature: '.$signed);
    }
  }
}
?>
