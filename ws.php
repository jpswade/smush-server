<?php

/* Settings */
$prefix = 'smush-';
$uploadpath = 'upload';
$resultspath = 'results';

/* Inputs */
$img = isset($_GET['img']) ? urldecode($_GET['img']) : null;

/* Variables */
$response = array();
$res = false;
$uploadpath = realpath($uploadpath) . DIRECTORY_SEPARATOR;
$resultspath = realpath($resultspath) . DIRECTORY_SEPARATOR;

/* Preflight Checks */
if (!is_dir($uploadpath)) {
    user_error('Upload path is invalid');
    die();
}
if (!is_writeable($uploadpath)) {
    user_error('Upload path is NOT writable');
    die();
}
if (!is_dir($resultspath)) {
    user_error('Results path is invalid');
    die();
}
if (!is_writeable($resultspath)) {
    user_error('Results path is NOT writable');
    die();
}

/* Required */
require('Smushit.lib.php');

/* Logic */
if ($img) {
    //web upload
    //check for multiple URLS?
    $matches = array();
    preg_match('/^https?:\/\/.+/i', $img, $matches);
    $match = array_shift($matches);
    $filename = basename($match);
    if (!$filename) {
        $filename = 'image';
    }
    if (!strrchr($filename, '.')) {
        $filename .= '.png';
    }
    $file = uniqid($prefix) . '-' . $filename;
    $srcfile = $uploadpath . $file;
    $res = copy($match, $srcfile);
} elseif (isset($_FILES)) {
    $fileInfo = array_shift($_FILES);
    $fileType = $fileInfo['type'];
    $fileTemp = $fileInfo['tmp_name'];
    $file = uniqid($prefix) . '-' . $fileInfo['name'];
    $srcfile = $uploadpath . $file;
    $res = move_uploaded_file($fileTemp, $srcfile);
} else {
    //no file given
    $response['error'] = 'Need an ?img=';
}

if ($res) {
    $destfile = $resultspath . $file;
    $smushit = new Smushit();
    $response = $smushit->optimize($srcfile, $destfile);
    $response['id'] = isset($_REQUEST['id']) ? urlencode($_REQUEST['id']) : '';
    //print_r($smushit->dbg);
}
header('Content-Type:application/json');
echo json_encode($response ? $response : null);
//eof