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
    SELECT
        id,
        company_name,
        company_description,
        contact_person,
        contact_number,
        address
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
| Job Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM jobs
    WHERE employer_id = ?
");

$stmt->execute([$employerId]);

$totalJobs = $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM jobs
    WHERE employer_id = ?
    AND status = 'approved'
");

$stmt->execute([$employerId]);

$approvedJobs = $stmt->fetchColumn();


$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM jobs
    WHERE employer_id = ?
    AND status = 'pending'
");

$stmt->execute([$employerId]);

$pendingJobs = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Application Statistics
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)

    FROM applications a

    INNER JOIN jobs j
        ON a.job_id = j.id

    WHERE j.employer_id = ?
");

$stmt->execute([$employerId]);

$totalApplicants = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Recent Applications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.status,
        a.applied_at,

        j.job_title,

        ap.first_name,
        ap.last_name

    FROM applications a

    INNER JOIN jobs j
        ON a.job_id = j.id

    INNER JOIN applicants ap
        ON a.applicant_id = ap.id

    WHERE j.employer_id = ?

    ORDER BY a.applied_at DESC

    LIMIT 5
");

$stmt->execute([$employerId]);

$recentApplications = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Employer Dashboard - Caloocan Job Portal</title>

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
    align-items: center;
}

.nav-links a {
    text-decoration: none;
    color: #333;
}

.logout {
    color: #dc2626 !important;
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
    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);
}

.stat-card h3 {
    font-size: 14px;
    color: #666;
    margin-bottom: 10px;
}

.stat-number {
    font-size: 30px;
    font-weight: bold;
    color: #2563eb;
}

.grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 25px;
}

.card {
    background: white;
    padding: 25px;
    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);

    margin-bottom: 20px;
}

.card h2 {
    margin-bottom: 20px;
}

.application {
    padding: 15px 0;
    border-bottom: 1px solid #eee;
}

.application:last-child {
    border-bottom: none;
}

.application h3 {
    margin-bottom: 5px;
}

.job-title {
    color: #555;
    margin-bottom: 8px;
}

.status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.reviewed {
    background: #dbeafe;
    color: #1e40af;
}

.shortlisted {
    background: #dcfce7;
    color: #166534;
}

.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.hired {
    background: #d1fae5;
    color: #065f46;
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

.btn-secondary {
    background: #64748b;
}

.company-info p {
    margin-bottom: 10px;
    line-height: 1.5;
}

@media (max-width: 800px) {

    .stats {
        grid-template-columns: 1fr 1fr;
    }

    .grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 500px) {

    .stats {
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

        <a href="../index.php">
            Home
        </a>

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="jobs.php">
            My Jobs
        </a>

        <a href="applicants.php">
            Applicants
        </a>

        <a href="../logout.php" class="logout">
            Logout
        </a>

    </div>

</nav>


<div class="container">

    <div class="welcome">

        <h1>
            Welcome, <?= htmlspecialchars($employer["company_name"]) ?>!
        </h1>

        <p>
            Manage your job postings and applicants.
        </p>

    </div>


    <!-- Statistics -->

    <div class="stats">

        <div class="stat-card">

            <h3>
                Total Jobs
            </h3>

            <div class="stat-number">
                <?= $totalJobs ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Published Jobs
            </h3>

            <div class="stat-number">
                <?= $approvedJobs ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Pending Jobs
            </h3>

            <div class="stat-number">
                <?= $pendingJobs ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Applicants
            </h3>

            <div class="stat-number">
                <?= $totalApplicants ?>
            </div>

        </div>

    </div>


    <div class="grid">


        <!-- Recent Applicants -->

        <div class="card">

            <h2>
                Recent Applications
            </h2>


            <?php if (count($recentApplications) > 0): ?>

                <?php foreach ($recentApplications as $application): ?>

                    <div class="application">

                        <h3>

                            <?= htmlspecialchars(
                                $application["first_name"]
                                . " "
                                . $application["last_name"]
                            ) ?>

                        </h3>


                        <div class="job-title">

                            <?= htmlspecialchars(
                                $application["job_title"]
                            ) ?>

                        </div>


                        <span class="status <?= htmlspecialchars(
                            $application["status"]
                        ) ?>">

                            <?= ucfirst(
                                htmlspecialchars(
                                    $application["status"]
                                )
                            ) ?>

                        </span>

                    </div>

                <?php endforeach; ?>


                <a
                    href="applicants.php"
                    class="btn"
                >
                    View All Applicants
                </a>


            <?php else: ?>

                <p>
                    No applications yet.
                </p>

            <?php endif; ?>

        </div>


        <!-- Company -->

        <div>

            <div class="card">

                <h2>
                    Company
                </h2>

                <div class="company-info">

                    <p>

                        <strong>
                            Company:
                        </strong>

                        <?= htmlspecialchars(
                            $employer["company_name"]
                        ) ?>

                    </p>


                    <p>

                        <strong>
                            Contact:
                        </strong>

                        <?= htmlspecialchars(
                            $employer["contact_person"]
                            ?? "Not provided"
                        ) ?>

                    </p>


                    <p>

                        <strong>
                            Phone:
                        </strong>

                        <?= htmlspecialchars(
                            $employer["contact_number"]
                            ?? "Not provided"
                        ) ?>

                    </p>


                    <p>

                        <strong>
                            Address:
                        </strong>

                        <?= htmlspecialchars(
                            $employer["address"]
                            ?? "Not provided"
                        ) ?>

                    </p>

                </div>

            </div>


            <div class="card">

                <h2>
                    Quick Actions
                </h2>

                <a
                    href="create-job.php"
                    class="btn"
                >
                    + Post a Job
                </a>

                <br><br>

                <a
                    href="jobs.php"
                    class="btn btn-secondary"
                >
                    Manage Jobs
                </a>

            </div>

        </div>

    </div>

</div>

</body>

</html>