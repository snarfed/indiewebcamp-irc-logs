<?php
date_default_timezone_set('America/Los_Angeles');

if(array_key_exists('bookmark', $_GET)) {
	?>
		<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0">
		<a href="/irc/today">Click this, then bookmark</a>
	<?
	die();
}

if(array_key_exists('HTTP_REFERER', $_SERVER) && $_SERVER['HTTP_REFERER'] == 'http://indiewebcamp.com/irc/today?bookmark') {
	?>
	<title>IndieWebCamp IRC</title>
	<meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="apple-touch-icon-precomposed" sizes="57x57" href="/irc/apple-touch-icon-57x57-precomposed.png">
    <link rel="apple-touch-icon-precomposed" sizes="72x72" href="/irc/apple-touch-icon-72x72-precomposed.png">
    <link rel="apple-touch-icon-precomposed" sizes="114x114" href="/irc/apple-touch-icon-114x114-precomposed.png">
    <link rel="apple-touch-icon-precomposed" sizes="144x144" href="/irc/apple-touch-icon-144x144-precomposed.png">
	<p>Bookmark this page or add to your home screen! When you visit it again, it will redirect you to today's logs.</p>
	<?php
	die();
}

header('Location: /irc/' . date('Y-m-d'));

