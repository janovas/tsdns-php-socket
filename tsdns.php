// TSDNS PHP Socket
// Connect to Database
<?php
/*use
-----Run program-----
screen -A -m -d -S tsdns php tsdnsqlu8.php
screen -x tsdns
deattach Ctrl + A + D

quit
$ screen -X -S [session # you want to kill] quit
screen -X -S 1858.tsdns quit
----exit----
screen -x tsdns
Ctrl + C

status
netstat -a | grep 41144
*/

error_reporting(E_ALL);
//error_reporting(0);


class DbConnect {
	public static $mysqli;
	private $mysql_info = array();
	public function __construct($host,$user,$pass,$db){
		$this->mysql_info['host'] = $host;
		$this->mysql_info['user'] = $user;
		$this->mysql_info['pass'] = $pass;
		$this->mysql_info['db'] = $db;
	}
	public function doConnect(){
		if(!$this->mysqli || !$this->mysqli->ping()){
			$this->mysqli = new mysqli($this->mysql_info['host'],$this->mysql_info['user'],$this->mysql_info['pass'],$this->mysql_info['db']);
			$this->mysqli->set_charset('utf8');

			if ($this->mysqli->connect_error) {
			    die('Connect Error (' . mysqli_connect_errno() . ') '
			            . mysqli_connect_error());
			}
		}
	}
}

$dbClass = new DbConnect('127.0.0.1','janovas_cutrue','7P000T7WVB678KZMVXKV','janovas_cutrue');
$dbClass->doConnect();

set_time_limit(0);

$address = '0.0.0.0';
$port = 41144;

if (($sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false) {
    echo "socket_create() failed: reason: " . socket_strerror(socket_last_error()) . "\n";
}

if (socket_bind($sock, $address, $port) === false) {
    echo "socket_bind() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
}

if (socket_listen($sock, 128) === false) {
    echo "socket_listen() failed: reason: " . socket_strerror(socket_last_error($sock)) . "\n";
}


function getsockip($sock)
	{
		socket_getpeername($sock,$ip);
		return $ip;
	}


$clients = array();

while(true){
	$read = array();
    $read[] = $sock;
    
    $read = array_merge($read,$clients);
    
    // Set up a blocking call to socket_select
    if(socket_select($read,$write = NULL, $except = NULL, $tv_sec = 5) < 1)
    {
        //    SocketServer::debug("Problem blocking socket_select?");
        continue;
    }
    
    // Handle new Connections
    if (in_array($sock, $read)) {        
        
        if (($msgsock = socket_accept($sock)) === false) {
            echo "socket_accept() fallรณ: razรณn: " . socket_strerror(socket_last_error($sock)) . "\n";
            break;
        }
		socket_getpeername($msgsock,$clip);
        $clients[] = $msgsock;
        $key = array_keys($clients, $msgsock);
        /* Enviar instrucciones. */
    }
    
    // Handle Input
    foreach ($clients as $key => $client) { // for each client        
        if (in_array($client, $read)) {
            if (false === ($buf = socket_read($client, 2048))) {
				unset($clients[$key]);
				socket_close($client);
                echo '['.$key.'|'.getsockip($client).'] - DISCONNECTED!';
				echo "\n";
                continue;
            }
            if (!$buf = trim($buf)) {
                continue;
            }
			
			$dbClass->doConnect();
			$svres = false;
			$exDomain = explode('.',$dbClass->mysqli->real_escape_string($buf),2);
			$chkQuery = $dbClass->mysqli->query("SELECT `port`,`server` FROM `user_server` WHERE `dns` = '".$exDomain[0]."' AND `dnss` = '".$exDomain[1]."' LIMIT 1;")->fetch_array(MYSQLI_ASSOC);
			if($chkQuery){
				$getSvIP = $dbClass->mysqli->query("SELECT `server_ip` FROM `server` WHERE `id` = '".$chkQuery['server']."' LIMIT 1;")->fetch_array(MYSQLI_ASSOC);
				$svres = $getSvIP['server_ip'].':'.$chkQuery['port'];
			}
			
			echo '['.$key.'|'.getsockip($client).'] - '.$buf.' => '.$svres;
			echo "\n";
			
			socket_write($client,($svres ? $svres:'122.155.168.202:40000'));
			socket_close($client);
			unset($clients[$key]);
			
        }
        
    }    
}

socket_close($sock);
