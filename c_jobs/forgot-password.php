<?php

session_start();

date_default_timezone_set("Asia/Manila");

require_once "config/database.php";
require_once "vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


$error = "";
$success = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim(
        strtolower(
            $_POST["email"] ?? ""
        )
    );


    if ($email === "") {

        $error = "Please enter your email address.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = "Please enter a valid email address.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Find User
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                email,
                role,
                is_active
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([
            $email
        ]);

        $user = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Security
        |--------------------------------------------------------------------------
        |
        | We still show the same success message
        | even if email does not exist.
        |
        */

        if ($user && (int) $user["is_active"] === 1) {

            /*
            |--------------------------------------------------------------------------
            | Generate Secure Token
            |--------------------------------------------------------------------------
            */

            $token = bin2hex(
                random_bytes(32)
            );

            $tokenHash = hash(
                "sha256",
                $token
            );

            /*
            |--------------------------------------------------------------------------
            | Token Expiration
            |--------------------------------------------------------------------------
            */

            $expiresAt = date(
                "Y-m-d H:i:s",
                time() + (60 * 30)
            );


            /*
            |--------------------------------------------------------------------------
            | Save Token
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE users

                SET
                    reset_token = ?,
                    reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 30 MINUTE)

                WHERE id = ?
            ");

            $stmt->execute([
                $tokenHash,
                $user["id"]
            ]);


            /*
            |--------------------------------------------------------------------------
            | Reset URL
            |--------------------------------------------------------------------------
            |
            | Change this if your project folder has a different name.
            |
            */

            $resetUrl =
                "http://localhost/c_jobs/reset-password.php?token="
                . urlencode($token);


            /*
            |--------------------------------------------------------------------------
            | Send Email
            |--------------------------------------------------------------------------
            */

            $mail = new PHPMailer(true);

            try {

                $mail->isSMTP();

                $mail->Host = "smtp.gmail.com";

                $mail->SMTPAuth = true;

                /*
                |--------------------------------------------------------------------------
                | Gmail Account
                |--------------------------------------------------------------------------
                |
                | Replace these with your Gmail SMTP credentials.
                |
                */

                $mail->Username = "cerdenamarcahron@gmail.com";

                $mail->Password = "jxpb mryi kulo wjkr";

                $mail->SMTPSecure =
                    PHPMailer::ENCRYPTION_STARTTLS;

                $mail->Port = 587;


                /*
                |--------------------------------------------------------------------------
                | Email
                |--------------------------------------------------------------------------
                */

                $mail->setFrom(
                    "no-reply@gmail.com",
                    "Caloocan Job Portal"
                );

                $mail->addAddress(
                    $user["email"]
                );


                $mail->isHTML(true);

                $mail->Subject =
                    "Caloocan Job Portal - Reset Your Password";


                $mail->Body = "

                    <div style=\"
                        font-family:Arial,sans-serif;
                        max-width:600px;
                        margin:auto;
                        padding:30px;
                        border:1px solid #ddd;
                        border-radius:10px;
                    \">

                        <h2 style=\"color:#2563eb;\">
                            Caloocan Job Portal
                        </h2>

                        <p>
                            We received a request to reset your password.
                        </p>

                        <p>
                            Click the button below to create a new password.
                        </p>

                        <p style=\"margin:30px 0;\">

                            <a
                                href=\"{$resetUrl}\"
                                style=\"
                                    background:#2563eb;
                                    color:white;
                                    padding:12px 20px;
                                    text-decoration:none;
                                    border-radius:6px;
                                    display:inline-block;
                                \"
                            >
                                Reset Password
                            </a>

                        </p>

                        <p>
                            This link will expire in
                            <strong>30 minutes</strong>.
                        </p>

                        <p>
                            If you did not request a password reset,
                            you can safely ignore this email.
                        </p>

                    </div>

                ";


                $mail->AltBody =
                    "Reset your JobPortal password using this link: "
                    . $resetUrl;


                $mail->send();


            } catch (Exception $e) {

                $error =
                    "Unable to send the password reset email. "
                    . "Please check your email configuration.";

            }

        }


        /*
        |--------------------------------------------------------------------------
        | Generic Success Message
        |--------------------------------------------------------------------------
        */

        if ($error === "") {

            $success =
                "If an account exists with that email address, "
                . "a password reset link has been sent.";

        }

    }

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Forgot Password - JobPortal
</title>


<style>

* {
    box-sizing: border-box;

    margin: 0;

    padding: 0;
}


body {
    font-family: Arial, sans-serif;

    background: #f5f7fb;

    min-height: 100vh;

    display: flex;

    align-items: center;

    justify-content: center;

    padding: 20px;
}


.container {
    width: 100%;

    max-width: 430px;
}


.card {
    background: white;

    padding: 35px;

    border-radius: 12px;

    box-shadow:
        0 4px 20px rgba(0,0,0,0.08);
}


.logo {
    text-align: center;

    font-size: 26px;

    font-weight: bold;

    color: #2563eb;

    margin-bottom: 25px;
}


h1 {
    font-size: 24px;

    margin-bottom: 10px;

    text-align: center;
}


.description {
    text-align: center;

    color: #666;

    font-size: 14px;

    line-height: 1.6;

    margin-bottom: 25px;
}


label {
    display: block;

    margin-bottom: 7px;

    font-weight: bold;

    font-size: 14px;
}


input {
    width: 100%;

    padding: 12px;

    border: 1px solid #ccc;

    border-radius: 6px;

    font-size: 14px;

    margin-bottom: 15px;
}


input:focus {
    outline: none;

    border-color: #2563eb;
}


button {
    width: 100%;

    padding: 12px;

    border: none;

    border-radius: 6px;

    background: #2563eb;

    color: white;

    font-size: 14px;

    font-weight: bold;

    cursor: pointer;
}


button:hover {
    background: #1d4ed8;
}


.message {
    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;

    font-size: 14px;

    line-height: 1.5;
}


.success {
    background: #dcfce7;

    color: #166534;
}


.error {
    background: #fee2e2;

    color: #991b1b;
}


.back {
    display: block;

    text-align: center;

    margin-top: 20px;

    color: #2563eb;

    text-decoration: none;

    font-size: 14px;
}


.back:hover {
    text-decoration: underline;
}

</style>

</head>


<body>


<div class="container">


    <div class="card">


        <div class="logo">
            JobPortal
        </div>


        <h1>
            Forgot Password?
        </h1>


        <p class="description">

            Enter your email address and we'll send you
            a link to reset your password.

        </p>


        <?php if ($success): ?>

            <div class="message success">

                <?= htmlspecialchars(
                    $success
                ) ?>

            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="message error">

                <?= htmlspecialchars(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <label for="email">
                Email Address
            </label>


            <input
                type="email"
                id="email"
                name="email"
                placeholder="Enter your email"
                value="<?= htmlspecialchars(
                    $_POST["email"] ?? ""
                ) ?>"
                required
            >


            <button type="submit">

                Send Reset Link

            </button>


        </form>


        <a
            href="login.php"
            class="back"
        >

            ← Back to Login

        </a>


    </div>


</div>


</body>

</html>