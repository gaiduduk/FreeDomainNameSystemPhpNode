<?php

include_once "../db.php";
$node_url = uencode($host_url . "node");

$user_login = get("user_login");
$user_password = get("user_password");
$token = get_int("token");
$stock_token = get("stock_token");
$message = "";
$user = null;

if ($user_login != null && !filter_var($user_login, FILTER_VALIDATE_EMAIL))
    db_error(USER_ERROR, "login is not email");

require("PHPMailer/Exception.php");
require("PHPMailer/PHPMailer.php");
require("PHPMailer/SMTP.php");

function send($to, $subject, $body)
{
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->IsSMTP();
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = 'ssl';
        $mail->Host = "smtp.gmail.com";
        $mail->Port = 465;
        $mail->IsHTML(true);
        $mail->Username = $GLOBALS["gmail_email"];
        $mail->Password = $GLOBALS["gmail_password"];
        $mail->SetFrom($GLOBALS["gmail_email"], "DarkCoin");
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AddAddress($to);
        $mail->send();
        return true;
    } catch (PHPMailer\PHPMailer\Exception $e) {
        return false;
    }
}


if ($user_login != null && $user_password != null) {
    $user = selectMap("select * from users where user_login = '$user_login'");
    $password_hash = hash("sha256", $user_password);
    if ($user != null) {
        $token = random_id();
        if ($user["user_password_hash"] == $password_hash) {
            updateList("users", array(
                "user_session_token" => $token,
            ), "user_id", $user["user_id"]);
            $user["user_session_token"] = $token;
        } else {
            $message = "Password is not correct";
        }
    } else {
        $token = random_id();
        $user_verify_token = random_id();
        insertList("users", array(
            "user_login" => $user_login,
            "user_password_hash" => $password_hash,
            "user_session_token" => $token,
            "user_stock_token" => random_id(),
            "user_verify_token" => $user_verify_token,
        ));
        echo "LOGIN VALIDATION NOT FINISHED";
        $validation_link = $host_url . "verify.php?user_validation_token=". $user_verify_token;
        send($user_login, "DarkCoin registration", "Click link follow: <a href='$validation_link'>$validation_link</a>");
    }
}

if ($stock_token != null) {
    $user = selectMap("select * from users where user_session_token = $stock_token");
    $token = $stock_token;
    if ($user == null)
        insertList("users", array(
            "user_login" => "user" . rand(1, 1000000),
            "user_password_hash" => hash("sha256", "pass" . rand(1, 1000000)),
            "user_session_token" => $stock_token,
            "user_stock_token" => $stock_token,
        ));
}

/*if ($stock_token != null && $user_password != null)
    //change passwrod*/

if ($user == null && $token != null)
    $user = selectMap("select * from users where user_session_token = $token");

$user_id = $user["user_id"];

if ($message == null && ($user == null || $token == null || $user_id == null))
    $message = "login_error";

if ($message != null)
    die(json_encode(array(
        "message" => $message
    )));




