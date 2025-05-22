<?php
    $db_server = "switchback.proxy.rlwy.net:14889";
    $db_user= "root";
    $db_pass= "dqyKLGTtpWKDvGAtJDjXMVumRGfehYus";
    $db_name= "railway";
   
    if($conn = mysqli_connect(
                        $db_server,
                        $db_user,
                        $db_pass,
                        $db_name
    )) {
       
    } else {
        die(''. mysqli_connect_error());
    }

?>