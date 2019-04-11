<?php
include "src/ipfs.php";
use Cloutier\PhpIpfsApi\IPFS;

header('Access-Control-Allow-Origin: *');

// connect to ipfs daemon API server
$ipfs = new IPFS("localhost", "8080", "5001");
$arr['hash'] = $ipfs->add($_GET['data']);

print json_encode($arr);
?>
