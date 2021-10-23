<?php

require_once 'vendor/autoload.php';

require_once 'init.php';


// **************** REGISTER USER ********************

// $app->get('/register', function() {

// });

// ******************** LOGIN USER ***********************

// $app->get('/login', function() {

// });

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