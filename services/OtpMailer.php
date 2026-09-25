<?php

require_once __DIR__ . "/../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


class OtpMailer
{

    public static function send(
        string $email,
        string $otp
    ): bool {

        $mail = new PHPMailer(true);


        try {

            /*
            |--------------------------------------------------------------------------
            | SMTP
            |--------------------------------------------------------------------------
            */

            $mail->isSMTP();

            $mail->Host =
                "smtp.gmail.com";

            $mail->SMTPAuth =
                true;


            /*
            |--------------------------------------------------------------------------
            | USE YOUR EXISTING GMAIL SMTP ACCOUNT
            |--------------------------------------------------------------------------
            */

            $mail->Username =
                "cerdenamarcahron@gmail.com";

            $mail->Password =
                "jxpb mryi kulo wjkr";


            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;

            $mail->Port =
                587;

            $mail->CharSet =
                "UTF-8";


            /*
            |--------------------------------------------------------------------------
            | SENDER
            |--------------------------------------------------------------------------
            |
            | Better if same email as authenticated Gmail.
            |
            */

            $mail->setFrom(
                "YOUR_GMAIL@gmail.com",
                "Caloocan Job Portal"
            );


            /*
            |--------------------------------------------------------------------------
            | RECIPIENT
            |--------------------------------------------------------------------------
            */

            $mail->addAddress(
                $email
            );


            /*
            |--------------------------------------------------------------------------
            | EMAIL
            |--------------------------------------------------------------------------
            */

            $mail->isHTML(true);

            $mail->Subject =
                "Caloocan Job Portal - Login Verification Code";


            $safeOtp =
                htmlspecialchars(
                    $otp,
                    ENT_QUOTES,
                    "UTF-8"
                );


            $mail->Body = "

                <div style=\"
                    font-family: Arial, sans-serif;
                    max-width: 600px;
                    margin: auto;
                    padding: 30px;
                    border: 1px solid #ddd;
                    border-radius: 10px;
                \">

                    <h2 style=\"
                        color:#2563eb;
                        margin-bottom:20px;
                    \">

                        Caloocan Job Portal

                    </h2>


                    <p>

                        We received a login attempt
                        for your account.

                    </p>


                    <p>

                        Enter the verification code
                        below to continue:

                    </p>


                    <div style=\"
                        margin: 25px 0;
                        padding: 20px;
                        background: #eff6ff;
                        border: 1px solid #bfdbfe;
                        border-radius: 8px;
                        text-align: center;
                        font-size: 32px;
                        font-weight: bold;
                        letter-spacing: 8px;
                        color: #1d4ed8;
                    \">

                        {$safeOtp}

                    </div>


                    <p>

                        This verification code expires in

                        <strong>
                            5 minutes
                        </strong>.

                    </p>


                    <p>

                        If you did not attempt to log in,
                        you can safely ignore this email.

                    </p>

                </div>

            ";


            $mail->AltBody =
                "Your Caloocan Job Portal verification code is "
                . $otp
                . ". This code expires in 5 minutes.";


            $mail->send();


            return true;


        } catch (Exception $e) {

            error_log(
                "OTP Mail Error: "
                . $mail->ErrorInfo
            );


            return false;
        }
    }
}