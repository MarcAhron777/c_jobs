<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "employer") {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| Get Employer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, company_name
    FROM employers
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$employer = $stmt->fetch();

if (!$employer) {
    die("Employer profile not found.");
}

$employerId = $employer["id"];


/*
|--------------------------------------------------------------------------
| Get Jobs
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        j.id,
        j.job_title,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.status,
        j.application_deadline,
        j.created_at,

        (
            SELECT COUNT(*)
            FROM applications a
            WHERE a.job_id = j.id
        ) AS applicant_count

    FROM jobs j

    WHERE j.employer_id = ?

    ORDER BY j.created_at DESC
");

$stmt->execute([$employerId]);

$jobs = $stmt->fetchAll();

$created = isset($_GET["created"]);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>My Jobs - Caloocan Job Portal</title>

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
    max-width: 1100px;
    margin: 40px auto;
    padding: 0 20px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    margin-bottom: 5px;
}

.header p {
    color: #666;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 6px;
}

.btn:hover {
    background: #1d4ed8;
}

.message {
    background: #dcfce7;
    color: #166534;

    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;
}

.job-card {
    background: white;

    padding: 25px;

    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);

    margin-bottom: 15px;
}

.job-card h2 {
    margin-bottom: 8px;
}

.meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;

    margin: 12px 0;
}

.meta span {
    background: #f1f5f9;

    padding: 6px 10px;

    border-radius: 5px;

    font-size: 13px;
}

.status {
    display: inline-block;

    padding: 6px 12px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    margin-bottom: 15px;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.approved {
    background: #dcfce7;
    color: #166534;
}

.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.closed {
    background: #e2e8f0;
    color: #475569;
}

.actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.action {
    display: inline-block;

    padding: 8px 12px;

    border-radius: 5px;

    text-decoration: none;

    background: #2563eb;

    color: white;

    font-size: 13px;
}

.edit {
    background: #64748b;
}

.applicants {
    background: #059669;
}

.empty {
    background: white;

    padding: 50px;

    text-align: center;

    border-radius: 10px;
}

@media (max-width: 700px) {

    .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
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

        <a href="jobs.php">
            My Jobs
        </a>

        <a href="applicants.php">
            Applicants
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</nav>


<div class="container">


    <div class="header">

        <div>

            <h1>
                My Jobs
            </h1>

            <p>
                Manage your job postings.
            </p>

        </div>


        <a
            href="create-job.php"
            class="btn"
        >
            + Post a Job
        </a>

    </div>


    <?php if ($created): ?>

        <div class="message">

            Job created successfully and submitted
            for admin approval.

        </div>

    <?php endif; ?>


    <?php if (count($jobs) > 0): ?>


        <?php foreach ($jobs as $job): ?>

            <div class="job-card">

                <h2>

                    <?= htmlspecialchars(
                        $job["job_title"]
                    ) ?>

                </h2>


                <div class="meta">

                    <span>

                        📍

                        <?= htmlspecialchars(
                            $job["location"]
                            ?? "Not specified"
                        ) ?>

                    </span>


                    <span>

                        💼

                        <?= htmlspecialchars(
                            $job["employment_type"]
                        ) ?>

                    </span>


                    <span>

                        👤

                        <?= $job["applicant_count"] ?>

                        applicant(s)

                    </span>

                </div>


                <span class="status <?= htmlspecialchars(
                    $job["status"]
                ) ?>">

                    <?= ucfirst(
                        htmlspecialchars(
                            $job["status"]
                        )
                    ) ?>

                </span>


                <div style="color:#777;font-size:14px;margin-bottom:15px;">

                    Posted:

                    <?= date(
                        "M d, Y",
                        strtotime($job["created_at"])
                    ) ?>

                </div>


                <div class="actions">


                    <?php if (
                        $job["status"] !== "closed"
                    ): ?>

                        <a
                            href="edit-job.php?id=<?= $job["id"] ?>"
                            class="action edit"
                        >
                            Edit
                        </a>

                    <?php endif; ?>


                    <?php if (
                        $job["applicant_count"] > 0
                    ): ?>

                        <a
                            href="applicants.php?job_id=<?= $job["id"] ?>"
                            class="action applicants"
                        >
                            View Applicants
                        </a>

                    <?php endif; ?>


                </div>

            </div>

        <?php endforeach; ?>


    <?php else: ?>

        <div class="empty">

            <h2>
                No Jobs Yet
            </h2>

            <p style="margin:10px 0 20px;color:#666;">

                Create your first job posting.

            </p>

            <a
                href="create-job.php"
                class="btn"
            >
                Post a Job
            </a>

        </div>

    <?php endif; ?>

</div>

</body>

</html>