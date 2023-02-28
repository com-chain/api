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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($response);
    return $json->{'server'};  
}

function getAccType($address, $contract){
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($response);
    $data= $json->{'data'};  
    
    return substr($data,-1);  
}

function getAccStatus($address, $contract){
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($response);
    $data= $json->{'data'}; 
    
    return substr($data,-1);
}


function isActive($address, $contract){
    $url   = getServerAddress()."/api.php";  
    $ch = curl_init();
    $ethCall = ['to' =>$contract, 
                'data' => '0x9f8a13d7000000000000000000000000'.substr($address,2)
               ];
    $fields = ['ethCall'=>$ethCall];
    $fields_string = http_build_query($fields);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($response);
    $data= $json->{'data'}; 
    
    return substr($data,-1);
}


function getVersion( $contract){
    $url   = getServerAddress()."/api.php";  
    $ch = curl_init();
    $ethCall = ['to' =>$contract, 
                'data' => '0x54fd4d50'
               ];
    $fields = ['ethCall'=>$ethCall];
    $fields_string = http_build_query($fields);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($response);
    $data= $json->{'data'}; 
    
    return pack("H*", $data);
}




function getNumber($address, $contract, $function){
    $url   = getServerAddress()."/api.php";  
    $ch = curl_init();
    $ethCall = ['to' =>$contract, 
                'data' => $function.'000000000000000000000000'.substr($address,2)
               ];
    $fields = ['ethCall'=>$ethCall];
    $fields_string = http_build_query($fields);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($response);
    $data= $json->{'data'};
    
    $data = '0x'.substr($data,-12);
    $val = hexdec($data);
    
    if ($val>(34359738368*4096)){
            $val=$val-68719476736*4096;
    }
    
    return $val;
}

function getNumberInMap($address1, $addresse2, $contract, $function){
    $url   = getServerAddress()."/api.php";  
    $ch = curl_init();
    $ethCall = ['to' =>$contract, 
                'data' => $function.'000000000000000000000000'.substr($address1,2).'000000000000000000000000'.substr($addresse2,2)
               ];
    $fields = ['ethCall'=>$ethCall];
    $fields_string = http_build_query($fields);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    // Set so curl_exec returns the result instead of outputting it.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, count($fields));
    curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Get the response and close the channel.
    $response = curl_exec($ch);
    curl_close($ch);
    
    $json = json_decode($response);
    $data= $json->{'data'}; 
    
    return hexdec($data);
}

function getBalance($address, $contract){
    return getNumber($address, $contract, '0x70a08231');
    
}

function getNTBalance($address, $contract){
    return getNumber($address, $contract, '0xae261aba');
}

function getCMBalance($address, $contract){
    return getNumber($address, $contract, '0xbbc72a17');
}

function getCMLimitM($address, $contract){
    return getNumber($address, $contract, '0xcc885a65');
}

function getCMLimitP($address, $contract){
    return getNumber($address, $contract, '0xae7143d6');
}



function checkSign($dat, $signature, $caller){
  
  return $caller==personal_ecRecover($dat, $signature);
}


function getAccountStatus($addresses, $contract) {
    $version = getVersion($contract);
    $result= array($add_1=0,$add_2=0);
    if (strlen($version)>0) {
        // New Contract use isActive
        foreach ( $addresses as $add) {
            $result[$add] = isActive($add, $contract);
        }
    } else {
        // Old contract fallback on getAccStatus
        foreach ( $addresses as $add) {
            $result[$add] = getAccStatus($add, $contract);
        }
    }
    
    return $result;
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
            $status =getAccountStatus(array($caller), $contract);
            $accStatus = $status[$caller];
            
            if ($acctype==2 && $accStatus==1){
                $result = true;
            }  
        }
    } catch (Exception $e) { }
    
    return $result;
    
}

?>
