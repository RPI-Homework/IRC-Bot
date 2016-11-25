<?php
class Hallbot_Port {

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

var $currentserver;
var $server_restart;
var $server_quit;

function Hallbot_Port($bot){

$this->botnick = $bot['botnick'];
$this->botpassword = $bot['botpassword'];
$this->botident = $bot['botident'];
$this->botrealname = $bot['botrealname'];
$this->localhost = $bot['localhost'];

$this->serveraddress = $bot['serveraddress'];
$this->serverport = $bot['serverport'];
$this->serverchannel = $bot['serverchannel'];

$this->version_message = "Hallbot Port";
$this->server_quit = "I am leaving the server";

set_time_limit(0);

$this->connect();
$this->loop();
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
}
}

function loop(){
while (!feof($this->fp)){
$this->send("PRIVMSG ".$this->serverchannel." :".gmmktime());
sleep(5);
}
$this->disconnect();
}

function send($data){
fputs($this->fp, $data."\r\n");
}
}


$bot['botnick'] = "Hall_Hall_Chat_Port";
$bot['botpassword'] = "8635yhj7sy43hg";
$bot['botident'] = "Hallbot";
$bot['botrealname'] = "hallhallchat.tk";
$bot['localhost'] = "localhost";

$bot['serveraddress'] = "irc.n00bstories.com";
$bot['serverport'] = "6667";
$bot['serverchannel'] = "Hall_Hall_Chat_Bot";

$mybot = new Hallbot_Port($bot);
?>