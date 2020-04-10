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
      
      
      public function getMessages($txId) {
        $sql ='SELECT amount, txId FROM WebHookMessage WHERE txId=\''.$txId.'\'';
        $ret = $this->query($sql);
        while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
            return $row;
        }
        
        return [];
      }
}

 $db = new MyLiteDB();

if (isset($_GET['cleanMessage'])) {

    $db->clearMessage();
}


if (isset($_GET['txId'])) {
    //DBG 
    //echo json_encode(["amount"=>0.02, "txId"=>$_GET['txId']]);
    echo json_encode($db->getMessages($txId));
}

if (isset($_POST['resources'])){}
// TODO put webhook message in DB

?>
