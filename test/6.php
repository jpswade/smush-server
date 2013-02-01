<pre>
<?php

/*
 * Tests visiting the URL with real img with task tag
 */

/* Settings */
$img = 'http://www.smushit.com/ysmush.it/css/skin/screenshot.png';
//$img = 'http://i.imgur.com/K1JjhC1.png';

$realurl = 'http://www.smushit.com/ysmush.it/ws.php?task=myTask&img=' . $img;
$oururl = 'http://' . $_SERVER['HTTP_HOST'] . '/ws.php?task=myTask&img=' . $img;

echo "Real URL: $realurl\n";
echo "Our URL: $oururl\n";

/* Expects */
$realurlhash = sha1($realurl);
$realjsonfile = "$realurlhash.json";
if (file_exists($realjsonfile)) {
    $expects = file_get_contents($realjsonfile);
} else {
    $expects = file_get_contents($realurl);
    file_put_contents($realjsonfile, $expects);
}
$expects = json_decode($expects);
$expects = get_object_vars($expects);
//var_dump($expects);

/* Actual */
$actual = file_get_contents($oururl);
$actual = json_decode($actual);
$actual = get_object_vars($actual);
//var_dump($actual);

/* Check */
$final = 'good';
foreach ($expects as $key => $value) {
    $e = $expects[$key];
    $a = $actual[$key];
    if ($e == $a) {
        $res = ($e == $a) ? 'good' : 'bad';
    } else {
        if (is_numeric($value)) {
            //round by 10
            $res = ((round($e/10) * 10) == (round($e/10) * 10)) ? 'good' : 'bad';
        }
        elseif (is_string($value)) {
            //is it similar?
            similar_text(basename($a), basename($e), $similar);
            $res = ($similar>50) ? 'good' : 'bad';
        } else {
            $res = 'bad';
        }
    }
    if ($res == 'bad') {
        $final = 'bad';
    }
    echo "$key($res): expect($e) actual($a)<br>\n";
}
$e = count($expects);
$a = count($actual);
if ($e != $a) {
    $final = 'bad';
    echo "count(bad): expect($e) actual($a)<br>\n";
}

if ($final == 'bad') {
    echo "<h1>Bad :(</h1>\n";
} else {
    echo "<h1>Good :)</h1>\n";
}
//eof