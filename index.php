<?php
require_once 'vendor/autoload.php';
require_once 'init.php';
require_once 'utils.php';
// Define app routes
require_once 'user.php';
require_once 'api.php';
require_once 'businessOwner.php';
require_once 'admin.php';

// Run app
$app->run();