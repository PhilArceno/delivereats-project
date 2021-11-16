<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

//********************************** Manage Restaurant *************************************************/

$app->get('/restaurants/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    $restaurant = DB::queryFirstRow("SELECT * FROM restaurant as r WHERE r.id=%d", $id);
    if (!$restaurant) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'error-page-not-found.html.twig');
    }
    $food = DB::query("SELECT * FROM food WHERE restaurant_id=%d", $id);
    foreach ($food as &$item) {
        $item['description'] = strip_tags($item['description']);
    }
    $categories = DB::query("SELECT c.id, name, rc.restaurant_id, rc.category_id FROM category as c JOIN restaurant_category as rc WHERE rc.restaurant_id=%i AND c.id=rc.category_id", $restaurant['id']); 
    return $this->view->render($response, 'restaurant.html.twig', ['categories' => $categories, 'restaurant' => $restaurant, 'food' => $food, 'userSession' => $_SESSION['user']]);
});


$app->get('/manage-restaurants', function ($request, $response, $args) use ($log)  {
    if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != "business") { // refuse if user not logged in AS Business Owner
        $response = $response->withStatus(403);
        return $this->view->render($response, 'businessOwner/not-owner.html.twig');
    }
    $restaurantList = DB::query("SELECT * FROM restaurant WHERE owner_id=%i",$_SESSION['user']['id']);
    foreach ($restaurantList as &$restaurant) {
        $fullBodyNoTags = strip_tags($restaurant['description']);
        $preview = mb_strimwidth($fullBodyNoTags, 0, 30, "...");
        $restaurant['description'] = $preview;
    }
    return $this->view->render($response, '/businessOwner/manage-restaurants.html.twig', ['list' => $restaurantList]);
});

//  ************************ ADD RESTAURANT *********************
$app->get('/add-restaurant', function ($request, $response, $args) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != "business") { // refuse if user not logged in AS Business Owner
        $response = $response->withStatus(403);
        return $this->view->render($response, 'businessOwner/not-owner.html.twig');
    }
    $apiKey = $_ENV['gMapsAPIKey'];
    $categories = DB::query("SELECT id, name FROM category");
    $valuesList = [
        'categories' => $categories
    ];
    return $this->view->render($response, "businessOwner/add-restaurant.html.twig", ['apiKey' => $apiKey, 'v' => $valuesList]);
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
    $selectedCategories = $request->getParam('categoriesChecked');
    $expectedCategories = DB::queryFirstColumn("SELECT id from category");

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

    // description validation
    $result = verifyPricing($pricing);
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

    // category validation
    $result = verifyCategories($selectedCategories, $expectedCategories);
    if ($result !== TRUE) {
        $errorList[] = $result;
    };

    if ($errorList) {
        $categories = DB::query("SELECT id, name FROM category");
        $valuesList = [
            'name' => $name, 'description' => $description, 'image' => $image, 'pricing' => $pricing, 'street' => $street, 'appartmentNo' => $appartmentNo,
            'postalCode' => $postalCode, 'city' => $city, 'province' => $province, 
            'selectedCategories' => $selectedCategories, 'categories' => $categories
        ];
        $log->debug(sprintf("Error with adding: name=%s, street=%s, cates=[%s], expectedCates=[%s]", 
            $name, $street, implode(',', $selectedCategories), implode(',', $expectedCategories)
        ));
        return $this->view->render($response, "businessOwner/add-restaurant.html.twig", ['errorList' => $errorList, 'v' => $valuesList, 'apiKey' => $apiKey]);
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
        $restaurantId = DB::insertId();
        foreach ($selectedCategories as &$categoryId) {
            DB::insert('restaurant_category', ['restaurant_id' => $restaurantId, 'category_id' => $categoryId]);
        }
        setFlashMessage("The restaurant has been added successfully. We are glad that you have become a member of our family.");
        return $response->withRedirect("/manage-restaurants");
    }
});

//  ************************ Delete restaurant**********************
$app->get('/restaurants/delete/{id:[0-9]+}', function ($request, $response, $args) {
    $restaurant = DB::queryFirstRow("SELECT * FROM restaurant WHERE id=%d", $args['id']);
    if (!$restaurant) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'error-page-not-found.html.twig');
    }
    return $this->view->render($response, 'businessOwner/restaurant-delete.html.twig', ['v' => $restaurant]);
});

$app->post('/restaurants/delete/{id:[0-9]+}', function ($request, $response, $args) {
    DB::delete('restaurant', "id=%d", $args['id']);
    setFlashMessage("The restaurant has been deleted");
    return $response->withRedirect("/manage-restaurants");
});

//********************************** Update restaurant details *************************************************/

$app->get('/restaurants/update/{id:[0-9]+}', function ($request, $response, $args) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != "business") { // refuse if user not logged in AS Business Owner
        $response = $response->withStatus(403);
        return $this->view->render($response, 'businessOwner/not-owner.html.twig');
    }
    $item = DB::queryFirstRow("SELECT * FROM restaurant WHERE id=%d", $args['id']);
    if (!$item || $item['owner_id'] != $_SESSION['user']['id']) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'error-page-not-found.html.twig');
    }
    return $this->view->render($response, "businessOwner/restaurant-update.html.twig", ['v' => $item]);
});


