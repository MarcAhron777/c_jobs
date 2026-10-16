<?php

session_start();

require_once "../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$totalUsers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role != 'admin'
")->fetchColumn();

$totalEmployers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM employers e
    INNER JOIN users u ON e.user_id = u.id
    WHERE u.is_active = 1
")->fetchColumn();

$totalPrivateEmployers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM employers e
    INNER JOIN users u ON e.user_id = u.id
    WHERE u.is_active = 1
    AND e.employer_type = 'private'
")->fetchColumn();

$totalLguEmployers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM employers e
    INNER JOIN users u ON e.user_id = u.id
    WHERE u.is_active = 1
    AND e.employer_type = 'lgu'
")->fetchColumn();

$totalApplicants = (int) $pdo->query("
    SELECT COUNT(*)
    FROM applicants a
    INNER JOIN users u ON a.user_id = u.id
    WHERE u.is_active = 1
")->fetchColumn();

$totalJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
")->fetchColumn();

$pendingJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE status = 'pending'
")->fetchColumn();

$approvedJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE status = 'approved'
")->fetchColumn();

$totalApplications = (int) $pdo->query("
    SELECT COUNT(*)
    FROM applications
")->fetchColumn();

$pendingEmployerVerifications = (int) $pdo->query("
    SELECT COUNT(*)
    FROM employers
    WHERE verification_status = 'pending'
")->fetchColumn();

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
    Admin Dashboard - Caloocan Job Portal
</title>

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

.container {
    max-width: 1250px;
    margin: 40px auto;
    padding: 0 20px 50px;
}

.welcome {
    margin-bottom: 30px;
}

.welcome h1 {
    margin-bottom: 8px;
}

.welcome p {
    color: #64748b;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #ffffff;
    padding: 25px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.stat-card h3 {
    color: #64748b;
    font-size: 14px;
    margin-bottom: 10px;
}

.stat-card .number {
    font-size: 30px;
    font-weight: bold;
}

.stat-card p {
    margin-top: 8px;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}

.quick-actions {
    background: #ffffff;
    padding: 25px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
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
    color: #ffffff;
    text-decoration: none;
    border-radius: 5px;
}

.action-btn:hover {
    background: #1d4ed8;
}

@media (max-width: 1000px) {
    .stats {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 600px) {
    .stats {
        grid-template-columns: 1fr;
    }

    .container {
        margin-top: 25px;
        padding: 0 15px 40px;
    }
}
</style>

</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>

<div class="container">

    <div class="welcome">

        <h1>
            Admin Dashboard
        </h1>

        <p>
            Manage jobs, employers, applicants,
            applications and reports.
        </p>

    </div>

    <div class="stats">

        <div class="stat-card">
            <h3>Total Users</h3>
            <div class="number">
                <?= number_format($totalUsers) ?>
            </div>
            <p>
                Employers and applicants.
            </p>
        </div>

        <div class="stat-card">
            <h3>Active Employers</h3>
            <div class="number">
                <?= number_format($totalEmployers) ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Private Employers</h3>
            <div class="number">
                <?= number_format($totalPrivateEmployers) ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>LGU Employers</h3>
            <div class="number">
                <?= number_format($totalLguEmployers) ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Applicants</h3>
            <div class="number">
                <?= number_format($totalApplicants) ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Total Jobs</h3>
            <div class="number">
                <?= number_format($totalJobs) ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Pending Jobs</h3>
            <div class="number">
                <?= number_format($pendingJobs) ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Approved Jobs</h3>
            <div class="number">
                <?= number_format($approvedJobs) ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Applications</h3>
            <div class="number">
                <?= number_format($totalApplications) ?>
            </div>
        </div>

        <div class="stat-card">
            <h3>Pending Employer Verification</h3>
            <div class="number">
                <?= number_format(
                    $pendingEmployerVerifications
                ) ?>
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
                Manage Applicants
            </a>

            <a href="reports.php" class="action-btn">
                View Reports
            </a>

        </div>

    </div>

</div>

</body>
</html>