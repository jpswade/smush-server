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
        require('Smushit.lib.php');
        $smushit = new Smushit();
        $response = $smushit->webservice($img, $id);
        //if ($smushit->dbg) { print_r($smushit->dbg); }   
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