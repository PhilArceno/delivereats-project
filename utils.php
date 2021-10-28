<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

// *****************************Functions to check verification:*****************************

function verifyName($name)
{ // // alternative regular expression: ^\d+\s+\w+\s+\w+$
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,100}$/', $name) != 1) { // no match
        return "The name must be 2-100 characters long made up of letters, digits, space, comma, dot, dash!";
    }
    return TRUE;
}

function verifyUserName($userName)
{ // // alternative regular expression: ^\d+\s+\w+\s+\w+$
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{2,100}$/', $userName) != 1) { // no match
        return "The username must be 2-100 characters long made up of letters, digits, space, comma, dot, dash!";
    }
    return TRUE;
}
// different regular expression for street: [0-9A-Z]* [0-9A-Z]*$     ^[a-zA-Z]+(?:[\s-][a-zA-Z]+)*$
function verifyStreet($street)
{
    if (preg_match('/^[0-9A-Za-z ,\.-]{2,100}$/', $street) != 1) { // no match
        return "Street name is not valid! please try again! it should just made up of letters, digits";
    }
    return TRUE;
}
function verifyCityName($city)
{
    if (preg_match('/^[a-zA-Z]+(?:[\s-][a-zA-Z]+)*$/', $city) != 1) { // no match
        return "City name is not valid! please try again";
    }
    return TRUE;
}

function verifyPhone($phone)
{
    if (preg_match('/^(\+\d{1,2}\s)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/', $phone) != 1) { // no match
        return "Phone number must be at least 10 digits long, including the area code.";
    }
    return TRUE;
}

function verifyPostalCode($postalCode)
{
    if (preg_match('/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i', $postalCode) != 1) { //no match
        return "Postal code must be formatted like so: A1B 2C3";
    }
    return TRUE;
}

function verifyAccountType($accountType)
{
    if (!($accountType == 'customer' || $accountType == 'business')) { //no match
        return "Invalid Account Type. Please select Y/N";
    }
    return TRUE;
}

function verifyProvince($province)
{ //   '/^(?:AB|BC|MB|N[BLTSU]|ON|PE|QC|SK|YT)*$/'
    if (preg_match('/^(?:AB|BC|MB|N[BLTSU]|ON|PE|QC|SK|YT)*$/', $province) != 1) { //no match
        return "Province should be exactly one of the following:\n \"AB\", \"BC\", \"MB\", \"NB\", \"NL\", \"NT\", \"NS\", \"NU\", \"ON\", \"PE\", \"QC\", \"SK\", \"YT\"";
    }
    return TRUE;
}


function verifyDescription($description)
    {
        if (preg_match('/^[a-zA-Z0-9\/\ \._\'"!?%*,-<>]{4,250}$/', $description) != 1) { // no match
            return "Description must be 4-250 characters long and consist of letters and digits and special characters (. _ ' \" ! - ? % * ,<>).";
        }
        return TRUE;
    }

    function verifyFoodDescription($description)
    {
        if (preg_match('/^[a-zA-Z0-9\/\ \._\'"!?%*,-<>]{4,250}$/', $description) != 1) { // no match
            return "Description must be 4-250 characters long and consist of letters and digits and special characters (. _ ' \" ! - ? % * ,<>).";
        }
        return TRUE;
    }


    function verifyUploadedFoodPhoto($photo, &$fileName)
    {

        if ($photo->getError() !== UPLOAD_ERR_OK) {
            return "Error uploading photo " . $photo->getError();
        }
        if ($photo->getSize() > 1024 * 1024) { // 1MB max
            return "File too big. 1MB max is allowed";
        }
        $info = getimagesize($photo->file);
        if (!$info) {
            return "File is not an image";
        }
        if ($info[0] < 200 || $info[0] > 1000 || $info[1] < 200 || $info[1] > 1000) {
            return "Width and height must be within 200-1000 pixels range";
        }
        $ext = "";
        switch ($info['mime']) {
            case 'image/jpeg':
                $ext = "jpg";
                break;
            case 'image/gif':
                $ext = "gif";
                break;
            case 'image/png':
                $ext = "png";
                break;
            default:
                return "Only JPG, GIF and PNG file types are allowed";
        }
        $filenameWithoutExtension = pathinfo($photo->getClientFilename(), PATHINFO_FILENAME);
        // Note: keeping the original extension is dangerious and would allow for code injection - very dangerous
        $sanitizedFileName = mb_ereg_replace('([^A-Za-z0-9_-])', '_', $filenameWithoutExtension);
        $fileName = 'uploads/' . $sanitizedFileName . "." . $ext;
        return TRUE;
    }


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