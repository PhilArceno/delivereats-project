<?php

require_once 'vendor/autoload.php';

require_once 'init.php';

$app->get('/admin/user/list', function ($request, $response, $args) use ($log) {

    $list = DB::query("SELECT * FROM `user`");
    return $this->view->render($response, 'admin/user-list.html.twig', ['list' => $list]);
});

$app->get('/admin/user/{op:edit|add}/{id:[0-9]+}', function ($request, $response, $args) {
    if (($args['op'] == 'add' && !empty($args['id'])) || ($args['op'] == 'edit' && empty($args['id']))) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/error-not-found.html.twig');
    }
    if ($args['op'] == 'edit') {
        $user = DB::queryFirstRow("SELECT * FROM `user` WHERE id=%d", $args['id']);
        if (!$user) {
            $response = $response->withStatus(404);
            return $this->view->render($response, 'admin/error-not-found.html.twig');
        }
    } else {
        $user = [];
    }
    return $this->view->render($response, 'admin/user-add-edit.html.twig', ['v' => $user, 'op' => $args['op']]);
});

$app->post('/admin/user/{op:edit|add}/{id:[0-9]+}', function ($request, $response, $args) {
    if (($args['op'] == 'add' && !empty($args['id'])) || ($args['op'] == 'edit' && empty($args['id']))) {
        $response = $response->withStatus(404);
        return $this->view->render($response, 'admin/error-not-found.html.twig');
    }
    $name = $request->getParam('name');
    $userName = $request->getParam('userName');
    $email = $request->getParam('email');
    $pass1 = $request->getParam('pass1');
    $pass2 = $request->getParam('pass2');
    $phone = $request->getParam('phone');
    $accountType = $request->getParam('accountType');


    //***************************** UPDATE IN PROGRESS: *****************************
    $errorList = [];

    // name validation
    $result = verifyName($name);
    if ($result !== TRUE) {
        $errorList[] = $result;
    }

    // username validation
    $result = verifyName($userName);
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
            'phone' => $phone, 'accountType' => $accountType
        ];
        return $this->view->render($response, "admin/user-add-edit.html.twig", ['errorList' => $errorList, 'v' => $valuesList]);
    } else {
        //  ************************ UPDATE DONE **********************
        $password = password_hash($pass1, PASSWORD_DEFAULT);
        $valuesList = [
            'name' => $name, 'userName' => $userName, 'password' => $password, 'email' => $email,
            'account_type' => $accountType];
        DB::insert('user', $valuesList);
        return $this->view->render($response, "admin/user-add-edit-success.html.twig", ['op' => $args['op']]);
    }
});