$app->post('/restaurants/update/{id:[0-9]+}', function ($request, $response, $args) use ($log) {
    $name = $request->getParam('name');
    $description = $request->getParam('description');
    $image = $request->getParam('image');
    
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
 
     if ($errorList) {
        $valuesList = [
            'name' => $name, 'description' => $description];
        $log->debug(sprintf("Error with updating: name=%s", 
            $name));
        return $this->view->render($response, "businessOwner/restaurant-update.html.twig", ['errorList' => $errorList, 'v' => $valuesList]);
    } else {
            $valuesList = [
            'name' => $name, 'description' => $description];
        DB::update('restaurant', $valuesList, "id=%i", $args['id']);
        setFlashMessage("The restaurant has been updated successfully.");
        return $response->withRedirect("/manage-restaurants");
    }
});



//********************************** Food List *************************************************/

$app->get('/food-list/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];
    $restaurant = DB::queryFirstRow("SELECT * FROM restaurant as r WHERE r.id=%d", $id);
    $food = DB::query("SELECT * FROM food WHERE restaurant_id=%d", $id);
    return $this->view->render($response, 'businessOwner/food-list.html.twig', ['restaurant' => $restaurant, 'food' => $food]);
});

//********************************** ADD FOOD *************************************************/
$app->get('/add-food/{id:[0-9]+}', function ($request, $response, $args) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != "business") { // refuse if user not logged in AS Business Owner
        $response = $response->withStatus(403);
        return $this->view->render($response, 'businessOwner/not-owner.html.twig');
    }
    return $this->view->render($response, "businessOwner/add-food.html.twig");
});

$app->post('/add-food/{id:[0-9]+}', function ($request, $response, $args) use ($log) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != "business") { // refuse if user not logged in AS Business Owner
        $response = $response->withStatus(403);
        return $this->view->render($response, 'businessOwner/not-owner.html.twig');
    }
    $name = $request->getParam('name');
    $price = $request->getParam('price');
    $description = $request->getParam('description');
    $image = $request->getParam('image');
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
    $result = verifyPrice($price);
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

    if ($errorList) {
        $valuesList = [
            'name' => $name, 'price' => $price, 'description' => $description, 'imageFilePath' => $image
        ];
        $log->debug(sprintf("Error with adding: name=%s, price=%s, description=%s, image=%s", $name, $price, $description, $image));
        return $this->view->render($response, "businessOwner/add-food.html.twig", ['errorList' => $errorList, 'v' => $valuesList]);
    } else {
        $restaurant_id = DB::queryFirstField("SELECT id FROM restaurant WHERE id=%i", $args['id']);
        $valuesList = [
            'name' => $name, 'price' => $price, 'description' => $description, 'imageFilePath' => $image,
            'restaurant_id' => $restaurant_id
        ];
        $uploadedImage->moveTo($destImageFilePath); // FIXME: check if it failed !
        $valuesList['imageFilePath'] = $destImageFilePath;
        DB::insert('food', $valuesList);
        setFlashMessage("The food item has been added successfully.");
        return $response->withRedirect("/manage-restaurants");
        //return $this->view->render($response, "businessOwner/add-food-success.html.twig");
    }
});


//  ************************ Delete food item**********************
$app->get('/food-delete/{id:[0-9]+}', function ($request, $response, $args) {
    $food = DB::queryFirstRow("SELECT * FROM food WHERE id=%d", $args['id']);
    if (!$food) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'error-page-not-found.html.twig');
    }
    return $this->view->render($response, 'businessOwner/food-delete.html.twig', ['v' => $food]);
});

$app->post('/food-delete/{id:[0-9]+}', function ($request, $response, $args) {
    $food = DB::queryFirstField("SELECT restaurant_id FROM food WHERE id=%d", $args['id']);
    DB::delete('food', "id=%d", $args['id']);
    setFlashMessage("The food item has been deleted");
    return $response->withRedirect("/food-list/" . $food);
});

//  ************************ Edit food item**********************
$app->get('/food-edit/{id:[0-9]+}', function ($request, $response, $args) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != "business") { // refuse if user not logged in AS Business Owner
        $response = $response->withStatus(403);
        return $this->view->render($response, 'businessOwner/not-owner.html.twig');
    }
    $item = DB::queryFirstRow("SELECT * FROM food WHERE id=%d", $args['id']);
    if (!$item) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'error-page-not-found.html.twig');
    }
    return $this->view->render($response, "businessOwner/food-edit.html.twig", ['v' => $item]);
});

$app->post('/food-edit/{id:[0-9]+}', function ($request, $response, $args) use ($log) {
    if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != "business") { // refuse if user not logged in AS Business Owner
        $response = $response->withStatus(403);
        return $this->view->render($response, 'businessOwner/not-owner.html.twig');
    }
    $name = $request->getParam('name');
    $price = $request->getParam('price');
    $description = $request->getParam('description');
    $image = $request->getParam('image');
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
    $result = verifyPrice($price);
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

    if ($errorList) {
        $valuesList = [
            'name' => $name, 'price' => $price, 'description' => $description, 'imageFilePath' => $image
        ];
        $log->debug(sprintf("Error with adding: name=%s, price=%s, description=%s, image=%s", $name, $price, $description, $image));
        return $this->view->render($response, "businessOwner/add-food.html.twig", ['errorList' => $errorList, 'v' => $valuesList]);
    } else {
        $valuesList = [
            'name' => $name, 'price' => $price, 'description' => $description, 'imageFilePath' => $image];
        $uploadedImage->moveTo($destImageFilePath); // FIXME: check if it failed !
        $valuesList['imageFilePath'] = $destImageFilePath;
        DB::update('food', $valuesList, "id=%d", $args['id']);
        setFlashMessage("The food item has been updated successfully.");
        return $response->withRedirect("/manage-restaurants");
    } 
});

