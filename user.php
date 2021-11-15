<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

$app->get('/', function ($request, $response, $args) use ($log) {
    $user = null;
    if ($_SESSION) {
        $user = $_SESSION['user'];
        $restaurants = DB::query("SELECT * FROM restaurant");
        $categories = DB::query("SELECT * FROM category");
        $restaurantCategories = DB::query("SELECT * FROM restaurant_category");
        $log->debug(sprintf("apiKey %s", $_ENV['gMapsAPIKey']));
        return $this->view->render($response, 'index.html.twig', ['userSession' => $user, 'restaurants' => $restaurants, 
        'categories' => $categories, 'rc' => $restaurantCategories, 'apiKey' => $_ENV['gMapsAPIKey']]);
    }
    return $this->view->render($response, 'index.html.twig', []);
});


$app->get('/change-address', function ($request, $response, $args) {
    return $this->view->render($response, 'address-form.html.twig', []);
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

        $record = DB::queryFirstRow("SELECT * FROM user WHERE username=%s", $userName);
        $loginSuccess = false;
        $errorList = [];
        if ($record !== null && password_verify($password, $record['password'])) {
            $loginSuccess = true;
        } else {
            $errorList[] = "Wrong username or password";
        }

        if (!$loginSuccess) {
            $log->debug(sprintf("Login failed for username %s from %s", $userName, $_SERVER['REMOTE_ADDR']));
            return $this->view->render($response, 'login.html.twig', ['errorList' => $errorList]);
        } else {
            unset($record['password']); // for security reasons remove password from session
            $_SESSION['user'] = $record; // remember user logged in
            $log->debug(sprintf("Login successful for username %s, uid=%d, from %s", $userName, $record['id'], $_SERVER['REMOTE_ADDR']));
            //return $this->view->render($response, 'index.html.twig', ['userSession' => $_SESSION['user']]);
            setFlashMessage("Login successful");
            return $response->withRedirect("/");  
        }
    }
);


// ************** LOGOUT USER ********************

$app->get('/logout', function ($request, $response, $args) use ($log) {
    $log->debug(sprintf("Logout successful for uid=%d, from %s", @$_SESSION['user']['id'], $_SERVER['REMOTE_ADDR']));
    unset($_SESSION['user']);
    //return $this->view->render($response, 'index.html.twig', ['userSession' => null]);
    setFlashMessage("You've been logged out");
    return $response->withRedirect("/");
});

// ************************ PROFILE USER *********************

$app->get('/account', function ($request, $response, $args) use ($log) {
    if (!isset($_SESSION['user'])) { // refuse if user not logged in
        $response = $response->withStatus(403);
        return $this->view->render($response, 'error-access-denied.html.twig');
    }
    return $this->view->render($response, 'account.html.twig', ['userSession' => $_SESSION['user']]);
});

$app->get('/profile', function ($request, $response, $args) use ($log) {
    $apiKey = $_ENV['gMapsAPIKey'];
    if (!isset($_SESSION['user'])) { // refuse if user not logged in
        $response = $response->withStatus(403);
        return $this->view->render($response, 'error-access-denied.html.twig');
    }
    return $this->view->render($response, 'profile.html.twig', ['userSession' => $_SESSION['user'],'apiKey' => $apiKey]);
});


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
    $result = verifyUsername($userName);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }
    $result = DB::queryFirstRow("SELECT * FROM user WHERE username=%s", $userName);
    if ($result != null) {
        $errorList[] = "This username is already registered! Please try another one";
    }

    // email validation    
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorList[] = "Email does not look valid";
        $email = "";
    }
    $result = DB::queryFirstRow("SELECT * FROM user WHERE email=%s", $email);
    if ($result != null) {
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

    // appartmentNo format validation
    $result = verifyAptNo($appartmentNo);
    if ($result !== TRUE) {
        $errorList[] = $result;
    };
    // street format validation
    $result = verifyStreet($street);
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
            'street' => $street, 'appartmentNo' => $appartmentNo,
            'postalCode' => $postalCode, 'city' => $city, 'province' => $province, 'phone' => $phone,
            'accountType' => $accountType
        ];
        $log->debug(sprintf("Error with registration: name=%s, street=%s, province=%s, address=%s", $name, $street, $province, $street));
        return $this->view->render($response, "register.html.twig", ['errorList' => $errorList, 'v' => $valuesList, 'apiKey' => $apiKey]);
    } else {
        //  ************************ REGISTRATION DONE **********************
        $password = password_hash($pass1, PASSWORD_DEFAULT);
        $postalCode = str_replace(' ', '', $postalCode);
        $addressValueList = [
            'province' => $province, 'city' => $city, 'street' => $street,
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

// used via AJAX
$app->get('/isemailtaken/{email}', function ($request, $response, $args) {
    $email = isset($args['email']) ? $args['email'] : "";
    $record = DB::queryFirstRow("SELECT id FROM user WHERE email=%s", $email);
    if ($record) {
        $json = json_encode("Email already in use", JSON_PRETTY_PRINT);
        return $response->write($json);
    } else {
        return $response->write(json_encode("", JSON_PRETTY_PRINT));
    }
});



// ****************** Browse *********************

$app->get('/browse', function ($request, $response, $args) {
    $records = DB::query("SELECT * FROM category as c");
    return $this->view->render($response, 'browse.html.twig', ['categories' => $records, 'userSession' => $_SESSION['user']]);
});


// ****************** Browse-->Categories *********************
$app->get('/browse/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    $restaurant = DB::query("SELECT * FROM `restaurant` WHERE `id` IN (SELECT `restaurant_id` FROM `restaurant_category` WHERE `category_id`=%d)", $id);
    return $this->view->render($response, 'browse-category.html.twig', ['restaurant' => $restaurant, 'userSession' => $_SESSION['user']]);
});

// ****************** Cart & checkout *********************

$app->get('/cart', function ($request, $response, $args) {
    return $this->view->render($response, 'cart.html.twig', []);
});

$app->get('/checkout', function ($request, $response, $args) {
    return $this->view->render($response, 'checkout.html.twig', []);
});

$app->get('/checkout-success', function ($request, $response, $args) {
    return $this->view->render($response, 'checkout-success.html.twig', []);
});



// ****************** Order *********************

$app->get('/orders', function ($request, $response, $args) {
    return $this->view->render($response, 'orders.html.twig');
});

// ****************** Not implemented *********************

$app->get('/feature-not-implemented', function ($request, $response, $args) {
    return $this->view->render($response, 'feature-not-implemented.html.twig');
});

