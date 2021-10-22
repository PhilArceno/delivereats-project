<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

function verifyUploadedPhoto($photo, &$fileName) {
    if ($photo->getError() !== UPLOAD_ERR_OK) {
        return "Error uploading photo " . $photo->getError();
    }
    if ($photo->getSize() > 5*1024*1024) {
        return "File too big. 5MB max is allowed.";
    }
    $info = getimagesize($photo->file);
    if ($info[0] < 200 || $info[0] > 1000 || $info[1] < 200 || $info[1] > 1000) {
        return "Width and height must be within 200-1000 pixels range";
    }
    $ext = "";
    switch ($info['mime']) {
        case 'image/jpeg' : $ext = "jpg"; break;
        case 'image/gif' : $ext = "gif";break;
        case 'image/png' : $ext = "png";break;
        case 'image/bmp' : $ext = "bmp";break;
        default:
            return "Only JPG, GIF, PNG, and BMP file types are allowed";
    }
    //Keeping the extension is dangerous and can allow for code injection.
    $filenameWithoutExtension = pathinfo($photo->getClientFilename(), PATHINFO_FILENAME);
    $sanitizedFileName = mb_ereg_replace('([^A-Za-z0-9_-])','_',$filenameWithoutExtension);
    $fileName = $sanitizedFileName . "." . $ext;
    return true;
}