<?php
    $host = 'switchback.proxy.rlwy.net:14889';
    $username = 'root';
    $password = 'dqyKLGTtpWKDvGAtJDjXMVumRGfehYus';
    $database = 'railway';

    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

