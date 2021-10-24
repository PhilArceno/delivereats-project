<?php

require_once 'vendor/autoload.php';

require_once 'init.php';


// **************** REGISTER USER ********************

// $app->get('/register', function() {


// ******************** LOGIN USER ***********************

// $app->get('/login', function() {

// });

// ************** LOGOUT USER ********************

// $app->get('/logout', function() {

// });

// ************************ PROFILE USER *********************

// $app->get('/profile', function() {

// });


// ================================================== Registeration: ================================================
$app->get('/register', function($request,$response,$args) {
    return $this->view->render($response, "register.html.twig");
});

$app->post('/register', function($request,$response,$args) {
    $userName = $request->getParam('userName');
    $email = $request->getParam('email');
    $pass1 = $request->getParam('pass1');
    $pass2 = $request->getParam('pass2');
    //$streetNo = $request->getParam('streetNo');
    $street = $request->getParam('street');
    $appartmentNo = $request->getParam('appartmentNo');
    $postalCode = $request->getParam('postalCode');
    $city = $request->getParam('city');
    $province = $request->getParam('province');
    $phone = $request->getParam('phone');

//========================= validation: =========================
$errorList = [];

// username validation
$result = verifyUserName($userName);
if ($result !== TRUE) {
     $errorList[] = $result; 
    }
// password validation    
if(filter_var($email, FILTER_VALIDATE_EMAIL)=== false){
    $errorList[] = "Email does not look valid"; 
    $email ="";
}
if($pass1 != $pass2){
    $errorList[] = "passwords do not match"; 
} else {
    if(strlen($pass1) <6 || strlen($pass1)>50
        || (preg_match("/[A-Z]/", $pass1) !== 1)
        || (preg_match("/[a-z]/", $pass1) !== 1)
        || (preg_match("/[0-9]/", $pass1) !== 1)
    ){
        $errorList[] = "Password must be 6-50 characters long and contain at least one "
        . "uppercase letter, one lowercase, and one digit.";
    }
}
// street format validation
$result = verifyStreet($street);
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

if($errorList){
    $valuesList = ['userName' => $userName, 'email' => $email, 'pass1' => $pass1, 'pass2' => $pass2,
    'street' => $street, 'appartmentNo' => $appartmentNo, 'postalCode' => $postalCode, 'city' => $city, 'province'=> $province];
    return $this->view->render($response, "register.html.twig", ['errorList' => $errorList, 'v' => $valuesList]);
}else{

   return $this->view->render($response, "register_success.html.twig");
}

});

// =========================Functions to check verification:=========================

function verifyUserName($name) {
    if (preg_match('/^\d+\s+\w+\s+\w+$/', $name) != 1) { // no match
        return "The format of the street is not correct. It should be in this format \"1223 Jerry Street\"";
    }
    return TRUE;
}
function verifyStreet($street) {
    if (preg_match('/^[a-zA-Z]+(?:[\s-][a-zA-Z]+)*$/', $street) != 1) { // no match
        return "City name is not valid! please try again! it should just consist of leeters, space or minus sign";
    }
    return TRUE;
}
function verifyCityName($city) {
    if (preg_match('/^[a-zA-Z]+(?:[\s-][a-zA-Z]+)*$/', $city) != 1) { // no match
        return "City name is not valid! please try again";
    }
    return TRUE;
}

function verifyPostalCode($postalCode) {
    if(preg_match('/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i', $postalCode) != 1) { //no match
        return "Postal code must be formatted like so: A1B 2C3";
    }
    return TRUE;
}

function verifyPhone($phone) {
    if(preg_match('/^\s*(?:\+?(\d{1,3}))?[-. (]*(\d{3})[-. )]*(\d{3})[-. ]*(\d{4})(?: *x(\d+))?\s*$/', $phone) !== 1) { // no match
        return "Phone number must be at least 10 digits long, including the area code.";
    }
    return TRUE;
}





//  ************************ ADD RESTAURANT *********************
$app->get('/add-restaurant', function ($request, $response, $args) {
    return $this->view->render($response, 'add-restaurant.html.twig');
});

//  ************************ RESTAURANT WAS ADDED*********************
$app->get('/add-restaurant-success', function ($request, $response, $args) {
    return $this->view->render($response, 'add-restaurant-success.html.twig');
});
