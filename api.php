<?php

require_once 'vendor/autoload.php';
require_once 'utils.php';
require_once 'init.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

\Stripe\Stripe::setApiKey('sk_test_51Jv6wCLkqYXs25lQjH3qEJLO2TwoYnL7H5WJzKdiOFWNPNOzAPj7JKJ7n7c8A4OdRmEfKm7eTWRgiHMxkJebMikX003aHW48c1');


$app->group('/api', function (App $app) use ($log) {
    // User
    $app->put("/user", function (Request $request, Response $response, array $args) use ($log) {
        $userId = $_SESSION['user']['id'];
        if (!$userId) {
            // NOTE: This should really be 401 code but JS will not cooperate in such case
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed"));
            return $response;
        }
        
        $json = $request->getBody();
        $userChanges = json_decode($json, TRUE);
        $expectedFields = ['name', 'email'];
        $submittedFields = array_keys($userChanges);

        if ($diff = array_diff($submittedFields, $expectedFields)) {
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("Invalid fields: [" . implode(',', $diff). "]"));
            return $response;
        }
        if ($diff = array_diff($expectedFields, $submittedFields)) {
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("Missing fields in Todo: [". implode(',', $diff). "]"));
            return $response;
        }

        $errorList = [];

        // name validation
        $result = verifyName($userChanges['name']);
        if ($result !== TRUE) {
            $errorList[] = $result;
        }

        // email validation
        if (filter_var($userChanges['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errorList[] = "Email does not look valid";
        }

        if ($errorList) {
            $log->debug(sprintf("Error with user update: name=%s, email=%s", $userChanges['name'], $userChanges['email']));
            $response = $response->withStatus(400);
            $response->getBody()->write(json_encode($errorList, JSON_PRETTY_PRINT));
            return $response;
        }
        
        DB::update("user", [
            'name' => $userChanges['name'],
            'email' => $userChanges['email']
        ], "id=%i", $userId);
        $log->debug(sprintf("User updated=%s", $userId));
        $_SESSION['user']['email'] = $userChanges['email'];
        $_SESSION['user']['name'] = $userChanges['name'];

        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode("User updated with id=".$userId));
        return $response;
    });

    // Address
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
            $errorList[] = $result;
        };

        // verify province
        $result = verifyProvince($address['province']);
        if ($result !== TRUE) {
            $errorList[] = $result;
        };

        //  postal code validation
        $result = verifyPostalCode($address['postal_code']);
        if ($result !== TRUE) {
            $errorList[] = $result;
        };

        $result = verifyCityName($address['city']);
        if ($result !== TRUE) {
            $errorList[] = $result;
        };

        if ($errorList) {
            $response = $response->withStatus(400);
            $response->getBody()->write(json_encode($errorList, JSON_PRETTY_PRINT));
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

    //cart
    $app->get('/cart', function (Request $request, Response $response, array $args) use ($log) {
        $userId = $_SESSION['user']['id'];
        if (!$userId) {
            // NOTE: This should really be 401 code but JS will not cooperate in such case
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed"));
            return $response;
        }

        $foodList = DB::query("SELECT user_id,food_id,id, name, cd.price,quantity, description,imageFilePath FROM cart_detail as cd LEFT JOIN food as f ON cd.food_id = f.id WHERE cd.user_id = %i;", $userId);
        if (!$foodList) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode("404 - not found"));
            return $response;
        }
        $json = json_encode($foodList, JSON_PRETTY_PRINT);
        $response->getBody()->write($json);
        return $response;
    });

    $app->post('/cart', function (Request $request, Response $response, array $args) use ($log) {
        $userId = $_SESSION['user']['id'];
        if (!$userId) {
            // NOTE: This should really be 401 code but JS will not cooperate in such case
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed", JSON_PRETTY_PRINT));
            return $response;
        }
        $json = $request->getBody();
        $food = json_decode($json, TRUE);

        $addedQuantity = null;
        //check if item exists in cart already
        $cartItem = DB::queryFirstRow("SELECT * FROM cart_detail WHERE user_id=%i and food_id=%i", $userId, $food['id']);
        //get the food's price
        $price = DB::queryFirstField("SELECT price FROM food WHERE id=%i", $food['id']);

        if ($cartItem) {
            $addedQuantity = $cartItem['quantity'] + 1;
            $price = $price + $cartItem['price'];
            DB::insertUpdate(
                'cart_detail',
                ['user_id' => $userId, 'food_id' => $food['id'], 'quantity' => $addedQuantity, 'price' => $price],
                ['quantity' => $addedQuantity, 'price' => $price]
            );
        } else {
            $addedQuantity = 1;
            DB::insert("cart_detail", ['user_id' => $userId, 'food_id' => $food['id'], 'quantity' => $addedQuantity, 'price' => $price]);
        }
        $log->debug("cart detail added for user id=" . $userId . " and food id=" . $food['id']);
        $response = $response->withStatus(201);
        $response->getBody()->write(json_encode("Added successfully", JSON_PRETTY_PRINT));
        return $response;
    });

    $app->delete('/cart/{foodId:[0-9]+}', function (Request $request, Response $response, array $args) use ($log) {
        $userId = $_SESSION['user']['id'];
        $foodId = $args['foodId'];

        if (!$userId) {
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed", JSON_PRETTY_PRINT));
            return $response;
        }

        DB::delete('cart_detail', 'user_id=%i and food_id=%i', $userId, $foodId);
        if (($counter = DB::affectedRows()) == false) {
            $response = $response->withStatus(404);
            $response->getBody()->write(json_encode("404 - not found"));
            return $response;
        } else {
            $response = $response->withStatus(200);
            $response->getBody()->write(json_encode(true));
            return $response;
        }
    });

    $app->put('/cart/{foodId:[0-9]+}', function (Request $request, Response $response, array $args) use ($log) {
        $userId = $_SESSION['user']['id'];

        if (!$userId) {
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed", JSON_PRETTY_PRINT));
            return $response;
        }

        $foodId = $args['foodId'];
        $json = $request->getBody();
        $food = json_decode($json, TRUE);

        if ($food['quantity'] == 0) {
            DB::delete('cart_detail', 'user_id=%i and food_id=%i', $userId, $foodId);
        } else {
            //get the food's price
            $price = DB::queryFirstField("SELECT price FROM food WHERE id=%i", $foodId);
            DB::update("cart_detail", ['quantity' => $food['quantity'], 'price' => ($price * $food['quantity'])], "user_id=%i and food_id=%i", $userId, $foodId);
        }

        $log->debug("Record cart_detail updated, user_id=" . $userId . ", food_id=" . $foodId);
        $count = DB::affectedRows();
        $response = $response->withStatus(200);
        $json = json_encode($count != 0, JSON_PRETTY_PRINT); // true or false
        return $response->getBody()->write($json);
    });

    //stripe
    $app->post('/create-stripe', function (Request $request, Response $response, array $args) use ($log) {
        $userId = $_SESSION['user']['id'];

        if (!$userId) {
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed", JSON_PRETTY_PRINT));
            return $response;
        }

        $json = $request->getBody();
        $jsonObj = json_decode($json, TRUE);

        $paymentIntent = \Stripe\PaymentIntent::create([
            'amount' => calculateOrderAmount($jsonObj['items'], $log),
            'currency' => 'cad',
            'payment_method_types' => [
                'card'
            ],
            'metadata' => [
                'user_id' => $userId
            ]
        ]);
        if (!$paymentIntent) {
            $response = $response->withStatus(400);
            $response->getBody()->write(json_encode("400 - There was an error creating payment intent."));
            return $response;
        }
        $output = [
            'clientSecret' => $paymentIntent->client_secret,
        ];
        $response = $response->withStatus(200);
        $response->getBody()->write(json_encode($output));
        return $response;
    });

    //orders
    $app->get('/orders', function (Request $request, Response $response, array $args) {
        $userId = $_SESSION['user']['id'];

        if (!$userId) {
            $response = $response->withStatus(403);
            $response->getBody()->write(json_encode("403 - authentication failed", JSON_PRETTY_PRINT));
            return $response;
        }
        $orderDetails = DB::query("SELECT order_id,food_id,quantity,od.price,f.name 'foodItem',f.imageFilePath as 'foodImage',restaurant_id, 
            r.name as 'restaurantName', r.imageFilePath as 'restaurantImage',date,order_status,total_price,customer_id FROM order_details as od 
            JOIN food as f JOIN user_order as uo JOIN restaurant r WHERE od.order_id=uo.id AND f.id=od.food_id AND restaurant_id=r.id AND customer_id=%i 
            ORDER BY date DESC", $userId);

        if (!$orderDetails) {
            $response = $response->withStatus(404);
            $response->getBody()->write("404 - Items not found", JSON_PRETTY_PRINT);
            return $response;
        }

        $response = $response->withStatus(200);
        $json = json_encode(['orderDetails' => $orderDetails], JSON_PRETTY_PRINT); // true or false
        return $response->getBody()->write($json);
    });

    $app->post('/order', function (Request $request, Response $response, array $args) use ($log) {
        $endpoint_secret = 'whsec_2n1r3qrXttmwmVhB35L3oQ8QLSNooryx';

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $sig_header,
                $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            http_response_code(400);
            exit();
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            http_response_code(400);
            exit();
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.created':
                // intent has been created
            case 'charge.succeeded':
                // charge went through
                $paymentIntent = $event->data->object;
                // ... handle other event types

                $amount = $paymentIntent['amount'] / 100;

                $cart = DB::query("SELECT * FROM cart_detail WHERE user_id=%i", $paymentIntent['metadata']['user_id']);
                if (!$cart) {
                    $response = $response->withStatus(404);
                    $response->getBody()->write(json_encode("404 - cart not found", JSON_PRETTY_PRINT));
                    return $response;
                }

                DB::insert("user_order", [
                    'date' => date_create('now')->format('Y-m-d'),
                    'order_status' => 'delivered',
                    'total_price' => $amount,
                    'customer_id' => $paymentIntent['metadata']['user_id']
                ]);
                $orderId = DB::insertId();
                
                foreach ($cart as $cd) {
                    DB::insert("order_details", [
                        'order_id' => $orderId, 'food_id' => $cd['food_id'], 'quantity' => $cd['quantity'], 'price' => $cd['price']
                    ]);
                }
                DB::query("DELETE FROM cart_detail WHERE user_id=%i", $paymentIntent['metadata']['user_id']);
                $response = $response->withStatus(201);
                $response->getBody()->write(json_encode($orderId));
                return $response;

            case 'payment_intent.succeeded':
                //payment intent success
            default:
                $log->debug('Received unknown event type ' . $event->type);
        }
    });
});
