<?php
     
session_set_cookie_params(43200,"/",".cchosting.org", true, true);

session_set_save_handler('_open',
                         '_close',
                         '_read',
                         '_write',
                         '_destroy',
                         '_clean');
     
function _open()
{
    global $_sess_cluster;
    global $_sess_db;
 
    if ($_sess_cluster = Cassandra::cluster('127.0.0.1') ->withCredentials("session_rw", "Private_access_sessions") ->build()) {
	$_sess_keyspace  = 'comchain';
	$_sess_db = $_sess_cluster->connect($_sess_keyspace);
	return true;
    }
 
    return FALSE;
}
 

function _close()
{
    global $_sess_db;
 
    return $_sess_db ->close();
}


function _read($id)
{
    global $_sess_db;
 
    $query = "SELECT data 
	      from sessions 
	      WHERE id = '$id'";
    $counter=0;
    foreach ($_sess_db->execute(new Cassandra\SimpleStatement($query)) as $row) {
        $string[$counter] = implode(",",$row);
        $counter++;
    }

    if(isset($string['0'])){
    	return $string['0'];
    }else{
	return '';
    }
}

function _write($id, $data)
{
    global $_sess_db;
 
    $access = time();
 
    $query = "INSERT
            INTO    sessions (id, access, data)
            VALUES  ('$id', $access, '$data')
	    USING TTL 43200";
 
    $_sess_db->execute(new Cassandra\SimpleStatement($query));
    return true;
}

function _destroy($id)
{
    global $_sess_db;
 
    $query = "DELETE
            FROM   sessions
            WHERE  id = '$id'";
 
    $_sess_db->execute(new Cassandra\SimpleStatement($query));

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }







    return true;
}

function _clean($max)
{
    global $_sess_db;
 
    $old = time() - $max;
 
    $query = "DELETE
            FROM   sessions
            WHERE  access < '$old'";
 
    $_sess_db->execute(new Cassandra\SimpleStatement($query));
    return true;
}

?>
