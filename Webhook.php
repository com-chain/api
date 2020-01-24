<?php

$NANT_TRANSFERT = "0xa5f7c148";
$CM_TRANSFERT = "0x60ca9c4c";
$private_key_path ='../ComChainKey/comchainwebhook_rsa';
$public_key_url ='https://com-chain.org/comchainwebhook_rsa.pub';



function createWebhookMessage($tr_hash, $server_name, $store_id, $store_ref, $from_add,$rawtx){
    $tr_info = substr($rawtx,-316,182);
    //get the type of transfert
    $funct_address = substr($tr_info,46,8);
    $type_tr = ("0x".strtolower($funct_address) == CM_TRANSFERT)? 'TransferCredit' : 'Transfer';

    // get the dest
    $dest = '0x'.substr($tr_info,78,40);
        
    // get the amount
    $amount = hexdec(substr($rawtx,-64))/100.0;


    $time = time();
    
    $base_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http"; 
    $base_link .= "://"; 
    $base_link .= $_SERVER['HTTP_HOST']; 
     
    $link = array (
                    "href"=>$base_link."/api.php?hash=".$tr_hash,
                    "method"=>"GET"
                  );
             
    $amount = array ( 
                        'sent'=> $amount,
                        'type'=> $type_tr,
                        'currency' => $server_name
                    );

             
    $resources = array (
                  'id'=>$tr_hash,
                  'create_time'=> $time,
                  'state'=>'completed',
                  'store_id' => $store_id,
                  'reference' => $store_ref,
                  'links'=>[$link],
                  
                  'addr_to' => $dest,
                  'amount'=>$amount
                 );   
    if (strlen($from_add)>0) {
        $resources['addr_from']=$from_add;
    }      
             
    
    $data = array ('id'=>$tr_hash,
             'create_time'=>$time, 
             'resource_type'=>'sale', 
             'event_type'=> 'PAYMENT.SALE.COMPLETED',
             'summary'=>'A sale has been completed. The payement has been processed.',
             'links'=>[$link],
             'resources'=>$resources
             );
    
    return $data;
}

// Sign and send message
function sendWebhook($url, $message) {
    $json_message = json_encode($message);
    $hash = crc32($json_message);
    $sign = "";
    $sign_key = openssl_pkey_get_private($private_key_path);
    if (openssl_sign($hash, $sign, $sign_key)) {
        $signed = base64_encode($sign);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 
                                                   'COMCHAIN-TRANSMISSION-SIG:'.$signed, 
                                                   'COMCHAIN-AUTH-ALGO:RSA',
                                                   'COMCHAIN-CERT-URL:'.$public_key_url));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $passed=true;
        if (!$response = curl_exec( $ch )){
             $passed=false;
        }
        curl_close($ch);
        
        if ($passed) {
           $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
           $passed = $code>=200 and $code<300;
        }
        return $passed;
    } else {
        return false;
    }
}


?>
