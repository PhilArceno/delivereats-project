<?php

require_once 'vendor/autoload.php';

require_once 'init.php';


$app->get('/', function ($request, $response, $args) {
    return $this->view->render($response, 'index.html.twig');
});


// ******************** LOGIN USER ***********************

$app->get('/login', function ($request, $response, $args) {
    return $this->view->render($response, 'login.html.twig');
});

$app->post(
    '/login',
    function ($request, $response, $args) use ($log) {
        $userName = $request->getParam('username');
        $password = $request->getParam('password');

        $record = DB::queryFirstRow("SELECT password FROM user WHERE username=%s", $userName);
        $loginSuccess = false;
        $errorList = [];
        if (password_verify($password, $record['password'])) {
            $loginSuccess = true;
        } else {
            $errorList[] = "Wrong username or password";
        }

        if (!$loginSuccess) {
            $log->debug(sprintf("Login failed for username %s", $userName));
            return $this->view->render($response, 'login.html.twig', ['errorList' => $errorList]);
        } else {
            unset($record['password']); // for security reasons remove password from session
            $_SESSION['user'] = $record; // remember user logged in
            $log->debug(sprintf("Login successful for username %s", $userName));
            return $this->view->render($response, 'index.html.twig', ['userSession' => $_SESSION['user']]);
        }
    }
);


// ************** LOGOUT USER ********************

$app->get('/logout', function ($request, $response, $args) use ($log) {
    $log->debug(sprintf("Logout successful for uid=%d", @$_SESSION['user']['id']));
    unset($_SESSION['user']);
    return $this->view->render($response, 'index.html.twig', ['userSession' => null]);
});

// ************************ PROFILE USER *********************

// $app->get('/profile', function() {

// });


// ************************************************ REGISTER USER ****************************************************
$app->get('/register', function ($request, $response, $args) {
    $apiKey = $_ENV['gMapsAPIKey'];
    return $this->view->render($response, "register.html.twig", ['apiKey' => $apiKey]);
});

$app->post('/register', function ($request, $response, $args) use ($log) {
    $apiKey = $_ENV['gMapsAPIKey'];
    $name = $request->getParam('name');
    $userName = $request->getParam('userName');
    $email = $request->getParam('email');
    $pass1 = $request->getParam('pass1');
    $pass2 = $request->getParam('pass2');
    $address = $request->getParam('address');
    $streetNo = $request->getParam('streetNo');
    $street = $request->getParam('street');
    $appartmentNo = $request->getParam('appartmentNo');
    $postalCode = $request->getParam('postalCode');
    $city = $request->getParam('city');
    $province = $request->getParam('province');
    $phone = $request->getParam('phone');
    $accountType = $request->getParam('accountType');


    //***************************** VALIDATION: *****************************
    $errorList = [];

    // name validation
    $result = verifyName($name);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    // username validation
    $result = verifyUserName($userName);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }
    $result = DB::queryFirstRow("SELECT * FROM user WHERE username=%s", $userName);
    if($result != null){
        $errorList[] = "This username is already registered! Please try another one";
    }

    // email validation    
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorList[] = "Email does not look valid";
        $email = "";
    }
    $result = DB::queryFirstRow("SELECT * FROM user WHERE email=%s", $email);
    if($result != null){
        $errorList[] = "Email is already registered";
    }

     // password validation 
    if ($pass1 != $pass2) {
        $errorList[] = "passwords do not match";
    } else {
        if (
            strlen($pass1) < 6 || strlen($pass1) > 50
            || (preg_match("/[A-Z]/", $pass1) !== 1)
            || (preg_match("/[a-z]/", $pass1) !== 1)
            || (preg_match("/[0-9]/", $pass1) !== 1)
        ) {
            $errorList[] = "Password must be 6-50 characters long and contain at least one "
                . "uppercase letter, one lowercase, and one digit.";
            $pass1 = "";
            $pass2 = "";
        }
    }
    // street format validation
    $result = verifyStreet($address);
    if ($result !== TRUE) {
        $errorList[] = $result;
    };

     // verify province
     $result = verifyProvince($province);
     if ($result !== TRUE) {
         $errorList[] = $result;
    };

    //  postal code validation
    $result = verifyPostalCode($postalCode);
    if ($result !== TRUE) {
        $errorList[] = $result;
    };

    // verify phone number
    $result = verifyPhone($phone);
    if ($result !== TRUE) {
        $errorList[] = $result;
    };

    // verify account type
    $result = verifyAccountType($accountType);
    if ($result !== TRUE) {
        $errorList[] = $result;
    };




    if ($errorList) {
        $valuesList = [
            'name' => $name, 'userName' => $userName, 'email' => $email, 'pass1' => $pass1, 'pass2' => $pass2,
            'streetNo' => $streetNo, 'street' => $street, 'address' => $address, 'appartmentNo' => $appartmentNo,
            'postalCode' => $postalCode, 'city' => $city, 'province' => $province, 'phone' => $phone,
            'accountType' => $accountType
        ];
        $log->debug(sprintf("Error with registration: name=%s, streetNo=%s, street=%s, province=%s, address=%s", $name, $streetNo, $street, $province, $address));
        return $this->view->render($response, "register.html.twig", ['errorList' => $errorList, 'v' => $valuesList, 'apiKey' => $apiKey]);
    } else {
        //  ************************ REGISTRATION DONE **********************
        $password = password_hash($pass1, PASSWORD_DEFAULT);
        $addressValueList = [
            'province' => $province, 'city' => $city, 'street_num' => $streetNo, 'street_name' => $street,
            'apt_num' => $appartmentNo, 'postal_code' => $postalCode
        ];
        DB::insert('address', $addressValueList);
        $addressId = DB::insertId();
        $valuesList = [
            'name' => $name, 'userName' => $userName, 'password' => $password, 'email' => $email,
            'account_type' => $accountType, 'address_id' => $addressId
        ];
        DB::insert('user', $valuesList);
        return $this->view->render($response, "register_success.html.twig");
    }
});

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

