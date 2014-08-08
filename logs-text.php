<?php
header('Content-Type: text/plain; charset=utf-8');

if(array_key_exists('timestamp', $_GET)) {

    if($prev) {
      // special case for multiple lines in the same second
      $p = $prev[count($prev)-1];
      if($p['timestamp'] == $current['timestamp'] && array_key_exists(count($prev)-2, $prev))
        $p = $prev[count($prev)-2];
      header('Link: <http://indiewebcamp.com/irc/' . $p['day'] . '/line/' . $p['timestamp'] . '>; rel="prev"', false);
    }
    
    if($next) {
      $n = $next[0];
      header('Link: <http://indiewebcamp.com/irc/' . $n['day'] . '/line/' . $n['timestamp'] . '>; rel="next"', false);
    }

    formatLineText($current);

} else {  
    
  if($yesterday) {
    header('Link: <http://indiewebcamp.com/irc/' . $yesterday . '>; rel="prev"', false);
  }
  if($tomorrow) {
    header('Link: <http://indiewebcamp.com/irc/' . $tomorrow . '>; rel="next"', false);
  }
  
  while($line=$logs->fetch()) {
    formatLineText($line);
  }

}

function formatLineText($line) {
	echo date('Y-m-d H:i:s', $line['timestamp']) . "\t";
	if($line['type'] == 64) {
  	echo "-->\t";
	} else {
  	echo '<' . $line['nick'] . ">\t";
  }
	echo $line['line'] . "\n";
}
