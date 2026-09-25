<?php

session_start();

date_default_timezone_set(
    "Asia/Manila"
);

require_once "config/database.php";
require_once "services/OtpMailer.php";


$error = "";


/*
|--------------------------------------------------------------------------
| OPTIONAL REDIRECT
|--------------------------------------------------------------------------
|
| Example:
|
| login.php?redirect=jobs/view.php?id=10
|
*/

$redirect =
    trim(
        $_POST["redirect"]
        ??
        $_GET["redirect"]
        ??
        ""
    );


/*
|--------------------------------------------------------------------------
| SAFE REDIRECT CHECK
|--------------------------------------------------------------------------
*/

function isSafeRedirect(
    string $url
): bool {

    if ($url === "") {
        return false;
    }


    if (
        strpos(
            $url,
            "\r"
        ) !== false
        ||
        strpos(
            $url,
            "\n"
        ) !== false
    ) {
        return false;
    }


    if (
        str_starts_with(
            $url,
            "//"
        )
    ) {
        return false;
    }


    if (
        preg_match(
            '/^[a-z][a-z0-9+\-.]*:/i',
            $url
        )
    ) {
        return false;
    }


    return true;
}


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {

    $email =
        trim(
            strtolower(
                $_POST["email"]
                ?? ""
            )
        );


    $password =
        $_POST["password"]
        ?? "";


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $email === ""
        ||
        $password === ""
    ) {

        $error =
            "Please enter your email and password.";

    } elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email address.";

    } else {

        try {


            /*
            |--------------------------------------------------------------------------
            | FIND USER
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare("

                    SELECT

                        id,
                        email,
                        password,
                        role,
                        is_active

                    FROM users

                    WHERE email = ?

                    LIMIT 1

                ");


            $stmt->execute([
                $email
            ]);


            $user =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /*
            |--------------------------------------------------------------------------
            | VERIFY USER
            |--------------------------------------------------------------------------
            */

            if (!$user) {

                $error =
                    "Invalid email or password.";

            } elseif (
                (int)
                $user["is_active"]
                !== 1
            ) {

                $error =
                    "Your account has been deactivated.";

            } elseif (
                !password_verify(
                    $password,
                    $user["password"]
                )
            ) {

                $error =
                    "Invalid email or password.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | ADMIN - DIRECT LOGIN
                |--------------------------------------------------------------------------
                */

                if (
                    $user["role"]
                    === "admin"
                ) {

                    session_regenerate_id(
                        true
                    );


                    $_SESSION["user_id"] =
                        $user["id"];


                    $_SESSION["email"] =
                        $user["email"];


                    $_SESSION["role"] =
                        $user["role"];


                    header(
                        "Location: admin/dashboard.php"
                    );

                    exit;
                }


                /*
                |--------------------------------------------------------------------------
                | APPLICANT / EMPLOYER ONLY
                |--------------------------------------------------------------------------
                */

                if (
                    !in_array(
                        $user["role"],
                        [
                            "applicant",
                            "employer"
                        ],
                        true
                    )
                ) {

                    $error =
                        "Invalid account role.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | GENERATE OTP
                    |--------------------------------------------------------------------------
                    */

                    $otp =
                        (string)
                        random_int(
                            100000,
                            999999
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | HASH OTP
                    |--------------------------------------------------------------------------
                    */

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
                        $user["id"]
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | SAVE OTP
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

                        $user["id"],

                        $otpHash

                    ]);


                    $otpId =
                        (int)
                        $pdo->lastInsertId();


                    /*
                    |--------------------------------------------------------------------------
                    | SEND EMAIL
                    |--------------------------------------------------------------------------
                    */

                    $emailSent =
                        OtpMailer::send(

                            $user["email"],

                            $otp

                        );


                    /*
                    |--------------------------------------------------------------------------
                    | EMAIL FAILED
                    |--------------------------------------------------------------------------
                    */

                    if (!$emailSent) {

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
                            "Unable to send the verification code. Please try again.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | TEMPORARY OTP SESSION
                        |--------------------------------------------------------------------------
                        |
                        | Hindi pa fully logged in.
                        |
                        */

                        session_regenerate_id(
                            true
                        );


                        unset(
                            $_SESSION["user_id"],
                            $_SESSION["email"],
                            $_SESSION["role"]
                        );


                        $_SESSION["otp_user_id"] =
                            (int)
                            $user["id"];


                        $_SESSION["otp_email"] =
                            $user["email"];


                        $_SESSION["otp_role"] =
                            $user["role"];


                        $_SESSION["otp_id"] =
                            $otpId;


                        /*
                        |--------------------------------------------------------------------------
                        | STORE REDIRECT
                        |--------------------------------------------------------------------------
                        */

                        if (
                            isSafeRedirect(
                                $redirect
                            )
                        ) {

                            $_SESSION[
                                "redirect_after_login"
                            ] =
                                $redirect;

                        } else {

                            unset(
                                $_SESSION[
                                    "redirect_after_login"
                                ]
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | GO TO OTP
                        |--------------------------------------------------------------------------
                        */

                        header(
                            "Location: verify-otp.php"
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
    Login - Caloocan Job Portal
</title>


<link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            rgba(0,0,0,0.35),
            rgba(0,0,0,0.35)
        ),
        url(
            "assets/images/caloocan-bg.png"
        )
        center center / cover no-repeat fixed;

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


.login-container {

    width:
        100%;

    max-width:
        420px;

    background:
        #ffffff;

    padding:
        35px;

    border-radius:
        10px;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,0.08);
}


.logo {

    text-align:
        center;

    font-size:
        26px;

    font-weight:
        bold;

    color:
        #2563eb;

    margin-bottom:
        10px;
}


h1 {

    text-align:
        center;

    font-size:
        23px;

    margin-bottom:
        8px;
}


.subtitle {

    text-align:
        center;

    color:
        #64748b;

    font-size:
        14px;

    margin-bottom:
        25px;
}


.form-group {

    margin-bottom:
        18px;
}


label {

    display:
        block;

    font-size:
        14px;

    font-weight:
        bold;

    margin-bottom:
        7px;
}


input {

    width:
        100%;

    padding:
        13px;

    border:
        1px solid #cbd5e1;

    border-radius:
        6px;

    font-size:
        14px;

    outline:
        none;
}


input:focus {

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 2px
        rgba(37,99,235,0.08);
}


.password-input {

    position:
        relative;
}


.password-input input {

    padding-right:
        45px;
}


.toggle-password {

    position:
        absolute;

    right:
        13px;

    top:
        50%;

    transform:
        translateY(-50%);

    color:
        #64748b;

    cursor:
        pointer;

    font-size:
        18px;
}


.error {

    background:
        #fee2e2;

    color:
        #b91c1c;

    padding:
        12px;

    border-radius:
        6px;

    margin-bottom:
        20px;

    font-size:
        14px;
}


.login-btn {

    width:
        100%;

    padding:
        13px;

    background:
        #2563eb;

    color:
        white;

    border:
        none;

    border-radius:
        6px;

    font-size:
        15px;

    font-weight:
        bold;

    cursor:
        pointer;
}


.login-btn:hover {

    background:
        #1d4ed8;
}


.forgot {

    text-align:
        right;

    margin-top:
        -8px;

    margin-bottom:
        15px;
}


.forgot a {

    color:
        #2563eb;

    text-decoration:
        none;

    font-size:
        13px;
}


.register-link {

    text-align:
        center;

    margin-top:
        20px;

    color:
        #64748b;

    font-size:
        14px;
}


.register-link a {

    color:
        #2563eb;

    font-weight:
        bold;

    text-decoration:
        none;
}


.back-home {

    text-align:
        center;

    margin-top:
        15px;

    font-size:
        14px;
}


.back-home a {

    color:
        #64748b;

    text-decoration:
        none;
}

</style>

</head>


<body>


<div class="login-container">


    <div class="logo">
        JobPortal
    </div>


    <h1>
        Welcome Back
    </h1>


    <p class="subtitle">

        Login to continue to your account.

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
            type="hidden"
            name="redirect"
            value="<?= htmlspecialchars(
                $redirect
            ) ?>"
        >


        <div class="form-group">

            <label for="email">

                Email Address

            </label>


            <input
                type="email"
                name="email"
                id="email"
                placeholder="you@example.com"
                value="<?= htmlspecialchars(
                    $_POST["email"]
                    ?? ""
                ) ?>"
                required
            >

        </div>


        <div class="form-group">

            <label for="password">

                Password

            </label>


            <div class="password-input">


                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Enter your password"
                    required
                >


                <span
                    class="toggle-password"
                    onclick="togglePassword()"
                    id="eyeIcon"
                >

                    <i class="bi bi-eye"></i>

                </span>


            </div>

        </div>


        <div class="forgot">

            <a href="forgot-password.php">

                Forgot Password?

            </a>

        </div>


        <button
            type="submit"
            class="login-btn"
        >

            Login

        </button>


    </form>


    <div class="register-link">

        Don't have an account?

        <a href="register.php">

            Create one

        </a>

    </div>


    <div class="back-home">

        <a href="index.php">

            ← Back to Home

        </a>

    </div>


</div>


<script>

function togglePassword()
{

    const password =
        document.getElementById(
            "password"
        );


    const eyeIcon =
        document.getElementById(
            "eyeIcon"
        );


    if (
        password.type
        === "password"
    ) {

        password.type =
            "text";


        eyeIcon.innerHTML =
            '<i class="bi bi-eye-slash"></i>';

    } else {

        password.type =
            "password";


        eyeIcon.innerHTML =
            '<i class="bi bi-eye"></i>';
    }

}

</script>


</body>

</html>