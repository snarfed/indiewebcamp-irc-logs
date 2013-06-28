<?php
include('inc.php');

refreshUsers();
loadUsers();

if(array_key_exists('timestamp', $_GET)) {

	$timestamp = $_GET['timestamp'];
	
	$current = false;
	$prev = false;
	$next = false;
	
	$query = db()->prepare('SELECT * FROM irclog WHERE channel="#indiewebcamp" AND timestamp = :timestamp AND hide=0');
	$query->bindParam(':timestamp', $timestamp);
	$query->execute();
	foreach($query as $q)
		$current = $q;
	
	$query = db()->prepare('SELECT * FROM irclog WHERE channel="#indiewebcamp" AND timestamp < :timestamp AND hide=0
		ORDER BY timestamp DESC LIMIT 4');
	$query->bindParam(':timestamp', $timestamp);
	$query->execute();
	foreach($query as $q) 
		$prev[] = $q;
	if($prev)
		$prev = array_reverse($prev);

	$query = db()->prepare('SELECT * FROM irclog WHERE channel="#indiewebcamp" AND timestamp > :timestamp AND hide=0
		ORDER BY timestamp ASC LIMIT 4');
	$query->bindParam(':timestamp', $timestamp);
	$query->execute();
	foreach($query as $q) 
		$next[] = $q;
	
	
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
	
	
	$logs = db()->prepare('SELECT * FROM irclog WHERE channel="#indiewebcamp" AND day=:day AND hide=0 ORDER BY datestamp');
	$logs->bindParam(':day', $date);
	$logs->execute();

}

?>
<html>
<head>
	<title>#indiewebcamp <?=$dateTitle?></title>
	<style type="text/css">
	body {
		font-family: courier, courier new, fixed-width;
		font-size: 10pt;
		margin: 0;
		padding: 0;
	}
	a.time {
		color: #999;
	}
	.msg-join, .msg-join a {
		color: #bbb;
	}
	.msg-wiki, .msg-wiki a {
		color: #542b76;
	}
	.msg-twitter, .msg-twitter a {
		color: #345e84;
	}
	a {
		color: #222299;
	}
	.nick, a.author {
		color: #a13d3d;
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
	
	.featured {
		font-size: 130%;
		margin: 20px 0;
	}
	
	</style>
	<meta name="viewport" content="width=device-width,initial-scale=1,user-scalable=0">
	<link rel="pingback" href="http://pingback.me/indiewebcamp/xmlrpc" />
	<link href="http://pingback.me/indiewebcamp/webmention" rel="http://webmention.org/" />
</head>
<body>

<div class="topbar">
	<h2><a href="/IRC#Logs">#indiewebcamp</a></h2>
	<h3><a href="/irc/<?= $currentDay ?>"><?= $dateTitle ?></a></h3>
	<ul class="right">
	<?php if(array_key_exists('timestamp', $_GET)) { ?>
		<li>
			<?php if($prev): $p = $prev[count($prev)-1]; ?>
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
		foreach($prev as $line) {
			echo formatLine($line, false);
		}
		?>
		<div class="featured">
			<?= formatLine($current); ?>
		</div>
		<?php
		foreach($next as $line) {
			echo formatLine($line, false);
		}
		?>
	<?php } else { ?>
		<div id="top" class="skip"><a href="#bottom">jump to bottom</a></div>
		<?php
		while($line=$logs->fetch()) {
			echo formatLine($line);
		}
		?>
		<div id="bottom" class="skip"><a href="#top">jump to top</a></div>
	<?php } ?>
</div>

<script type="text/javascript" src="jquery-1.10.1.min.js"></script>
<script type="text/javascript">
$(function(){
	if(window.location.hash) { // has a # already, convenient :)
		$(window.location.hash).addClass('hilite');
	}
});
</script>
</body>
</html>
