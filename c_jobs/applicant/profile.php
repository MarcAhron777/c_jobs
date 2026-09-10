<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "applicant") {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION["user_id"];

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| Update Profile
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $firstName = trim($_POST["first_name"] ?? "");
    $lastName = trim($_POST["last_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $bio = trim($_POST["bio"] ?? "");


    if ($firstName === "" || $lastName === "") {

        $error = "First name and last name are required.";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE applicants

                SET
                    first_name = ?,
                    last_name = ?,
                    phone = ?,
                    address = ?,
                    bio = ?

                WHERE user_id = ?
            ");

            $stmt->execute([
                $firstName,
                $lastName,
                $phone ?: null,
                $address ?: null,
                $bio ?: null,
                $userId
            ]);

            $success = "Profile updated successfully.";

        } catch (PDOException $e) {

            $error = "Failed to update profile.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Get Profile
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        first_name,
        last_name,
        phone,
        address,
        bio,
        profile_picture

    FROM applicants

    WHERE user_id = ?

    LIMIT 1
");

$stmt->execute([$userId]);

$applicant = $stmt->fetch();

if (!$applicant) {
    die("Applicant profile not found.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        My Profile - Caloocan Job Portal
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
            color: #333;
        }

        .navbar {
            background: white;
            border-bottom: 1px solid #ddd;
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        h1 {
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
        }

        input,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 15px;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        button {
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover {
            background: #1d4ed8;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2563eb;
        }

        @media (max-width: 600px) {

            .row {
                grid-template-columns: 1fr;
            }

            .navbar {
                padding: 15px 20px;
            }

        }

    </style>

</head>

<body>


<nav class="navbar">

    <div class="logo">
        JobPortal
    </div>

    <div class="nav-links">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="resume.php">
            Resume
        </a>

        <a href="applications.php">
            Applications
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</nav>


<div class="container">

    <a href="dashboard.php" class="back">
        ← Back to Dashboard
    </a>


    <div class="card">

        <h1>
            My Profile
        </h1>

        <p class="subtitle">
            Update your personal information.
        </p>


        <?php if ($success): ?>

            <div class="message success">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST">


            <div class="row">

                <div class="form-group">

                    <label>
                        First Name
                    </label>

                    <input
                        type="text"
                        name="first_name"
                        value="<?= htmlspecialchars(
                            $applicant["first_name"]
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Last Name
                    </label>

                    <input
                        type="text"
                        name="last_name"
                        value="<?= htmlspecialchars(
                            $applicant["last_name"]
                        ) ?>"
                        required
                    >

                </div>

            </div>


            <div class="form-group">

                <label>
                    Phone
                </label>

                <input
                    type="text"
                    name="phone"
                    value="<?= htmlspecialchars(
                        $applicant["phone"] ?? ""
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    Address
                </label>

                <input
                    type="text"
                    name="address"
                    value="<?= htmlspecialchars(
                        $applicant["address"] ?? ""
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label>
                    Bio
                </label>

                <textarea
                    name="bio"
                    placeholder="Tell employers a little about yourself..."
                ><?= htmlspecialchars(
                    $applicant["bio"] ?? ""
                ) ?></textarea>

            </div>


            <button type="submit">
                Save Changes
            </button>

        </form>

    </div>

</div>

</body>

</html>