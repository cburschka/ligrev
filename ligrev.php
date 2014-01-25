<?php
define("L_DEBUG", 0);
define("L_INFO", 1);
define("L_CAUT", 2);
define("L_WARN", 3);
define("L_AAAA", 4);

// Default error reporting level
define("L_REPORT", L_INFO);


// Take over PHP's error handling, since it's a picky whore sometimes.
function php_error_handler($no, $str, $file, $line) {
  switch ($no) {
    case E_ERROR:
    case E_RECOVERABLE_ERROR:
      l("[PHP] ".$str. " at ".$file.":".$line, L_AAAA);
      die();
      break;
    case E_WARNING:
    case E_PARSE:
      l("[PHP] ".$str. " at ".$file.":".$line, L_WARN);
      break;
    case E_NOTICE:
      l("[PHP] ".$str. " at ".$file.":".$line, L_CAUT);
      break;
    case E_DEPRECATED:
    case E_STRICT:
      l("[PHP] ".$str. " at ".$file.":".$line, L_DEBUG);
      break;
    default:
      l("[PHP] ".$str. " at ".$file.":".$line, L_INFO);
      break;
  }
  return true;
}


// Function to log/echo to the console. Includes timestamp and what-not
function l($text, $level = L_INFO) {
  // get current log time
  $time = date("H:i:s");
  switch ($level) {
    case L_DEBUG:
      $tag = "[\033[0;36mDBUG\033[0m]";
      break;
    case L_INFO:
      $tag = "[\033[0;37mINFO\033[0m]";
    default:
      break;
    case L_CAUT:
      $tag = "[\033[0;33mCAUT\033[0m]";
      break;
    case L_WARN:
      $tag = "[\033[0;31mWARN\033[0m]";
      break;
    case L_AAAA:
      $tag = "[\033[41mAAAA\033[0m]";
      break;
  }
  if ($level >= L_REPORT) echo "[".$time."] ".$tag." ".$text.PHP_EOL;
}

set_error_handler("php_error_handler");

set_include_path(get_include_path().PATH_SEPARATOR.__DIR__.DIRECTORY_SEPARATOR.'lib');

// Hey, let's load some things
l("Reading config.php...");
require_once 'config.php';

l("Loading libraries...");
require_once 'JAXL/jaxl.php';
require_once 'MDB2.php';
require_once 'ligparse.php';
l("[MDB2] Connecting to database server...");
$db =& MDB2::connect($config['db']);
l("[MDB2] Connected to database type ".$db->connected_dsn['phptype']." ".$db->connected_server_info);


l("[JAXL] Loading JAXL and connecting...");
$client = new JAXL($config['jaxl']);

$client->require_xep(array(
	'0045',	// MUC
	'0203',	// Delayed Delivery
	'0199'  // XMPP Ping
));

$rooms = array();
$users = array();

$client->add_cb('on_auth_success', function() {
	global $client, $config, $rooms;
	l("[JAXL] Connected with jid ".$client->full_jid->to_string());
  
  foreach ($config['rooms'] as $id => $jid) {
    $rooms[$id] = new XMPPJid($jid.'/'.$config['botname']);
    var_dump($rooms[$id]);
    l("[JAXL] Joining room ".$rooms[$id]->to_string());
    $client->xeps['0045']->join_room($rooms[$id]);
    l("[JAXL] Joined room ".$rooms[$id]->to_string());
  }
});

$client->add_cb('on_auth_failure', function($reason) {
	global $client;
	$client->send_end_stream();
	l("[JAXL] Auth failure: ".$reason, L_WARN);
});


// Where the magic happens. "Magic" "Happens". I dunno why I type this either.
$client->add_cb('on_groupchat_message', function($stanza) {
	global $client;
	
	$from = new XMPPJid($stanza->from);
	if($from->resource) {
    if(!$stanza->exists('delay', NS_DELAYED_DELIVERY)) {
      l("[JAXL] Rec'd from ".$from->resource." in room ".$from->node.": ".$stanza->body);
      $text = $stanza->body;
      $room = $from->bare;
      $html = '';
      $author = $from->resource;
      
      $is_html = false;
      if (array_key_exists(1, $stanza->childrens)) {
        $is_html = ($stanza->childrens[1]->name == "html");
      }
      if ($is_html)
        $html = $stanza->childrens[1]->to_string();
      
      
      // Is this something ligrev wants to parse?
      if(strpos($text, '/') === 0 || strpos($text, '!') === 0 || strpos($text, ':') === 0) {
        $textParts = explode(' ', $text);
        parseCustomCommands($text, $textParts, $room, $from->resource);
      }
    } else {
      l("[JAXL] Rec'd message (delayed)");
    }
	}
});
$client->add_cb('on_presence_stanza', function($stanza) {
	global $client, $users;
	
	$from = new XMPPJid($stanza->from);
	if($from->resource) {
    $room = $from->bare;
    $author = $from->resource;
    
    // Get x->item
    if($x = $stanza->exists("x", "http://jabber.org/protocol/muc#user")) {
      if($item = $x->exists("item"));
    } else {
      return false;
    }
    
    $ident_jid = $item->attrs['jid'];
    
    if(!array_key_exists($room, $users)) {
      // add the room to the users array
      $users[$room] = array();
    }
    if(array_key_exists($ident_jid, $users[$room])) {
      $users[$room][$ident_jid]['pdn'] = $author;
    } else {
      $users[$room][$ident_jid] = array(
        'pdn' => $author
      );
    }
    
    // If it's a logout, remove the entry from the users table and l(logout)
    $logout = false;
    if(array_key_exists("type", $stanza->attrs)) {
      if($stanza->attrs["type"] == "unavailable") {
        $logout = true;
      }
    }
    if(!$logout) {  
      l("User ".$from->to_string()." has jid ".$ident_jid);
    } else {
      unset($users[$room][$ident_jid]);
      l("User ".$from->to_string()." has logged out.");
    }
	}
});

$client->start();
$db->disconnect();