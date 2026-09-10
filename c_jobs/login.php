<?php

session_start();

require_once "config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {

        $error = "Please enter your email and password.";

    } else {

        try {

            $stmt = $pdo->prepare("
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

            $stmt->execute([$email]);

            $user = $stmt->fetch();

            if (!$user) {

                $error = "Invalid email or password.";

            } elseif (!$user["is_active"]) {

                $error = "Your account has been deactivated.";

            } elseif (!password_verify($password, $user["password"])) {

                $error = "Invalid email or password.";

            } else {

                // Regenerate session ID
                session_regenerate_id(true);

                // Store user information
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];

                // Redirect based on role

                if ($user["role"] === "applicant") {

                    header("Location: applicant/dashboard.php");
                    exit;

                } elseif ($user["role"] === "employer") {

                    header("Location: employer/dashboard.php");
                    exit;

                } elseif ($user["role"] === "admin") {

                    header("Location: admin/dashboard.php");
                    exit;

                } else {

                    $error = "Invalid account role.";

                }
            }

        } catch (PDOException $e) {

            $error = "Something went wrong. Please try again.";

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

    <title>Login - Caloocan Job Portal</title>


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

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f1f5f9;

            min-height: 100vh;

            display: flex;

            align-items: center;

            justify-content: center;

            padding: 20px;
        }


        /* =========================
           LOGIN CONTAINER
           ========================= */

        .login-container {

            width: 100%;

            max-width: 420px;

            background: #ffffff;

            padding: 35px;

            border-radius: 10px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.08);
        }


        /* =========================
           LOGO
           ========================= */

        .logo {

            text-align: center;

            font-size: 26px;

            font-weight: bold;

            color: #2563eb;

            margin-bottom: 10px;
        }


        /* =========================
           TITLE
           ========================= */

        h1 {

            text-align: center;

            font-size: 23px;

            margin-bottom: 8px;
        }


        .subtitle {

            text-align: center;

            color: #64748b;

            font-size: 14px;

            margin-bottom: 25px;
        }


        /* =========================
           FORM
           ========================= */

        .form-group {

            margin-bottom: 18px;
        }


        label {

            display: block;

            font-size: 14px;

            font-weight: bold;

            margin-bottom: 7px;
        }


        input {

            width: 100%;

            padding: 13px;

            border: 1px solid #cbd5e1;

            border-radius: 6px;

            font-size: 14px;

            outline: none;

            transition: 0.2s;
        }


        input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px rgba(37, 99, 235, 0.08);
        }


        /* =========================
           PASSWORD INPUT
           ========================= */

        .password-input {

            position: relative;

            width: 100%;
        }


        .password-input input {

            padding-right: 45px;
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
           ERROR
           ========================= */

        .error {

            background: #fee2e2;

            color: #b91c1c;

            padding: 12px;

            border-radius: 6px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        /* =========================
           LOGIN BUTTON
           ========================= */

        button[type="submit"] {

            width: 100%;

            padding: 13px;

            background: #2563eb;

            color: white;

            border: none;

            border-radius: 6px;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;

            transition: 0.2s;
        }


        button[type="submit"]:hover {

            background: #1d4ed8;
        }


        /* =========================
           REGISTER LINK
           ========================= */

        .register-link {

            text-align: center;

            margin-top: 20px;

            color: #64748b;

            font-size: 14px;
        }


        .register-link a {

            color: #2563eb;

            font-weight: bold;

            text-decoration: none;
        }


        .register-link a:hover {

            text-decoration: underline;
        }


        /* =========================
           BACK HOME
           ========================= */

        .back-home {

            text-align: center;

            margin-top: 15px;

            font-size: 14px;
        }


        .back-home a {

            color: #64748b;

            text-decoration: none;
        }


        .back-home a:hover {

            color: #2563eb;
        }

    </style>

</head>


<body>


<div class="login-container">


    <!-- LOGO -->

    <div class="logo">
        JobPortal
    </div>


    <!-- TITLE -->

    <h1>
        Welcome Back
    </h1>


    <p class="subtitle">
        Login to continue to your account.
    </p>


    <!-- ERROR MESSAGE -->

    <?php if ($error): ?>

        <div class="error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- LOGIN FORM -->

    <form method="POST">


        <!-- EMAIL -->

        <div class="form-group">

            <label for="email">
                Email Address
            </label>

            <input
                type="email"
                name="email"
                id="email"
                placeholder="you@example.com"
                required
            >

        </div>


        <!-- PASSWORD -->

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


                <!-- EYE GLYPH -->

                <span
                    class="toggle-password"
                    onclick="togglePassword()"
                    id="eyeIcon"
                    title="Show password"
                >

                    <i class="bi bi-eye"></i>

                </span>

            </div>

        </div>


        <!-- FORGOT PASSWORD -->

        <div
            style="
                text-align: right;
                margin-top: -8px;
                margin-bottom: 15px;
            "
        >

            <a
                href="forgot-password.php"
                style="
                    color: #2563eb;
                    text-decoration: none;
                    font-size: 13px;
                "
            >

                Forgot Password

            </a>

        </div>


        <!-- LOGIN BUTTON -->

        <button type="submit">

            Login

        </button>


    </form>


    <!-- REGISTER -->

    <div class="register-link">

        Don't have an account?

        <a href="register.php">
            Create one
        </a>

    </div>


    <!-- BACK HOME -->

    <div class="back-home">

        <a href="index.php">

            ← Back to Home

        </a>

    </div>


</div>


<!-- =========================
     SHOW / HIDE PASSWORD
     ========================= -->

<script>

function togglePassword() {

    const password =
        document.getElementById("password");

    const eyeIcon =
        document.getElementById("eyeIcon");


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
