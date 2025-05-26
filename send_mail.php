<?php

require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
function send_mail($to, $password): void
{
    try{
        $mail = new PHPMailer();

//Server settings
        $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
        $mail->isSMTP();                                            //Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
        $mail->Username   = 'klinika.dentare311@gmail.com';                     //SMTP username
        $mail->Password   = 'holu fvur lqar pnkl ';                               //SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
        $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

//Recipients
        $mail->setFrom('klinika.dentare311@gmail.com', 'Klinika Dentare');
        $mail->addAddress($to);     //Add a recipient


//Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = 'Your account has been created';
        $mail->Body    =
"Hello!\n\nYour account has been created.\n\n
username : ".$to."\n
password : ".$password;


        $mail->send();
    } catch (Exception $e) {

    }
}