// used via AJAX
//$app->get('/isemailtaken/[{email}]', function ($request, $response, $args) {
//  $email = isset($args['email']) ? $args['email'] : "";
//$record = DB::queryFirstRow("SELECT userId FROM user WHERE email=%s", $email);
//if ($record) {
//  return $response->write("Email already in use");
//} else {
//  return $response->write("");
//}
//});






//  ************************ ADD RESTAURANT *********************
$app->get('/add-restaurant', function ($request, $response, $args) {
    $apiKey = $_ENV['gMapsAPIKey'];
    return $this->view->render($response, "add-restaurant.html.twig", ['apiKey' => $apiKey]);
});

$app->post('/add-restaurant', function ($request, $response, $args) use ($log) {
    $apiKey = $_ENV['gMapsAPIKey'];
    $name = $request->getParam('name');
    $description = $request->getParam('description');
    $image = $request->getParam('image');
    $pricing = $request->getParam('pricing');
    $address = $request->getParam('address');
    $streetNo = $request->getParam('streetNo');
    $street = $request->getParam('street');
    $appartmentNo = $request->getParam('appartmentNo');
    $postalCode = $request->getParam('postalCode');
    $city = $request->getParam('city');
    $province = $request->getParam('province');
    $owner_id = $_SESSION['user']['id'];

    $errorList = [];

    $result = verifyName($name);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    function verifyDescription($description)
    {
        if (preg_match('/^[a-zA-Z0-9\ \._\'"!?%*,-]{4,250}$/', $description) != 1) { // no match
            return "Description must be 4-250 characters long and consist of letters and digits and special characters (. _ ' \" ! - ? % * ,).";
        }
        return TRUE;
    }

    $result = verifyDescription($description);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    $hasPhoto = false;
    $mimeType = "";

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedImage = $uploadedFiles['image'];

    // image validation
    $uploadedImage = $request->getUploadedFiles()['image'];
    $destImageFilePath = null;
    $result = verifyUploadedPhoto($uploadedImage, $destImageFilePath);
    if ($result !== TRUE) {
        $errorList []= $result;
    }

    // street format validation
    $result = verifyStreet($address);
    if ($result !== TRUE) {
        $errorList[] = $result;
    };
    //  postal code validation
    $result = verifyPostalCode($postalCode);
    if ($result !== TRUE) {
        $errorList[] = $result;
    };

    if ($errorList) {
        $valuesList = [
            'name' => $name, 'description' => $description, 'image' => $image, 'pricing' => $pricing, 'streetNo' => $streetNo, 'street' => $street, 'address' => $address, 'appartmentNo' => $appartmentNo,
            'postalCode' => $postalCode, 'city' => $city, 'province' => $province
        ];
        $log->debug(sprintf("Error with adding: name=%s, streetNo=%s, street=%s, address=%s", $name, $streetNo, $street, $address));
        return $this->view->render($response, "add-restaurant.html.twig", ['errorList' => $errorList, 'v' => $valuesList, 'apiKey' => $apiKey]);
    } else {
        $addressValueList = [
            'province' => $province, 'city' => $city, 'street_num' => $streetNo, 'street_name' => $street,
            'apt_num' => $appartmentNo, 'postal_code' => $postalCode
        ];
        DB::insert('address', $addressValueList);
        $addressId = DB::insertId();
        $valuesList = [
            'name' => $name, 'description' => $description, 'pricing' => $pricing,
            'owner_id' => $owner_id, 'address_id' => $addressId
        ];
        $uploadedImage->moveTo($destImageFilePath); // FIXME: check if it failed !
        $valuesList['itemImagePath'] = $destImageFilePath;
        DB::insert('restaurant', $valuesList);

        return $this->view->render($response, "add-restaurant-success.html.twig");
    }
});
