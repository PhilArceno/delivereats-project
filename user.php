<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

$app->get('/', function ($request, $response, $args) {
    $user = null;
    if ($_SESSION) {
        $user = $_SESSION['user'];
        $restaurants = DB::query("SELECT * FROM restaurant");
        $categories = DB::query("SELECT * FROM category");
    }
    return $this->view->render($response, 'index.html.twig', ['userSession' => $user, 'restaurants' => $restaurants, 'categories' => $categories]);
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

$app->get('/account', function ($request, $response, $args) {
    return $this->view->render($response, 'account.html.twig', ['userSession' => $_SESSION['user']]);
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
    //$address = $request->getParam('address');
    //$streetNo = $request->getParam('streetNo');
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
        $log->debug(sprintf("Error with registration: name=%s, street=%s, province=%s, address=%s", $name, $street, $province));
        return $this->view->render($response, "register.html.twig", ['errorList' => $errorList, 'v' => $valuesList, 'apiKey' => $apiKey]);
    } else {
        //  ************************ REGISTRATION DONE **********************
        $password = password_hash($pass1, PASSWORD_DEFAULT);
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
$app->get('/isemailtaken/[{newEmail}]', function ($request, $response, $args) {
    $email = isset($args['email']) ? $args['email'] : "";
    $record = DB::queryFirstRow("SELECT id FROM user WHERE email=%s", $email);
    if ($record) {
        return $response->write("Email already in use");
    } else {
        return $response->write("");
    }
});

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
    $description = strip_tags($description, "<p><ul><li><em><strong><i><b><ol><h3><h4><h5><span>");

    // description validation
    $result = verifyDescription($description);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    // image validation
    $uploadedImage = $request->getUploadedFiles()['image'];
    $destImageFilePath = null;
    $result = verifyUploadedPhoto($uploadedImage, $destImageFilePath);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

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

    if ($errorList) {
        $valuesList = [
            'name' => $name, 'description' => $description, 'image' => $image, 'pricing' => $pricing, 'street' => $street, 'appartmentNo' => $appartmentNo,
            'postalCode' => $postalCode, 'city' => $city, 'province' => $province];
        $log->debug(sprintf("Error with adding: name=%s, street=%s", $name, $street));
        return $this->view->render($response, "add-restaurant.html.twig", ['errorList' => $errorList, 'v' => $valuesList, 'apiKey' => $apiKey]);
    } else {
        $addressValueList = [
            'province' => $province, 'city' => $city, 'street' => $street,
            'apt_num' => $appartmentNo, 'postal_code' => $postalCode
        ];
        DB::insert('address', $addressValueList);
        $addressId = DB::insertId();
        $valuesList = [
            'name' => $name, 'description' => $description, 'pricing' => $pricing,
            'owner_id' => $owner_id, 'address_id' => $addressId
        ];
        $uploadedImage->moveTo($destImageFilePath); // FIXME: check if it failed !
        $valuesList['imageFilePath'] = $destImageFilePath;
        DB::insert('restaurant', $valuesList);

        return $this->view->render($response, "add-restaurant-success.html.twig");
    }
});
//********************************** ADD FOOD *************************************************/
$app->get('/add-food/{id:[0-9]+}', function ($request, $response, $args) {
    return $this->view->render($response, "add-food.html.twig");
});

$app->post('/add-food/{id:[0-9]+}', function ($request, $response, $args) use ($log) {
    $name = $request->getParam('name');
    $price = $request->getParam('price');
    $description = $request->getParam('description');
    $image = $request->getParam('image');
    $owner_id = $_SESSION['user']['id'];
    $errorList = [];

    $result = verifyName($name);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    $description = strip_tags($description, "<p><ul><li><em><strong><i><b><ol><h3><h4><h5><span>");
    // description validation
    $result = verifyDescription($description);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }
    

    // price validation
    if (!is_numeric($price) || $price <= 0 || $price > 999.99) {
        $errorList[] = "price must be a number greater than 0 and less than 999.99";
    }


    // image validation
    $uploadedImage = $request->getUploadedFiles()['image'];
    $destImageFilePath = null;
    $result = verifyUploadedPhoto($uploadedImage, $destImageFilePath);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    if ($errorList) {
        $valuesList = [
            'name' => $name, 'price' => $price, 'description' => $description, 'imageFilePath' => $image
        ];
        $log->debug(sprintf("Error with adding: name=%s, price=%s, description=%s, image=%s", $name, $price, $description, $image));
        return $this->view->render($response, "add-food.html.twig", ['errorList' => $errorList, 'v' => $valuesList]);
    } else {
        $restaurant_id = DB::queryFirstField("SELECT id FROM restaurant WHERE id=%i", $args['id']);
        $valuesList = [
            'name' => $name, 'price' => $price, 'description' => $description, 'imageFilePath' => $image,
            'restaurant_id' => $restaurant_id
        ];
        $uploadedImage->moveTo($destImageFilePath); // FIXME: check if it failed !
        $valuesList['imageFilePath'] = $destImageFilePath;
        DB::insert('food', $valuesList);

        return $this->view->render($response, "add-food-success.html.twig");
    }
});

//********************************** Manage Restaurant *************************************************/

$app->get('/manage-restaurant', function ($request, $response, $args) use ($log)  {
    $restaurantList = DB::query("SELECT * FROM restaurant WHERE owner_id=%i",$_SESSION['user']['id']);
    foreach ($restaurantList as &$restaurant) {
        $fullBodyNoTags = strip_tags($restaurant['description']);
        $preview = mb_strimwidth($fullBodyNoTags, 0, 30, "...");
        $restaurant['description'] = $preview;
    }
    return $this->view->render($response, 'manage-restaurant.html.twig', ['list' => $restaurantList]);
});
// ****************** Browse *********************

$app->get('/browse', function ($request, $response, $args) {
    $records = DB::query("SELECT * FROM category as c");
    return $this->view->render($response, 'browse.html.twig', ['categories' => $records, 'userSession' => $_SESSION['user']]);
});


$app->get('/restaurant/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    $restaurant = DB::queryFirstRow("SELECT * FROM restaurant as r WHERE r.id=%d", $id);

    $food = DB::query("SELECT * FROM food WHERE restaurant_id=%d", $id);
    return $this->view->render($response, 'restaurant.html.twig', ['restaurant' => $restaurant, 'food' => $food]);
});
