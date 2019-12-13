<?php

 class MyLiteDB extends SQLite3 {
      function __construct() {
         $this->open('./webhookMessage.db');
         
         $sql = "CREATE TABLE IF NOT EXISTS WebHookMessage (message TEXT  NOT NULL, date  TEXT  NOT NULL)";
         $this->query($sql);
      }
      
      function __destruct() {
         $this->close();
      }
      
      public function clearMessage() {
        $sql ="DELETE FROM WebHookMessage";
        $this->query($sql);
      }
      
      public function insertMessage($message) {
        $sql ="INSERT INTO WebHookMessage(message, date) values (\"$code\", DATETIME('now') )";
        $this->query($sql);
      }
      
      
      public function getMessages() {
        $sql ="SELECT message, date FROM WebHookMessage ORDER BY date";
        $ret = $this->query($sql);
        $result = array();
        while($row = $ret->fetchArray(SQLITE3_ASSOC) ){
            $sub = array("message"=>$row['message'], "date"=>$row['date']);
            array_push($result,$sub);
        }
        
        return $result;
      }
}

 $db = new MyLiteDB();

if (isset($_GET['cleanMessage'])) {

    $db->clearMessage();
}

if (isset($_POST['resources'])) {
    $db->insertMessage(json_encode($_POST['resources']));
}

$address = "0x9e898bc7c13ba309a412904f07aff65a13e15d32";
$shopId = 1;
$serverName = "Lemanopolis";
$amount =0.01;
$tx_id = 'TEST_001';

echo '
<html>
    <body>
        <div>
            To Pay: <a target="_blank" href="https://v2.cchosting.org/index.html?address='.$address.'&amount='.$amount.'&shopId='.$shopId.'&txId='.urlencode($tx_id).'&serverName='.$serverName.'"> Click</a>
        </div>
        <div>
            List of Messages: <a href="./testWebHook.php?cleanMessage=1"> Clear Messages</a>
            <table>';

$messages = $db->getMessages();
foreach ($messages as $value){
    echo '<tr><td>'.$value['message'].'</td><td>'.$value['date'].'</td></tr>';
}
   
            
echo '                
            </tr>
            </table>
        </div>
    </body>
</html>';     
     
     
     
?>
