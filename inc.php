<?php
date_default_timezone_set('America/Los_Angeles');
require_once('Regex.php');
require_once('php_calendar.php');

define('PDO_DSN', 'mysql:dbname=nerdhaus;host=db.node');
define('PDO_USER', 'cronos');
define('PDO_PASS', 'node');

$types = array(
	2 => 'message',
	64 => 'join',
	'wiki' => 'wiki',
	'twitter' => 'twitter'
);


function db()
{
        static $db;
        if(!isset($db))
        {
                #try {
                        $db = new PDO(PDO_DSN, PDO_USER, PDO_PASS);
                        header('X-DB: true');
                #} catch (PDOException $e) {
                #        header('HTTP/1.1 500 Server Error');
                #        die(json_encode(array('error'=>'database_error', 'error_description'=>'Connection failed: ' . $e->getMessage())));
                #}
        }
        return $db;
}


function filterText($text) {
	/*
	for($i=0; $i<strlen($text); $i++) {
		if(ord($text[$i]) < 32)
			$text[$i] = '';
	}
	*/

	$text = htmlspecialchars($text);
	#$text = mb_encode_numericentity($text);
	$text = preg_replace(Regex_URL::$expression, Regex_URL::$replacement, $text);
	$text = preg_replace(Regex_Twitter::$expression, Regex_Twitter::$replacement, $text);
	$text = preg_replace(Regex_WikiPage::$expression, Regex_WikiPage::$replacement, $text);
	return $text;
}

function xmlEscapeText($text, $autolink=TRUE) {
	# escape the source line of text
	$text = str_replace(array('&','<','>','"'), array('&amp;','&lt;','&gt;','&quot;'), $text);
	
	if($autolink) {
		# add links for URLs and twitter names
		$text = preg_replace(Regex_URL::$expression, Regex_URL::$replacement, $text);
		$text = preg_replace(Regex_Twitter::$expression, Regex_Twitter::$replacement, $text);
	}
	
	return $text;
}

function stripIRCControlChars($text) {
	$text = preg_replace('/\x03\d{1,2}/', '', $text);
	$text = preg_replace('/\x03/', '', $text);
	return $text;
}

function trimString($str, $length, $allow_word_break=false) {
// trims $str to $length characters
// if $str is too long, it puts … on the end
// if $allow_word_break is true, doesn't split a word in the middle

	if( strlen($str) <= $length ) {
		return $str;
	} else {
		if( $allow_word_break ) {
			return trim(substr($str,0,$length-3))."...";
		} else {
			$newstr = substr($str,0,$length-3);
			return substr($newstr, 0, strrpos($newstr, " "))."...";
		}
	}
}

