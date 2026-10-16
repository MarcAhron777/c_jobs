<?php

session_start();

require_once "config/database.php";

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| DEFAULT FORM VALUES
|--------------------------------------------------------------------------
*/

$role = $_POST["role"] ?? "";

$firstName = trim($_POST["first_name"] ?? "");
$lastName = trim($_POST["last_name"] ?? "");
$phone = trim($_POST["phone"] ?? "");

$employerType = trim($_POST["employer_type"] ?? "");
$companyName = trim($_POST["company_name"] ?? "");

$email = trim($_POST["email"] ?? "");


/*
|--------------------------------------------------------------------------
| REGISTER
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    /*
    |--------------------------------------------------------------------------
    | BASIC VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $email === "" ||
        $password === "" ||
        $role === ""
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (
        !in_array(
            $role,
            ["applicant", "employer"],
            true
        )
    ) {
        $error = "Invalid account type.";
    }


    /*
    |--------------------------------------------------------------------------
    | APPLICANT VALIDATION
    |--------------------------------------------------------------------------
    */

    elseif (
        $role === "applicant" &&
        (
            $firstName === "" ||
            $lastName === ""
        )
    ) {
        $error = "First name and last name are required.";
    }


    /*
    |--------------------------------------------------------------------------
    | EMPLOYER VALIDATION
    |--------------------------------------------------------------------------
    */

    elseif (
        $role === "employer" &&
        !in_array(
            $employerType,
            ["private", "lgu"],
            true
        )
    ) {
        $error = "Please select an employer type.";
    } elseif (
        $role === "employer" &&
        $companyName === ""
    ) {
        $error = "Company / Organization Name is required.";
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE ACCOUNT
    |--------------------------------------------------------------------------
    */

    else {
        try {
            /*
            |--------------------------------------------------------------------------
            | CHECK EMAIL
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            if ($stmt->fetch()) {
                $error = "An account with this email already exists.";
            } else {
                /*
                |--------------------------------------------------------------------------
                | HASH PASSWORD
                |--------------------------------------------------------------------------
                */

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /*
                |--------------------------------------------------------------------------
                | START TRANSACTION
                |--------------------------------------------------------------------------
                */

                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | CREATE USER
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        email,
                        password,
                        role
                    )
                    VALUES (?, ?, ?)
                ");

                $stmt->execute([
                    $email,
                    $passwordHash,
                    $role
                ]);

                $userId = (int) $pdo->lastInsertId();


                /*
                |--------------------------------------------------------------------------
                | CREATE APPLICANT PROFILE
                |--------------------------------------------------------------------------
                */

                if ($role === "applicant") {
                    $stmt = $pdo->prepare("
                        INSERT INTO applicants (
                            user_id,
                            first_name,
                            last_name,
                            phone
                        )
                        VALUES (?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $userId,
                        $firstName,
                        $lastName,
                        $phone !== ""
                            ? $phone
                            : null
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | CREATE EMPLOYER PROFILE
                |--------------------------------------------------------------------------
                */

                if ($role === "employer") {
                    $stmt = $pdo->prepare("
                        INSERT INTO employers (
                            user_id,
                            company_name,
                            employer_type
                        )
                        VALUES (?, ?, ?)
                    ");

                    $stmt->execute([
                        $userId,
                        $companyName,
                        $employerType
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $pdo->commit();

                $success =
                    "Registration successful! You can now login.";


                /*
                |--------------------------------------------------------------------------
                | CLEAR FORM
                |--------------------------------------------------------------------------
                */

                $role = "";
                $firstName = "";
                $lastName = "";
                $phone = "";

                $employerType = "";
                $companyName = "";

                $email = "";
            }

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log(
                "Registration error: " .
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
        Register - Caloocan Job Portal
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
            font-family: Arial, Helvetica, sans-serif;

            background:
                linear-gradient(
                    rgba(0, 0, 0, 0.35),
                    rgba(0, 0, 0, 0.35)
                ),
                url("assets/images/caloocan-bg.png")
                center center / cover no-repeat fixed;

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 30px;
        }


        /*
        |--------------------------------------------------------------------------
        | REGISTER CONTAINER
        |--------------------------------------------------------------------------
        */

        .register-container {
            width: 100%;
            max-width: 500px;

            background: #ffffff;

            padding: 35px;

            border-radius: 10px;

            box-shadow:
                0 10px 30px rgba(0, 0, 0, 0.08);
        }


        /*
        |--------------------------------------------------------------------------
        | LOGO
        |--------------------------------------------------------------------------
        */

        .logo {
            text-align: center;

            font-size: 26px;
            font-weight: bold;

            color: #2563eb;

            margin-bottom: 10px;
        }


        /*
        |--------------------------------------------------------------------------
        | TITLE
        |--------------------------------------------------------------------------
        */

        .title {
            text-align: center;

            font-size: 22px;

            margin-bottom: 8px;
        }


        .subtitle {
            text-align: center;

            color: #64748b;

            font-size: 14px;

            margin-bottom: 25px;
        }


        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-group {
            margin-bottom: 17px;
        }


        label {
            display: block;

            font-size: 14px;
            font-weight: bold;

            margin-bottom: 7px;
        }


        input,
        select {
            width: 100%;

            padding: 12px;

            border: 1px solid #cbd5e1;
            border-radius: 6px;

            font-size: 14px;

            outline: none;

            transition: 0.2s;
        }


        input:focus,
        select:focus {
            border-color: #2563eb;

            box-shadow:
                0 0 0 2px rgba(37, 99, 235, 0.08);
        }


        /*
        |--------------------------------------------------------------------------
        | EMPLOYER TYPE INFO
        |--------------------------------------------------------------------------
        */

        .field-help {
            display: block;

            margin-top: 6px;

            color: #64748b;

            font-size: 12px;
            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | PASSWORD INPUT
        |--------------------------------------------------------------------------
        */

        .password-input {
            position: relative;

            width: 100%;
        }


        .password-input input {
            padding-right: 45px;
        }


        /*
        |--------------------------------------------------------------------------
        | EYE ICON
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | SUBMIT BUTTON
        |--------------------------------------------------------------------------
        */

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


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .error {
            background: #fee2e2;

            color: #b91c1c;

            padding: 12px;

            border-radius: 6px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        .success {
            background: #dcfce7;

            color: #166534;

            padding: 12px;

            border-radius: 6px;

            margin-bottom: 20px;

            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | LOGIN LINK
        |--------------------------------------------------------------------------
        */

        .login-link {
            text-align: center;

            margin-top: 20px;

            font-size: 14px;

            color: #64748b;
        }


        .login-link a {
            color: #2563eb;

            font-weight: bold;

            text-decoration: none;
        }


        .login-link a:hover {
            text-decoration: underline;
        }


        /*
        |--------------------------------------------------------------------------
        | ROLE FIELDS
        |--------------------------------------------------------------------------
        */

        .role-fields {
            display: none;
        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 600px) {

            body {
                padding: 15px;
            }

            .register-container {
                padding: 25px 20px;
            }

        }

    </style>

</head>


<body>


<div class="register-container">


    <!-- LOGO -->

    <div class="logo">
        JobPortal
    </div>


    <!-- TITLE -->

    <h1 class="title">
        Create an Account
    </h1>


    <p class="subtitle">
        Join JobPortal and find your next opportunity.
    </p>


    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <!-- SUCCESS -->

    <?php if ($success): ?>

        <div class="success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <!-- FORM -->

    <form method="POST">


        <!-- ACCOUNT TYPE -->

        <div class="form-group">

            <label for="role">
                I want to
            </label>

            <select
                name="role"
                id="role"
                required
                onchange="showRoleFields()"
            >

                <option value="">
                    Select account type
                </option>


                <option
                    value="applicant"
                    <?= $role === "applicant"
                        ? "selected"
                        : ""
                    ?>
                >
                    Find a Job
                </option>


                <option
                    value="employer"
                    <?= $role === "employer"
                        ? "selected"
                        : ""
                    ?>
                >
                    Hire Employees
                </option>

            </select>

        </div>


        <!-- =====================================================
             APPLICANT FIELDS
        ====================================================== -->

        <div
            id="applicant-fields"
            class="role-fields"
        >

            <div class="form-group">

                <label for="first_name">
                    First Name
                </label>

                <input
                    type="text"
                    name="first_name"
                    id="first_name"
                    value="<?= htmlspecialchars(
                        $firstName
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label for="last_name">
                    Last Name
                </label>

                <input
                    type="text"
                    name="last_name"
                    id="last_name"
                    value="<?= htmlspecialchars(
                        $lastName
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label for="phone">
                    Phone Number
                </label>

                <input
                    type="text"
                    name="phone"
                    id="phone"
                    value="<?= htmlspecialchars(
                        $phone
                    ) ?>"
                >

            </div>

        </div>


        <!-- =====================================================
             EMPLOYER FIELDS
        ====================================================== -->

        <div
            id="employer-fields"
            class="role-fields"
        >


            <!-- EMPLOYER TYPE -->

            <div class="form-group">

                <label for="employer_type">
                    Employer Type
                </label>

                <select
                    name="employer_type"
                    id="employer_type"
                >

                    <option value="">
                        Select employer type
                    </option>


                    <option
                        value="private"
                        <?= $employerType === "private"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Private Employer
                    </option>


                    <option
                        value="lgu"
                        <?= $employerType === "lgu"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Local Government Unit (LGU)
                    </option>

                </select>

                <small class="field-help">
                    LGU accounts are still subject to
                    administrator verification before
                    job posting access is approved.
                </small>

            </div>


            <!-- COMPANY / ORGANIZATION -->

            <div class="form-group">

                <label for="company_name">
                    Company / Organization Name
                </label>

                <input
                    type="text"
                    name="company_name"
                    id="company_name"
                    value="<?= htmlspecialchars(
                        $companyName
                    ) ?>"
                >

            </div>

        </div>


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
                value="<?= htmlspecialchars($email) ?>"
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

        </div>


        <!-- CONFIRM PASSWORD -->

        <div class="form-group">

            <label for="confirm_password">
                Confirm Password
            </label>


            <div class="password-input">

                <input
                    type="password"
                    name="confirm_password"
                    id="confirm_password"
                    placeholder="Confirm your password"
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

        </div>


        <!-- SUBMIT -->

        <button type="submit">
            Create Account
        </button>

    </form>


    <!-- LOGIN LINK -->

    <div class="login-link">

        Already have an account?

        <a href="login.php">
            Login
        </a>

    </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| SHOW ROLE FIELDS
|--------------------------------------------------------------------------
*/

function showRoleFields() {
    const role =
        document.getElementById("role").value;

    const applicantFields =
        document.getElementById(
            "applicant-fields"
        );

    const employerFields =
        document.getElementById(
            "employer-fields"
        );

    const firstName =
        document.getElementById(
            "first_name"
        );

    const lastName =
        document.getElementById(
            "last_name"
        );

    const employerType =
        document.getElementById(
            "employer_type"
        );

    const companyName =
        document.getElementById(
            "company_name"
        );


    /*
    |--------------------------------------------------------------------------
    | HIDE EVERYTHING FIRST
    |--------------------------------------------------------------------------
    */

    applicantFields.style.display = "none";
    employerFields.style.display = "none";

    firstName.required = false;
    lastName.required = false;

    employerType.required = false;
    companyName.required = false;


    /*
    |--------------------------------------------------------------------------
    | APPLICANT
    |--------------------------------------------------------------------------
    */

    if (role === "applicant") {
        applicantFields.style.display =
            "block";

        firstName.required = true;
        lastName.required = true;
    }


    /*
    |--------------------------------------------------------------------------
    | EMPLOYER
    |--------------------------------------------------------------------------
    */

    if (role === "employer") {
        employerFields.style.display =
            "block";

        employerType.required = true;
        companyName.required = true;
    }
}


/*
|--------------------------------------------------------------------------
| SHOW / HIDE PASSWORD
|--------------------------------------------------------------------------
*/

function togglePassword(
    passwordId,
    iconId
) {
    const password =
        document.getElementById(
            passwordId
        );

    const eyeIcon =
        document.getElementById(
            iconId
        );

    if (password.type === "password") {
        password.type = "text";

        eyeIcon.innerHTML =
            '<i class="bi bi-eye-slash"></i>';

        eyeIcon.title =
            "Hide password";
    } else {
        password.type = "password";

        eyeIcon.innerHTML =
            '<i class="bi bi-eye"></i>';

        eyeIcon.title =
            "Show password";
    }
}


/*
|--------------------------------------------------------------------------
| LOAD CORRECT FIELDS ON PAGE LOAD
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        showRoleFields();
    }
);

</script>


</body>

</html>