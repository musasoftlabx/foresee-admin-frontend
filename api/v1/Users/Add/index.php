<?php
require '../../functions.php';
$token = RetrieveAndValidateTokenFromRequest();
header('Content-Type: application/json');

$_JSON = json_decode(file_get_contents('php://input'), true);

$addedBy = TokenDeconstruct($token, 'username');

$passkey  = substr(str_shuffle("23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnopqrstuvwxyz"), 0, 8);
$password = PasswordHasher($passkey);

try {
    $count = ChainPDO("SELECT COUNT(id) FROM users WHERE username REGEXP ?", [$_JSON['username']])->fetch(PDO::FETCH_COLUMN);

    if ($count > 0) {
        $username = $username . $count;
    }

    ChainPDO("INSERT INTO users VALUES (NULL, ?, ?, ?, NULL, ?, 1, NULL, now(), ?)", [
        ucwords(strtolower(strip_tags($_JSON['firstName']))),
        ucwords(strtolower(strip_tags($_JSON['lastName']))),
        $_JSON['username'],
        $_JSON['role'],
        $addedBy
    ]);

    $LastID = $DB->lastInsertId();

    echo json_encode(ChainPDO("SELECT id, firstName, lastName, username, role, addedBy, DATE_FORMAT(addedOn, '%a, %D %m %y at %r') AS addedOn FROM users WHERE id = ?", [$LastID])->fetch());
} catch (PDOException $e) {
    http_response_code(500);
    echo SERVER_ERROR;

    LogToDB(json_encode(
        [
            'logtype'   => 'ERROR',
            'operation' => 'DATABASE',
            'service'   => basename(dirname(__FILE__)),
            'request'   => file_get_contents('php://input'),
            'response'  => json_encode($e),
        ]
    ));
}
