<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

function startsWith($string, $startString)
{
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}

$app->add(function (ServerRequestInterface $request, ResponseInterface $response, callable $next) {
    $url = $request->getUri()->getPath();
    if (startsWith($url, "/admin")) {
        if (!isset($_SESSION['user']) || $_SESSION['user']['account_type'] != "admin") { // refuse if user not logged in AS ADMIN
            $response = $response->withStatus(403);
            return $this->view->render($response, 'admin/error-access-denied.html.twig');
        }
    }
    return $next($request, $response);
});

$app->group('/admin', function () use ($app, $log) {

    $app->get('', function ($request, $response, $args) use ($log) {
        return $this->view->render($response, 'admin/index.html.twig');
    });

    $app->get('/user/list', function ($request, $response, $args) use ($log) {
        $list = DB::query("SELECT * FROM `user`");
        return $this->view->render($response, 'admin/user-list.html.twig', ['list' => $list]);
    });

    $app->get('/user/edit/{id:[0-9]+}', function ($request, $response, $args) {
        $user = DB::queryFirstRow("SELECT * FROM user WHERE id=%d", $args['id']);
        if (!$user) {
            $response = $response->withStatus(404);
            return $this->view->render($response, 'admin/error-not-found.html.twig');
        }
        $address = DB::queryFirstRow("SELECT * FROM `address` WHERE id=%d", $user['address_id']);
        print_r($user);
        print_r($address);
        return $this->view->render($response, 'admin/user-edit.html.twig', ['v' => $user, 'a' => $address]);
    });

    $app->post('/user/{op:edit|add}/{id:[0-9]+}', function ($request, $response, $args) {
        if (($args['op'] == 'add' && !empty($args['id'])) || ($args['op'] == 'edit' && empty($args['id']))) {
            $response = $response->withStatus(404);
            return $this->view->render($response, 'admin/error-not-found.html.twig');
        }
        $name = $request->getParam('name');
        $userName = $request->getParam('userName');
        $email = $request->getParam('email');
        $pass1 = $request->getParam('pass1');
        $street = $request->getParam('street');
        $appartmentNo = $request->getParam('appartmentNo');
        $postalCode = $request->getParam('postalCode');
        $city = $request->getParam('city');
        $province = $request->getParam('province');
        $phone = $request->getParam('phone');
        $accountType = $request->getParam('accountType');


        //  ************************ UPDATE DONE **********************
        $password = password_hash($pass1, PASSWORD_DEFAULT);
        $valuesList = [
            'name' => $name, 'username' => $userName, 'password' => $password, 'email' => $email,
        ];
        DB::update('user', $valuesList, "id=%d", $args['id']);
        return $this->view->render($response, "admin/user-edit-success.html.twig");
    });

    //  ************************ Delete user**********************
    $app->get('/user/delete/{id:[0-9]+}', function ($request, $response, $args) {
        $user = DB::queryFirstRow("SELECT * FROM user WHERE id=%d", $args['id']);
        if (!$user) {
            $response = $response->withStatus(404);
            return $this->view->render($response, 'admin/error-not-found.html.twig');
        }
        return $this->view->render($response, 'admin/user-delete.html.twig', ['v' => $user]);
    });

    $app->post('/user/delete/{id:[0-9]+}', function ($request, $response, $args) {
        DB::delete('user', "id=%d", $args['id']);
        return $this->view->render($response, 'admin/user-delete-success.html.twig');
    });
    //  ************************ List of orders **********************
    $app->get('/orders-check', function ($request, $response, $args) use ($log) {
        $list = DB::query("SELECT * FROM `user_order`");
        return $this->view->render($response, 'admin/orders-list.html.twig', ['list' => $list]);
    });

    //  ************************ List of food items **********************
    $app->get('/food-items', function ($request, $response, $args) use ($log) {
        $list = DB::query("SELECT * FROM `food`");
        return $this->view->render($response, 'admin/food-list.html.twig', ['list' => $list]);
    });

        //  ************************ Delete food item **********************
        $app->get('/food/delete/{id:[0-9]+}', function ($request, $response, $args) {
            $item = DB::queryFirstRow("SELECT * FROM food WHERE id=%d", $args['id']);
            if (!$item) {
                $response = $response->withStatus(404);
                return $this->view->render($response, 'admin/error-not-found.html.twig');
            }
            return $this->view->render($response, 'admin/food-delete.html.twig', ['v' => $item]);
        });
    
        $app->post('/food/delete/{id:[0-9]+}', function ($request, $response, $args) {
            DB::delete('food', "id=%d", $args['id']);
            return $this->view->render($response, 'admin/food-delete-success.html.twig');
        });
});
