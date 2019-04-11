<?php
include "src/ipfs.php";
use Cloutier\PhpIpfsApi\IPFS;

header('Access-Control-Allow-Origin: *');

// connect to ipfs daemon API server
$ipfs = new IPFS("localhost", "8080", "5001");
$arr['data'] = str_replace("    a831rwxi1a3gzaorw1w2z49dlsor", "",$ipfs->cat($_GET['addr']));

print json_encode($arr);
?>
