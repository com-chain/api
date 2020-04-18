<?php 

class MyLiteDB extends SQLite3 {
      function __construct() {
         $this->open('./webhookMessage.db');
         
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
}

if (isset($_GET['allMessages'])) {
    foreach ($db->getMessages() as $row ){
        echo json_encode($row).'<br/>';
    }
}


if (isset($_GET['txId'])) {
    echo json_encode($db->getMessage($_GET['txId']));
}

if (isset($_POST['resources'])){
    // minimal data cherry picking: DO NOT USE AS IT! you need (at least) to check:
    //  - message signature (in the HTTP Header) check 
    //  - that the dest. account (addr_to) is the right one
    
    $amount = $_POST['resources']['amount'];
    $txId = $_POST['resources']['reference'];
    $db->insertMessage($amount,$txId);
}

?>
