<?php
class Spacedock_Port {

var $fp;

var $botnick;
var $botpassword;
var $botident;
var $botrealname;
var $localhost;
var $quit_message;

var $serveraddress;
var $serverport;
var $serverchannel;

var $database_host;
var $database_user;
var $database_password;
var $database_name;


var $currentserver;
var $db;
var $server_restart;
var $server_quit;
var $using_host;
var $global_user;

var $registered;

function Spacedock_Port($bot){

$this->botnick = $bot['botnick'];
$this->botpassword = $bot['botpassword'];
$this->botident = $bot['botident'];
$this->botrealname = $bot['botrealname'];
$this->localhost = $bot['localhost'];

$this->serveraddress = $bot['serveraddress'];
$this->serverport = $bot['serverport'];
$this->serverchannel = $bot['serverchannel'];

$this->database_host = $bot['database_host'];
$this->database_user = $bot['database_user'];
$this->database_password = $bot['database_password'];
$this->database_name = $bot['database_name'];

$this->version_message = "Spacedock Port 2.0";
$this->server_quit = "Spacedock Port 2.0 is leaving the server";

set_time_limit(0);

$this->connect();
$this->loop();
}

function database_connect(){
$this->db = mysql_connect($this->database_host, $this->database_user, $this->database_password)or die('Error 1: Server Could Not Connect');
mysql_select_db($this->database_name, $this->db)or die('Error 2: Server Could Not Connect');
}

function disconnect(){
$this->send("QUIT :".$this->server_quit);
fclose($this->fp);
exit();
}

function connect(){
$this->fp = pfsockopen($this->serveraddress,$this->serverport, &$err_num, &$err_msg, 30);
if (!$this->fp){
exit;
}else{
$this->send("NICK ".$this->botnick);
//$this->send("USER ".$this->botident." * s :kriegchan.org");
$this->send("USER ".$this->botident.' '.$this->localhost.' '.$this->serveraddress.' :'.$this->botrealname);
sleep(3);
$this->send("PRIVMSG NickServ IDENTIFY ".$this->botpassword);
$this->send("PRIVMSG HostServ ON");
$this->send("PRIVMSG ChanServ INVITE ".$this->serverchannel);
sleep(3);
$this->send("JOIN ".$this->serverchannel);
}
}

function loop(){
while (!feof($this->fp)){
$this->database_connect();
$result = mysql_query("SELECT `on` FROM `bot`;")or die("Error 3: Server Could Not Find 'ON' button");
mysql_close($this->db);
$row = mysql_fetch_assoc($result);
if ($row['on'] == 0)
{
$this->disconnect();
}
$this->database_connect();
$random_row = mysql_query("SELECT * FROM `bot_port` ORDER BY rand() LIMIT 1");
mysql_close($this->db);
if (mysql_num_rows($random_row) == 0)
{
$this->send("PRIVMSG ".$this->serverchannel." :Spacedock currently has no links");
sleep(5);
}
else
{
$rand = mysql_fetch_assoc($random_row);
if (!$rand['text'])
{
$this->send("PRIVMSG ".$this->serverchannel." :Spacedock currently has no links");
sleep(5);
}
else
{
$this->send("PRIVMSG ".$this->serverchannel." :".$rand['text']);
sleep(5);
}
}
}
$this->disconnect();
}

function send($data){
fputs($this->fp, $data."\r\n");
}
}


$bot['botnick'] = "Spacedock_Port";
$bot['botpassword'] = "325dsf4sdgset53221sfdsgdf";
//$bot['botpassword'] = "ashdyw673hjsaf";
$bot['botident'] = "SpacedockP";
$bot['botrealname'] = "Kriegchan.org";
$bot['localhost'] = "localhost";

$bot['serveraddress'] = "irc.partyvan.fm";
$bot['serverport'] = "6667";
$bot['serverchannel'] = "#SpacePort";

$bot['database_host'] = "localhost";
$bot['database_user'] = "loby_bot";
$bot['database_password'] = "3FJ9&Yx,jxT:";
$bot['database_name'] = "loby_bot";

$mybot = new Spacedock_Port($bot);
?>