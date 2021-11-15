<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

// *****************************Functions to check verification:*****************************

function verifyName($name)
{ // // alternative regular expression: ^\d+\s+\w+\s+\w+$
    if (preg_match('/^[a-zA-Z0-9 \',\.-]{2,50}$/', $name) != 1) { // no match
        return "The name must be 2-50 characters long made up of apostrophe, letters, digits, space, comma, dot, dash!";
    }
    return TRUE;
}

function verifyUsername($name)
{ // // alternative regular expression: ^\d+\s+\w+\s+\w+$
    if (preg_match('/^[a-zA-Z0-9 ,\.-]{4,30}$/', $name) != 1) { // no match
        return "The username must be 4-30 characters long made up of letters, digits, space, comma, dot, dash!";
    }
    return TRUE;
}

// different regular expression for street: [0-9A-Z]* [0-9A-Z]*$     ^[a-zA-Z]+(?:[\s-][a-zA-Z]+)*$
function verifyAptNo($apt)
{
    if($apt==""){
        return TRUE;
    }
    if (!is_numeric($apt) || $apt < 0 || $apt > 99999999999) {
        return "apartment number should be a number between 0-99999999999";
    }
    return TRUE;
}


// different regular expression for street: [0-9A-Z]* [0-9A-Z]*$     ^[a-zA-Z]+(?:[\s-][a-zA-Z]+)*$
function verifyStreet($street)
{
    if (preg_match('/^[0-9A-Za-z ,\.-]{2,50}$/', $street) != 1) { // no match
        return "Street name is not valid! it should be 2-50 characters long made up of letters, digits , space, comma, dot, dash!";
    }
    return TRUE;
}
function verifyCity($city)
{
    if (preg_match('/^[a-zA-Z]+(?:[\s-][a-zA-Z]+)*$/', $city) != 1) { // no match
        return "City name is not valid! please try again";
    }
    if (strlen($city)<2 || strlen($city)>30) { // no match
        return "City name should be between 2-30 character.";
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
    if ($province == "") return "Province should not be empty";
    if (preg_match('/^(?:AB|BC|MB|N[BLTSU]|ON|PE|QC|SK|YT)*$/', $province) != 1) { //no match
        return "Province should be exactly one of the following:\n \"AB\", \"BC\", \"MB\", \"NB\", \"NL\", \"NT\", \"NS\", \"NU\", \"ON\", \"PE\", \"QC\", \"SK\", \"YT\"";
    }
    return TRUE;
}

    function verifyDescription($description)
    {
        if (preg_match('/^[a-zA-Z0-9\/\ \._\'"!?%*,-<>]{4,1000}$/', $description) != 1) { // no match
            return "Description must be 4-1000 characters long and consist of letters and digits and special characters (. _ ' \" ! - ? % * ,<>).";
        }
        return TRUE;
    }

    function verifyPricing($pricing)
    {
        $expectedFields = ['$', '$$', '$$$', '$$$$'];
        if (!in_array($pricing, $expectedFields)) {
            return "Invalid Pricing Submitted. Must be '$', '$$', '$$$', or '$$$$'";
        }
        return TRUE;
    }
    function verifyPrice($price)
    {
        if (!is_numeric($price) && ($price <= 0 || $price > 999.99)) {
            return "Price must be a number greater than 0 and less than 1000";
        }
        return TRUE;
    }

    
    function verifyCategories($selectedCategories, $expectedCategories)
    {
        if (empty($selectedCategories)) {
            return "You didnt select any categories.";
        }
        if (!array_intersect($selectedCategories, $expectedCategories)) {
            return "Invalid Categories Submitted. Must be one of the input options.";
        }
        return TRUE;
    }

    function verifyUploadedPhoto($photo, &$fileName)
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
    function calculateOrderAmount(array $items, $log): int {
        // Replace this constant with a calculation of the order's amount
        // Calculate the order total on the server to prevent
        // people from directly manipulating the amount on the client
        $total = 0;
        
        foreach ($items as $item) {
            $price = DB::queryFirstField("SELECT price FROM cart_detail WHERE user_id=%i and food_id=%i",$_SESSION['user']['id'], $item['id']);
            $total += $price;
        }
        $total = (($total + 10) * 1.15);
        //stripe uses cents
        return $total * 100;
    }