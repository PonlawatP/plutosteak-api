<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

function getUser($username)
{
    $c = $GLOBALS['connect'];
    $stmt = $c->prepare('SELECT * FROM User WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows == 1) {
        $row = $res->fetch_assoc();
        return $row;
    } else return "null";
}

$app->post('/account/login', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $body = json_decode($request->getBody(), true);

    $user = getUser($body['username']);

    if ($user === 'null' || ($user['password'] == null && $body['password'] != '')) {
        $json = json_encode(['success' => false], JSON_PRETTY_PRINT);
    } else {
        if ($user['password'] == null) {
            unset($user['password']);
            $json = json_encode(['success' => true, 'data' => $user], JSON_PRETTY_PRINT);
        } else {
            if (password_verify($body['password'], $user['password'])) {
                unset($user['password']);
                $json = json_encode(['success' => true, 'data' => $user], JSON_PRETTY_PRINT);
            } else {
                $json = json_encode(['success' => false], JSON_PRETTY_PRINT);
            }
        }
    }


    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/account/changepass', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $body = json_decode($request->getBody(), true);

    $user = getUser($body['username']);

    if ($user === 'null') {
        $json = json_encode(['success' => false], JSON_PRETTY_PRINT);
    } else {
        if ($body['password_old'] === $body['password_new']) {
            $json = json_encode(['success' => false], JSON_PRETTY_PRINT);
        } else if ($user['password'] == null || password_verify($body['password_old'], $user['password'])) {
            $ppre = $c->prepare('UPDATE `User` SET `password` = ? WHERE username = ?;');
            $pwd = password_hash($body['password_new'], PASSWORD_DEFAULT);

            $ppre->bind_param('ss', $pwd, $body['username']);

            $ppre->execute();

            $json = json_encode(['success' => true], JSON_PRETTY_PRINT);
        } else {
            $json = json_encode(['success' => false], JSON_PRETTY_PRINT);
        }
    }


    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/account', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $body = json_decode($request->getBody(), true);

    $user = getUser($body['username']);

    if ($user != 'null') {
        $json = json_encode(['success' => false], JSON_PRETTY_PRINT);
    } else {
        $ppre = $c->prepare('insert into User (name, phone_number, username, password) values (?, ?, ?, ?);');
        $pwd = password_hash($body['password'], PASSWORD_DEFAULT);

        $ppre->bind_param('ssss', $body['name'], $body['phone_number'], $body['username'], $pwd);

        $ppre->execute();

        $json = json_encode(['success' => true], JSON_PRETTY_PRINT);
    }


    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/account', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $sql = 'SELECT uid, name, phone_number, username, is_staff FROM User';
    $stmt = $c->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    foreach ($result as $row) {
        array_push($data, $row);
    }

    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});
