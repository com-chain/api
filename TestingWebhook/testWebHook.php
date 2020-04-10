<?php

$address = "0x9e898bc7c13ba309a412904f07aff65a13e15d32";
$shopId = 1;
$serverName = "Lemanopolis";
$amount =0.01;

$protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";

$url = $_SERVER['REQUEST_URI']; 
$parts = explode('/',$url);
for ($i = 0; $i < count($parts) - 1; $i++) {
 $dir .= $parts[$i] . "/";
}
$server_url = $protocol . $_SERVER['HTTP_HOST'].$dir."/listener.php?txId=";


if (isset($_GET['$amount'])){
    $amount =$_GET['$amount'];
}
$tx_id = 'TEST_'.uniqid();


echo '
<html>
  <head>
    <title>Merci de bien vouloir effectuer votre payement</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=no" />
    <script type="text/javascript" src="qrcodejs/jquery.min.js"></script>
    <script type="text/javascript" src="qrcodejs/qrcode.js"></script>
  </head>
    <body>
    
    <h1 style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:18px;text-align:center;">Information pour le payement en '.$serverName.'</h1>

    <div id="pay" style="display:block;width:100%;">
        <h2 style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:14px;text-align:center;">Montant à payer: '.$amount.' '.$serverName.' </h2>
        <h2 style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:14px;text-align:center;">Vous pouvez scanner le QR avec l\'app Biletujo</h2>
       
        <svg style="margin:20px auto 10px auto; display:block;height:300px;" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                <g id="qrcode"/>
        </svg>
        
        <h2 style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:14px;text-align:center;">ou</h2>
       
        <a style="margin:20px auto 10px auto; padding:10px 20px;display:block; border:solid 1px black; width:150px;text-align:center; text-decoration:none; color:black;background-color:#DDDDDD;" target="_blank" href="https://v2.cchosting.org/index.html?address='.$address.'&amount='.$amount.'&shopId='.$shopId.'&txId='.urlencode($tx_id).'&serverName='.$serverName.'"> Payer dans la web-app</a>
    </div>
    <div id="confirm" style="display:none;">
        <h2 style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:14px;text-align:center;">Merci!</h2>
        <h2 style="display:block;width:calc(100% - 60px);padding:0px 30px 0 30px;font-size:14px;text-align:center;">Nous avons reçu votre payement de <span id="amount_pay"></span> '.$serverName.'</h2>
       
    </div>';
    
echo'</body>
    <script type="text/javascript">
      var qrcode = new QRCode(document.getElementById("qrcode"), {
          width : 300,
          height : 300,
          useSVG: true
      });

     qrcode.makeCode("https://v2.cchosting.org/index.html?address='.$address.'&amount='.$amount.'&shopId='.$shopId.'&txId='.urlencode($tx_id).'&serverName='.$serverName.'");
    </script>
    
    <script type="text/javascript">
      var tx_id = "'.$tx_id.'"; 
       
      function httpGetAsync () {
            var xmlHttp = new XMLHttpRequest();
            xmlHttp.onreadystatechange = function() { 
                if (xmlHttp.readyState == 4 && xmlHttp.status == 200)
                    getCallback(JSON.parse(xmlHttp.responseText));
            }
            xmlHttp.open("GET", "'.$server_url.urlencode($tx_id).'", true); 
            xmlHttp.send(null);
      } 
      
      function getCallback(returned_obj){
        if ("amount" in returned_obj && returned_obj["txId"]=="'.$tx_id.'"){
            document.getElementById("amount_pay").innerHTML=returned_obj["amount"];
            document.getElementById("pay").style.display="none";
            document.getElementById("confirm").style.display="block";
            clearInterval(interval);
        }
      }
      
      var interval = setInterval(httpGetAsync,2000);
       
    </script>
    
</html>';     
     
     
     
?>
