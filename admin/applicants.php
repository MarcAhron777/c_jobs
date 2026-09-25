<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}


// =========================
// ACTIVATE / DEACTIVATE
// =========================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $userId = $_POST["user_id"] ?? null;
    $action = $_POST["action"] ?? null;

    if ($userId && in_array($action, ["activate", "deactivate"], true)) {

        $isActive = $action === "activate" ? 1 : 0;

        $stmt = $pdo->prepare("
            UPDATE users
            SET is_active = ?
            WHERE id = ?
            AND role = 'applicant'
        ");

        $stmt->execute([
            $isActive,
            $userId
        ]);
    }

    header("Location: applicants.php");
    exit;
}


// =========================
// GET APPLICANTS
// =========================

$stmt = $pdo->query("
    SELECT
        a.id,
        a.user_id,
        a.first_name,
        a.last_name,
        a.phone,
        a.address,
        a.bio,
        a.created_at,
        u.email,
        u.is_active,

        (
            SELECT COUNT(*)
            FROM applications app
            WHERE app.applicant_id = a.id
        ) AS total_applications,

        (
            SELECT COUNT(*)
            FROM resumes r
            WHERE r.applicant_id = a.id
        ) AS total_resumes

    FROM applicants a

    INNER JOIN users u
        ON a.user_id = u.id

    ORDER BY a.created_at DESC
");

$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Applicants - Admin</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
        }

        .navbar {
            background: #111827;
            padding: 18px 50px;
        }

        .navbar-container {
            max-width: 1200px;
            margin: auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: white;
            text-decoration: none;
            font-size: 22px;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .logout {
            background: #dc2626;
            padding: 8px 14px;
            border-radius: 5px;
        }

        .container {
            max-width: 1250px;
            margin: 40px auto;
            padding: 0 20px;
        }

        h1 {
            margin-bottom: 10px;
        }

        .description {
            color: #666;
            margin-bottom: 30px;
        }

        .table-container {
            background: white;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f9fafb;
            font-size: 13px;
        }

        td {
            font-size: 14px;
        }

        .status {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .active {
            background: #dcfce7;
            color: #166534;
        }

        .inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn {
            border: none;
            padding: 7px 12px;
            border-radius: 4px;
            cursor: pointer;
            color: white;
            font-size: 12px;
        }

        .activate {
            background: #16a34a;
        }

        .deactivate {
            background: #dc2626;
        }

        .view-btn {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 7px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
        }

    </style>

</head>

<body>


<nav class="navbar">

    <div class="navbar-container">

        <a href="dashboard.php" class="logo">
            Job Portal Admin
        </a>

        <div class="nav-links">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="jobs.php">
                Manage Jobs
            </a>

            <a href="employers.php">
                Manage Employers
            </a>

            <a href="applicants.php">
                Manage Applicants
            </a>

            <a href="../logout.php" class="logout">
                Logout
            </a>

        </div>

    </div>

</nav>


<div class="container">

    <h1>
        Applicants
    </h1>

    <p class="description">
        View and manage registered applicants.
    </p>


    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>
                        Name
                    </th>

                    <th>
                        Email
                    </th>

                    <th>
                        Phone
                    </th>

                    <th>
                        Applications
                    </th>

                    <th>
                        Resumes
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Registered
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (empty($applicants)): ?>

                    <tr>

                        <td colspan="8">
                            No applicants found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($applicants as $applicant): ?>

                        <tr>

                            <td>

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $applicant["first_name"]
                                        . " "
                                        . $applicant["last_name"]
                                    );
                                    ?>

                                </strong>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $applicant["email"]
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo htmlspecialchars(
                                    $applicant["phone"] ?: "N/A"
                                );
                                ?>

                            </td>


                            <td>

                                <?php
                                echo $applicant["total_applications"];
                                ?>

                            </td>


                            <td>

                                <?php
                                echo $applicant["total_resumes"];
                                ?>

                            </td>


                            <td>

                                <?php if ($applicant["is_active"]): ?>

                                    <span class="status active">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="status inactive">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php
                                echo date(
                                    "M d, Y",
                                    strtotime($applicant["created_at"])
                                );
                                ?>

                            </td>


                            <td>

                                <form method="POST">

                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?php echo $applicant["user_id"]; ?>"
                                    >


                                    <?php if ($applicant["is_active"]): ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="deactivate"
                                        >

                                        <button
                                            type="submit"
                                            class="btn deactivate"
                                        >
                                            Deactivate
                                        </button>

                                    <?php else: ?>

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="activate"
                                        >

                                        <button
                                            type="submit"
                                            class="btn activate"
                                        >
                                            Activate
                                        </button>

                                    <?php endif; ?>

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


</body>

</html>