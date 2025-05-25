<?php
//this is to create the first admin in the database

require_once "db.php";
$pass = password_hash('admin', PASSWORD_DEFAULT);

$conn->query("Insert into users(role_id, name, email, password_hash) values (1, 'admin', 'klinika.dentare311@gmail.com', '$pass');");