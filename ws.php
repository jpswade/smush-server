<?php

/* Required */
require('Smushit.lib.php');

/* Variables */
$response = array();
$img = urldecode($_GET['img']);
$fileInfo = $_FILES['files'];
$prefix = 'smush-';
$uploadpath = 'upload';
$downloadpath = 'download';

/* Logic */
if (!$img && ($fileInfo == NULL || $fileInfo['error'] != NULL)) {
    $response['error'] = 'Need an ?img=';
} else {
    $smushit = new Smushit();

    if ($img) {
        $matches = array();

        if (preg_match('/^https?:\/\/.+/i', $img, $matches)) {

            $filename = preg_replace('/^http.+\//i', '', $img);
            $filename = preg_replace('/\?.+/', '', $filename);
            $filename = preg_replace('/\#.+/', '', $filename);

            if ($filename == '') {
                $filename = 'image';
            }
            $ext = strrchr($filename, '.');
            if ($ext == '') {
                $filename = $filename . '.png';
            }

            $file = uniqid($prefix) . '-' . $filename;
            $filepath = $uploadpath . DIRECTORY_SEPARATOR . $file;
            $res = $smushit->copy($img, $filepath);
        } else {
            $response['code'] = 400;
            $response['error'] = 'img paramter is invalid';
            echo json_encode($response);
            return;
        }
    } else {
        $fileType = $fileInfo['type'];
        $fileTemp = $fileInfo['tmp_name'];
        $file = uniqid($prefix) . '-' . $fileInfo['name'];
        $filepath = $uploadpath . DIRECTORY_SEPARATOR . $file;
        $res = move_uploaded_file($fileTemp, $filepath);
    }

    if (!$res) {
        $response['code'] = 500;
        $response['error'] = 'error occur while save file';
    } else {

        $path = $downloadpath . DIRECTORY_SEPARATOR;
        $response = $smushit->optimize($filepath, $path . $file);

        if ($response['error'] != NULL) {
            $response['code'] = 500;
        } else {
            if ($response['src_size'] == $response['dest_size']) {
                $response['error'] = 'No savings.';
                $response['src_size'] = -1;
            }
            $response['code'] = 200;
        }
    }
}
$response['id'] = isset($id) ? $id : null;
header('Content-Type:application/json');
echo json_encode($response);
//eof