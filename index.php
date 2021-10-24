<?php
require_once 'vendor/autoload.php';
require_once 'init.php';
require_once 'utils.php';
// Define app routes
//require_once 'admin.php';
require_once 'user.php';

// Run app
$app->run();