function formatLine($line, $mf=true) {
	global $types;
	ob_start();
	
	$user = userForNick($line['nick']);
	$permalink = false;

  $blank_avatar = '<div class="avatar" style="opacity: .20;"><img src="/irc/user.svg" width="20" height="20"></div>';
  $avatar = $blank_avatar;

	if($user) {
	  if(property_exists($user->properties, 'photo')) {
  	  $avatar = '<div class="avatar"><img src="' . $user->properties->photo[0] . '" width="20" height="20"></div>';
	  }
		$who = $avatar . '<span class="' . ($mf ? 'p-author h-card' : '') . '">'
			. '<a href="' . @$user->properties->url[0] . '" class="author ' . ($mf ? 'p-nickname p-name u-url' : '') . ' target="_blank">' . $line['nick'] . '</a>'
			. '</span>';
	} else {
		$who = $avatar . '<span class="' . ($mf ? 'p-author h-card' : '') . '">'
			. '<span class="' . ($mf ? 'p-nickname p-name' : '') . '">' . $line['nick'] . '</span>'
			. '</span>';
	}
			
	$line['line'] = stripIRCControlChars($line['line']);

  // Wiki edits
	if(preg_match('/^\[\[(?<page>.+)\]\](?: (?<type>[!NM]*|delete))? (?<url>.+) \* (?<user>.+) \* (?:\((?<size>[+-]\d+)\))?(?:deleted)?(?<comment>.*)/', $line['line'], $match)) {
		$line['type'] = 'wiki';
		$user = userForHost($match['user']);

		if($user) {
  	  if(property_exists($user->properties, 'photo')) {
    	  $avatar = '<div class="avatar"><img src="' . $user->properties->photo[0] . '" width="20" height="20"></div>';
  	  }	else {
    	  $avatar = $blank_avatar;
  	  }
  		$who = $avatar . '<span class="' . ($mf ? 'p-author h-card' : '') . '">'
  		  . '<a class="author ' . ($mf ? 'p-nickname p-name' : '') . '" href="http://' . strtolower($match['user']) . '" target="_blank">' . strtolower($match['user']) . '</a>'
  		  . '</span>';
		} else {
  		$who = $blank_avatar . '<span class="' . ($mf ? 'p-author h-card' : '') . '">'
  		  . '<a class="author ' . ($mf ? 'p-nickname p-name' : '') . '" href="http://' . strtolower($match['user']) . '" target="_blank">' . strtolower($match['user']) . '</a>'
  		  . '</span>';
		}

		if(trim($match['url']) == 'delete')
		  $action = 'deleted';
		elseif(strpos($match['type'], 'N') !== false)
		  $action = 'created';
		else
		  $action = 'edited';

		if($action == 'deleted') {
  		if(preg_match('/"\[\[(.+)\]\]": (.+)/', $match['comment'], $dmatch)) {
    		$match['page'] = $dmatch[1];
    		$match['comment'] = $dmatch[2];
  		}
  		$line['diff'] = 'http://indiewebcamp.com/Special:Log/delete';
		} else {
      $line['diff'] = $match['url'];
		}
		
		$match['page'] = str_replace(' ', '_', $match['page']);

		$line['line'] = $action . ' /' . $match['page'] . ($action == 'deleted' ? '' : ' (' . ($match['size']) . ')') . (trim($match['comment']) ? ' "' . trim($match['comment']) . '"' : '');
  }

	// Old twitter citations	
	if(preg_match('/^https?:\/\/twitter.com\/([^ ]+) /', $line['line'], $match)) {
		$line['type'] = 'twitter';
		$line['line'] = str_replace(array($match[0].':: ',$match[0]), '', $line['line']);
		$avatar = '<div class="avatar"><img src="http://twitter.com/' . $match[1] . '/profile_image" width="20"></div>';
		$who = $avatar . '<a href="http://twitter.com/' . $match[1] . '" class="author ' . ($mf ? 'p-author h-card p-url' : '') . '" target="_blank">@<span class="p-name p-nickname">' . $match[1] . '</span></a>';
	}

	// New tweets
	if(preg_match('/\[@([^\]]+)\] (.+) \((http:\/\/twtr\.io\/[^ ]+|https:\/\/twitter\.com\/[^ ]+)\)/', $line['line'], $match)) {
		$line['type'] = 'twitter';
		$line['line'] = $match[2];
		$permalink = $match[3];
		$avatar = '<div class="avatar"><img src="http://twitter.com/' . $match[1] . '/profile_image" width="20"></div>';
		$who = $avatar . '<a href="https://twitter.com/' . $match[1] . '" class="author ' . ($mf ? 'p-author h-card' : '') . '" target="_blank">@<span class="p-name p-nickname">' . $match[1] . '</span></a>';
	}

  // Ugly hack for Loqi ACTIONs
  if($line['nick'] == 'Loqi') {
    if(preg_match('/^ACTION (.+)/', $line['line'], $match)) {
      $line['line'] = $match[1];
    }
  }

	# localize the timestamp to the person who spoke
	if($user && property_exists($user->properties, 'tz')) {
		$tz = $user->properties->tz[0];
	} else {
		$tz = 'America/Los_Angeles';
	}
	$date = new DateTime();
	$date->setTimestamp(round($line['timestamp']/1000));
	try {
		$date->setTimezone(new DateTimeZone($tz));
	} catch(Exception $e) {
		$date->setTimezone(new DateTimeZone('America/Los_Angeles'));
	}

	$url = 'http://' . $_SERVER['SERVER_NAME'] . '/irc/' . date('Y-m-d', round($line['timestamp']/1000)) . '/line/' . $line['timestamp'];
	$urlInContext = 'http://' . $_SERVER['SERVER_NAME'] . '/irc/' . date('Y-m-d', round($line['timestamp']/1000)) . '#t' . $line['timestamp'];
	
	// Different css for retweets
	$classes = array();
	if($line['type'] == 'twitter' && preg_match('/^RT /', $line['line']))
		$classes[] = 'retweet';
	
	echo '<div id="t' . $line['timestamp'] . '" class="' . ($mf ? 'h-entry' : '') . ' line msg-' . $types[$line['type']] . ' ' . implode(' ', $classes) . '">';
	  echo '<a href="' . $urlInContext . '" class="hash">#</a> ';
	
		echo '<time class="dt-published" datetime="' . $date->format('c') . '">';
			echo '<a href="' . $url . '" class="' . ($mf ? 'u-url' : '') . ' time" >' . date('H:i', round($line['timestamp']/1000)) . '</a>';
		echo '</time> ';

		if($line['type'] != 64)
			echo '<span class="nick">' . $who . '</span> ';

		echo '<span class="' . ($mf ? 'e-content p-name' : '') . '">';
			echo filterText($line['line']);
		echo '</span>';
		
		if($line['type'] == 'twitter') {
  		echo ' (<a href="' . $permalink . '" class="u-url" target="_blank">' . preg_replace('/https?:\/\//', '', $permalink) . '</a>)';
		} elseif($line['type'] == 'wiki') {
  		echo ' (<a href="' . $line['diff'] . '" class="u-url" target="_blank">view diff</a>)';
		}
		
	echo "</div>\n";
	
	return ob_get_clean();
}

function refreshUsers() {
	if(filemtime('users.json') < time() - 300) {
		$users = file_get_contents('http://pin13.net/mf2/?url=http%3A%2F%2Findiewebcamp.com%2Firc-people');
		if(trim($users))
			file_put_contents('users.json', $users);
	}
}

$users = array();

function loadUsers() {
	global $users;
	$data = json_decode(file_get_contents('users.json'));
	if(property_exists($data, 'items') && property_exists($data->items[1], 'children')) {
  	foreach($data->items[1]->children as $item) {
  		if(in_array('h-card', $item->type)) {
  			$users[] = $item;
  		}
  	}
	}
}

function userForNick($nick) {
	global $users;

	foreach($users as $u) {
		if(@strtolower($u->properties->nickname[0]) == strtolower($nick)) {
			return $u;
		}
	}
	return null;
}

function userForHost($host) {
  global $users;
  
	foreach($users as $u) {
	  $userHost = property_exists($u->properties, 'url') ? preg_replace('/https?:\/\//','', strtolower($u->properties->url[0])) : false;
		if($userHost && $userHost == strtolower($host)) {
			return $u;
		}
	}
	return null;
}

function debug($thing) {
  if($_SERVER['REMOTE_ADDR'] == '24.21.213.88') {
    var_dump($thing);
  }
}
