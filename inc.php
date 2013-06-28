<?php
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
                try {
                        $db = new PDO(PDO_DSN, PDO_USER, PDO_PASS);
                } catch (PDOException $e) {
                        header('HTTP/1.1 500 Server Error');
                        die(json_encode(array('error'=>'database_error', 'error_description'=>'Connection failed: ' . $e->getMessage())));
                }
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
	$text = preg_replace(Regex_URL::$expression, Regex_URL::$replacement, $text);
	$text = preg_replace(Regex_Twitter::$expression, Regex_Twitter::$replacement, $text);
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
	
	if($user) {
		$who = '&lt;<span class="' . ($mf ? 'p-author h-card' : '') . '">'
			. '<a href="' . @$user->properties->url[0] . '" class="author ' . ($mf ? 'p-nickname p-name u-url' : '') . '">' . $line['nick'] . '</a>'
			. '</span>&gt;';
	} else {
		$who = '&lt;<span class="' . ($mf ? 'p-author h-card' : '') . '">'
			. '<span class="' . ($mf ? 'p-nickname p-name' : '') . '">' . $line['nick'] . '</span>'
			. '</span>&gt;';
	}
			
	$line['line'] = stripIRCControlChars($line['line']);

	if(preg_match('/^\[\[.+\]\].+http.+\*.+\*.*/', $line['line']))
		$line['type'] = 'wiki';
	
	if(preg_match('/^https?:\/\/twitter.com\/([^ ]+) /', $line['line'], $match)) {
		$line['type'] = 'twitter';
		$line['line'] = str_replace(array($match[0].':: ',$match[0]), '', $line['line']);
		$who = '&lt;<a href="http://twitter.com/' . $match[1] . '" class="author ' . ($mf ? 'p-author h-card p-url' : '') . '">@<span class="p-name p-nickname">' . $match[1] . '</span></a>&gt;';
	}

	# TODO: localize the timestamp to the person who spoke
	if($user && property_exists($user->properties, 'tz')) {
		$tz = $user->properties->tz[0];
	} else {
		$tz = 'America/Los_Angeles';
	}
	$date = new DateTime();
	$date->setTimestamp($line['timestamp']);
	$date->setTimezone(new DateTimeZone($tz));

	$url = 'http://' . $_SERVER['SERVER_NAME'] . '/irc/' . date('Y-m-d', $line['timestamp']) . '/line/' . $line['timestamp'];
	echo '<div id="t' . $line['timestamp'] . '" class="' . ($mf ? 'h-entry' : '') . ' line msg-' . $types[$line['type']] . '">';
		echo '<time class="dt-published" datetime="' . $date->format('c') . '">';
			echo '<a href="' . $url . '" class="' . ($mf ? 'u-url' : '') . ' time" >' . date('H:i', $line['timestamp']) . '</a>';
		echo '</time> ';

		if($line['type'] != 64)
			echo '<span class="nick">' . $who . '</span> ';

		echo '<span class="' . ($mf ? 'p-content p-name' : '') . '">' . filterText($line['line']) . '</span>';
	echo "</div>\n";
	
	return ob_get_clean();
}

function refreshUsers() {
	if(filemtime('users.json') < time() - 60) {
		$users = file_get_contents('http://pin13.net/mf2/?url=http%3A%2F%2Findiewebcamp.com%2Firc-people');
		if(trim($users))
			file_put_contents('users.json', $users);
	}
}

$users = array();

function loadUsers() {
	global $users;
	$data = json_decode(file_get_contents('users.json'));
	foreach($data->items as $item) {
		if(in_array('h-card', $item->type)) {
			$users[] = $item;
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

