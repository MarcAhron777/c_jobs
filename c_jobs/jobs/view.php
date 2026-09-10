<?php

session_start();

require_once "../config/database.php";


$jobId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($jobId <= 0) {
    die("Invalid job.");
}


/*
|--------------------------------------------------------------------------
| Get Job
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        j.id,
        j.job_title,
        j.description,
        j.requirements,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.is_remote,
        j.application_deadline,
        j.created_at,

        e.id AS employer_id,
        e.company_name,
        e.company_description,
        e.company_logo,
        e.contact_person

    FROM jobs j

    INNER JOIN employers e
        ON j.employer_id = e.id

    WHERE j.id = ?
    AND j.status = 'approved'
    AND LOWER(j.location) LIKE '%caloocan%'

    LIMIT 1
");

$stmt->execute([$jobId]);

$job = $stmt->fetch();


if (!$job) {
    die("Job not found.");
}


/*
|--------------------------------------------------------------------------
| Check if applicant already applied
|--------------------------------------------------------------------------
*/

$alreadyApplied = false;

if (
    isset($_SESSION["user_id"])
    && $_SESSION["role"] === "applicant"
) {

    $stmt = $pdo->prepare("
        SELECT ap.id

        FROM applications ap

        INNER JOIN applicants a
            ON ap.applicant_id = a.id

        WHERE ap.job_id = ?
        AND a.user_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $jobId,
        $_SESSION["user_id"]
    ]);

    $alreadyApplied = (bool) $stmt->fetch();

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>

        <?= htmlspecialchars(
            $job["job_title"]
        ) ?>

        - Caloocan Job Portal

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
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2563eb;
        }

        .grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.05);

            margin-bottom: 20px;
        }

        h1 {
            margin-bottom: 10px;
        }

        h2 {
            margin-bottom: 15px;
        }

        .company {
            color: #555;
            font-size: 18px;
            margin-bottom: 20px;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .meta span {
            background: #f1f5f9;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
        }

        .salary {
            color: #166534;
            font-weight: bold;
        }

        .section {
            margin-top: 30px;
        }

        .section p {
            line-height: 1.8;
            white-space: pre-line;
        }

        .apply-btn {
            display: block;
            width: 100%;
            text-align: center;

            padding: 13px;

            background: #2563eb;
            color: white;

            text-decoration: none;
            border-radius: 6px;

            font-weight: bold;
        }

        .apply-btn:hover {
            background: #1d4ed8;
        }

        .disabled-btn {
            background: #94a3b8;
            cursor: not-allowed;
        }

        .login-message {
            background: #eff6ff;
            color: #1e40af;
            padding: 15px;
            border-radius: 7px;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .login-message a {
            color: #1d4ed8;
            font-weight: bold;
        }

        .company-card h3 {
            margin-bottom: 10px;
        }

        .company-card p {
            color: #666;
            line-height: 1.6;
        }

        .date {
            color: #777;
            font-size: 14px;
        }

        @media (max-width: 750px) {

            .grid {
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

        <a href="index.php">
            Find Jobs
        </a>


        <?php if (isset($_SESSION["user_id"])): ?>

            <?php if (
                $_SESSION["role"] === "applicant"
            ): ?>

                <a href="../applicant/dashboard.php">
                    Dashboard
                </a>

            <?php elseif (
                $_SESSION["role"] === "employer"
            ): ?>

                <a href="../employer/dashboard.php">
                    Dashboard
                </a>

            <?php endif; ?>


            <a href="../logout.php">
                Logout
            </a>

        <?php else: ?>

            <a href="../login.php">
                Login
            </a>

            <a href="../register.php">
                Register
            </a>

        <?php endif; ?>

    </div>

</nav>


<div class="container">


    <a href="index.php" class="back">
        ← Back to Jobs
    </a>


    <div class="grid">


        <!-- Main Job Details -->

        <div>

            <div class="card">

                <h1>

                    <?= htmlspecialchars(
                        $job["job_title"]
                    ) ?>

                </h1>


                <div class="company">

                    <?= htmlspecialchars(
                        $job["company_name"]
                    ) ?>

                </div>


                <div class="meta">

                    <span>

                        📍

                        <?= htmlspecialchars(
                            $job["location"]
                            ?? "Not specified"
                        ) ?>

                    </span>


                    <?php if ($job["is_remote"]): ?>

                        <span>
                            🌐 Remote
                        </span>

                    <?php endif; ?>


                    <span>

                        💼

                        <?= htmlspecialchars(
                            $job["employment_type"]
                        ) ?>

                    </span>


                    <?php if (
                        $job["salary_min"] !== null
                        || $job["salary_max"] !== null
                    ): ?>

                        <span class="salary">

                            ₱<?= number_format(
                                $job["salary_min"] ?? 0,
                                0
                            ) ?>

                            -

                            ₱<?= number_format(
                                $job["salary_max"] ?? 0,
                                0
                            ) ?>

                        </span>

                    <?php endif; ?>

                </div>


                <div class="date">

                    Posted:

                    <?= date(
                        "F d, Y",
                        strtotime($job["created_at"])
                    ) ?>

                </div>


                <?php if (
                    !empty($job["application_deadline"])
                ): ?>

                    <div class="date"
                         style="margin-top:8px;">

                        Application Deadline:

                        <?= date(
                            "F d, Y",
                            strtotime(
                                $job["application_deadline"]
                            )
                        ) ?>

                    </div>

                <?php endif; ?>


                <div class="section">

                    <h2>
                        Job Description
                    </h2>

                    <p>

                        <?= htmlspecialchars(
                            $job["description"]
                        ) ?>

                    </p>

                </div>


                <?php if (
                    !empty($job["requirements"])
                ): ?>

                    <div class="section">

                        <h2>
                            Requirements
                        </h2>

                        <p>

                            <?= htmlspecialchars(
                                $job["requirements"]
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>

            </div>


            <!-- Company -->

            <div class="card company-card">

                <h2>
                    About the Company
                </h2>


                <h3>

                    <?= htmlspecialchars(
                        $job["company_name"]
                    ) ?>

                </h3>


                <?php if (
                    !empty($job["company_description"])
                ): ?>

                    <p>

                        <?= htmlspecialchars(
                            $job["company_description"]
                        ) ?>

                    </p>

                <?php else: ?>

                    <p>
                        No company description available.
                    </p>

                <?php endif; ?>

            </div>

        </div>


        <!-- Apply Sidebar -->

        <div>

            <div class="card">

                <h2>
                    Interested in this job?
                </h2>


                <?php if (!isset($_SESSION["user_id"])): ?>

                    <div class="login-message">

                        Please

                        <a href="../login.php">
                            login
                        </a>

                        as an applicant to apply for this job.

                    </div>


                    <a
                        href="../login.php"
                        class="apply-btn"
                    >
                        Login to Apply
                    </a>


                <?php elseif (
                    $_SESSION["role"] !== "applicant"
                ): ?>

                    <div class="login-message">

                        Only applicants can apply for jobs.

                    </div>


                <?php elseif ($alreadyApplied): ?>

                    <div class="login-message">

                        You have already applied for this job.

                    </div>


                    <a
                        href="../applicant/applications.php"
                        class="apply-btn"
                    >
                        View Applications
                    </a>


                <?php else: ?>

                    <a
                        href="apply.php?id=<?= $job["id"] ?>"
                        class="apply-btn"
                    >
                        Apply Now
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

</body>

</html>