<?php
require_once 'vendor/autoload.php';
require_once 'init.php';
require_once 'utils.php';
// Define app routes
require_once 'api.php';
require_once 'admin.php';
require_once 'user.php';
require_once 'businessOwner.php';

// Run app
$app->run();