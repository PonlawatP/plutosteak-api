<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// bill status
// 0: cart
// 1: ordered
// 2: cooking
// 3: delivering
// 4: done
// 5: cancel

function getFood($food_id)
{
    $c = $GLOBALS['connect'];
    $sql = "
        SELECT 
            *
        FROM 
            `Foods`
        WHERE
            fid = ?
    ";
    $stmt = $c->prepare($sql);
    $stmt->bind_param('i', $food_id);
    $stmt->execute();
    $result = $stmt->get_result();

    foreach ($result as $row) {
        return $row;
    }
    return -1;
}

function getOrdersInBill($bill_id)
{
    $c = $GLOBALS['connect'];
    $sql = "
        SELECT 
            `Order`.oid, `Order`.fid, `Order`.price, `Order`.amount
        FROM 
            `Order`
        WHERE
            `Order`.bid = ?
    ";
    $stmt = $c->prepare($sql);
    $stmt->bind_param('i', $bill_id);
    $stmt->execute();
    $result = $stmt->get_result();

    foreach ($result as $row) {
        return $result;
    }
    return -1;
}

function getOrderInBill($bill_id, $food_id)
{
    $c = $GLOBALS['connect'];
    $sql = "
        SELECT 
            `Order`.oid, `Order`.price, `Order`.amount
        FROM 
            `Order`
        WHERE
            `Order`.bid = ?
        AND
            `Order`.fid = ?
    ";
    $stmt = $c->prepare($sql);
    $stmt->bind_param('ii', $bill_id, $food_id);
    $stmt->execute();
    $result = $stmt->get_result();

    foreach ($result as $row) {
        return $row;
    }
    return -1;
}

function getBillOrderID($username)
{
    $c = $GLOBALS['connect'];
    $sql = '
        SELECT 
            bid, status
        FROM 
            Bill INNER JOIN User
        ON
            STATUS = 0
            AND
            User.uid = Bill.uid
            AND
            User.username = ?
    ';
    $stmt = $c->prepare($sql);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();

    foreach ($result as $row) {
        return $row['bid'];
    }
    return -1;
}

