<?php

require('Smushit.lib.php');

$response = array();
$img = urldecode($_GET["img"]);
$fileInfo = $_FILES["files"];

if (!$img && ($fileInfo == NULL || $fileInfo["error"] != NULL)) {
    $response["code"] = 400;
    $response["error"] = "no file upload";
} else {
    $smushit = new Smushit();

    if ($img) {
        $matches = array();

        if (preg_match("/^https?:\/\/.+/i", $img, $matches)) {

            $filename = preg_replace("/^http.+\//i", "", $img);
            $filename = preg_replace("/\?.+/", "", $filename);
            $filename = preg_replace("/\#.+/", "", $filename);

            if ($filename == "") {
                $filename = "image";
            }
            $ext = strrchr($filename, ".");
            if ($ext == "") {
                $filename = $filename . ".png";
            }

            $file = uniqid("att-") . "-" . $filename;
            $filepath = 'upload/' . $file;
            $res = copy($img, $filepath);
        } else {
            $response["code"] = 400;
            $response["error"] = "img paramter is invalid";
            echo json_encode($response);
            return;
        }
    } else {
        $fileType = $fileInfo["type"];
        $fileTemp = $fileInfo["tmp_name"];
        $file = uniqid("att-") . "-" . $fileInfo["name"];
        $filepath = 'upload/' . $file;
        $res = move_uploaded_file($fileTemp, $filepath);
    }

    if (!$res) {
        $response["code"] = 500;
        $response["error"] = "error occur while save file";
    } else {

        $response = $smushit->optimize($filepath, 'download/' . $file);

        if ($response["error"] != NULL) {
            $response["code"] = 500;
        } else {
            if ($response["src_size"] == $response["dest_size"]) {
                $response["error"] = "No savings.";
                $response["src_size"] = -1;
            }
            $response["code"] = 200;
        }
    }
}
echo json_encode($response);