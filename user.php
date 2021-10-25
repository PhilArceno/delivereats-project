<?php

require_once 'vendor/autoload.php';

require_once 'init.php';


// **************** REGISTER USER ********************

$app->get('/register', function($request, $response, $args) {
    $apiKey = $_ENV['gMapsAPIKey'];
    return $this->view->render($response, 'register.html.twig', ['apiKey' => $apiKey]);
});

// ******************** LOGIN USER ***********************

$app->get('/login', function($request, $response, $args) {
    return $this->view->render($response, 'login.html.twig');
});

// ************** LOGOUT USER ********************

// $app->get('/logout', function() {

// });

// ************************ PROFILE USER *********************

// $app->get('/profile', function() {

// });


//  ************************ ADD RESTAURANT *********************
$app->get('/add-restaurant', function ($request, $response, $args) {
    return $this->view->render($response, 'add-restaurant.html.twig');
});

//  ************************ RESTAURANT WAS ADDED*********************
$app->get('/add-restaurant-success', function ($request, $response, $args) {
    return $this->view->render($response, 'add-restaurant-success.html.twig');
});