<?php





function createWebhookMessage($tr_hash, $server_name, $store_id, $store_ref, $type_tr, $from_add, $dest,$amount){

    $time = time();
    
    $base_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http"; 
    $base_link .= "://"; 
    $base_link .= $_SERVER['HTTP_HOST']; 
     
    $link = array (
                    "href"=>$base_link."/api.php?hash=".$tr_hash,
                    "method"=>"GET"
                  );
             
    $amount = array ( 
                        'sent'=> "".$amount,
                        'type'=> $type_tr,
                        'currency' => $server_name
                    );

             
    $resources = array (
                  'id'=>$tr_hash,
                  'create_time'=> "".$time,
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
             'create_time'=>"".$time, 
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

    
    $private_key_path ='../ComChainKey/comchainwebhook_rsa';
    $public_key_url ='https://com-chain.org/comchainwebhook_rsa.pub';


    $json_message = json_encode($message);
    $hash = crc32($json_message);
    $sign = "";
    $sign_key = openssl_pkey_get_private(file_get_contents($private_key_path));
    if (openssl_sign($hash, $sign, $sign_key)) {
        $signed = base64_encode($sign);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('COMCHAIN-TRANSMISSION-SIG:'.$signed, 
                                                   'COMCHAIN-AUTH-ALGO:RSA',
                                                   'COMCHAIN-CERT-URL:'.$public_key_url));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $passed=true;
        if (!$response = curl_exec( $ch )){
             $passed=false;
        }
        
        
        if ($passed) {
           $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
           $passed = $code>=200 and $code<300;
        }
        
        curl_close($ch);

        return $passed;
    } else {
        return false;
    }
}


?>
