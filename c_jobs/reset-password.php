<?php

session_start();

require_once "config/database.php";


$error = "";
$success = "";

$token = trim(
    $_GET["token"]
    ?? $_POST["token"]
    ?? ""
);


if ($token === "") {

    die("Invalid or missing password reset token.");

}


/*
|--------------------------------------------------------------------------
| Hash Token
|--------------------------------------------------------------------------
*/

$tokenHash = hash(
    "sha256",
    $token
);


/*
|--------------------------------------------------------------------------
| Find Valid Token
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        email,
        role
    FROM users

    WHERE reset_token = ?

    AND reset_token_expires_at IS NOT NULL

    AND reset_token_expires_at > NOW()

    AND is_active = 1

    LIMIT 1
");

$stmt->execute([
    $tokenHash
]);

$user = $stmt->fetch();


if (!$user) {

    die("
        <div style=\"
            font-family:Arial;
            text-align:center;
            padding:60px;
        \">

            <h2>
                Invalid or Expired Link
            </h2>

            <p style=\"margin-top:10px;\">

                This password reset link is invalid
                or has already expired.

            </p>

            <p style=\"margin-top:20px;\">

                <a href=\"forgot-password.php\">
                    Request a new reset link
                </a>

            </p>

        </div>
    ");

}


/*
|--------------------------------------------------------------------------
| Reset Password
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $password = $_POST["password"] ?? "";

    $confirmPassword =
        $_POST["confirm_password"]
        ?? "";


    /*
    |--------------------------------------------------------------------------
    | Validate Password
    |--------------------------------------------------------------------------
    */

    if (strlen($password) < 8) {

        $error =
            "Password must be at least 8 characters.";

    } elseif ($password !== $confirmPassword) {

        $error =
            "Passwords do not match.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Hash Password
        |--------------------------------------------------------------------------
        */

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        /*
        |--------------------------------------------------------------------------
        | Update Password
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE users

            SET
                password = ?,
                reset_token = NULL,
                reset_token_expires_at = NULL

            WHERE id = ?
        ");


        $stmt->execute([
            $passwordHash,
            $user["id"]
        ]);


        $success =
            "Your password has been reset successfully.";

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
    Reset Password - Caloocan Job Portal
</title>


<!-- Bootstrap Icons -->

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

    font-family: Arial, sans-serif;

    background: #f5f7fb;

    min-height: 100vh;

    display: flex;

    justify-content: center;

    align-items: center;

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

    text-align: center;

    font-size: 24px;

    margin-bottom: 10px;
}


.description {

    text-align: center;

    color: #666;

    font-size: 14px;

    margin-bottom: 25px;
}


label {

    display: block;

    margin-bottom: 7px;

    font-weight: bold;

    font-size: 14px;
}


/* =========================
   PASSWORD INPUT
   ========================= */

.password-input {

    position: relative;

    width: 100%;

    margin-bottom: 17px;
}


.password-input input {

    width: 100%;

    padding: 12px;

    padding-right: 45px;

    border: 1px solid #ccc;

    border-radius: 6px;

    font-size: 14px;

    outline: none;

    margin-bottom: 0;

    transition: 0.2s;
}


.password-input input:focus {

    border-color: #2563eb;

    box-shadow:
        0 0 0 2px rgba(37, 99, 235, 0.08);
}


/* =========================
   EYE ICON
   ========================= */

.toggle-password {

    position: absolute;

    right: 13px;

    top: 50%;

    transform: translateY(-50%);

    color: #64748b;

    cursor: pointer;

    font-size: 18px;

    line-height: 1;

    display: flex;

    align-items: center;

    justify-content: center;

    user-select: none;

    transition: 0.2s;
}


.toggle-password:hover {

    color: #2563eb;
}


/* =========================
   RESET BUTTON
   ========================= */

button[type="submit"] {

    width: 100%;

    padding: 12px;

    background: #2563eb;

    color: white;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    font-weight: bold;

    font-size: 15px;

    transition: 0.2s;
}


button[type="submit"]:hover {

    background: #1d4ed8;
}


/* =========================
   MESSAGE
   ========================= */

.message {

    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;

    text-align: center;

    font-size: 14px;
}


.success {

    background: #dcfce7;

    color: #166534;
}


.error {

    background: #fee2e2;

    color: #991b1b;
}


/* =========================
   LOGIN LINK
   ========================= */

.login-link {

    display: block;

    text-align: center;

    margin-top: 20px;

    color: #2563eb;

    text-decoration: none;

    font-size: 14px;
}


.login-link:hover {

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


        <?php if ($success): ?>


            <h1>
                Password Reset
            </h1>


            <div class="message success">

                <?= htmlspecialchars(
                    $success
                ) ?>

            </div>


            <a
                href="login.php"
                class="login-link"
            >

                ← Go to Login

            </a>


        <?php else: ?>


            <h1>
                Reset Password
            </h1>


            <p class="description">

                Create a new password for your account.

            </p>


            <?php if ($error): ?>

                <div class="message error">

                    <?= htmlspecialchars(
                        $error
                    ) ?>

                </div>

            <?php endif; ?>


            <form method="POST">


                <!-- RESET TOKEN -->

                <input
                    type="hidden"
                    name="token"
                    value="<?= htmlspecialchars(
                        $token
                    ) ?>"
                >


                <!-- NEW PASSWORD -->

                <label for="password">
                    New Password
                </label>


                <div class="password-input">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter new password"
                        minlength="8"
                        required
                    >


                    <span
                        class="toggle-password"
                        onclick="togglePassword(
                            'password',
                            'eyePassword'
                        )"
                        id="eyePassword"
                        title="Show password"
                    >

                        <i class="bi bi-eye"></i>

                    </span>

                </div>


                <!-- CONFIRM PASSWORD -->

                <label for="confirm_password">
                    Confirm Password
                </label>


                <div class="password-input">

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        placeholder="Confirm new password"
                        minlength="8"
                        required
                    >


                    <span
                        class="toggle-password"
                        onclick="togglePassword(
                            'confirm_password',
                            'eyeConfirmPassword'
                        )"
                        id="eyeConfirmPassword"
                        title="Show password"
                    >

                        <i class="bi bi-eye"></i>

                    </span>

                </div>


                <!-- RESET BUTTON -->

                <button type="submit">

                    Reset Password

                </button>


            </form>


        <?php endif; ?>


    </div>


</div>


<script>

/*
|--------------------------------------------------------------------------
| SHOW / HIDE PASSWORD
|--------------------------------------------------------------------------
*/

function togglePassword(passwordId, iconId) {

    const password =
        document.getElementById(passwordId);

    const eyeIcon =
        document.getElementById(iconId);


    if (password.type === "password") {

        // Show password

        password.type = "text";

        eyeIcon.innerHTML =
            '<i class="bi bi-eye-slash"></i>';

        eyeIcon.title =
            "Hide password";

    } else {

        // Hide password

        password.type = "password";

        eyeIcon.innerHTML =
            '<i class="bi bi-eye"></i>';

        eyeIcon.title =
            "Show password";

    }

}

</script>


</body>

</html>