<?php

/*
 * Tests visiting the URL with no params
 */

$oururl = 'http://' . $_SERVER['HTTP_HOST'] . '/ws.php';
$realurl = 'http://www.smushit.com/ysmush.it/ws.php';

$realurlhash = sha1($realurl);
$realjsonfile = "$realurlhash.json";
if (file_exists($realjsonfile)) {
    $expects = file_get_contents($realjsonfile);
} else {
    $expects = file_get_contents($realurl);
    file_put_contents($realjsonfile, $expects);
}
$expects = htmlspecialchars($expects);

$actual = file_get_contents($oururl);
$actual = htmlspecialchars($actual);

if ($expects != $actual) {
    echo "<h1>Bad :(</h1>\n";
    echo "Expected: <pre>$expects</pre>\n";
    echo "Actual: <pre>$actual</pre>\n";
} else {
    echo "<h1>Good :)</h1>\n";
}
//eof