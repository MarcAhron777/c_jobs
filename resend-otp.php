<?php

session_start();

date_default_timezone_set(
    "Asia/Manila"
);

require_once "config/database.php";
require_once "services/OtpMailer.php";


/*
|--------------------------------------------------------------------------
| MUST HAVE OTP SESSION
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["otp_user_id"])
    ||
    !isset($_SESSION["otp_email"])
) {

    header(
        "Location: login.php"
    );

    exit;
}


$userId =
    (int)
    $_SESSION["otp_user_id"];


$email =
    $_SESSION["otp_email"];


try {


    /*
    |--------------------------------------------------------------------------
    | CHECK LAST OTP
    |--------------------------------------------------------------------------
    |
    | Prevent repeated spam.
    |
    */

    $stmt =
        $pdo->prepare("

            SELECT

                created_at

            FROM login_otps

            WHERE user_id = ?

            ORDER BY id DESC

            LIMIT 1

        ");


    $stmt->execute([
        $userId
    ]);


    $lastOtp =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if ($lastOtp) {

        $stmt =
            $pdo->prepare("

                SELECT

                    TIMESTAMPDIFF(
                        SECOND,
                        ?,
                        NOW()
                    ) AS seconds_passed

            ");


        $stmt->execute([
            $lastOtp["created_at"]
        ]);


        $difference =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        $seconds =
            (int)
            (
                $difference[
                    "seconds_passed"
                ]
                ?? 0
            );


        /*
        |--------------------------------------------------------------------------
        | 60 SECOND COOLDOWN
        |--------------------------------------------------------------------------
        */

        if ($seconds < 60) {

            $_SESSION["otp_notice"] =
                "Please wait "
                . (60 - $seconds)
                . " seconds before requesting another code.";


            header(
                "Location: verify-otp.php"
            );

            exit;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GENERATE NEW OTP
    |--------------------------------------------------------------------------
    */

    $otp =
        (string)
        random_int(
            100000,
            999999
        );


    $otpHash =
        password_hash(
            $otp,
            PASSWORD_DEFAULT
        );


    /*
    |--------------------------------------------------------------------------
    | INVALIDATE OLD OTP
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare("

            UPDATE login_otps

            SET is_used = 1

            WHERE user_id = ?

            AND is_used = 0

        ");


    $stmt->execute([
        $userId
    ]);


    /*
    |--------------------------------------------------------------------------
    | INSERT NEW OTP
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare("

            INSERT INTO login_otps
            (
                user_id,
                otp_hash,
                expires_at,
                attempts,
                is_used
            )

            VALUES
            (
                ?,
                ?,
                DATE_ADD(
                    NOW(),
                    INTERVAL 5 MINUTE
                ),
                0,
                0
            )

        ");


    $stmt->execute([

        $userId,

        $otpHash

    ]);


    $newOtpId =
        (int)
        $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | SEND EMAIL
    |--------------------------------------------------------------------------
    */

    $sent =
        OtpMailer::send(

            $email,

            $otp

        );


    if (!$sent) {

        $stmt =
            $pdo->prepare("

                UPDATE login_otps

                SET is_used = 1

                WHERE id = ?

            ");


        $stmt->execute([
            $newOtpId
        ]);


        $_SESSION["otp_error"] =
            "Unable to send a new verification code.";

    } else {

        $_SESSION["otp_id"] =
            $newOtpId;


        $_SESSION["otp_notice"] =
            "A new verification code has been sent.";
    }


} catch (
    PDOException $e
) {

    error_log(
        $e->getMessage()
    );


    $_SESSION["otp_error"] =
        "Unable to resend verification code.";
}


/*
|--------------------------------------------------------------------------
| BACK TO VERIFY
|--------------------------------------------------------------------------
*/

header(
    "Location: verify-otp.php"
);

exit;