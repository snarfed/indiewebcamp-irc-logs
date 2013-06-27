<?php
include('inc.php');

$last = db()->prepare('SELECT * FROM irclog WHERE channel="#indiewebcamp" ORDER BY id DESC LIMIT 1');
$last->execute();
$last = $last->fetch();

header('Content-type: application/atom+xml');
echo '<?xml version="1.0" encoding="utf-8"?>', "\n";
?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:activity="http://activitystrea.ms/spec/1.0/" xmlns:ostatus="http://ostatus.org/schema/1.0">
	<title>IndieWebCamp IRC Logs</title>
	<subtitle>IRC Logs</subtitle>
	<link href="http://indiewebcamp.com/irc/feed.atom" rel="self" type="application/atom+xml" />
	<link href="http://indiewebcamp.com/IRC/" rel="alternate" type="text/html" />
	<id>http://indiewebcamp.com/irc/feed.atom</id>
	<updated><?= date('c', $last['timestamp']) ?></updated>
	<link rel="hub" href="http://pubsubhubbub.appspot.com/" />
	<author>
		<activity:object-type>http://activitystrea.ms/schema/1.0/group</activity:object-type>
		<name>IndieWebCamp</name>
		<uri>http://indiewebcamp.com/</uri>
		<link rel="alternate" type="text/html" href="http://indiewebcamp.com/IRC/" />
	</author>
<?php

$types = array(
	2 => 'message',
	64 => 'join',
	'wiki' => 'wiki',
	'twitter' => 'twitter'
);

$logs = db()->prepare('SELECT * FROM irclog WHERE channel="#indiewebcamp" ORDER BY id DESC LIMIT 5');
$logs->execute();

while($line=$logs->fetch()) {
	if($line['type'] == 64) continue;

	$who = $line['nick'];
	$line['line'] = stripIRCControlChars($line['line']);

	if(preg_match('/^\[\[.+\]\].+http.+?\*(.+)\*.*/', $line['line'], $match)) {
		$line['type'] = 'wiki';
		$line['line'] = preg_replace('/^Loqi: /', '', $line['line']);
		$who = trim($match[1]);
	}
	
	if(preg_match('/\[https?:\/\/twitter.com\/([^\]]+)\] /', $line['line'], $match)) {
		$line['type'] = 'twitter';
		$line['line'] = str_replace($match[0], '', $line['line']);
		#$who = '<a href="http://twitter.com/' . $match[1] . '">@' . $match[1] . '</a>';
		$who = $match[1];
	}
	if(preg_match('/https?:\/\/twitter.com\/([^\]]+) :: /', $line['line'], $match)) {
		$line['type'] = 'twitter';
		$line['line'] = str_replace($match[0], '', $line['line']);
		#$who = '<a href="http://twitter.com/' . $match[1] . '">@' . $match[1] . '</a>';
		$who = $match[1];
	}
	?>
	<entry>
		<title><?= $who . ': ' . xmlEscapeText(trimString($line['line'], 100), FALSE) ?></title>
		<link href="http://indiewebcamp.com/irc/<?= $line['day'] . '#t' . $line['timestamp'] ?>" type="text/html" />
		<id>indiewebcamp.com:irc:<?= $line['id'] ?></id>
		<updated><?= date('c', $line['timestamp']) ?></updated>
		<content type="xhtml" xml:space="preserve">
			<div xmlns="http://www.w3.org/1999/xhtml" style="white-space: pre-wrap;"><?= $who . ': ' . xmlEscapeText($line['line']) ?></div>
		</content>
		<author>
			<name><?= $who ?></name>
		</author>
	</entry>
	<?php
}

?>
</feed>