$app->get('/account/cart', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $username = $request->getHeaders()['Username'][0];

    $sql = '
        SELECT 
            `Order`.fid, Foods.name, `Order`.price, `Order`.amount, (`Order`.price*`Order`.amount) AS total_price, Foods.img
        FROM 
            `Order` INNER JOIN Foods INNER JOIN User INNER JOIN Bill
        ON
            Foods.fid = `Order`.fid
            AND
            Bill.uid = User.uid
            AND
            `Order`.bid = Bill.bid
            AND
            Bill.status = 0
            AND
            User.username = ?
    ';
    $stmt = $c->prepare($sql);
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    $cart_price = 0;
    foreach ($result as $row) {
        array_push($data, $row);
        $cart_price += $row['total_price'];
    }

    $json = json_encode(array('cart' => $data, 'cart_price' => $cart_price), JSON_PRETTY_PRINT);

    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->put('/account/cart', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $username = $request->getHeaders()['Username'][0];
    $body = json_decode($request->getBody(), true);

    $bid = getBillOrderID($username);
    $user = getUser($username);

    if ($bid == -1) {
        $json = json_encode(array('success' => false), JSON_PRETTY_PRINT);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    $sql = 'UPDATE `Bill` SET `Customer_name` = ?, `phone_number` = ?, `address` = ? WHERE bid = ?';
    $stmt = $c->prepare($sql);
    $stmt->bind_param('sssi', $body['customer_name'], $body['phone_number'], $body['address'], $bid);
    $stmt->execute();
    
    if($user['name'] !== $body['customer_name'] || $user['phone_number'] !== $body['phone_number'] || $user['address'] !== $body['address']){
        $sql = 'UPDATE `User` SET `name` = ?, `phone_number` = ?, `address` = ? WHERE username = ?';
        $stmt = $c->prepare($sql);
        $stmt->bind_param('ssss', $body['customer_name'], $body['phone_number'], $body['address'], $username);
        $stmt->execute();
    }

    $json = json_encode(array('success' => true), JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/account/cart', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $username = $request->getHeaders()['Username'][0];
    $body = json_decode($request->getBody(), true);

    $bid = getBillOrderID($username);

    $user_data = getUser($username);

    if ($bid == -1) {
        $uid = getUser($username)['uid'];

        $sql = 'INSERT INTO `Bill` (`uid`, `Customer_name`, `phone_number`, `address`) VALUES (?, ?, ?, ?)';
        $stmt = $c->prepare($sql);
        $stmt->bind_param('isss', $uid, $user_data['name'], $user_data['phone_number'], $user_data['address']);
        // $stmt->bind_param('isss', $uid, $body['customer_name'], $body['phone_number'], $body['address']);
        $stmt->execute();

        $bid = getBillOrderID($username);
    }
    // type
    //  + increase
    //  - decrease
    //  a clear
    // @ clear(all)
    //  c custom
    $o_res = getOrderInBill($bid, $body['fid']);
    if ($o_res == -1) { //not have food in order
        $food = getFood($body['fid']);

        if ($body['type'] == "+") {
            $sql = 'INSERT INTO `Order` (`fid`, `bid`, `price`, `amount`) VALUES (?, ?, ?, ?)';
            $stmt = $c->prepare($sql);
            $stmt->bind_param('iidi', $body['fid'], $bid, $food['price'], $body['amount']);
            $stmt->execute();
        } else if ($body['type'] == "c") {
            $sql = 'INSERT INTO `Order` (`fid`, `bid`, `price`, `amount`) VALUES (?, ?, ?, ?)';
            $stmt = $c->prepare($sql);
            $stmt->bind_param('iidi', $body['fid'], $bid, $food['price'], $body['amount']);
            $stmt->execute();
        }
    } else { //have food in order
        if ($body['type'] == "+") { //inc
            $sql = 'UPDATE `Order` SET `amount` = `amount`+1 WHERE oid = ?';
            $stmt = $c->prepare($sql);
            $stmt->bind_param('i', $o_res['oid']);
            $stmt->execute();
        } else if ($body['type'] == "-") { //dec
            if ($o_res['amount'] == 1) {
                $sql = 'DELETE FROM `Order` WHERE oid = ?';
                $stmt = $c->prepare($sql);
                $stmt->bind_param('i', $o_res['oid']);
                $stmt->execute();
            } else {
                $sql = 'UPDATE `Order` SET `amount` = `amount`-1 WHERE oid = ?';
                $stmt = $c->prepare($sql);
                $stmt->bind_param('i', $o_res['oid']);
                $stmt->execute();
            }
        } else if ($body['type'] == "a") { //remove
            $sql = 'DELETE FROM `Order` WHERE oid = ?';
            $stmt = $c->prepare($sql);
            $stmt->bind_param('i', $o_res['oid']);
            $stmt->execute();
        } else if ($body['type'] == "c") { //custom
            if ($body['amount'] <= 0) {
                $sql = 'DELETE FROM `Order` WHERE oid = ?';
                $stmt = $c->prepare($sql);
                $stmt->bind_param('i', $o_res['oid']);
                $stmt->execute();
            } else {
                $sql = 'UPDATE `Order` SET `amount` = ? WHERE oid = ?';
                $stmt = $c->prepare($sql);
                $stmt->bind_param('ii', $body['amount'], $o_res['oid']);
                $stmt->execute();
            }
        } else if ($body['type'] == "@") { //remove all
            $sql = 'DELETE FROM `Order` WHERE bid = ?';
            $stmt = $c->prepare($sql);
            $stmt->bind_param('i', $bid);
            $stmt->execute();
        }
    }

    $json = json_encode(array('success' => true), JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/account/cart/submit', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $username = $request->getHeaders()['Username'][0];

    $bid = getBillOrderID($username);

    if ($bid == -1) {
        $json = json_encode(array('success' => false), JSON_PRETTY_PRINT);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }

    $orders = getOrdersInBill($bid);
    $total_price = 0;
    foreach ($orders as $row) {
        $total_price += ($row['price'] * $row['amount']);
    }

    $sql = 'UPDATE `Bill` SET `total_price` = ?, `status` = ? WHERE bid = ?';
    $stmt = $c->prepare($sql);
    $status = 1;
    $stmt->bind_param('iii', $total_price, $status, $bid);
    $stmt->execute();

    $json = json_encode(array('success' => true), JSON_PRETTY_PRINT);
    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

// order section

$app->get('/account/order', function (Request $request, Response $response, $args) {
    $c = $GLOBALS['connect'];

    $username = $request->getHeaders()['Username'][0];

    $sql  = '
        SELECT
            `bid`,  `Customer_name`,  Bill.`phone_number`, Bill.`address`,  `Total_price`,  `Datetime`,  `status`
        FROM
            Bill 
            INNER JOIN User
        WHERE
            Bill.status <> 0
            AND
            Bill.uid = User.uid
            AND
            User.username = ?
    ';
    if ($username === "_admin") {
        $sql = '
        SELECT
            `bid`,  `Customer_name`,  Bill.`phone_number`, Bill.`address`,  `Total_price`,  `Datetime`,  `status`
        FROM
            Bill
        WHERE
            Bill.status <> 0
    ';
    }
    $stmt = $c->prepare($sql);
    if ($username !== "_admin") {
        $stmt->bind_param('s', $username);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    foreach ($result as $row) {
        $row['orders'] = array();
        $sql = '
        SELECT 
            `Order`.fid, Foods.name, `Order`.price, `Order`.amount, (`Order`.price*`Order`.amount) AS total_price, Foods.img
        FROM 
            `Order` INNER JOIN Foods INNER JOIN Bill
        ON
            Foods.fid = `Order`.fid
            AND
            `Order`.bid = Bill.bid
            AND
            Bill.bid = ?
    ';
        $stmt = $c->prepare($sql);
        $stmt->bind_param('i', $row['bid']);
        $stmt->execute();
        $order = $stmt->get_result();

        foreach ($order as $o_res) {
            array_push($row['orders'], $o_res);
        }

        unset($row['password']);
        array_push($data, $row);
    }

    $json = json_encode(array('data' => $data), JSON_PRETTY_PRINT);

    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/account/order/{bid}', function (Request $request, Response $response, $args) {
    $bid = $args['bid'];
    $c = $GLOBALS['connect'];

    $sql = '
        SELECT
        `bid`,  `Customer_name`,  Bill.`phone_number`, Bill.`address`,  `Total_price`,  `Datetime`,  `status`
        FROM
            Bill
        WHERE
            bid = ?
    ';
    $stmt = $c->prepare($sql);
    $stmt->bind_param('i', $bid);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = array();
    foreach ($result as $row) {
        $row['orders'] = array();
        $sql = '
        SELECT
            `Order`.fid, Foods.name, `Order`.price, `Order`.amount, (`Order`.price*`Order`.amount) AS total_price, Foods.img
        FROM
            `Order` INNER JOIN Foods INNER JOIN Bill
        ON
            Foods.fid = `Order`.fid
            AND
            `Order`.bid = Bill.bid
            AND
            Bill.bid = ?
    ';
        $stmt = $c->prepare($sql);
        $stmt->bind_param('i', $row['bid']);
        $stmt->execute();
        $order = $stmt->get_result();

        foreach ($order as $o_res) {
            array_push($row['orders'], $o_res);
        }

        $data = $row;
    }

    $json = json_encode(array('data' => $data), JSON_PRETTY_PRINT);

    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/account/order/{bid}', function (Request $request, Response $response, $args) {
    $bid = $args['bid'];
    $c = $GLOBALS['connect'];
    $body = json_decode($request->getBody(), true);

    $sql = 'UPDATE `Bill` SET `status` = ? WHERE bid = ?';
    $stmt = $c->prepare($sql);
    $stmt->bind_param('ii', $body['status'], $bid);
    $stmt->execute();

    $json = json_encode(array('success' => true), JSON_PRETTY_PRINT);

    $response->getBody()->write($json);
    return $response->withHeader('Content-Type', 'application/json');
});
