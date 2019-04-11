<?php
header('Access-Control-Allow-Origin: *');
if (strlen($_GET['addr']) == 46) {
        $addr = preg_replace("/[^a-zA-Z0-9]+/", "", $_GET['addr']);
}else{
        echo "Bye!";
}

$cmd="ipfs name resolve /ipns/".$addr." 2>&1";
$cmd = str_replace("\\", "", $cmd);
$arr['hash'] = trim(shell_exec($cmd));

print json_encode($arr,JSON_UNESCAPED_SLASHES);
?>
