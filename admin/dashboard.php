<?php

session_start();

require_once "../config/database.php";

// Admin access only
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login.php");
    exit;
}

// Total users
$stmt = $pdo->query("
    SELECT COUNT(*) 
    FROM users
    WHERE role != 'admin'
");

$totalUsers = $stmt->fetchColumn();

// Total employers
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM employers e
    INNER JOIN users u ON e.user_id = u.id
    WHERE u.is_active = 1
");

$totalEmployers = $stmt->fetchColumn();

// Total applicants
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM applicants a
    INNER JOIN users u ON a.user_id = u.id
    WHERE u.is_active = 1
");

$totalApplicants = $stmt->fetchColumn();

// Total jobs
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM jobs
");

$totalJobs = $stmt->fetchColumn();

// Pending jobs
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE status = 'pending'
");

$pendingJobs = $stmt->fetchColumn();

// Approved jobs
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE status = 'approved'
");

$approvedJobs = $stmt->fetchColumn();

// Total applications
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM applications
");

$totalApplications = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard - Caloocan Job Portal</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #222;
        }

        .navbar {
            background: #111827;
            color: white;
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
            padding: 9px 15px;
            border-radius: 5px;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome {
            margin-bottom: 30px;
        }

        .welcome h1 {
            margin-bottom: 8px;
        }

        .welcome p {
            color: #666;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 30px;
            font-weight: bold;
        }

        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }

        .quick-actions h2 {
            margin-bottom: 20px;
        }

        .actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-block;
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }

        .action-btn:hover {
            background: #1d4ed8;
        }

        @media (max-width: 900px) {

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 600px) {

            .navbar {
                padding: 15px 20px;
            }

            .navbar-container {
                flex-direction: column;
                gap: 15px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

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

    <div class="welcome">

        <h1>
            Admin Dashboard
        </h1>

        <p>
            Manage jobs, employers, applicants, and applications.
        </p>

    </div>


    <div class="stats">

        <div class="stat-card">

            <h3>
                Total Users
            </h3>

            <div class="number">
                <?php echo $totalUsers; ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Employers
            </h3>

            <div class="number">
                <?php echo $totalEmployers; ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Applicants
            </h3>

            <div class="number">
                <?php echo $totalApplicants; ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Total Jobs
            </h3>

            <div class="number">
                <?php echo $totalJobs; ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Pending Jobs
            </h3>

            <div class="number">
                <?php echo $pendingJobs; ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Approved Jobs
            </h3>

            <div class="number">
                <?php echo $approvedJobs; ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Applications
            </h3>

            <div class="number">
                <?php echo $totalApplications; ?>
            </div>

        </div>

    </div>


    <div class="quick-actions">

        <h2>
            Quick Actions
        </h2>

        <div class="actions">

            <a href="jobs.php" class="action-btn">
                Manage Jobs
            </a>

            <a href="employers.php" class="action-btn">
                Manage Employers
            </a>

            <a href="applicants.php" class="action-btn">
                View Applicants
            </a>

        </div>

    </div>

</div>


</body>

</html>