<?php
include('inc.php');

refreshUsers();
loadUsers();

function respondFromCache($cacheFile) {
	return;
	if(file_exists($cacheFile)) {
		readfile($cacheFile);
		die();
	}
}

$isBot = array_key_exists('HTTP_USER_AGENT', $_SERVER) && preg_match('/(bot|spider|crawl|slurp)/i', $_SERVER['HTTP_USER_AGENT']);


if(array_key_exists('timestamp', $_GET)) {

	// Check the cache
	$cacheFile = dirname(__FILE__).'/cache/'.intval($_GET['timestamp']).'.json';

	$timestamp = $_GET['timestamp'];
	
	$current = false;
	$prev = false;
	$next = false;

	if(file_exists($cacheFile)) {
		$cache = json_decode(file_get_contents($cacheFile), true);
		$current = $cache['current'];
		$next = $cache['next'];
		$prev = $cache['prev'];
	} else {
		
		$query = db()->prepare('SELECT id,timestamp,line,type,nick,day FROM irclog WHERE channel="#indiewebcamp" AND timestamp = :timestamp AND hide=0');
		$query->bindParam(':timestamp', $timestamp);
		$query->execute();
		while($q = $query->fetch(PDO::FETCH_ASSOC))
			$current = $q;
		
		// Don't bother retrieving lines around the linked line for crawlers
		$query = db()->prepare('SELECT id,timestamp,line,type,nick,day 
		  FROM irclog 
		  WHERE channel="#indiewebcamp" AND timestamp <= :timestamp AND id < :id 
		    AND hide=0
			ORDER BY timestamp DESC LIMIT 4');
		$query->bindParam(':id', $current['id']);
		$query->bindParam(':timestamp', $timestamp);
		$query->execute();
		while($q = $query->fetch(PDO::FETCH_ASSOC)) 
			$prev[] = $q;
		if($prev)
			$prev = array_reverse($prev);
	
		$query = db()->prepare('SELECT id,timestamp,line,type,nick,day 
		  FROM irclog 
		  WHERE channel="#indiewebcamp" AND timestamp >= :timestamp AND id > :id
		    AND hide=0
			ORDER BY timestamp ASC LIMIT 4');
		$query->bindParam(':id', $current['id']);
		$query->bindParam(':timestamp', $timestamp);
		$query->execute();
		while($q = $query->fetch(PDO::FETCH_ASSOC)) 
			$next[] = $q;
  
    if($next) {
      // only cache if there are future lines
  		file_put_contents($cacheFile, json_encode(array(
  			'current' => $current,
  			'next' => $next,
  			'prev' => $prev
  		)));
		}
	}
	
	$dateTitle = $current['day'];
	$currentDay = $current['day'];
	
} else {
	
	if(array_key_exists('ordinaldate', $_GET)) {
		$date = date('Y-m-d', mktime(0,0,0, 1,$_GET['ordinaldate'],$_GET['ordinalyear']));
		$dateTitle = $_GET['ordinalyear'] . '-' . $_GET['ordinaldate'];
	}
	else {
		$date = $_GET['date'];
		$dateTitle = $date;
	}
	$currentDay = $date;
	
	$tomorrow = date('Y-m-d', strtotime($date)+86400);
	$yesterday = date('Y-m-d', strtotime($date)-86400);
	
	if(strtotime($date)+86400 > time())
		$tomorrow = false;
	
	#$cacheFile = dirname(__FILE__).'/cache/'.intval($_GET['timestamp']).'.html';
	#respondFromCache($cacheFile);
	
	$logs = db()->prepare('SELECT * FROM irclog WHERE channel="#indiewebcamp" AND timestamp >= :min AND timestamp < :max AND hide=0 ORDER BY timestamp');
	#$logs->bindParam(':day', $date);
	$logs->bindValue(':min', strtotime($date.' 00:00:00')*1000);
	$logs->bindValue(':max', strtotime($date.' 23:59:59')*1000);
	$logs->execute();

}


if(
  (array_key_exists('HTTP_USER_AGENT', $_SERVER) && preg_match('/(curl)/i', $_SERVER['HTTP_USER_AGENT']))
) {
  include('logs-text.php');
  die();
}

header('Content-Type: text/html; charset=utf-8');
ob_start();
?>
<html>
<head>
	<title>#indiewebcamp <?=$dateTitle?></title>
	<style type="text/css">
	body {
		font-family: "Helvetica Neue", sans-serif;
		font-size: 10pt;
		margin: 0;
		padding: 0;
	}
	a.time {
		color: #999;
	}
	a.hash {
  	color: #BBB;
  	text-decoration: none;
	}
	.line {
  	min-height: 22px;
	}
	.msg-join, .msg-join a {
		color: #bbb;
	}
	.msg-wiki, .msg-wiki a, .msg-wiki a.author {
		color: #7e3db4;
	}
	.msg-twitter.retweet {
		opacity: 0.5;
	}
	.msg-twitter, .msg-twitter a {
		color: #2087e1;
	}
	a {
		color: #222299;
	}
	.nick, a.author {
		color: #a13d3d;
	}
	.msg-twitter a.author {
  	color: #2087e1;
	}
	.author {
  	font-weight: bold;
	}
	.avatar {
    margin-right: 3px;
    margin-top: 1px;
    margin-bottom: 1px;
    display: inline-block;
	}
	.avatar img {
    -webkit-border-radius: 3px;
    -moz-border-radius: 3px;
    border-radius: 3px;
    vertical-align: middle;
	}
	
	.topbar {
		background-color: #e3d5d5;
		margin-bottom: 0px;
		padding: 3px;
	}
	.topbar h2, .topbar h3 {
		float: left;
		margin: 0;
		padding: 0;
		line-height: 26px;
	}
	.topbar h2 {
		margin-right: 20px;
		margin-left: 10px;
	}
	.topbar ul.right {
		float: right;
		list-style-type: none;
		margin: 0;
		padding: 0;
		margin-right: 10px;
	}
	.topbar ul.right li {
		float: left;
		margin-left: 20px;
		line-height: 26px;
		font-size: 15px;
	}
	.topbar .disabled {
		color: #999;
	}
	.clear {
		clear: both;
	}
	
	.logs {
		padding: 10px;
	}
	
	.hilite {
		background-color: #fffdd0;
	}
	
	.skip {
		margin-bottom: 12px;
	}
	#bottom {
		margin-top: 12px;
	}
	
	@media (max-width: 320px) and (orientation: portrait) {
		body {
			width: 100%;
			font-size: 11pt;
		}
		.line {
			margin-bottom: 6px;
		}
	}
	
	.line:hover {
  	background-color: #fffdeb;
	}
	
	.featured {
		font-size: 130%;
		margin: 20px 0;
	}
	
	</style>
	<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0">
	<link rel="pingback" href="http://webmention.io/indiewebcamp/xmlrpc" />
	<link href="http://webmention.io/indiewebcamp/webmention" rel="webmention" />
</head>
<body>

<div class="topbar">
	<h2><a href="/IRC/logs">#indiewebcamp</a></h2>
	<h3><a href="/irc/<?= $currentDay ?>"><?= $dateTitle ?></a></h3>
	<ul class="right">
                <li>
                  <form action="http://www.google.com/search" method="get" style="margin-bottom: 0;">
                    <input type="text" name="q" placeholder="Search">
                    <input type="submit" value="Search">
                    <input type="hidden" name="as_sitesearch" value="indiewebcamp.com/irc">
                  </form>
                </li>
	<?php if(array_key_exists('timestamp', $_GET)) { ?>
		<li>
			<?php if($prev): 
			  // special case for multiple lines in the same second
			  $p = $prev[count($prev)-1];
			  if($p['timestamp'] == $current['timestamp'] && array_key_exists(count($prev)-2, $prev))
			    $p = $prev[count($prev)-2];
			?>
				<a href="/irc/<?= $p['day'] ?>/line/<?= $p['timestamp'] ?>" rel="prev">Back</a>
			<?php else: ?>
				<span class="disabled">Back</span>
			<?php endif; ?>
		</li>
		<li>
			<?php if($next): $n = $next[0]; ?>
				<a href="/irc/<?= $n['day'] ?>/line/<?= $n['timestamp'] ?>" rel="next">Next</a>
			<?php else: ?>
				<span class="disabled">Next</span>
			<?php endif; ?>
		</li>
	<?php } else { ?>
		<li>
			<?php if($yesterday): ?>
				<a href="./<?= $yesterday ?>" rel="prev">Prev</a>
			<?php else: ?>
				<span class="disabled">Prev</span>
			<?php endif; ?>
		</li>
		<li>
			<?php if($tomorrow): ?>
				<a href="./<?= $tomorrow ?>" rel="next">Next</a>
			<?php else: ?>
				<span class="disabled">Next</span>
			<?php endif; ?>
		</li>
	<?php } ?>
	</ul>
	<div class="clear"></div>
</div>

<div class="logs">
	<?php if(array_key_exists('timestamp', $_GET)) { ?>
		<?php
		$lines = 1;
		
		if(!$isBot && $prev) {
			foreach($prev as $line) {
  			$lines++;
				echo formatLine($line, false);
			}
		}

		?>
		<div class="featured">
			<?= formatLine($current); ?>
		</div>
		<?php
		
		if(!$isBot && $next) {
			foreach($next as $line) {
  			$lines++;
				echo formatLine($line, false);
			}
		}
		
		?>
	<?php } else { ?>
		<div id="top" class="skip"><a href="#bottom">jump to bottom</a></div>
		<?php
		$lines = 0;
		while($line=$logs->fetch()) {
			echo formatLine($line);
			$lines++;
		}
		?>
		<div id="bottom" class="skip"><a href="#top">jump to top</a></div>
	<?php } ?>
</div>

<script type="text/javascript">
	if(window.location.hash) {
		var n = document.getElementById(window.location.hash.replace('#',''));
		n.classList.add('hilite');
	}
	window.addEventListener("hashchange", function(){
	  var n = document.getElementsByClassName('line');
	  Array.prototype.filter.call(n, function(el){ el.classList.remove('hilite') });
		var n = document.getElementById(window.location.hash.replace('#',''));
		n.classList.add('hilite');
	}, false);
</script>
</body>
</html>
<?php

if($lines == 0) {
  header('HTTP/1.1 404 Not Found');
}

echo ob_get_clean();
