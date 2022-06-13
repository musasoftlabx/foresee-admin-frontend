<?php
require '../../functions.php';
$token = RetrieveAndValidateTokenFromRequest();
header('Content-Type: application/json');
echo json_encode(ChainPDO("SELECT id, firstName, lastName, username, role, profilePicture, addedBy, DATE_FORMAT(addedOn, '%a, %D-%m-%y at %r') AS addedOn FROM users ORDER BY id DESC")->fetchAll());
