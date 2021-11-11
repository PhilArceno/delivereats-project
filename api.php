<?php

require_once 'vendor/autoload.php';
require_once 'utils.php';
require_once 'init.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;


$app->group('/api', function (App $app) use ($log) {

    $app->get('/address', function (Request $request, Response $response, array $args) use ($log) {
        $userId = $_SESSION['user']['id'];
        if (!$userId) {
            // NOTE: This should really be 401 code but JS will not cooperate in such case
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed"));
            return $response;
        }

        $addressId = DB::queryFirstField("SELECT address_id FROM user WHERE id=%i", $userId);
        $address = DB::queryFirstRow("SELECT * FROM address WHERE id=%i", $addressId);
        if (!$address) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode("404 - not found"));
            return $response;
        }
        $json = json_encode($address, JSON_PRETTY_PRINT);
        $response->getBody()->write($json);
        return $response;
    });

    $app->put('/address/{id: [0-9]+}', function (Request $request, Response $response, array $args) use ($log) {
        $userId = $_SESSION['user']['id'];
        if (!$userId) {
            // NOTE: This should really be 401 code but JS will not cooperate in such case
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed"));
            return $response;
        }
        $id = $args['id'];
        $json = $request->getBody();
        $address = json_decode($json, TRUE);
        $errorList = [];

        $result = verifyStreet($address['street']);
        if ($result !== TRUE) {
            $errorList['street'] = $result;
        };

        // verify province
        $result = verifyProvince($address['province']);
        if ($result !== TRUE) {
            $errorList['province'] = $result;
        };

        //  postal code validation
        $result = verifyPostalCode($address['postal_code']);
        if ($result !== TRUE) {
            $errorList['postalCode'] = $result;
        };

        $result = verifyCityName($address['city']);
        if ($result !== TRUE) {
            $errorList['city'] = $result;
        };

        if ($errorList) {
            $response = $response->withStatus(400);
            $response->getBody()->write(json_encode($errorList));
            return $response;
        }

        $originalAddress = DB::queryFirstRow("SELECT * FROM address WHERE id=%i", $id);
        if (!$originalAddress) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode("404 - not found"));
            return $response;
        }
        $address['postal_code'] = str_replace(' ', '', $address['postal_code']);
        DB::update('address', $address, "id=%i", $id);
        $log->debug("Record address updated, id=" . $id);
        $count = DB::affectedRows();
        $json = json_encode($count != 0, JSON_PRETTY_PRINT); // true or false
        return $response->getBody()->write($json);
    });
});


$app->get('/cart', function (Request $request, Response $response, array $args) use ($log) {
    $userId = $_SESSION['user']['id'];
    if (!$userId) {
        // NOTE: This should really be 401 code but JS will not cooperate in such case
        $response = $response->withStatus(403);
        $response->getBody()->write(json_encode("403 - authentication failed"));
        return $response;
    }

    $foodList = DB::query("SELECT * FROM cart_detail LEFT JOIN food ON cart_detail.food_id = food.id WHERE cart_detail.user_id = %i;", $userId);
    if (!$foodList) {
        $response = $response->withStatus(404);
        $response->getBody()->write(json_encode("404 - not found"));
        return $response;
    }
    $json = json_encode($foodList, JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response;
});

