<?php

session_start();

date_default_timezone_set(
    "Asia/Manila"
);

require_once "config/database.php";


$error = "";


/*
|--------------------------------------------------------------------------
| OTP SESSION REQUIRED
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["otp_user_id"])
    ||
    !isset($_SESSION["otp_id"])
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


$otpId =
    (int)
    $_SESSION["otp_id"];


$email =
    $_SESSION["otp_email"];


/*
|--------------------------------------------------------------------------
| MASK EMAIL
|--------------------------------------------------------------------------
*/

function maskEmail(
    string $email
): string {

    $parts =
        explode(
            "@",
            $email,
            2
        );


    if (
        count($parts)
        !== 2
    ) {

        return $email;
    }


    $name =
        $parts[0];


    $domain =
        $parts[1];


    if (
        strlen($name)
        <= 2
    ) {

        $masked =
            substr(
                $name,
                0,
                1
            )
            . "***";

    } else {

        $masked =
            substr(
                $name,
                0,
                2
            )
            . str_repeat(
                "*",
                max(
                    3,
                    strlen($name) - 2
                )
            );
    }


    return
        $masked
        . "@"
        . $domain;
}


/*
|--------------------------------------------------------------------------
| VERIFY
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {

    $enteredOtp =
        trim(
            $_POST["otp"]
            ?? ""
        );


    if (
        !preg_match(
            '/^\d{6}$/',
            $enteredOtp
        )
    ) {

        $error =
            "Please enter the 6-digit verification code.";

    } else {

        try {


            $stmt =
                $pdo->prepare("

                    SELECT

                        id,
                        user_id,
                        otp_hash,
                        expires_at,
                        attempts,
                        is_used

                    FROM login_otps

                    WHERE id = ?

                    AND user_id = ?

                    LIMIT 1

                ");


            $stmt->execute([

                $otpId,

                $userId

            ]);


            $otp =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (
                !$otp
                ||
                (int)
                $otp["is_used"]
                === 1
            ) {

                $error =
                    "This verification code is no longer valid.";


            } elseif (
                (int)
                $otp["attempts"]
                >= 5
            ) {

                $error =
                    "Too many incorrect attempts. Please request a new code.";


            } else {


                /*
                |--------------------------------------------------------------------------
                | CHECK EXPIRY
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $pdo->prepare("

                        SELECT

                            CASE

                                WHEN expires_at > NOW()
                                THEN 1

                                ELSE 0

                            END AS active

                        FROM login_otps

                        WHERE id = ?

                    ");


                $stmt->execute([
                    $otpId
                ]);


                $expiry =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    !$expiry
                    ||
                    (int)
                    $expiry["active"]
                    !== 1
                ) {

                    $stmt =
                        $pdo->prepare("

                            UPDATE login_otps

                            SET is_used = 1

                            WHERE id = ?

                        ");


                    $stmt->execute([
                        $otpId
                    ]);


                    $error =
                        "Your verification code has expired. Request a new code.";


                } elseif (
                    !password_verify(
                        $enteredOtp,
                        $otp["otp_hash"]
                    )
                ) {


                    /*
                    |--------------------------------------------------------------------------
                    | WRONG OTP
                    |--------------------------------------------------------------------------
                    */

                    $stmt =
                        $pdo->prepare("

                            UPDATE login_otps

                            SET attempts =
                                attempts + 1

                            WHERE id = ?

                        ");


                    $stmt->execute([
                        $otpId
                    ]);


                    $remaining =
                        4
                        -
                        (int)
                        $otp["attempts"];


                    if (
                        $remaining <= 0
                    ) {

                        $error =
                            "Too many incorrect attempts. Request a new verification code.";

                    } else {

                        $error =
                            "Incorrect verification code. "
                            . $remaining
                            . " attempt(s) remaining.";
                    }


                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | GET USER AGAIN
                    |--------------------------------------------------------------------------
                    */

                    $stmt =
                        $pdo->prepare("

                            SELECT

                                id,
                                email,
                                role,
                                is_active

                            FROM users

                            WHERE id = ?

                            LIMIT 1

                        ");


                    $stmt->execute([
                        $userId
                    ]);


                    $user =
                        $stmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (
                        !$user
                        ||
                        (int)
                        $user["is_active"]
                        !== 1
                    ) {

                        $error =
                            "Your account is unavailable.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | MARK OTP USED
                        |--------------------------------------------------------------------------
                        */

                        $stmt =
                            $pdo->prepare("

                                UPDATE login_otps

                                SET is_used = 1

                                WHERE id = ?

                            ");


                        $stmt->execute([
                            $otpId
                        ]);


                        /*
                        |--------------------------------------------------------------------------
                        | COMPLETE LOGIN
                        |--------------------------------------------------------------------------
                        */

                        session_regenerate_id(
                            true
                        );


                        $_SESSION["user_id"] =
                            $user["id"];


                        $_SESSION["email"] =
                            $user["email"];


                        $_SESSION["role"] =
                            $user["role"];


                        $redirect =
                            $_SESSION[
                                "redirect_after_login"
                            ]
                            ?? "";


                        /*
                        |--------------------------------------------------------------------------
                        | REMOVE OTP SESSION
                        |--------------------------------------------------------------------------
                        */

                        unset(

                            $_SESSION[
                                "otp_user_id"
                            ],

                            $_SESSION[
                                "otp_email"
                            ],

                            $_SESSION[
                                "otp_role"
                            ],

                            $_SESSION[
                                "otp_id"
                            ],

                            $_SESSION[
                                "redirect_after_login"
                            ]

                        );


                        /*
                        |--------------------------------------------------------------------------
                        | REQUESTED PAGE
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $redirect !== ""
                            &&
                            !str_starts_with(
                                $redirect,
                                "//"
                            )
                            &&
                            !preg_match(
                                '/^[a-z][a-z0-9+\-.]*:/i',
                                $redirect
                            )
                        ) {

                            header(
                                "Location: "
                                . $redirect
                            );

                            exit;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | DEFAULT DASHBOARD
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $user["role"]
                            === "applicant"
                        ) {

                            header(
                                "Location: applicant/dashboard.php"
                            );

                            exit;

                        } elseif (
                            $user["role"]
                            === "employer"
                        ) {

                            header(
                                "Location: employer/dashboard.php"
                            );

                            exit;
                        }


                        header(
                            "Location: index.php"
                        );

                        exit;
                    }
                }
            }


        } catch (
            PDOException $e
        ) {

            error_log(
                $e->getMessage()
            );


            $error =
                "Something went wrong. Please try again.";
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
    Verify Login - JobPortal
</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {

    font-family:
        Arial,
        sans-serif;

    background:
        #f5f7fb;

    min-height:
        100vh;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    padding:
        20px;
}


.card {

    width:
        100%;

    max-width:
        430px;

    background:
        white;

    padding:
        35px;

    border-radius:
        12px;

    box-shadow:
        0 4px 20px
        rgba(0,0,0,0.08);
}


.logo {

    text-align:
        center;

    color:
        #2563eb;

    font-weight:
        bold;

    font-size:
        26px;

    margin-bottom:
        20px;
}


h1 {

    text-align:
        center;

    margin-bottom:
        10px;
}


.description {

    text-align:
        center;

    color:
        #64748b;

    font-size:
        14px;

    line-height:
        1.6;

    margin-bottom:
        25px;
}


.error {

    padding:
        12px;

    background:
        #fee2e2;

    color:
        #991b1b;

    border-radius:
        6px;

    margin-bottom:
        20px;

    font-size:
        14px;
}


.otp-input {

    width:
        100%;

    padding:
        15px;

    border:
        1px solid #cbd5e1;

    border-radius:
        7px;

    text-align:
        center;

    font-size:
        25px;

    font-weight:
        bold;

    letter-spacing:
        10px;

    margin-bottom:
        15px;
}


.verify-btn {

    width:
        100%;

    padding:
        13px;

    border:
        none;

    background:
        #2563eb;

    color:
        white;

    border-radius:
        6px;

    font-weight:
        bold;

    cursor:
        pointer;
}


.resend {

    text-align:
        center;

    margin-top:
        20px;

    font-size:
        14px;
}


.resend a {

    color:
        #2563eb;

    text-decoration:
        none;

    font-weight:
        bold;
}


.cancel {

    display:
        block;

    margin-top:
        15px;

    text-align:
        center;

    color:
        #64748b;

    text-decoration:
        none;

    font-size:
        14px;
}

</style>

</head>


<body>


<div class="card">


    <div class="logo">

        JobPortal

    </div>


    <h1>

        Verify Login

    </h1>


    <p class="description">

        We sent a 6-digit verification
        code to

        <strong>

            <?= htmlspecialchars(
                maskEmail(
                    $email
                )
            ) ?>

        </strong>.

        <br>

        The code expires in 5 minutes.

    </p>


    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <form method="POST">


        <input
            type="text"
            name="otp"
            class="otp-input"
            maxlength="6"
            inputmode="numeric"
            autocomplete="one-time-code"
            pattern="[0-9]{6}"
            placeholder="000000"
            required
            autofocus
        >


        <button
            type="submit"
            class="verify-btn"
        >

            Verify & Login

        </button>


    </form>


    <div class="resend">

        Didn't receive the code?

        <a href="resend-otp.php">

            Resend OTP

        </a>

    </div>


    <a
        href="login.php"
        class="cancel"
    >

        ← Back to Login

    </a>


</div>


</body>

</html>