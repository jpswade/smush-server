<?php

/* Inputs */
$img = isset($_GET['img']) ? urldecode($_GET['img']) : null;
$id = isset($_REQUEST['id']) ? urlencode($_REQUEST['id']) : '';
$task = isset($_REQUEST['task']) ? urlencode($_REQUEST['task']) : '';

/* Variables */
$response = array();

/* Logic */
if ($img || $_FILES) {
    if (filter_var($img, FILTER_VALIDATE_URL)) {
        /* Required */
        require('Smush.lib.php');
        $smush = new Smush();
        $response = $smush->webservice($img, $id);
        //if ($smush->dbg) { print_r($smush->dbg); }   
        $response['id'] = $id;
    }
} else {
    $response['error'] = 'Need an ?img=';
    $response['id'] = $id ? $id : null;
}

/* Response */
header('Content-Type:application/json');
echo json_encode($response ? $response : null);
//eof