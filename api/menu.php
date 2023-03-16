<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/menu', function (Request $request, Response $response) {
    $conn = $GLOBALS['connect'];
    $sql = 'select fid AS id, Foods.name, Food_type.name as TYPE, price , img from Foods inner join Food_type on Foods.tid = Food_type.tid';
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    foreach ($result as $row) {
        array_push($data, $row);
    }

    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});

$app->get('/menu/type/{food_type}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['connect'];


    $sql = 'select fid AS id, Foods.name, Food_type.name as TYPE, price , img from Foods inner join Food_type on Foods.tid = Food_type.tid WHERE Food_type.name LIKE ?';
    $stmt = $conn->prepare($sql);
    $name = '%' . $args['food_type'] . '%';
    $stmt->bind_param('s', $name);
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
$app->get('/menu/name/{food_name}', function (Request $request, Response $response, $args) {
    $conn = $GLOBALS['connect'];


    $sql = 'select fid AS id, Foods.name, Food_type.name as TYPE, price , img from Foods inner join Food_type on Foods.tid = Food_type.tid WHERE Foods.name LIKE ?';
    $stmt = $conn->prepare($sql);
    $name = '%' . $args['food_name'] . '%';
    $stmt->bind_param('s', $name);
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

$app->get('/menu/view/{fid}', function (Request $request, Response $response, $args) {
    $fid = $args['fid'];
    $conn = $GLOBALS['connect'];
    $sql = 'select fid AS id, Foods.name, Food_type.name as TYPE, price , img from Foods inner join Food_type on Foods.tid = Food_type.tid WHERE Foods.fid = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $fid);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        array_push($data, $row);
    }
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK));
    return $response
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus(200);
});


$app->post('/menu', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);

    $conn = $GLOBALS['connect'];


    $sql = 'UPDATE Food_type SET `index`=`index`+1 WHERE tid=?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $jsonData['tid']);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    if ($affected > 0) {
        $sql = 'SELECT `index` FROM Food_type WHERE tid = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $jsonData['tid']);
        $stmt->execute();
        $result = $stmt->get_result();
        $fid = $result->fetch_assoc()['index'];
        $res_fid = (int)($jsonData['tid'] . '' . sprintf("%02d", $fid));

        $sql = 'insert into Foods (fid, tid, name, price, img) values (?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iisss', $res_fid, $jsonData['tid'], $jsonData['name'], $jsonData['price'], $jsonData['img']);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        if ($affected > 0) {
            $data = ["affected_rows" => $affected, "new_fid" => $res_fid];
            $response->getBody()->write(json_encode($data));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        }
    }
});

$app->put('/menu/{fid}', function (Request $request, Response $response, $args) {
    $json = $request->getBody();
    $jsonData = json_decode($json, true);
    $fid = $args['fid'];
    $conn = $GLOBALS['connect'];
    $sql = 'update landmark set name=?, price=?, img=? where fid = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssi', $jsonData['name'], $jsonData['price'], $jsonData['img'], $fid);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    if ($affected > 0) {
        $data = ["affected_rows" => $affected];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
});

$app->delete('/menu/{id}', function (Request $request, Response $response, $args) {
    $id = $args['id'];
    $conn = $GLOBALS['connect'];
    $sql = 'delete from Foods where fid = ?';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    if ($affected > 0) {
        $data = ["affected_rows" => $affected];
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }
});
