<pre>
<?php

/*
 * Tests visiting the URL with fake img param
 */

$oururl = 'http://' . $_SERVER['HTTP_HOST'] . '/ws.php?img=x';
$realurl = 'http://www.smushit.com/ysmush.it/ws.php?img=x';

echo "Real URL: $realurl\n";
echo "Our URL: $oururl\n";

$realurlhash = sha1($realurl);
$realjsonfile = "$realurlhash.json";
if (file_exists($realjsonfile)) {
    $expects = file_get_contents($realjsonfile);
} else {
    $expects = file_get_contents($realurl);
    file_put_contents($realjsonfile, $expects);
}
$expects = htmlspecialchars($expects);

$expects = htmlspecialchars(file_get_contents($realurl));
$actual = htmlspecialchars(file_get_contents($oururl));

if ($expects != $actual) {
    echo "<h1>Bad :(</h1>\n";
    echo "Expected: <pre>$expects</pre>\n";
    echo "Actual: <pre>$actual</pre>\n";
} else {
    echo "<h1>Good :)</h1>\n";
}
//eof