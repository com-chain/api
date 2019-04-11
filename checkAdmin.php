<?php
require_once './ecrecover_helper.php';
header('Access-Control-Allow-Origin: *');
$maxAccounts=1000;



function base64url_decode($data) { 
  return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); 
} 


function getServerAddress() {
    $protocol = $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
    return $protocol.'://'.$_SERVER['HTTP_HOST'];
   // return "https://node-001.cchosting.org";
}

function getServerConfig($server){
    $url   = getServerAddress()."/ipns/Qmcir6CzDtTZvywPt9N4uXbEjp3CJeVpW6CetMG6f93QNt/configs/".$server.".json";  
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($response);
    return $json->{'server'};  
}

function getAccType($address,$contract){
    $url   = getServerAddress()."/api.php";  
    $ch = curl_init();
    $ethCall = ['to' =>$contract, 
                'data' => '0xba99af70000000000000000000000000'.substr($address,2)
               ];
    $fields = ['ethCall'=>$ethCall]; 
    $fields_string = http_build_query($fields);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
    
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($response);
    $data= $json->{'data'};  
    
    return substr($data,-1);  
}

function getAccStatus($address,$contract){
    $url   = getServerAddress()."/api.php";  
    $ch = curl_init();
    $ethCall = ['to' =>$contract, 
                'data' => '0x61242bdd000000000000000000000000'.substr($address,2)
               ];
    $fields = ['ethCall'=>$ethCall];
    $fields_string = http_build_query($fields);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
    
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($response);
    $data= $json->{'data'}; 
    
    return substr($data,-1);
}

function checkSign($dat, $signature, $caller){
  
  return $caller==personal_ecRecover($dat, $signature);
}


function checkLegitimateAdmin($dat, $signature, $caller, $server){
    $result = false;
    try {
        // Check the signature is ok
        if (checkSign($dat, $signature, $caller)){
            // get the config and the contract
            $config = getServerConfig($server);
            $contract = $config->{'contract_1'};
            
            // Get the caller type and status
            $acctype = getAccType($caller, $contract);
            $accStatus = getAccStatus($caller, $contract);
            
            if ($acctype==2 && $accStatus==1){
                $result = true;
            }  
        }
    } catch (Exception $e) { }
    
    return $result;
    
}

?>
