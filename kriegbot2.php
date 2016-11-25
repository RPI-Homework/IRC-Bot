<?php
class Spacedock {

var $fp;
var $lfp;
var $rfp;

var $rawdata;
var $data;

var $log;

var $rawlog;
var $irclog;

var $lecture;
var $lecture_pause;

var $botnick;
var $botpassword;
var $botident;
var $botrealname;
var $localhost;
var $quit_message;

var $serveraddress;
var $serverport;
var $serverchannel;
var $port;

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
var $voteban;
var $votekick;
var $roulett;

function Spacedock($bot){
$this->log = $bot['log'];

$this->rawlog = $bot['rawlog'];
$this->irclog = $bot['irclog'];

$this->rfp = NULL;

$this->botnick = $bot['botnick'];
$this->botpassword = $bot['botpassword'];
$this->botident = $bot['botident'];
$this->botrealname = $bot['botrealname'];
$this->localhost = $bot['localhost'];
$this->port = $bot['ircport'];

$this->serveraddress = $bot['serveraddress'];
$this->serverport = $bot['serverport'];
$this->serverchannel = $bot['serverchannel'];

$this->database_host = $bot['database_host'];
$this->database_user = $bot['database_user'];
$this->database_password = $bot['database_password'];
$this->database_name = $bot['database_name'];

$this->version_message = "Spacedock 2.0";

$this->currentserver = NULL;
$this->db = NULL;
$this->server_restart = "Restarting Spacedock";
$this->server_quit = "Spacedock is leaving the server";
$this->using_host = NULL;
$this->global_user = NULL;
$this->registered = NULL;

$this->voteban['#Kriegchan']['num'] = 0;
$this->votekick['#Kriegchan']['num'] = 0;
$this->roulett['#Kriegchan'] = 0;

set_time_limit(0); #sets the timeout limit of the script

#connects to the database


#handles the connection
$this->connect();
$this->receive();
}

function database_connect(){
$this->db = mysql_connect($this->database_host, $this->database_user, $this->database_password)or die('Error 1: Server Could Not Connect');
mysql_select_db($this->database_name, $this->db)or die('Error 2: Server Could Not Connect');
}

function connect(){
$this->fp = pfsockopen($this->serveraddress,$this->serverport, &$err_num, &$err_msg, 30);

if (!$this->fp){
$this->logging("There was an error in connecting to ".$this->serveraddress);
exit;
}else{
$this->send("NICK ".$this->botnick);
//$this->send("USER ".$this->botident." * s :kriegchan.org");
$this->send("USER ".$this->botident.' '.$this->localhost.' '.$this->serveraddress.' :'.$this->botrealname);
sleep(3);
$this->send("PRIVMSG NickServ IDENTIFY ".$this->botpassword);
$this->send("PRIVMSG HostServ ON");
$this->send("PRIVMSG ChanServ INVITE ".$this->serverchannel);
$this->send("PRIVMSG ChanServ INVITE ".$this->port);
sleep(3);
$this->send("JOIN ".$this->serverchannel);
$this->send("JOIN ".$this->port);
$this->logs("4JOINED SERVER");
$this->logging("Connected to ".$this->serveraddress." as ".$this->botnick."<br>");
}
}

function logs($data){
if(!$this->ifp){ #file not open
$this->ifp = fopen($this->irclog, 'w');
}
$time = date('H:i');
fputs($this->ifp, $time." - ".$data."\n");
}

function disconnect(){
$this->logs("4QUITING SERVER");
$this->send("QUIT :".$this->server_quit);
fclose($this->fp);
fclose($this->lfp);
fclose($this->ifp);
exit();
}

function restart(){
$this->logs("4RESTARTING ".$this->botnick);
$this->send("QUIT :".$this->server_restart);
fclose($this->fp);
$this->fp = pfsockopen($this->serveraddress,$this->serverport, &$err_num, &$err_msg, 30);
$this->send("NICK ".$this->botnick);
//$this->send("USER ".$this->botident." * s :kriegchan.org");
$this->send("USER ".$this->botident.' '.$this->localhost.' '.$this->serveraddress.' :'.$this->botrealname);
sleep(3);
$this->send("PRIVMSG NickServ IDENTIFY ".$this->botpassword);
$this->send("PRIVMSG HostServ ON");
$this->send("PRIVMSG ChanServ INVITE ".$this->serverchannel);
$this->send("PRIVMSG ChanServ INVITE ".$this->port);
sleep(3);
$this->send("JOIN ".$this->serverchannel);
$this->send("JOIN ".$this->port);
$this->logs("4RE-JOINED SERVER");
$this->logging("RE-Connected to ".$this->serveraddress." as ".$this->botnick."<br>");
}

function receive(){
while (!feof($this->fp)){
$this->database_connect();
$result = mysql_query("SELECT `on` FROM `bot`")or die("Error 3: Server Could Not Find 'ON' button");
mysql_close($this->db);
$row = mysql_fetch_assoc($result);
if ($row['on'] == 0)
{
$this->disconnect();
}
$this->rawdata = fgets($this->fp, 1024);
$this->rawdata = str_replace("\r", "", str_replace("\n", "", $this->rawdata));
$this->database_connect();
$this->process_data();
if($this->log AND strtolower($this->data['sent_to']) != strtolower($this->port))
{
$this->logging();
}
$result = mysql_query("SELECT * FROM `bot_banlist` WHERE `time` <= ".gmmktime()." AND `time` != 0");

if (mysql_num_rows($result) != 0)
{
while ($row = mysql_fetch_assoc($result))
{
$this->logs("4MODE ".$row['channel']." -b ".$row['host']);
$this->logs("4MODE ".$row['channel']." -b ".$row['user']);
$this->send("MODE ".$row['channel']." -b ".$row['host']);
$this->send("MODE ".$row['channel']." -b ".$row['user']);
mysql_query("DELETE FROM `bot_banlist` WHERE `bot_banlist`.`id` == " . $row['id']);
}
}
if($this->rfp AND $this->lecture_pause['num']!=1 AND $this->lecture_pause['time'] <= gmmktime()){
$this->lecture();
}
foreach (array_keys($this->voteban) as $channel)
{
if($this->voteban[$channel]['num'] == 5 AND $this->voteban[$channel]['time'] <= gmmktime()){
$this->voteban[$channel]['num'] = 0;
$this->voteban[$channel]['time'] = 0;
$this->serverchannel = $channel;
$this->votebanresult();
}
elseif($this->voteban[$channel]['num'] >= 1 AND $this->voteban[$channel]['time'] <= gmmktime()){
$this->voteban[$channel]['num']++;
$this->voteban[$channel]['time'] = gmmktime() + 15;
$this->serverchannel = $channel;
$this->votebantimer();
}
}
foreach (array_keys($this->votekick) as $channel)
{
if($this->votekick[$channel]['num'] == 5 AND $this->votekick[$channel]['time'] <= gmmktime()){
$this->votekick[$channel]['num'] = 0;
$this->votekick[$channel]['time'] = 0;
$this->serverchannel = $channel;
$this->votekickresult();
}
elseif($this->votekick[$channel]['num'] >= 1 AND $this->votekick[$channel]['time'] <= gmmktime()){
$this->votekick[$channel]['num']++;
$this->votekick[$channel]['time'] = gmmktime() + 15;
$this->serverchannel = $channel;
$this->votekicktimer();
}
}
mysql_close($this->db);
}
$this->disconnect();
}

function process_data(){
$this->registered = 0;
$params = explode(" ", $this->rawdata);
$message = str_replace($params[0] . " ", "", $this->rawdata);
$message = str_replace($params[1] . " ", "", $message);
$message = str_replace($params[2] . " :", "", $message);
$this->data['message'] = $message;
$message = substr($message, 0, 1);
$from = explode ("!", $params[0]);
$user = str_replace(":", "", $from[0]);
$details = explode ("@", $from[1]);
#stores the data in an array
$this->data['from'] = $user;
$this->data['fullhost'] = $from[1];
$this->data['ident'] = $details[0];
$this->data['host'] = $details[1];
$params[3] = str_replace(":", "", $params[3]);
$this->data['action'] = $params[3];
$this->data['sent_to'] = $params[2];
$this->serverchannel = $params[2];
$this->data['ping'] = $params[0];
$this->data['ping2'] = str_replace(":", "", $params[1]);

if(strtolower($this->data['sent_to']) != strtolower($this->port))
{
if($params[1] == 'JOIN')
{
$this->data['sent_to'] = str_replace(":", "", $this->data['sent_to']);
$this->serverchannel = $this->data['sent_to'];
$this->isregistered();
if($this->data['from'] == $this->botnick)
{
$this->logs($this->botnick . " JOINED ".$this->serverchannel);
}
$this->onjoin();
}

if($this->serverchannel == $this->botnick)
{
$this->serverchannel = "PM SYSTEM";
$maction = explode(" ", $this->data['message']);
$mfullaction = str_replace($maction[0] . " ", "", $this->data['message']);
$this->data['message_action'] = $maction[0];
$this->data['message_target'] = $maction[1];
$this->data['message_target2'] = $maction[2];
$this->data['message_action_text'] = str_replace(" ", "%20", $mfullaction);
$this->data['message_action_text_plain'] = $mfullaction;
$this->data['message_action_text_plain2'] = str_replace($maction[1] . " ", "", $mfullaction);
$this->data['message_action_text_plain_with_params'] = substr(str_replace($maction[0], "", str_replace($maction[1], "", $mfullaction)), 2);
$this->pm_parse_data();
}

if($params[1] == 'KICK')
{
$this->onkick();
}

if($message == "!"){
$maction = explode(" ", $this->data['message']);
$mfullaction = str_replace($maction[0] . " ", "", $this->data['message']);
$this->data['action'] = 'TRUE';
$this->data['message_action'] = $maction[0];
$this->data['message_target'] = $maction[1];
$this->data['message_target2'] = $maction[2];
$this->data['message_action_text'] = str_replace(" ", "%20", $mfullaction);
$this->data['message_action_text_plain'] = $mfullaction;
$this->data['message_action_text_plain2'] = str_replace($maction[1] . " ", "", $mfullaction);
$this->data['message_action_text_plain_with_params'] = substr(str_replace($maction[0], "", str_replace($maction[1], "", $mfullaction)), 2);
$this->parse_data();
}

if($this->data['ping'] == 'PING'){
$this->send("PONG");
$this->logs("4PONG");
$this->currentserver = $this->data['ping2'];
}
}
}

function logging(){
if(!$this->lfp){ #file not open
$this->lfp = fopen($this->rawlog, 'w');
}

$time = date('d/m/Y-H:i');
fputs($this->lfp, $time." - ".$this->rawdata."\n");
}

function pm_parse_data(){
switch($this->data['message_action']){
case 'adduser':
case 'user':
case 'removeuser':
case 'deluser':
case 'join':
case 'leave':
case 'quit':
case 'restart':
case 'login':
case 'logout':
case 'lecture':
case 'delport':
case 'addport':
case 'viewport':
case 'off':
$this->pm_global_option();
break;
case 'register':
$this->global_register();
break;
case 'userlevel':
$this->global_user_level();
break;
case 'version':
$this->version();
break;
case 'whois':
$this->whois();
break;
case 'help':
$this->help();
break;
case 'VERSION':
$this->logs("4VERSION FROM ".$this->data['from']);
$this->send("NOTICE ".$this->data['from']." :VERSION " . $this->version_message . "");
break;
case 'TIME':
$this->logs("4TIME FROM ".$this->data['from']);
$this->send("NOTICE ".$this->data['from']." :TIME ".date("l F d H:i:s T Y")."");
break;
case 'PING':
$this->logs("4PING FROM ".$this->data['from']);
$this->send("NOTICE ".$this->data['from']." :PING ".gmmktime()."");
break;
default:
if(!fnmatch("*.*.*", $this->data['from']) AND "Spacedock" != $this->data['from'] AND "anonymous.services" != $this->data['from']  AND "Global" != $this->data['from'] AND "NickServ" != $this->data['from'] AND "HostServ" != $this->data['from'] AND "ChanServ" != $this->data['from'] AND "Shaun" != $this->data['from'])
{
$this->logs("PM FROM ".$this->data['from']);
}
}
}

function parse_data(){
if($this->data['action'] == 'TRUE'){
switch($this->data['message_action']){
case '!addquote':
case '!login':
case '!logout':
case '!own':
case '!deown':
case '!admin':
case '!deadmin':
case '!op':
case '!deop':
case '!hop':
case '!dehop':
case '!rejoin':
case '!voice':
case '!devoice':
case '!kick':
case '!ban':
case '!topic':
case '!mode':
case '!user':
case '!removeuser':
case '!deluser':
case '!adduser':
case '!setjoin':
case '!leave':
case '!viewjoin':
case '!deljoin':
case '!stop':
$this->channel_option();
break;
case '!glogin':
case '!glogout':
case '!guser':
case '!gadduser':
case '!gremoveuser':
case '!gdeluser':
case '!join':
case '!quit':
case '!restart':
case '!lecture':
case '!delport':
case '!addport':
case '!viewport':
case '!off':
$this->global_option();
break;
case '!register':
$this->register();
break;
case '!gregister':
$this->global_register();
break;
case '!time':
$this->time();
break;
case '!8ball':
$this->ball8();
break;
case '!roulette':
$this->roulette();
break;
case '!userlevel':
$this->my_user_level();
break;
case '!quote':
$this->quote();
break;
case '!base64':
$this->base64();
break;
case '!md5':
$this->md5();
break;
case '!say':
$this->say();
break;
case '!rot13':
$this->rot13();
break;
case '!version':
$this->version();
break;
case '!whois':
$this->whois();
break;
case '!votekick':
$this->votekick();
break;
case '!voteban':
$this->voteban();
break;
case '!vote':
$this->vote();
break;
case '!unvote':
$this->unvote();
break;
case '!tiny':
$this->tinyurl();
break;
case '!help':
$this->help();
break;
}
}
} 

function global_register() {
$result = mysql_query("SELECT `level` FROM `bot_global_access` WHERE `user` = '" . $this->data['from'] . "';");
$this->isregistered();
$user_level = $this->global_level();
#checks for user level in database
if ($user_level == 0)
{
if(mysql_num_rows($result) != 0)
{
$this->send("NOTICE ".$this->data['from']." :You cannot register twice.");
$this->logs("2GLOBAL REGISTER FAILED (CANNOT REGISTER TWICE) FROM ".$this->data['from']." ON ".$this->serverchannel);
}
else {
mysql_query("INSERT INTO `" . $this->database_name . "`.`bot_global_access` (`id` ,`user` , `host` , `level`)VALUES (NULL , '" . $this->data['from'] . "', '" . $this->data['fullhost'] . "', '1');");
$this->logs("2GLOBAL REGISTER SUCCESS FROM ".$this->data['from']." ON ".$this->serverchannel);
}
}
elseif ($user_level == -1) {
$this->logs("2GLOBAL REGISTER FAILED (NEED TO LOGIN FIRST) FROM ".$this->data['from']." ON ".$this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :You need to log in before you can register.");
}
}

function register() {
$result = mysql_query("SELECT `level` FROM `bot_access` WHERE `user` = '" . $this->data['from'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");
$this->isregistered();
$user_level = $this->user_level();
#checks for user level in database
if ($user_level == 0)
{
if(mysql_num_rows($result) != 0)
{
$this->send("NOTICE ".$this->data['from']." :You cannot register twice.");
$this->logs("3REGISTER FAILED (CANNOT REGISTER TWICE) FROM ".$this->data['from']." ON ".$this->serverchannel);
}
else {
mysql_query("INSERT INTO `" . $this->database_name . "`.`bot_access` (`id` ,`user` , `host` , `level` ,`channel` ,`server`)VALUES (NULL , '" . $this->data['from'] . "', '" . $this->data['fullhost'] . "', '1', '" . $this->serverchannel . "', '" . $this->serveraddress . "');");
$this->logs("3REGISTER SUCCESS FROM ".$this->data['from']." ON ".$this->serverchannel);
}
}
elseif ($user_level == -1) {
$this->send("NOTICE ".$this->data['from']." :You need to log in before you can register.");
$this->logs("3REGISTER FAILED (NEED TO LOGIN FIRST) FROM ".$this->data['from']." ON ".$this->serverchannel);
}
}

function global_adduser($user_level) {
$result = mysql_query("SELECT `level` FROM `bot_global_access` WHERE `user` = '" . $this->data['message_target'] . "';");
#checks for user level in database
if(mysql_num_rows($result) != 0)
{
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is already registered");
$this->logs("2GLOBAL ADDUSER FAILED (ALREADY REGISTERED) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
elseif ($this->data['message_target2']>=$user_level)
{
$this->send("NOTICE ".$this->data['from']." : You cannot add other Administrators");
$this->logs("2GLOBAL ADDUSER FAILED (LEVEL TOO HIGH) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
elseif ($this->data['message_target2']<0)
{
$this->send("NOTICE ".$this->data['from']." : You cannot add below level 0");
$this->logs("2GLOBAL ADDUSER FAILED (LEVEL TOO LOW) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
else {
mysql_query("INSERT INTO `" . $this->database_name . "`.`bot_global_access` (`id` ,`user` , `host` , `level`)VALUES (NULL , '" . $this->data['message_target'] . "', NULL, '" . $this->data['message_target2'] . "');");
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is now added to Global access list as level " . $this->data['message_target2']);
$this->logs("2GLOBAL ADDUSER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
}

function adduser($user_level) {
$result = mysql_query("SELECT `level` FROM `bot_access` WHERE `user` = '" . $this->data['message_target'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");
#checks for user level in database
if(mysql_num_rows($result) != 0)
{
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is already registered");
$this->logs("3ADDUSER FAILED (ALREADY REGISTERED) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
else {
if ($this->global_user == 1 AND $user_level >= 9)
{
if($this->data['message_target2']<0){ #cant add a user level higher or equal to yours
$this->send("NOTICE ".$this->data['from']." :You cannot have a level below 0");
$this->logs("3ADDUSER FAILED (LEVEL TO LOW) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
elseif($this->data['message_target2']>10){ #cant add a user level higher or equal to yours
$this->send("NOTICE ".$this->data['from']." :You cannot have a level above 10");
$this->logs("3ADDUSER FAILED (LEVEL TO HIGH) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
else
{
mysql_query("INSERT INTO `" . $this->database_name . "`.`bot_access` (`id` ,`user` , `host` , `level` ,`channel` ,`server`)VALUES (NULL , '" . $this->data['message_target'] . "', NULL, '" . $this->data['message_target2'] . "', '" . $this->serverchannel . "', '" . $this->serveraddress . "');");
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is now added to channel access list as level " . $this->data['message_target2']);
$this->logs("3ADDUSER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
}
else
{
if($row['level']==10)
{
$this->send("NOTICE ".$this->data['from']." :You cannot add owners");
$this->logs("3ADDUSER FAILED (CANNOT ADD OWNERS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
elseif($this->data['message_target2']>=$user_level){ #cant add a user level higher or equal to yours
$this->send("NOTICE ".$this->data['from']." :You cannot add a rank higher than or equal to yours");
$this->logs("3ADDUSER FAILED (CANNOT ADD OWNERS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
elseif($this->data['message_target2']<0){ #cant add a user level higher or equal to yours
$this->send("NOTICE ".$this->data['from']." :You cannot have a level below 0");
$this->logs("3ADDUSER FAILED (LEVEL TOO LOW) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
else
{
mysql_query("INSERT INTO `" . $this->database_name . "`.`bot_access` (`id` ,`user` , `host` , `level` ,`channel` ,`server`)VALUES (NULL , '" . $this->data['message_target'] . "', NULL, '" . $this->data['message_target2'] . "', '" . $this->serverchannel . "', '" . $this->serveraddress . "');");
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is now added to channel access list as level " . $this->data['message_target2']);
$this->logs("3ADDUSER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
}
}
}
}

function isregistered() {
$this->registered = 0;
$codes = array('is a registered nick',
'is an identified user',
'is a registered and identified nick',
'has identified for this nick'
);
$this->logs("4WHOIS ".$this->data['from']);
$this->send("WHOIS ".$this->data['from']);

#sees if user us registered
for ($i=0; $i<5;$i++){
mysql_close($this->db);
$response = fgets($this->fp, 1024);
$this->database_connect();
foreach($codes as $code){
if(substr_count($response, $code)){
$this->registered = 1;
}
}
}
}

function global_level(){
$result = mysql_query("SELECT `level` FROM `bot_global_access` WHERE `user` = '" . $this->data['from'] . "';");
$result2 = mysql_query("SELECT `level` FROM `bot_global_access` WHERE `host` = '" . $this->data['fullhost'] . "';");
#checks for user level in database
if($this->registered){
if(mysql_num_rows($result) != 0)
{
$this->using_host = 0;
$this->global_user = 1;
$row = mysql_fetch_assoc($result);
$level = $row['level'];
}
elseif(mysql_num_rows($result2) != 0)
{
$this->using_host = 1;
$this->global_user = 1;
$row = mysql_fetch_assoc($result2);
$level = $row['level'];
}
else
{
$this->using_host = 0;
$this->global_user = 0;
$level = 0;
}
}
elseif(mysql_num_rows($result2) != 0)
{
$this->using_host = 1;
$this->global_user = 1;
$row = mysql_fetch_assoc($result2);
$level = $row['level'];
}
else
{
$this->using_host = 0;
$this->global_user = 0;
$level = -1;
}
return $level;
}

function user_level(){
$result = mysql_query("SELECT `level` FROM `bot_access` WHERE `user` = '" . $this->data['from'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");
$result2 = mysql_query("SELECT `level` FROM `bot_access` WHERE `host` = '" . $this->data['fullhost'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");
#checks for user level in database
if($this->registered){
if(mysql_num_rows($result) != 0)
{
$this->using_host = 0;
$row = mysql_fetch_assoc($result);
$level = $row['level'];
}
elseif(mysql_num_rows($result2) != 0)
{
$this->using_host = 1;
$row = mysql_fetch_assoc($result2);
$level = $row['level'];
}
else
{
$this->using_host = 0;
$level = 0;
}
}
elseif(mysql_num_rows($result2) != 0)
{
$this->using_host = 1;
$row = mysql_fetch_assoc($result2);
$level = $row['level'];
}
else
{
$this->using_host = 0;
$level = -1;
}
return $level;
}

function send($data){
fputs($this->fp, $data."\r\n");
}

function not_allowed($level){
$this->send("NOTICE ".$this->data['from']." :You must be a channel level ".$level." to use this command.");
}

function global_not_allowed($level){
$this->send("NOTICE ".$this->data['from']." :You must be a global level ".$level." to use this command.");
}

function global_option(){
$this->isregistered();
$user_level = $this->global_level();
if(!$this->data['message_target']){
$user = $this->data['from'];
}else{
$user = $this->data['message_target'];
}

switch(strtolower($this->data['message_action'])){
case '!glogin':
if($user_level>=1){
$this->global_login();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'LOGIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 1 ON ".$this->serverchannel);
$this->global_not_allowed('1');
}
break;
case '!glogout':
if($user_level>=1){
$this->global_logout();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'LOGOUT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 1 ON ".$this->serverchannel);
$this->global_not_allowed('1');
}
break;
case '!guser':
if($user_level>=9){
$this->global_add_user($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'USER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
case '!gdeluser':
case '!gremoveuser':
if($user_level>=9){
$this->global_removeuser($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'REMOVEUSER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
case '!off':
if($user_level>=10){
$this->off();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'OFF' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 10 ON ".$this->serverchannel);
$this->global_not_allowed('10');
}
break;
case '!addport':
if($user_level>=9){
$this->addport();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'ADDPORT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
case '!viewport':
if($user_level>=9){
$this->viewport();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'VIEWPORT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
case '!delport':
if($user_level>=9){
$this->delport();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'DELPORT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
case '!gadduser':
if($user_level>=9){
$this->global_adduser($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'ADDUSER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
case '!lecture':
if($user_level>=5){
if(strtolower($this->data['message_target']) == 'pause'){
$this->lecture_pause['num'] = 1;
$this->lecture_pause['time'] = gmmktime();
$this->logs("2LECTURE PAUSED BY ".$this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Lecture Successfully Paused.");
}else if(strtolower($this->data['message_target']) == 'stop'){
fclose($this->rfp);
$this->lecture_pause['num'] = 1;
$this->lecture_pause['time'] = gmmktime();
$this->rfp = NULL;
$this->logs("2LECTURE STOPED BY ".$this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Lecture Successfully Stopped.");
}else if(strtolower($this->data['message_target']) == 'start'){
$this->lecture_pause['num'] = 0;
$this->lecture_pause['time'] = gmmktime();
$this->logs("2LECTURE UNPAUSED BY ".$this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Lecture Successfully Started.");
}else{
#opens the file if needed
if($this->rfp == NULL){
$this->lecture_pause['num'] = 0;
$this->lecture_pause['time'] = gmmktime();
$this->rfp = fopen("lectures/" . $this->data['message_target'] . ".txt", 'r');
$this->logs("2LECTURE ".$this->data['message_target']." STARTED BY ".$this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Lecture Successfully Started.");
}else{
$this->send("NOTICE ".$this->data['from']." :An error occured when trying to start the lecture.");
}
}
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'LECTURE' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 5 ON ".$this->serverchannel);
$this->global_not_allowed('5');
}
break;
case '!quit':
if($user_level>=9){
$this->disconnect();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'QUIT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
case '!join':
if($user_level>=9){
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'JOIN' ".$this->data['message_target']." ON ".$this->serverchannel);
$this->send("JOIN ".$this->data['message_target']);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'JOIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
case '!restart':
if($user_level>=9){
$this->restart();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'RESTART' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->global_not_allowed('9');
}
break;
}
}

function pm_global_option(){
$this->isregistered();
$user_level = $this->global_level();
if(!$this->data['message_target']){
$user = $this->data['from'];
}else{
$user = $this->data['message_target'];
}
switch(strtolower($this->data['message_action'])){
case 'adduser':
if($user_level>=9){
$this->global_adduser($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'ADDUSER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'deluser':
case 'removeuser':
if($user_level>=9){
$this->global_removeuser($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'REMOVEUSER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'user':
if($user_level>=9){
$this->global_add_user($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'USER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'login':
if($user_level>=1){
$this->global_login();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'LOGIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 1 BY PM");
$this->global_not_allowed('1');
}
break;
case 'logout':
if($user_level>=1){
$this->global_logout();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'LOGOUT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 1 BY PM");
$this->global_not_allowed('1');
}
break;
case 'quit':
if($user_level>=9){
$this->disconnect();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'QUIT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'off':
if($user_level>=10){
$this->off();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'OFF' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 10 BY PM");
$this->global_not_allowed('10');
}
break;
case 'addport':
if($user_level>=9){
$this->addport();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'ADDPORT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'viewport':
if($user_level>=9){
$this->viewport();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'VIEWPORT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'delport':
if($user_level>=9){
$this->delport();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'DELPORT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'join':
if($user_level>=9){
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'JOIN' ".$this->data['message_target']." BY PM");
$this->send("JOIN ".$this->data['message_target']);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'JOIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'leave':
if($user_level>=9){
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'LEAVE' ".$this->data['message_target']." BY PM");
$this->send("PART ".$this->data['message_target']);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'LEAVE' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
case 'restart':
if($user_level>=9){
$this->restart();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE GLOBAL COMMAND 'RESTART' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 BY PM");
$this->global_not_allowed('9');
}
break;
}
}

function channel_option(){
$this->isregistered();
$user_level1 = $this->global_level();
$user_level2 = $this->user_level();
if($user_level1 >= $user_level2)
{
$user_level = $user_level1;
}
else
{
$user_level = $user_level2;
$this->global_user = 0;
}

if(!$this->data['message_target']){
$user = $this->data['from'];
}else{
$user = $this->data['message_target'];
}

switch(strtolower($this->data['message_action'])){
case '!addquote':
if($user_level>=0){
$time = time();

mysql_query("INSERT INTO `bot_quote` (`added_by`, `quote`, `time_posted`) VALUES ('".$this->data['from']."', '".$this->data['message_action_text_plain']."', '$time')");

$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'ADDQUOTE' ON " . $this->serverchannel . " WITH TEXT ".$this->data['message_action_text_plain']);
$this->send("NOTICE ".$this->data['from']." :Your quote was successfully added.");
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'ADDQUOTE' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 0 ON ".$this->serverchannel);
$this->not_allowed('0');
}
break;
case '!own':
if($user_level>=10){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'OWN' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." +q " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'OWN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 10 ON ".$this->serverchannel);
$this->not_allowed('10');
}
break;
case '!deown':
if($user_level>=10){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'DEOWN' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." -q " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'DEOWN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 10 ON ".$this->serverchannel);
$this->not_allowed('10');
}
break;
case '!admin':
if($user_level>=9){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'ADMIN' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." +a " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'ADMIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->not_allowed('9');
}
break;
case '!leave':
if($user_level>=9){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'LEAVE' ON ".$this->serverchannel);
$this->send("PART ".$this->serverchannel);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'LEAVE' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->not_allowed('9');
}
break;
case '!deadmin':
if($user_level>=9){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'DEADMIN' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." -a " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'DEADMIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 9 ON ".$this->serverchannel);
$this->not_allowed('9');
}
break;
case '!op':
if($user_level>=7){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'OP' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." +o " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'OP' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 7 ON ".$this->serverchannel);
$this->not_allowed('7');
}
break;
case '!deop':
if($user_level>=7){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'DEOP' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." -o " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'ADOP' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 7 ON ".$this->serverchannel);
$this->not_allowed('7');
}
break;
case '!hop':
if($user_level>=6){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'HOP' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." +h " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'HOP' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 6 ON ".$this->serverchannel);
$this->not_allowed('6');
}
break;
case '!dehop':
if($user_level>=6){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'DEHOP' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." -h " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'DEHOP' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 6 ON ".$this->serverchannel);
$this->not_allowed('6');
}
break;
case '!stop':
if($user_level>=6){
$this->stopvote();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'STOP' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 6 ON ".$this->serverchannel);
$this->not_allowed('6');
}
break;
case '!rejoin':
if($user_level>=6){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'REJOIN' ON ".$this->serverchannel);
$this->send("PART ".$this->data['sent_to']);
$this->send("JOIN ".$this->data['sent_to']);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'REJOIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 6 ON ".$this->serverchannel);
$this->not_allowed('6');
}
break;
case '!voice':
if($user_level>=3){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'VOICE' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." +v " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'VOICE' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 3 ON ".$this->serverchannel);
$this->not_allowed('3');
}
break;
case '!devoice':
if($user_level>=3){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'DEVOICE' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("MODE ".$this->data['sent_to']." -v " .$user);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'DEVOICE' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 3 ON ".$this->serverchannel);
$this->not_allowed('3');
}
break;
case '!kick':
if($user_level>=5){
$this->kick($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'KICK' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 5 ON ".$this->serverchannel);
$this->not_allowed('5');
}
break;
case '!topic':
if($user_level>=5){
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'TOPIC' ON " . $this->serverchannel);
$this->send("TOPIC ".$this->data['sent_to']." :".$this->data['message_action_text_plain']);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'TOPIC' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 5 ON ".$this->serverchannel);
$this->not_allowed('5');
}
break;
case '!mode':
if($user_level>=7){
if($user_level!=10){
$this->data['message_target'] = str_replace("q", "", $this->data['message_target']);
if($user_level!=9){
$this->data['message_target'] = str_replace("a", "", $this->data['message_target']);
}
}
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'MODE' ON " . $this->serverchannel);
$this->logs("4MODE ".$this->data['sent_to']." ".$this->data['message_target']." ".$this->data['message_action_text_plain2']);
$this->send("MODE ".$this->data['sent_to']." ".$this->data['message_target']." ".$this->data['message_action_text_plain2']);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'MODE' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 7 ON ".$this->serverchannel);
$this->not_allowed('7');
}
break;
case '!user':
if($user_level>=2){
$this->add_user($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'USER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 2 ON ".$this->serverchannel);
$this->not_allowed('2');
}
break;
case '!adduser':
if($user_level>=2){
$this->adduser($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'ADDUSER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 2 ON ".$this->serverchannel);
$this->not_allowed('2');
}
break;
case '!deluser':
case '!removeuser':
if($user_level>=2){
$this->removeuser($user_level);
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'DELUSER' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 2 ON ".$this->serverchannel);
$this->not_allowed('2');
}
break;
case '!login':
if($user_level>=1){
$this->login();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'LOGIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 1 ON ".$this->serverchannel);
$this->not_allowed('1');
}
break;
case '!logout':
if($user_level>=1){
$this->logout();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'LOGOUT' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 1 ON ".$this->serverchannel);
$this->not_allowed('1');
}
break;
case '!setjoin':
if($user_level>=0){
$this->setjoin();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'SETJOIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 0 ON ".$this->serverchannel);
$this->not_allowed('0');
}
break;
case '!viewjoin':
if($user_level>=0){
$this->viewjoin();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'VIEWJOIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 0 ON ".$this->serverchannel);
$this->not_allowed('0');
}
break;
case '!deljoin':
if($user_level>=0){
$this->deljoin();
}else{
$this->logs("5".$this->data['from']." NOT ALLOWED TO USE CHANNEL COMMAND 'DELJOIN' BECAUSE LEVEL IS ".$user_level." AND REQUIRES 0 ON ".$this->serverchannel);
$this->not_allowed('0');
}
break;
}
}

function kick($user_level) {
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'KICK' ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$from = $this->data['from'];
$this->data['from'] = $this->data['message_target'];
$this->data['fullhost'] = $this->fullhost($this->data['message_target']);
$this->isregistered();
$user_level1 = $this->global_level();
$user_level2 = $this->user_level();
if($user_level1 >= $user_level2)
{
$targetuser_level = $user_level1;
}
else
{
$targetuser_level = $user_level2;
$global_targetuser = 0;
}
if ($global_targetuser == 1 AND $targetuser_level >= 9 AND $this->global_user != 1)
{
$this->data['message_target'] = $from;
}
elseif($targetuser_level > $user_level)
{
$this->data['message_target'] = $from;
}
if(strtolower($this->data['message_target']) != strtolower($this->botnick)){
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['message_target']." :".$this->data['message_action_text_plain2']);
$this->send("KICK ".$this->data['sent_to']." ".$this->data['message_target']." :".$this->data['message_action_text_plain2']);
}else{
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['from']." :Now that was a mistake...wasnt it?");
$this->send("KICK ".$this->data['sent_to']." ".$this->data['from']." :Now that was a mistake...wasnt it?");
}
}

function add_user($user_level){
if(!$this->data['message_target'])
{

$result = mysql_query("SELECT * FROM `bot_access` WHERE `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."' ORDER BY `level` ASC");

if(mysql_num_rows($result) == 0)
{
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'USER' ON " . $this->serverchannel . " FOR LIST");
$this->send("NOTICE ".$this->data['from']." :There are currently no users with access on ".$this->serverchannel);
}
else
{
while ($row = mysql_fetch_assoc($result))
{
if(!$data)
{
$data = $row['user'] . " " . $row['level'];
}
else
{
$data = $row['user'] . " " . $row['level'] . ", " . $data;
}
}
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'USER' ON " . $this->serverchannel . " FOR LIST");
$this->send("NOTICE ".$this->data['from']." :" . $data . " with access on ".$this->serverchannel);
}
}
else
{

$result = mysql_query("SELECT `level` FROM `bot_access` WHERE `user` = '".$this->data['message_target']."' AND `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."'");

if(mysql_num_rows($result) == 0)
{
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'USER' ON " . $this->serverchannel . " FOR LEVEL OF ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is not registered to " . $this->serverchannel);
}
else
{
$row = mysql_fetch_assoc($result);
if(!$this->data['message_target2'])
{
$this->logs("3".$this->data['from']." USED CHANNEL COMMAND 'USER' ON " . $this->serverchannel . " FOR LEVEL OF ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is level ".$row['level']);
}
else
{
if ($this->global_user == 1 AND $user_level >= 9)
{
if($this->data['message_target2']<0){ #cant add a user level higher or equal to yours
$this->logs("3USER FAILED (LEVEL TOO LOW) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$row['level']);
$this->send("NOTICE ".$this->data['from']." :You cannot have a level below 0");
}
elseif($this->data['message_target2']>10){ #cant add a user level higher or equal to yours
$this->logs("3USER FAILED (LEVEL TOO HIGH) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
$this->send("NOTICE ".$this->data['from']." :You cannot have a level above 10");
}
else
{
#modify user level

mysql_query("UPDATE `bot_access` SET `level` = '".$this->data['message_target2']."' WHERE `user` = '".$this->data['message_target']."' AND `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."'");

$this->logs("3USER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']." FROM ".$row['level']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']."'s user access level has been successfully changed to level ".$this->data['message_target2']);
}
}
else
{
if($row['level']==10)
{
$this->send("NOTICE ".$this->data['from']." :You cannot edit owners");
$this->logs("3USER FAILED (CANNOT EDIT OWNERS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
}
elseif($row['level']>=$user_level)
{
$this->logs("3USER FAILED (CANNOT EDIT HIGHER LEVEL) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$row['level']);
$this->send("NOTICE ".$this->data['from']." :You cannot edit someone who is higher than you in rank");
}
elseif($this->data['message_target2']>=$user_level){ #cant add a user level higher or equal to yours
$this->logs("3USER FAILED (LEVEL TOO HIGH) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']);
$this->send("NOTICE ".$this->data['from']." :You cannot add a rank higher than or equal to yours");
}
elseif($this->data['message_target2']<0){ #cant add a user level higher or equal to yours
$this->logs("3USER FAILED (LEVEL TOO LOW) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$row['level']);
$this->send("NOTICE ".$this->data['from']." :You cannot have a level below 0");
}
else
{
#modify user level

mysql_query("UPDATE `bot_access` SET `level` = '".$this->data['message_target2']."' WHERE `user` = '".$this->data['message_target']."' AND `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."'");

$this->logs("3USER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']." FROM ".$row['level']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']."'s user access level has been successfully changed to level ".$this->data['message_target2']);
}
}
}
}
}
}

function global_add_user($user_level){
if(!$this->data['message_target'])
{

$result = mysql_query("SELECT * FROM `bot_global_access` ORDER BY `level` ASC");

if(mysql_num_rows($result) == 0)
{
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'USER' ON " . $this->serverchannel . " FOR LIST");
$this->send("NOTICE ".$this->data['from']." :There are currently no users with global access");
}
else
{
while ($row = mysql_fetch_assoc($result))
{
if(!$data)
{
$data = $row['user'] . " " . $row['level'];
}
else
{
$data = $row['user'] . " " . $row['level'] . ", " . $data;
}
}
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'USER' ON " . $this->serverchannel . " FOR LIST");
$this->send("NOTICE ".$this->data['from']." :" . $data . " with global access");
}
}
else
{

$result = mysql_query("SELECT `level` FROM `bot_global_access` WHERE `user` = '".$this->data['message_target']."'");

if(mysql_num_rows($result) == 0)
{
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'USER' ON " . $this->serverchannel . " FOR LEVEL OF ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is not a global user");
}
else
{
$row = mysql_fetch_assoc($result);
if(!$this->data['message_target2'])
{
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'USER' ON " . $this->serverchannel . " FOR LEVEL OF ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." is global level ".$row['level']);
}
else
{
if($row['level']==10)
{
$this->send("NOTICE ".$this->data['from']." :You cannot edit owners");
$this->logs("2GLOBAL USER FAILED (CANNOT EDIT OWNERS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
}
elseif($row['level']>=$user_level)
{
$this->send("NOTICE ".$this->data['from']." :You cannot edit other Administrators");
$this->logs("2GLOBAL USER FAILED (CANNOT EDIT ADMINISTRATORS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
}
elseif($this->data['message_target2']>=$user_level){ #cant add a user level higher or equal to yours
$this->send("NOTICE ".$this->data['from']." :You cannot add other Administrators");
$this->logs("2GLOBAL USER FAILED (CANNOT ADD ADMINISTRATORS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
}
elseif($this->data['message_target2']<0){ #cant add a user level higher or equal to yours
$this->logs("2GLOBAL USER FAILED (LEVEL TOO LOW) FROM ".$this->data['from']." ON " . $this->serverchannel . " LEVEL ".$this->data['message_target2']);
$this->send("NOTICE ".$this->data['from']." :You cannot have a level below 0");
}
else
{
#modify user level

mysql_query("UPDATE `bot_global_access` SET `level` = '".$this->data['message_target2']."' WHERE `user` = '".$this->data['message_target']."'");

$this->logs("2GLOBAL USER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$this->data['message_target2']." FROM ".$row['level']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']."'s user global access level has been successfully changed to level ".$this->data['message_target2']);
}
}
}
}
}

function removeuser($user_level){

$result = mysql_query("SELECT * FROM `bot_access` WHERE `user` = '".$this->data['message_target']."' AND `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."'");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);
if ($this->global_user == 1 AND $user_level >= 9)
{

mysql_query("DELETE FROM `bot_access` WHERE `bot_access`.`id` = '" . $row['id'] . "'");

$this->logs("3DELUSER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." has been removed from the channel access list");
}
else
{
if($row['level']==10)
{
$this->send("NOTICE ".$this->data['from']." :You cannot remove owners");
$this->logs("3DELUSER FAILED (CANNOT REMOVE OWNERS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
}
elseif($row['level']>=$user_level)
{
$this->logs("3DELUSER FAILED (LEVEL TOO HIGH) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']." LEVEL ".$row['level']);
$this->send("NOTICE ".$this->data['from']." :You cannot remove a user ranked higher than you");
}
else
{
#modify user level

mysql_query("DELETE FROM `bot_access` WHERE `bot_access`.`id` = '" . $row['id'] . "'");

$this->logs("3DELUSER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." has been removed from the channel access list");
}
}
}
}

function global_removeuser($user_level){

$result = mysql_query("SELECT * FROM `bot_global_access` WHERE `user` = '".$this->data['message_target']."'");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);
if($row['level']==10)
{
$this->logs("2GLOBAL DELUSER FAILED (CANNOT REMOVE OWNERS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :You cannot remove owners");
}
elseif($row['level']>=$user_level)
{
$this->logs("2GLOBAL DELUSER FAILED (CANNOT REMOVE ADMINS) FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :You cannot remove other Administrators");
}
else
{
#modify user level

mysql_query("DELETE FROM `bot_global_access` WHERE `bot_global_access`.`id` = '" . $row['id'] . "'");

$this->logs("2GLOBAL DELUSER SUCCESS FROM ".$this->data['from']." ON " . $this->serverchannel . " FOR ".$this->data['message_target']);
$this->send("NOTICE ".$this->data['from']." :".$this->data['message_target']." has been removed from the global access list");
}
}
}

function time(){
$time = gmmktime();
$hour = substr($this->data['message_target'], 1);
if($this->data['message_target'][0] == '+'){ #is a plus
$time += $hour*60*60;
}else if($this->data['message_target'][0] == '-'){ #is a minus
$time -= $hour*60*60;
}else{
$time += $this->data['message_target']*60*60;
}

if($hour>12 OR $this->data['message_target']>12 OR !is_numeric($this->data['message_target']) OR !is_numeric($hour)){
$time = gmmktime();
}
$this->logs($this->data['from']." REQUESTED 'TIME' ON ".$this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :The current time is ".date('G:i', $time));
}

function ball8(){
$words = array('Yes', 'No', "Don't Count On It", 'For Sure', 'Of Course', 'Doubtful', "Don't Be Stupid", 'Why You Asking Me...Just Leave Me Alone', 'Yes...Its In The Stars', 'Hmmmm', 'Sorry Im Away Right Now...Try Again Later', "Don't Make Me Laugh you Muppit!!");

if($this->data['message_target']){
$num = rand(0, (count($words)-1));
$this->logs($this->data['from']." REQUESTED '8BALL' ON ".$this->serverchannel);
$this->send("PRIVMSG ".$this->data['sent_to']." :".$this->data['from']." - $words[$num]");
}else{
$this->send("NOTICE ".$this->data['from']." :Usage: !8ball <question>");
}
}

function my_user_level(){
$this->isregistered();
$user_level = $this->global_level();
if ($this->global_user == 1)
{
$this->send("NOTICE ".$this->data['from']." :Your global user level is: ".$user_level);
}
$this->logs($this->data['from']." REQUESTED 'USERLEVEL' ON ".$this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :Your channel user level is: ".$this->user_level());
}

function quote(){
switch($this->data['message_target']){
case 'user':

$result = mysql_query("SELECT `quote` FROM `bot_quote` WHERE `quote` LIKE '%".$this->data['message_target2']."%' ORDER BY rand() LIMIT 0,1");

$this->logs($this->data['from']." REQUESTED 'QUOTE' ON " . $this->serverchannel . " FOR USER ".$this->data['message_target2']);
while($row = mysql_fetch_assoc($result)){
$quote = $row[quote];
}
break;
case 'from':

$result = mysql_query("SELECT `quote` FROM `bot_quote` WHERE `added_by` = '".$this->data['message_target2']."' ORDER BY rand() LIMIT 0,1");

$this->logs($this->data['from']." REQUESTED 'QUOTE' ON " . $this->serverchannel . " FROM ".$this->data['message_target2']);
while($row = mysql_fetch_assoc($result)){
$quote = $row[quote];
}
break;
case 'last':

$result = mysql_query("SELECT `quote` FROM `bot_quote` ORDER BY `time_posted` DESC LIMIT 0,1");

$this->logs($this->data['from']." REQUESTED 'QUOTE' ON " . $this->serverchannel . " FROM LAST QUOTE");
while($row = mysql_fetch_assoc($result)){
$quote = $row[quote];
}
break;
case 'number':

$result = mysql_query("SELECT `quote` FROM `bot_quote` WHERE `id` = '".$this->data['message_target2']."'");

$this->logs($this->data['from']." REQUESTED 'QUOTE' ON " . $this->serverchannel . " NUMBER".$this->data['message_target2']);
while($row = mysql_fetch_assoc($result)){
$quote = $row[quote];
}
break;
case 'random':

$result = mysql_query("SELECT `quote` FROM `bot_quote` ORDER BY rand() LIMIT 0,1");

$this->logs($this->data['from']." REQUESTED 'QUOTE' ON " . $this->serverchannel . " RANDOM");
while($row = mysql_fetch_assoc($result)){
$quote = $row[quote];
}
break;
default:
$this->logs($this->data['from']." REQUESTED 'QUOTE' ON " . $this->serverchannel . " USAGE");
$quote = "Usage: !quote <user|from|last|number|random> <value> (note bash, last and random require no <value>)";
}

if($quote == ''){
$quote = "No Quote Found.";
}
$this->send("NOTICE ".$this->data['from']." :$quote");
}

function lecture(){
mysql_close($this->db);
$line = fgets($this->rfp, 1024);
$this->database_connect();
$this->send("PRIVMSG #Kriegchan : " . $line);
$this->lecture_pause['time'] = gmmktime() + 4;
if(feof($this->rfp)){
$this->rfp = NULL;
}
}

function base64(){
if(strtolower($this->data['message_target']) == 'encode'){ #encode to base64
$this->logs($this->data['from']." REQUESTED 'BASE64' ON " . $this->serverchannel . " ENCODE");
$value = base64_encode($this->data['message_action_text_plain_with_params']);
}else if(strtolower($this->data['message_target']) == 'decode'){ #decode
$this->logs($this->data['from']." REQUESTED 'BASE64' ON " . $this->serverchannel . " DECODE");
$value = base64_decode($this->data['message_action_text_plain_with_params']);
}else{ #send usage method
$this->logs($this->data['from']." REQUESTED 'BASE64' ON " . $this->serverchannel . " USAGE");
$value = "Usage: !base64 <encode|decode> <message to encode/decode>";
}
$this->send("NOTICE ".$this->data['from']." :$value");
}

function md5(){
if($this->data['message_action_text_plain']){
$this->logs($this->data['from']." REQUESTED 'MD5' ON " . $this->serverchannel . " ENCODE");
$value = md5($this->data['message_action_text_plain']);
}else{
$this->logs($this->data['from']." REQUESTED 'MD5' ON " . $this->serverchannel . " USAGE");
$value = "Usage: !md5 <plaintext>";
}
$this->send("NOTICE ".$this->data['from']." :$value");
}

function rot13(){
if($this->data['message_action_text_plain']){
$this->logs($this->data['from']." REQUESTED 'ROT13' ON " . $this->serverchannel . " ENCODE");
$value = str_rot13($this->data['message_action_text_plain']);
}else{
$this->logs($this->data['from']." REQUESTED 'ROT13' ON " . $this->serverchannel . " USAGE");
$value = "Usage: !rot13 <message>";
}
$this->send("NOTICE ".$this->data['from']." :$value");
}

function version(){
$this->logs($this->data['from']." REQUESTED 'VERSION' ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :".$this->version_message);
}

function onjoin(){
if($this->registered)
{

$result = mysql_query("SELECT `message` FROM `bot_join` WHERE `user` = '" . $this->data['from'] . "' AND `channel` = '" . $this->data['sent_to'] . "' AND `server` = '" . $this->serveraddress . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);
$this->logs("JOIN MESSAGE FOR ".$this->data['from']." ON " . $this->serverchannel);
$this->send("PRIVMSG ".$this->data['sent_to']." :".$row['message']);
}
}
else
{

$result2 = mysql_query("SELECT `user` FROM `bot_access` WHERE `host` = '" . $this->data['fullhost'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");


$result3 = mysql_query("SELECT `user` FROM `bot_global_access` WHERE `host` = '" . $this->data['fullhost'] . "';");

if(mysql_num_rows($result2) != 0)
{
$user = mysql_fetch_assoc($result2);
$stuff = 1;
}
elseif(mysql_num_rows($result3) != 0)
{
$user = mysql_fetch_assoc($result3);
$stuff = 1;
}
else
{
$stuff = 0;
}
if ($stuff == 1)
{

$result = mysql_query("SELECT `message` FROM `bot_join` WHERE `user` = '" . $user['user'] . "' AND `channel` = '" . $this->data['sent_to'] . "' AND `server` = '" . $this->serveraddress . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);
$this->logs("JOIN MESSAGE FOR ".$user['user']." AS ".$this->data['from']." ON " . $this->serverchannel);
$this->send("PRIVMSG ".$this->data['sent_to']." :".$row['message']);
}
}
}
}

function viewjoin(){
if($this->registered)
{

$result = mysql_query("SELECT `message` FROM `bot_join` WHERE `user` = '" . $this->data['from'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);
$this->logs($this->data['from']." REQUESTED 'VIEWJOIN' ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :".$row['message']);
}
}
}

function setjoin(){
if($this->registered)
{

$result = mysql_query("SELECT `message` FROM `bot_join` WHERE `user` = '" . $this->data['from'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);

mysql_query("UPDATE `" . $this->database_name . "`.`bot_join` SET `message` = '" . $this->data['message_action_text_plain'] . "' WHERE `bot_join`.`id` = " . $result['id']);

$this->logs($this->data['from']." 'SETJOIN' ON " . $this->serverchannel . " TO ".$this->data['message_action_text_plain']);
$this->send("NOTICE ".$this->data['from']." :Join message changed.");
}
else
{

mysql_query("INSERT INTO `" . $this->database_name . "`.`bot_join` (`id` ,`user` ,`channel` ,`server` ,`message`)VALUES (NULL , '" . $this->data['from'] . "', '" . $this->serverchannel . "', '" . $this->serveraddress . "', '" . $this->data['message_action_text_plain'] . "');");

$this->logs($this->data['from']." 'SETJOIN' ON " . $this->serverchannel . " TO ".$this->data['message_action_text_plain']);
$this->send("NOTICE ".$this->data['from']." :Join message set.");
}
}
}

function deljoin(){
if($this->registered)
{

$result = mysql_query("SELECT * FROM `bot_join` WHERE `user` = '" . $this->data['from'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);

mysql_query("DELETE FROM `bot_join` WHERE `bot_join`.`id` = " . $row['id']);

$this->logs($this->data['from']." REQUESTED 'DELJOIN' ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :Join message deleted.");
}
}
}

function onkick(){
if ($this->data['action'] == $this->botnick)
{
$this->logs($this->botnick . " KICKED FROM ".$this->data['sent_to']." BY ".$this->data['from']);
$this->send("JOIN ".$this->serverchannel);
}
}

function login(){

$result = mysql_query("SELECT `id` FROM `bot_access` WHERE `user` = '" . $this->data['from'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);

mysql_query("UPDATE `" . $this->database_name . "`.`bot_access` SET `host` = '" . $this->data['fullhost'] . "' WHERE `bot_access`.`id` = " . $row['id']);

$this->logs($this->data['from']." REQUESTED 'LOGIN' ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :Your host is now associated with your nick");
}
else
{
$this->logs($this->data['from']." REQUESTED 'LOGIN' AND FAILED ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :There was an error associating your host with your nick");
}
}

function logout(){

$result = mysql_query("SELECT `id` FROM `bot_access` WHERE `user` = '" . $this->data['from'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");
$result2 = mysql_query("SELECT `id` FROM `bot_access` WHERE `host` = '" . $this->data['fullhost'] . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);

mysql_query("UPDATE `" . $this->database_name . "`.`bot_access` SET `host` = NULL WHERE `bot_access`.`id` = " . $row['id']);

$this->logs($this->data['from']." REQUESTED 'LOGOUT' ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :Your host is no longer associated with your nick");
}
elseif(mysql_num_rows($result2) != 0)
{
$row = mysql_fetch_assoc($result2);

mysql_query("UPDATE `" . $this->database_name . "`.`bot_access` SET `host` = NULL WHERE `bot_access`.`id` = " . $row['id']);

$this->logs($this->data['from']." REQUESTED 'LOGOUT' ON " . $this->serverchannel . " FOR " . $row['user']);
$this->send("NOTICE ".$this->data['from']." :Your host is no longer associated with your nick");
}
else
{
$this->logs($this->data['from']." REQUESTED 'LOGOUT' AND FAILED ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :There was an error unassociating your host with your nick");
}
}

function global_login(){

$result = mysql_query("SELECT `id` FROM `bot_global_access` WHERE `user` = '" . $this->data['from'] . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);

mysql_query("UPDATE `" . $this->database_name . "`.`bot_global_access` SET `host` = '" . $this->data['fullhost'] . "' WHERE `bot_global_access`.`id` = " . $row['id']);

$this->logs($this->data['from']." REQUESTED GLOBAL 'LOGIN' ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :Your host is now associated with your nick");
}
else
{
$this->logs($this->data['from']." REQUESTED GLOBAL 'LOGIN' AND FAILED ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :There was an error associating your host with your nick");
}
}

function global_logout(){

$result = mysql_query("SELECT `id` FROM `bot_global_access` WHERE `user` = '" . $this->data['from'] . "';");
$result2 = mysql_query("SELECT `id` FROM `bot_global_access` WHERE `host` = '" . $this->data['fullhost'] . "';");

if(mysql_num_rows($result) != 0)
{
$row = mysql_fetch_assoc($result);

mysql_query("UPDATE `" . $this->database_name . "`.`bot_global_access` SET `host` = NULL WHERE `bot_global_access`.`id` = " . $row['id']);

$this->logs($this->data['from']." REQUESTED GLOBAL 'LOGOUT' ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :Your host is no longer associated with your nick");
}
elseif(mysql_num_rows($result2) != 0)
{
$row = mysql_fetch_assoc($result2);

mysql_query("UPDATE `" . $this->database_name . "`.`bot_global_access` SET `host` = NULL WHERE `bot_global_access`.`id` = " . $row['id']);

$this->logs($this->data['from']." REQUESTED GLOBAL 'LOGOUT' ON " . $this->serverchannel . " FOR " . $row['user']);
$this->send("NOTICE ".$this->data['from']." :Your host is no longer associated with your nick");
}
else
{
$this->logs($this->data['from']." REQUESTED GLOBAL 'LOGOUT' AND FAILED ON " . $this->serverchannel);
$this->send("NOTICE ".$this->data['from']." :There was an error unassociating your host with your nick");
}
}

function fullhost($user){
$this->logs("4USERHOST ".$user);
$this->send("USERHOST ".$user);
mysql_close($this->db);
$response = fgets($this->fp, 1024);
$this->database_connect();
$params = explode(" ", $response);
if($params[3] == ':')
{
$this->send("PRIVMSG ".$this->data['sent_to']." :".$user." does not exist");
return FALSE;
}
else
{
$host = str_ireplace($user . "=+", "", $params[3]);
$host = str_ireplace($user . "=-", "", $host);
$host = str_replace(":", "", $host);
}
return $host;
}

function whois(){
$host = $this->fullhost($this->data['message_target']);
$this->logs($this->data['from']." REQUESTED 'WHOIS' ON " . $this->serverchannel . " FOR ". $this->data['message_target']);
if ($host)
{
$result2 = mysql_query("SELECT `user` FROM `bot_access` WHERE `host` = '" . $host . "' AND `channel` = '" . $this->serverchannel . "' AND `server` = '" . $this->serveraddress . "';");
$result3 = mysql_query("SELECT `user` FROM `bot_global_access` WHERE `host` = '" . $host . "';");
if(mysql_num_rows($result2) != 0)
{
while($user = mysql_fetch_assoc($result2))
{
if(strtolower($this->data['message_target']) == strtolower($user['user']))
{
$this->send("PRIVMSG ".$this->data['sent_to']." :".$this->data['message_target']." is him/herself");
}
else
{
$this->send("PRIVMSG ".$this->data['sent_to']." :".$this->data['message_target']." is actually ".$user['user']);
}
}
}
elseif(mysql_num_rows($result3) != 0)
{
while($user = mysql_fetch_assoc($result3))
{
if(strtolower($this->data['message_target']) == strtolower($user['user']))
{
$this->send("PRIVMSG ".$this->data['sent_to']." :".$this->data['message_target']." is him/herself");
}
else
{
$this->send("PRIVMSG ".$this->data['sent_to']." :".$this->data['message_target']." is actually ".$user['user']);
}
}
}
else
{
$this->send("PRIVMSG ".$this->data['sent_to']." :".$this->data['message_target']." is not linked to a nick");
}
}
}

function votekick(){
if($this->voteban[$this->serverchannel]['num'] == 0 AND $this->votekick[$this->serverchannel]['num'] == 0)
{
mysql_query("DELETE FROM `bot_vote` WHERE `bot_vote`.`channel` = '".$this->serverchannel."' AND `bot_vote`.`server` = '".$this->serveraddress."'");
$this->logs($this->data['from']." REQUESTED 'VOTEKICK' ON " . $this->serverchannel . " FOR ". $this->data['message_target']);
$this->isregistered();
$user_level1 = $this->global_level();
$user_level2 = $this->user_level();
if($user_level1 >= $user_level2)
{
$user_level = $user_level1;
}
else
{
$user_level = $user_level2;
$this->global_user = 0;
}
$from = $this->data['from'];
$this->data['from'] = $this->data['message_target'];
$this->data['fullhost'] = $this->fullhost($this->data['message_target']);
$this->isregistered();
$user_level1 = $this->global_level();
$user_level2 = $this->user_level();
$this->data['from'] = $from;
if($user_level1 >= $user_level2)
{
$targetuser_level = $user_level1;
}
else
{
$targetuser_level = $user_level2;
$global_targetuser = 0;
}
if ($global_targetuser == 1 AND $targetuser_level >= 9 AND $this->global_user != 1)
{
$this->data['message_target'] = $from;
}
elseif($targetuser_level > $user_level)
{
$this->data['message_target'] = $from;
}
if(strtolower($this->data['message_target']) != strtolower($this->botnick)){
$this->votebanned[$this->serverchannel]['banuser'] = $this->data['message_target'];
}else{
$this->votebanned[$this->serverchannel]['banuser'] = $this->data['from'];
}
$this->votebanned[$this->serverchannel]['banserver'] = $this->serveraddress;
$banuser = $this->votebanned[$this->serverchannel]['banuser'];
$banserver = $this->votebanned[$this->serverchannel]['banserver'];
$banchannel = $this->serverchannel;
$this->votekick[$this->serverchannel]['num'] = 1;
$this->votekick[$this->serverchannel]['time'] = gmmktime() + 15;
$this->send("USERHOST ".$banuser);
mysql_close($this->db);
$response = fgets($this->fp, 1024);
$this->database_connect();
$params = explode(" ", $response);
if($params[3] == ':')
{
$this->send("PRIVMSG ".$banchannel." :".$banuser." does not exist");
$this->votekick[$this->serverchannel]['num'] = 0;
}
else
{
$hosts = explode("@", $params[3]);
$host = $hosts[1];
mysql_query("INSERT INTO `".$this->database_name."`.`bot_votekick` (`id` ,`user` ,`host` ,`channel` ,`server` ,`active` ,`yes` ,`no` ,`result` ,`time`)VALUES (NULL , '".$banuser."', '".$host."', '".$banchannel."', '".$banserver."', '1', '', '', '', '');");
$id = mysql_insert_id();
$this->votebanned[$this->serverchannel]['id'] = $id;
$this->send("PRIVMSG ".$banchannel." :Vote Kick started Against ".$banuser." BY ".$this->data['from']);
$this->send("PRIVMSG ".$banchannel." :Please use !vote yes and !vote no now");
}
}
else
{
$this->logs("NOT ALLOWED TO !VOTEKICK SINCE VOTE ALREADY GOING ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("PRIVMSG ".$this->serverchannel." :A vote is already going");
}
}

function votekicktimer() {
$banserver = $this->votebanned[$this->serverchannel]['banserver'];
$banchannel = $this->serverchannel;
$results = mysql_query("SELECT `vote` FROM `bot_vote` WHERE `channel` = '".$banchannel."' AND `server` = '".$banserver."'");
while ($result = mysql_fetch_assoc($results))
{
$num += $result['vote'];
}
if (!$num)
{
$num = 0;
}
$num2 = mysql_num_rows($results);
$num2 = $num2 - $num;
$this->send("PRIVMSG ".$banchannel." :Votes Yes - ".$num." Votes No - ".$num2);
}

function votekickresult() {
$banuser = $this->votebanned[$this->serverchannel]['banuser'];
$bantime = $this->votebanned[$this->serverchannel]['bantime'];
$banserver = $this->votebanned[$this->serverchannel]['banserver'];
$banid = $this->votebanned[$this->serverchannel]['id'];
$banchannel = $this->serverchannel;
$results = mysql_query("SELECT `vote` FROM `bot_vote` WHERE `channel` = '".$banchannel."' AND `server` = '".$banserver."'");
while ($result = mysql_fetch_assoc($results))
{
$num += $result['vote'];
}
if (!$num)
{
$num = 0;
}
$num3 = mysql_num_rows($results);
if ($num3 != 0)
{
$num2 = $num3 - $num;
$this->send("PRIVMSG ".$banchannel." :Votes Yes - ".$num." Votes No - ".$num2);
$num4 = $num / $num3 * 10;
}
else
{
$num4 = 0;
$num3 = 0;
$num2 = 0;
$num = 0;
}
if($num4 > 5 AND $num3 >= 3)
{
mysql_query("UPDATE `".$this->database_name."`.`bot_votekick` SET `active` = '0', `yes` = '".$num."', `no` = '".$num2."', `result` = '1', `at` = '".gmmktime()."' WHERE `bot_votekick`.`id` = ". $banid);
$this->logs("VOTEKICK SUCCESS ON " . $banchannel . " AGAINST " . $banuser . " - Votes Yes - ".$num." Votes No - ".$num2);
$this->send("PRIVMSG ".$banchannel." :".$banuser." will be kicked");
$this->logs("4KICK ".$banchannel." ".$banuser." :Vote Kick was a success");
$this->send("KICK ".$banchannel." ".$banuser." :Vote Kick was a success");
}
else
{
mysql_query("UPDATE `".$this->database_name."`.`bot_votekick` SET `active` = '0', `yes` = '".$num."', `no` = '".$num2."', `result` = '0', `at` = '".gmmktime()."' WHERE `bot_votekick`.`id` = ". $banid);
$this->logs("VOTEKICK FAILED ON " . $banchannel . " AGAINST " . $banuser . " - Votes Yes - ".$num." Votes No - ".$num2);
$this->send("PRIVMSG ".$banchannel." :".$banuser." will not be kicked");
}
mysql_query("DELETE FROM `bot_vote` WHERE `bot_vote`.`channel` = '".$banchannel."' AND `bot_vote`.`server` = '".$banserver."'");
}

function vote(){
if($this->voteban[$this->serverchannel]['num'] != 0 OR $this->votekick[$this->serverchannel]['num'] != 0)
{
if (!$this->data['message_target'])
{
$banserver = $this->votebanned[$this->serverchannel]['banserver'];
$banchannel = $this->serverchannel;
$results = mysql_query("SELECT `vote` FROM `bot_vote` WHERE `channel` = '".$banchannel."' AND `server` = '".$banserver."'");
while ($result = mysql_fetch_assoc($results))
{
$num += $result['vote'];
}
if (!$num)
{
$num = 0;
}
$num2 = mysql_num_rows($results);
$num2 = $num2 - $num;
$this->send("PRIVMSG ".$banchannel." :Votes Yes - ".$num." Votes No - ".$num2);
}
else
{
$result = mysql_query("SELECT * FROM `bot_vote` WHERE `user` = '".$this->data['from']."' AND `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."'");
$result2 = mysql_query("SELECT * FROM `bot_vote` WHERE `host` = '".$this->data['fullhost']."' AND `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."'");
if(mysql_num_rows($result2) + mysql_num_rows($result) == 0)
{
switch(strtolower($this->data['message_target'])){
case 'yes':
mysql_query("INSERT INTO `".$this->database_name."`.`bot_vote` (`id` ,`user` ,`host` ,`channel` ,`server` ,`vote`)VALUES (NULL , '".$this->data['from']."', '".$this->data['fullhost']."', '".$this->serverchannel."', '".$this->serveraddress."', '1');");
$this->logs("YES VOTE ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Your Vote of Yes has been logged");
break;
case 'maybe':
$vote = rand(0,1);
mysql_query("INSERT INTO `".$this->database_name."`.`bot_vote` (`id` ,`user` ,`host` ,`channel` ,`server` ,`vote`)VALUES (NULL , '".$this->data['from']."', '".$this->data['fullhost']."', '".$this->serverchannel."', '".$this->serveraddress."', '".$vote."');");
if ($vote == 1)
{
$this->logs("MAYBE YES VOTE ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Your Vote of Yes has been logged");
}
else
{
$this->logs("MAYBE NO VOTE ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Your Vote of No has been logged");
}
break;
case 'no':
mysql_query("INSERT INTO `".$this->database_name."`.`bot_vote` (`id` ,`user` ,`host` ,`channel` ,`server` ,`vote`)VALUES (NULL , '".$this->data['from']."', '".$this->data['fullhost']."', '".$this->serverchannel."', '".$this->serveraddress."', '0');");
$this->logs("NO VOTE ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Your Vote of No has been logged");
break;
}
}
else
{
$this->logs("ATTEMPT TO VOTE TWICE ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :You cannot vote twice");
}
}
}
else
{
$this->logs("NOT ALLOWED TO !VOTE SINCE NO VOTE GOING ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :There is currently no vote going");
}
}

function unvote() {
if($this->voteban[$this->serverchannel]['num'] != 0 OR $this->votekick[$this->serverchannel]['num'] != 0)
{
$result = mysql_query("SELECT * FROM `bot_vote` WHERE `user` = '".$this->data['from']."' AND `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."'");
$result2 = mysql_query("SELECT * FROM `bot_vote` WHERE `host` = '".$this->data['fullhost']."' AND `channel` = '".$this->serverchannel."' AND `server` = '".$this->serveraddress."'");
if(mysql_num_rows($result2) + mysql_num_rows($result) == 0)
{
$this->logs("NO VOTE TO REMOVE ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :You did not vote yet");
}
else
{
mysql_query("DELETE FROM `bot_vote` WHERE `bot_vote`.`host` = '".$this->data['fullhost']."' AND `bot_vote`.`channel` = '".$this->serverchannel."' AND `bot_vote`.`server` = '".$this->serveraddress."'");
mysql_query("DELETE FROM `bot_vote` WHERE `bot_vote`.`user` = '".$this->data['from']."' AND `bot_vote`.`channel` = '".$this->serverchannel."' AND `bot_vote`.`server` = '".$this->serveraddress."'");
$this->logs("VOTE REMOVED ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :Your vote was successfully removed");
}
}
else
{
$this->logs("NOT ALLOWED TO !UNVOTE SINCE NO VOTE GOING ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("NOTICE ".$this->data['from']." :There is currently no vote going");
}
}

function votebantimer() {
$banserver = $this->votebanned[$this->serverchannel]['banserver'];
$banchannel = $this->serverchannel;
$results = mysql_query("SELECT `vote` FROM `bot_vote` WHERE `channel` = '".$banchannel."' AND `server` = '".$banserver."'");
while ($result = mysql_fetch_assoc($results))
{
$num += $result['vote'];
}
if (!$num)
{
$num = 0;
}
$num2 = mysql_num_rows($results);
$num2 = $num2 - $num;
$this->send("PRIVMSG ".$banchannel." :Votes Yes - ".$num." Votes No - ".$num2);
}

function votebanresult() {
$banuser = $this->votebanned[$this->serverchannel]['banuser'];
$bantime = $this->votebanned[$this->serverchannel]['bantime'];
$banserver = $this->votebanned[$this->serverchannel]['banserver'];
$banid = $this->votebanned[$this->serverchannel]['id'];
$host = $this->votebanned[$this->serverchannel]['banhost'];
$banchannel = $this->serverchannel;
$results = mysql_query("SELECT `vote` FROM `bot_vote` WHERE `channel` = '".$banchannel."' AND `server` = '".$banserver."'");
while ($result = mysql_fetch_assoc($results))
{
$num += $result['vote'];
}
if (!$num)
{
$num = 0;
}
$num3 = mysql_num_rows($results);
if ($num3 != 0)
{
$num2 = $num3 - $num;
$this->send("PRIVMSG ".$banchannel." :Votes Yes - ".$num." Votes No - ".$num2);
$num4 = $num / $num3 * 100;
}
else
{
$num4 = 0;
$num3 = 0;
$num2 = 0;
$num = 0;
}
if($num4 >= 75 AND $num3 >= 3)
{
mysql_query("UPDATE `".$this->database_name."`.`bot_voteban` SET `active` = '0', `yes` = '".$num."', `no` = '".$num2."', `result` = '1', `at` = '".gmmktime()."' WHERE `bot_voteban`.`id` = ". $banid);
$this->logs("VOTEBAN SUCCESS ON " . $banchannel . " AGAINST " . $banuser . " FOR ".$bantime." - Votes Yes - ".$num." Votes No - ".$num2);
$this->send("PRIVMSG ".$banchannel." :".$banuser." will be banned for ".$bantime." seconds");
$bantimes = $bantime + gmmktime();
$this->logs("4MODE ".$banchannel." +b ".$host);
$this->logs("4KICK ".$banchannel." ".$banuser." :Vote Ban was a success");
$this->send("MODE ".$banchannel." +b ".$host);
$this->send("KICK ".$banchannel." ".$banuser." :Vote Ban was a success");
mysql_query("INSERT INTO `" . $this->database_name . "`.`bot_banlist` (`id` ,`user` ,`host` ,`channel` ,`server` ,`time`)VALUES (NULL , '" . $banuser . "', '" . $host . "', '" . $banchannel . "', '" . $banserver . "', '" . $bantimes . "')");
}
else
{
mysql_query("UPDATE `".$this->database_name."`.`bot_voteban` SET `active` = '0', `yes` = '".$num."', `no` = '".$num2."', `result` = '0', `at` = '".gmmktime()."' WHERE `bot_voteban`.`id` = ". $banid);
$this->logs("VOTEBAN FAILED ON " . $banchannel . " AGAINST " . $banuser . " - Votes Yes - ".$num." Votes No - ".$num2);
$this->send("PRIVMSG ".$banchannel." :".$banuser." will not be banned");
}
mysql_query("DELETE FROM `bot_vote` WHERE `bot_vote`.`channel` = '".$banchannel."' AND `bot_vote`.`server` = '".$banserver."'");
}

function voteban(){
if($this->voteban[$this->serverchannel]['num'] == 0 AND $this->votekick[$this->serverchannel]['num'] == 0)
{
mysql_query("DELETE FROM `bot_vote` WHERE `bot_vote`.`channel` = '".$this->serverchannel."' AND `bot_vote`.`server` = '".$this->serveraddress."'");
$this->logs($this->data['from']." REQUESTED 'VOTEBAN' ON " . $this->serverchannel . " FOR ". $this->data['message_target']);
$this->isregistered();
$user_level1 = $this->global_level();
$user_level2 = $this->user_level();
if($user_level1 >= $user_level2)
{
$user_level = $user_level1;
}
else
{
$user_level = $user_level2;
$this->global_user = 0;
}
$from = $this->data['from'];
$this->data['from'] = $this->data['message_target'];
$this->data['fullhost'] = $this->fullhost($this->data['message_target']);
$this->isregistered();
$user_level1 = $this->global_level();
$user_level2 = $this->user_level();
$this->data['from'] = $from;
if($user_level1 >= $user_level2)
{
$targetuser_level = $user_level1;
}
else
{
$targetuser_level = $user_level2;
$global_targetuser = 0;
}
if ($global_targetuser == 1 AND $targetuser_level >= 9 AND $this->global_user != 1)
{
$this->data['message_target'] = $from;
}
elseif($targetuser_level > $user_level)
{
$this->data['message_target'] = $from;
}
if(strtolower($this->data['message_target']) != strtolower($this->botnick)){
$this->votebanned[$this->serverchannel]['banuser'] = $this->data['message_target'];
}else{
$this->votebanned[$this->serverchannel]['banuser'] = $from;
}
$this->votebanned[$this->serverchannel]['banserver'] = $this->serveraddress;
$banuser = $this->votebanned[$this->serverchannel]['banuser'];
$banserver = $this->votebanned[$this->serverchannel]['banserver'];
$banchannel = $this->serverchannel;
$this->voteban[$this->serverchannel]['num'] = 1;
$this->voteban[$this->serverchannel]['time'] = gmmktime() + 15;
$bantime = $this->data['message_target2'];
if(!is_numeric($bantime))
{
$bantime = 300;
}
elseif($bantime>600)
{
$bantime = 600;
}
elseif($bantime<30)
{
$bantime = 30;
}
$this->votebanned[$this->serverchannel]['bantime'] = $bantime;
$this->send("USERHOST ".$banuser);
mysql_close($this->db);
$response = fgets($this->fp, 1024);
$this->database_connect();
$params = explode(" ", $response);
if($params[3] == ':')
{
$this->send("PRIVMSG ".$banchannel." :".$banuser." does not exist");
$this->voteban[$this->serverchannel]['num'] = 0;
}
else
{
$hosts = explode("@", $params[3]);
$host = $hosts[1];
$this->votebanned[$this->serverchannel]['banhost'] = $host;
mysql_query("INSERT INTO `".$this->database_name."`.`bot_voteban` (`id` ,`user` ,`host` ,`channel` ,`server` ,`active` ,`yes` ,`no` ,`result` ,`at` ,`time`)VALUES (NULL , '".$banuser."', '".$host."', '".$banchannel."', '".$banserver."', '1', '', '', '', '', '".$bantime."');");
$id = mysql_insert_id();
$this->votebanned[$this->serverchannel]['id'] = $id;
$this->send("PRIVMSG ".$banchannel." :Vote Ban started Against ".$banuser." for ".$bantime." seconds");
$this->send("PRIVMSG ".$banchannel." :Please use !vote yes and !vote no now");
}
}
else
{
$this->logs("NOT ALLOWED TO !VOTEBAN SINCE VOTE ALREADY GOING ON " . $this->serverchannel . " BY " . $this->data['from']);
$this->send("PRIVMSG ".$this->serverchannel." :A vote is already going");
}
}

function stopvote(){
$banuser = $this->votebanned[$this->serverchannel]['banuser'];
$bantime = $this->votebanned[$this->serverchannel]['bantime'];
$banserver = $this->votebanned[$this->serverchannel]['banserver'];
$banid = $this->votebanned[$this->serverchannel]['id'];
$host = $this->votebanned[$this->serverchannel]['banhost'];
$banchannel = $this->serverchannel;
if($this->votekick[$this->serverchannel]['num'] != 0)
{
mysql_query("UPDATE `".$this->database_name."`.`bot_votekick` SET `active` = '0', `yes` = '0', `no` = '0', `result` = '9', `at` = '".gmmktime()."' WHERE `bot_votekick`.`id` = ". $banid);
$this->logs("3VOTEKICK STOPPED BY ".$this->data['from']." ON ".$this->serverchannel);
$this->send("PRIVMSG ".$banchannel." :Vote Kick Successfully Stopped");
}
elseif($this->voteban[$this->serverchannel]['num'] != 0)
{
mysql_query("UPDATE `".$this->database_name."`.`bot_voteban` SET `active` = '0', `yes` = '0', `no` = '0', `result` = '9', `at` = '".gmmktime()."' WHERE `bot_voteban`.`id` = ". $banid);
$this->logs("3VOTEBAN STOPPED BY ".$this->data['from']." ON ".$this->serverchannel);
$this->send("PRIVMSG ".$banchannel." :Vote Ban Successfully Stopped");
}
else
{
$this->logs("3VOTE STOPPED BY ".$this->data['from']." ON ".$this->serverchannel);
$this->send("PRIVMSG ".$banchannel." :Vote Successfully Stopped");
}
$this->voteban[$this->serverchannel]['num'] = 0;
$this->voteban[$this->serverchannel]['time'] = 0;
$this->votekick[$this->serverchannel]['num'] = 0;
$this->votekick[$this->serverchannel]['time'] = 0;
mysql_query("DELETE FROM `bot_vote` WHERE `bot_vote`.`channel` = '".$banchannel."' AND `bot_vote`.`server` = '".$banserver."'");
$this->votebanned[$this->serverchannel]['banuser'] = 0;
$this->votebanned[$this->serverchannel]['bantime'] = 0;
$this->votebanned[$this->serverchannel]['banserver'] = 0;
$this->votebanned[$this->serverchannel]['id'] = 0;
$this->votebanned[$this->serverchannel]['banhost'] = 0;
}

function roulette(){
$num = $this->roulett[$this->serverchannel];
if (!$this->data['message_target'])
{
if ($num == 0)
{
$num = rand(0,5);
if($num == 5){$num = -4;}
$this->roulett[$this->serverchannel] = $num;
if ($num == 0)
{
$this->logs($this->data['from']." SHOT ON SPIN");
$this->send("PRIVMSG ".$this->data['sent_to']." :*SPINS*/*BANG*");
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']."'s Brains Splattered Everywhere");
$this->send("KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']."'s Brains Splattered Everywhere");
}
else
{
$this->logs($this->data['from']." MISS ON SPIN");
$this->send("PRIVMSG ".$this->data['sent_to']." :*SPINS*/*CLICK*");
}
}
elseif ($num == 1)
{
$this->roulett[$this->serverchannel] = 0;
$this->logs($this->data['from']." SHOT");
$this->send("PRIVMSG ".$this->data['sent_to']." :*BANG*");
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']."'s Brains Splattered Everywhere");
$this->send("KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']."'s Brains Splattered Everywhere");
}
elseif ($num <= 0)
{
$num++;
$this->roulett[$this->serverchannel] = $num;
$this->logs($this->data['from']." MISS");
$this->send("PRIVMSG ".$this->data['sent_to']." :*CLICK*");
}
else
{
$num--;
$this->roulett[$this->serverchannel] = $num;
$this->logs($this->data['from']." MISS");
$this->send("PRIVMSG ".$this->data['sent_to']." :*CLICK*");
}
}
else
{
$this->send("USERHOST ".$this->data['message_target']);
mysql_close($this->db);
$response = fgets($this->fp, 1024);
$this->database_connect();
$params = explode(" ", $response);
if($params[3] == ':')
{
$this->send("PRIVMSG ".$this->data['sent_to']." :".$this->data['message_target']." does not exist");
}
else
{
if ($num == 0)
{
$num = rand(0,5);
if($num == 5){$num = -4;}
$this->roulett[$this->serverchannel] = $num;
if ($num == 0)
{
$this->send("PRIVMSG ".$this->data['sent_to']." :*SPINS*/*BANG*");
$this->logs($this->data['from']." SHOT ".$this->data['message_target']." ON SPIN");
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['message_target']." :".$this->data['from']." Splattered ".$this->data['message_target']."'s Brains Everywhere");
$this->send("KICK ".$this->data['sent_to']." ".$this->data['message_target']." :".$this->data['from']." Splattered ".$this->data['message_target']."'s Brains Everywhere");
}
else
{
$this->send("PRIVMSG ".$this->data['sent_to']." :*SPINS*/*CLICK*");
$this->logs($this->data['from']." FAILED TO SHOOT ".$this->data['message_target']." ON SPIN");
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']." Cheated at Roulette");
$this->send("KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']." Cheated at Roulette");
}
}
elseif ($num == 1)
{
$this->roulett[$this->serverchannel] = 0;
$this->logs($this->data['from']." SHOT ".$this->data['message_target']);
$this->send("PRIVMSG ".$this->data['sent_to']." :*BANG*");
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['message_target']." :".$this->data['from']." Splattered ".$this->data['message_target']."'s Brains Everywhere");
$this->send("KICK ".$this->data['sent_to']." ".$this->data['message_target']." :".$this->data['from']." Splattered ".$this->data['message_target']."'s Brains Everywhere");
}
elseif ($num <= 0)
{
$num++;
$this->roulett[$this->serverchannel] = $num;
$this->logs($this->data['from']." FAILED TO SHOOT ".$this->data['message_target']);
$this->send("PRIVMSG ".$this->data['sent_to']." :*CLICK*");
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']." Cheated at Roulette");
$this->send("KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']." Cheated at Roulette");
}
else
{
$num--;
$this->roulett[$this->serverchannel] = $num;
$this->logs($this->data['from']." FAILED TO SHOOT ".$this->data['message_target']);
$this->send("PRIVMSG ".$this->data['sent_to']." :*CLICK*");
$this->logs("4KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']." Cheated at Roulette");
$this->send("KICK ".$this->data['sent_to']." ".$this->data['from']." :".$this->data['from']." Cheated at Roulette");
}
}
}
}

function get_content($url){
    $ch = curl_init();

    curl_setopt ($ch, CURLOPT_URL, $url);
    curl_setopt ($ch, CURLOPT_HEADER, 0);

    ob_start();

    curl_exec ($ch);
    curl_close ($ch);
    $string = ob_get_contents();

    ob_end_clean();
   
    return $string;    
}

function tinyurl() {
$data = str_ireplace("http://", "", $this->data['message_target']);
if($data){
$tinyurl = $this->get_content("http://tinyurl.com/api-create.php?url=http://".$data);
$this->logs($this->data['from']." REQUESTED 'TINYURL' ON " . $this->serverchannel . " FOR ". $this->data['message_target']);
$this->send("PRIVMSG ".$this->data['sent_to']." :".$tinyurl);
fclose($this->gfp);
}
}

function addport() {
$message = str_replace("'", "''", $this->data['message_action_text_plain']);
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'ADDPORT' ON " . $this->serverchannel);
mysql_query("INSERT INTO `". $this->database_name ."`.`bot_port` (`id` ,`text`)VALUES (NULL , '" . $message . "');");
}

function delport() {
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'DELPORT' ON " . $this->serverchannel . " PORT " . $this->data['message_target']);
mysql_query("DELETE FROM `bot_port` WHERE `bot_port`.`id` = ".$this->data['message_target']." LIMIT 1");
}

function viewport() {
$result = mysql_query("SELECT * FROM `bot_port` WHERE `id` = ".$this->data['message_target']." LIMIT 1");
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'VIEWPORT' ON " . $this->serverchannel . " PORT " . $this->data['message_target']);
if(mysql_num_rows($result) == 0)
{
$this->send("NOTICE ".$this->data['from']." :No Message");
}
else
{
$row = mysql_fetch_assoc($result);
if(!$row['text'])
{
$this->send("NOTICE ".$this->data['from']." :No Message Text");
}
else
{
$this->send("NOTICE ".$this->data['from']." :".$row['text']);
}
}
}

function off() {
$this->logs("2".$this->data['from']." USED GLOBAL COMMAND 'OFF' ".$this->data['message_target']." ON ".$this->serverchannel);
$result = mysql_query("UPDATE `". $this->database_name ."`.`bot` SET `on` = '0' WHERE `bot`.`id` = 1");
}

function say(){
$this->isregistered();
$user_level1 = $this->global_level();
$user_level2 = $this->user_level();
if($user_level1 >= $user_level2)
{
$user_level = $user_level1;
}
else
{
$user_level = $user_level2;
}
if($user_level!=10){
$this->data['message_action_text_plain'] = str_replace("`", "", $this->data['message_action_text_plain']);
}
while(fnmatch("*[Dd][Cc][Cc]*" , $this->data['message_action_text_plain']))
{
$this->data['message_action_text_plain'] = str_ireplace("dcc", "", $this->data['message_action_text_plain']);
}
$this->logs($this->data['from']." REQUESTED 'SAY' ON " . $this->serverchannel . " FOR TEXT ". $this->data['message_action_text_plain']);
$this->send("PRIVMSG ".$this->data['sent_to']." :".$this->data['message_action_text_plain']);
}

function help(){
$this->isregistered();
$user_level1 = $this->global_level();
$user_level2 = $this->user_level();
if($user_level1 >= $user_level2)
{
$user_level = $user_level1;
}
else
{
$user_level = $user_level2;
$this->global_user = 0;
}
if(!$this->data['message_target']){ #show main help
$this->logs($this->data['from']." REQUESTED 'HELP' ON " . $this->serverchannel);
if($user_level>=1){$message = "!addquote, !quote, !setjoin, !deljoin, !viewjoin, !userlevel, !login, !logout, !whois, !say";}
elseif($user_level==0){$message = "!addquote, !quote, !setjoin, !deljoin, !viewjoin, !register, !gregister";}
$this->send("PRIVMSG ".$this->data['sent_to']." :!time, !8ball, !base64, !md5, !rot13, !version, !roulette, !help, !votekick, !voteban, !vote, !unvote, !tiny: Use !help <command> for more info");
$this->send("NOTICE ".$this->data['from']." :Additional Commands: " . $message);
if($user_level>=2){
$this->send("NOTICE ".$this->data['from']." :You can also use !help mod for moderator commands");
}
if($this->global_user == 1){
$this->send("NOTICE ".$this->data['from']." :You can also use !help global for global commands");
}
}
else{
switch(strtolower($this->data['message_target'])){
case 'global':
$this->logs($this->data['from']." REQUESTED GLOBAL 'HELP' ON " . $this->serverchannel);
if ($this->global_user == 1)
{
if($user_level==10){$message = 'Global Owner Commands: !deown, !own, !admin, !deadmin, !op, !deop, !hop, !dehop, !voice, !devoice, !stop, !mode, !kick, !rejoin, !topic, !user, !adduser, !removeuser, !lecture, !quit, !restart, !join, !leave, !glogin, !glogout, !guser, !gadduser, !gremoveuser, !addport, !viewport, !delport, !off';}
elseif($user_level==9){$message = 'Global Administrative Commands: !admin, !deadmin, !op, !deop, !hop, !dehop, !voice, !devoice, !stop, !mode, !kick, !rejoin, !topic, !user, !adduser, !removeuser, !lecture, !quit, !restart, !join, !leave, !glogin, !glogout, !guser, !gadduser, !gremoveuser, !addport, !viewport, !delport';}
elseif($user_level==8){$message = 'Global Advanced Operator Commands: !op, !deop, !hop, !dehop, !voice, !devoice, !stop, !mode, !kick, !rejoin, !topic, !user, !adduser, !removeuser, !lecture, !glogin, !glogout';}
elseif($user_level==7){$message = 'Global Operator Commands: !op, !deop, !hop, !dehop, !voice, !devoice, !stop, !mode, !kick, !rejoin, !topic, !user, !adduser, !removeuser, !lecture, !glogin, !glogout';}
elseif($user_level==6){$message = 'Global Advanced Half-Operator Commands: !hop, !dehop, !voice, !devoice, !stop, !kick, !rejoin, !topic, !user, !adduser, !removeuser, !lecture, !glogin, !glogout';}
elseif($user_level==5){$message = 'Global Half-Operator Commands: !voice, !devoice, !kick, !topic, !user, !adduser, !removeuser, !lecture, !glogin, !glogout';}
elseif($user_level==4){$message = 'Global Advanced Voice Commands: !voice, !devoice, !user, !adduser, !removeuser, !glogin, !glogout';}
elseif($user_level==3){$message = 'Global Voice Commands: !voice, !devoice, !user, !adduser, !removeuser, !glogin, !glogout';}
elseif($user_level==2){$message = 'Global Advanced User Commands: !glogin, !glogout, !user, !adduser, !removeuser';}
elseif($user_level==1){$message = 'Global User Commands: !glogin, !glogout';}
$this->send("NOTICE ".$this->data['from']." :" . $message);
}
break;
case 'mod':
$this->logs($this->data['from']." REQUESTED MODERATOR 'HELP' ON " . $this->serverchannel);
if($user_level==10){$message = 'Owner Commands: !deown, !own, !admin, !deadmin, !op, !deop, !hop, !dehop, !voice, !devoice, !stop, !mode, !kick, !rejoin, !topic, !user, !adduser, !removeuser, !leave';}
elseif($user_level==9){$message = 'Administrative Commands: !admin, !deadmin, !op, !deop, !hop, !dehop, !voice, !devoice, !stop, !mode, !kick, !rejoin, !topic, !user, !adduser, !removeuser, !leave';}
elseif($user_level==8){$message = 'Advanced Operator Commands: !op, !deop, !hop, !dehop, !voice, !devoice, !stop, !mode, !kick, !rejoin, !topic, !user, !adduser, !removeuser';}
elseif($user_level==7){$message = 'Operator Commands: !op, !deop, !hop, !dehop, !voice, !devoice, !stop, !mode, !kick, !rejoin, !topic, !user, !adduser, !removeuser';}
elseif($user_level==6){$message = 'Advanced Half-Operator Commands: !hop, !dehop, !voice, !devoice, !stop, !kick, !rejoin, !topic, !user, !adduser, !removeuser';}
elseif($user_level==5){$message = 'Half-Operator Commands: !voice, !devoice, !kick, !topic, !user, !adduser, !removeuser';}
elseif($user_level==4){$message = 'Advanced Voice Commands: !voice, !devoice, !user, !adduser, !removeuser';}
elseif($user_level==3){$message = 'Voice Commands: !voice, !devoice, !user, !adduser, !removeuser';}
elseif($user_level==2){$message = 'Advanced User Commands: !user, !adduser, !removeuser';}
elseif($user_level==1){$message = 'You do not get Moderator Commands';}
elseif($user_level==0){$message = 'You need to register first';}
elseif($user_level==-1){$message = 'You need to login first';}
$this->send("NOTICE ".$this->data['from']." :" . $message);
break;
case 'addport':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!addport <text>: To add <text> as a message from Spacedock_Port - 'ADDPORT' PM COMMAND");}
}
break;
case 'off':
if ($this->global_user == 1)
{
if($user_level>=10){$this->send("NOTICE ".$this->data['from']." :!off: To turn both " . $this->botnick . " and Spacedock_port off - 'OFF' PM COMMAND");}
}
break;
case 'viewport':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!viewport <id>: To view that message from Spacedock_Port - 'VIEWPORT' PM COMMAND");}
}
break;
case 'delport':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!delport <id>: To delete that message from Spacedock_Port - 'DELPORT' PM COMMAND");}
}
break;
case 'join':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!join <channel>: To make bot join another channel - 'JOIN' PM COMMAND");}
}
break;
case 'guser':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!guser <nick> <level>: Modify Global user list - 'USER' PM COMMAND");}
}
break;
case 'gadduser':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!gadduser <nick> <level>: Add to Global user list - 'ADDUSER' PM COMMAND");}
}
break;
case 'gremoveuser':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!gremoveuser <nick>: To remove user from Global user list - 'REMOVEUSER' PM COMMAND");}
}
break;
case 'gregister':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!gregister: To register your current nick into the bot's global database - 'REGISTER' PM COMMAND");}
}
break;
case 'leave':
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!leave <channel>: To make bot leave a channel - 'LEAVE' PM COMMAND");}
break;
case 'quit':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!quit: To make bot leave server - 'QUIT' PM COMMAND");}
}
break;
case 'restart':
if ($this->global_user == 1)
{
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!restart: To make bot quit and rejoin server - 'RESTART' PM COMMAND");}
}
break;
case 'own':
if($user_level>=10){$this->send("NOTICE ".$this->data['from']." :!own <nick>: To give <nick> Owner Status");}
break;
case 'deown':
if($user_level>=10){$this->send("NOTICE ".$this->data['from']." :!deown <nick>: To remove Owner Status from <nick>");}
break;
case 'admin':
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!admin <nick>: To give <nick> Administrative Status");}
break;
case 'deadmin':
if($user_level>=9){$this->send("NOTICE ".$this->data['from']." :!deadmin <nick>: To remove Administrative Status from <nick>");}
break;
case 'op':
if($user_level>=7){$this->send("NOTICE ".$this->data['from']." :!op <nick>: To give <nick> Operator Status");}
break;
case 'deop':
if($user_level>=7){$this->send("NOTICE ".$this->data['from']." :!deop <nick>: To remove Operator Status from <nick>");}
break;
case 'mode':
if($user_level>=7){$this->send("NOTICE ".$this->data['from']." :!mode <mode>: To set a channel mode");}
break;
case 'hop':
if($user_level>=6){$this->send("NOTICE ".$this->data['from']." :!hop <nick>: To give <nick> Half-Operator Status");}
break;
case 'dehop':
if($user_level>=6){$this->send("NOTICE ".$this->data['from']." :!dehop <nick>: To remove Half-Operator Status from <nick>");}
break;
case 'rejoin':
if($user_level>=6){$this->send("NOTICE ".$this->data['from']." :!rejoin: Makes bot rejoin channel");}
break;
case 'stop':
if($user_level>=6){$this->send("NOTICE ".$this->data['from']." :!stop: To stop anything currently running - Example: Votekick, Voteban");}
break;
case 'kick':
if($user_level>=5){$this->send("NOTICE ".$this->data['from']." :!kick <nick>: To kick <nick> from channel");}
break;
case 'lecture':
if ($this->global_user == 1)
{
if($user_level>=5){$this->send("NOTICE ".$this->data['from']." :!lecture <filename|pause|stop|start>: To start a log of what you say - 'LECTURE' PM COMMAND");}
}
break;
case 'voice':
if($user_level>=3){$this->send("NOTICE ".$this->data['from']." :!voice <nick>: To give <nick> Voice Status");}
break;
case 'devoice':
if($user_level>=3){$this->send("NOTICE ".$this->data['from']." :!devoice <nick>: To remove Voice Status from <nick>");}
break;
case 'user':
if($user_level>=2){$this->send("NOTICE ".$this->data['from']." :!user <nick> <level>: To change <nick> to <level>");}
break;
case 'adduser':
if($user_level>=2){$this->send("NOTICE ".$this->data['from']." :!user <nick> <level>: To add <nick> to <level>");}
break;
case 'removeuser':
if($user_level>=2){$this->send("NOTICE ".$this->data['from']." :!user <nick>: To remove <nick> from channel access");}
break;
case 'userlevel':
if($user_level>=1){$this->send("NOTICE ".$this->data['from']." :!userlevel: To view your current userlevel");}
break;
case 'login':
if($user_level>=1){$this->send("NOTICE ".$this->data['from']." :!login: To make the bot reconize you by current hostname");}
break;
case 'logout':
if($user_level>=1){$this->send("NOTICE ".$this->data['from']." :!logout: To make the bot no longer reconize your hostname");}
break;
case 'addquote':
if($user_level>=0){$this->send("NOTICE ".$this->data['from']." :!addquote <message>: To add <message> to quote database");}
break;
case 'quote':
if($user_level>=0){$this->send("NOTICE ".$this->data['from']." :!quote <user|from|last|number|random> <value>: To view certian quotes, 'NOTE' Last and Random do not need values");}
break;
case 'register':
if($user_level==0){$this->send("NOTICE ".$this->data['from']." :!register: To register your current nick into the bot's database");}
break;
case 'setjoin':
if($user_level>=0){$this->send("NOTICE ".$this->data['from']." :!setjoin <message>: To set <message> to display when your join this channel");}
break;
case 'deljoin':
if($user_level>=0){$this->send("NOTICE ".$this->data['from']." :!deljoin: To remove your join message");}
break;
case 'viewjoin':
if($user_level>=0){$this->send("NOTICE ".$this->data['from']." :!viewjoin: To view your join message");}
break;
case 'time':
$this->send("PRIVMSG ".$this->data['sent_to']." :!time <+/- time zone>: To find the time at a current time zone");
break;
case 'whois':
$this->send("PRIVMSG ".$this->data['sent_to']." :!whois <nick>: To find who that bot views that nick as");
break;
case '8ball':
$this->send("PRIVMSG ".$this->data['sent_to']." :!8ball <question>: To have 8ball predict the outcome of a <question>");
break;
case 'base64':
$this->send("PRIVMSG ".$this->data['sent_to']." :!base64 <encode|decode> <message>: To base64 <encode> or <decode> a <message>");
break;
case 'md5':
$this->send("PRIVMSG ".$this->data['sent_to']." :!md5 <message>: To md5 encode <message>");
break;
case 'rot13':
$this->send("PRIVMSG ".$this->data['sent_to']." :!rot13 <message>: To rot13 encode <message>");
break;
case 'version':
$this->send("PRIVMSG ".$this->data['sent_to']." :!version: To view current version of this bot");
break;
case 'help':
$this->send("PRIVMSG ".$this->data['sent_to']." :!help <command>: To view all current possible commands");
break;
case 'say':
$this->send("PRIVMSG ".$this->data['sent_to']." :!say <text>: To make " . $this->botnick . " say <text> on the channel");
break;
case 'roulette':
$this->send("PRIVMSG ".$this->data['sent_to']." :!roulette <nick>: To take your chance at IRC Russian Roulette - NOTE <nick> will attempt to shoot someone else");
break;
case 'voteban':
$this->send("PRIVMSG ".$this->data['sent_to']." :!voteban <nick> <time>: To vote ban <nick> for <time> seconds - NOTE no less than 30 seconds and no more than 600");
break;
case 'votekick':
$this->send("PRIVMSG ".$this->data['sent_to']." :!votekick <nick>: To vote kick <nick> from channel");
break;
case 'vote':
$this->send("PRIVMSG ".$this->data['sent_to']." :!vote <yes|maybe|no>: Use during a vote to place your vote");
break;
case 'tiny':
$this->send("PRIVMSG ".$this->data['sent_to']." :!tiny <url>: To have tinyurl make give it a url");
break;
case 'unvote':
$this->send("PRIVMSG ".$this->data['sent_to']." :!unvote: To remove the vote you placed");
break;
}
}
}
}
$bot['log'] = 1;
$bot['rawlog'] = "botlog.txt";
$bot['irclog'] = "irclog.txt";

$bot['botnick'] = "Spacedock";
$bot['botpassword'] = "325dsf4sdgset53221sfdsgdf";
$bot['botident'] = "Spacedock";
$bot['botrealname'] = "Kriegchan.org";
$bot['localhost'] = "localhost";

$bot['serveraddress'] = "irc.partyvan.fm";
$bot['serverport'] = "6667";
$bot['serverchannel'] = "#kriegchan";
$bot['ircport'] = "#Spaceport";

$bot['database_host'] = "localhost";
$bot['database_user'] = "b10_2172556";
$bot['database_password'] = "whatever";
$bot['database_name'] = "b10_2172556_bot";

$mybot = new Spacedock($bot);
?>