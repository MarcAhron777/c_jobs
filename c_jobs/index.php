<?php

session_start();

require_once "config/database.php";

// Get latest approved jobs from database
$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.job_title,
        j.description,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.is_remote,
        j.created_at,
        e.company_name,
        e.company_logo
    FROM jobs j
    INNER JOIN employers e
        ON j.employer_id = e.id
    WHERE j.status = 'approved'
    ORDER BY j.created_at DESC
    LIMIT 6
");

$stmt->execute();

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Caloocan Job Portal</title>

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

        a {
            text-decoration: none;
        }

        /* =========================
           NAVBAR
        ========================= */

        .navbar {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 18px 60px;
        }

        .navbar-container {
            max-width: 1200px;
            margin: auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .nav-links a {
            color: #333;
            font-size: 15px;
        }

        .nav-links a:hover {
            color: #2563eb;
        }

        .btn {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-login {
            color: #2563eb !important;
            border: 1px solid #2563eb;
        }

        .btn-register {
            background: #2563eb;
            color: #fff !important;
        }

        /* =========================
           HERO
        ========================= */

        .hero {
            background: #2563eb;
            padding: 80px 20px;
            text-align: center;
            color: #ffffff;
        }

        .hero h1 {
            font-size: 42px;
            margin-bottom: 15px;
        }

        .hero p {
            font-size: 18px;
            margin-bottom: 35px;
        }

        .search-box {
            max-width: 900px;
            margin: auto;
            background: #ffffff;
            padding: 10px;
            border-radius: 8px;
            display: flex;
            gap: 10px;
        }

        .search-box input {
            flex: 1;
            padding: 14px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 15px;
            outline: none;
        }

        .search-box button {
            padding: 14px 25px;
            border: none;
            border-radius: 5px;
            background: #111827;
            color: #ffffff;
            cursor: pointer;
            font-weight: bold;
        }

        /* =========================
           JOB SECTION
        ========================= */

        .jobs-section {
            max-width: 1200px;
            margin: 60px auto;
            padding: 0 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .section-header h2 {
            font-size: 28px;
        }

        .section-header a {
            color: #2563eb;
            font-weight: 600;
        }

        /* =========================
           JOB GRID
        ========================= */

        .job-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .job-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 25px;
            transition: 0.2s;
        }

        .job-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        .job-card-header {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .company-logo {
            width: 50px;
            height: 50px;
            flex-shrink: 0;
        }

        .company-logo img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }

        .default-logo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
        }

        .job-info h3 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .company-name {
            color: #666;
            font-size: 14px;
        }

        .job-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 15px;
            color: #555;
            font-size: 14px;
        }

        .job-salary {
            font-size: 16px;
            font-weight: bold;
            color: #16a34a;
            margin-bottom: 20px;
        }

        .job-card-footer {
            border-top: 1px solid #eee;
            padding-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .job-date {
            color: #888;
            font-size: 12px;
        }

        .view-job-btn {
            background: #2563eb;
            color: #ffffff;
            padding: 8px 14px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 600;
        }

        .view-job-btn:hover {
            background: #1d4ed8;
        }

        /* =========================
           NO JOBS
        ========================= */

        .no-jobs {
            grid-column: 1 / -1;
            text-align: center;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 50px 20px;
        }

        .no-jobs h3 {
            margin-bottom: 10px;
        }

        .no-jobs p {
            color: #777;
        }

        /* =========================
           EMPLOYER CTA
        ========================= */

        .employer-section {
            background: #111827;
            color: #ffffff;
            padding: 60px 20px;
            text-align: center;
        }

        .employer-section h2 {
            font-size: 30px;
            margin-bottom: 12px;
        }

        .employer-section p {
            margin-bottom: 25px;
            color: #d1d5db;
        }

        .post-job-btn {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            padding: 13px 25px;
            border-radius: 6px;
            font-weight: bold;
        }

        /* =========================
           FOOTER
        ========================= */

        .footer {
            background: #ffffff;
            border-top: 1px solid #e5e7eb;
            padding: 25px;
            text-align: center;
            color: #777;
            font-size: 14px;
        }

        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .job-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .navbar {
                padding: 18px 25px;
            }

        }

        @media (max-width: 600px) {

            .navbar-container {
                flex-direction: column;
                gap: 15px;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }

            .hero h1 {
                font-size: 32px;
            }

            .search-box {
                flex-direction: column;
            }

            .job-grid {
                grid-template-columns: 1fr;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

        }

    </style>

</head>

<body>


<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar">

    <div class="navbar-container">

        <a href="index.php" class="logo">
            Job Portal
        </a>

        <div class="nav-links">

            <a href="jobs/index.php">
                Find Jobs
            </a>

            <?php if (isset($_SESSION["user_id"])): ?>

                <?php if ($_SESSION["role"] === "employer"): ?>

                    <a href="employer/dashboard.php">
                        Dashboard
                    </a>

                <?php elseif ($_SESSION["role"] === "applicant"): ?>

                    <a href="applicant/dashboard.php">
                        Dashboard
                    </a>

                <?php elseif ($_SESSION["role"] === "admin"): ?>

                    <a href="admin/dashboard.php">
                        Admin Dashboard
                    </a>

                <?php endif; ?>

                <a href="logout.php" class="btn btn-login">
                    Logout
                </a>

            <?php else: ?>

                <a href="login.php" class="btn btn-login">
                    Login
                </a>

                <a href="register.php" class="btn btn-register">
                    Register
                </a>

            <?php endif; ?>

        </div>

    </div>

</nav>


<!-- =========================
     HERO
========================= -->

<section class="hero">

    <h1>Find Your Next Job</h1>

    <p>
        Discover opportunities and connect with great employers.
    </p>

    <form
        action="jobs/index.php"
        method="GET"
        class="search-box"
    >

        <input
            type="text"
            name="search"
            placeholder="Job title, keyword, or company"
        >

        <input
            type="text"
            name="location"
            placeholder="Location"
        >

        <button type="submit">
            Search Jobs
        </button>

    </form>

</section>


<!-- =========================
     LATEST JOBS
========================= -->

<section class="jobs-section">

    <div class="section-header">

        <h2>
            Latest Jobs
        </h2>

        <a href="jobs/index.php">
            View All Jobs →
        </a>

    </div>


    <div class="job-grid">

        <?php if (empty($jobs)): ?>

            <div class="no-jobs">

                <h3>
                    No jobs available yet
                </h3>

                <p>
                    New job opportunities will appear here once they are approved.
                </p>

            </div>

        <?php else: ?>

            <?php foreach ($jobs as $job): ?>

                <div class="job-card">


                    <!-- COMPANY + JOB TITLE -->

                    <div class="job-card-header">

                        <div class="company-logo">

                            <?php if (!empty($job["company_logo"])): ?>

                                <img
                                    src="uploads/<?php echo htmlspecialchars($job["company_logo"]); ?>"
                                    alt="<?php echo htmlspecialchars($job["company_name"]); ?>"
                                >

                            <?php else: ?>

                                <div class="default-logo">

                                    <?php
                                    echo strtoupper(
                                        substr(
                                            $job["company_name"],
                                            0,
                                            1
                                        )
                                    );
                                    ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="job-info">

                            <h3>
                                <?php
                                echo htmlspecialchars(
                                    $job["job_title"]
                                );
                                ?>
                            </h3>

                            <p class="company-name">

                                <?php
                                echo htmlspecialchars(
                                    $job["company_name"]
                                );
                                ?>

                            </p>

                        </div>

                    </div>


                    <!-- JOB DETAILS -->

                    <div class="job-details">

                        <span>

                            📍

                            <?php
                            echo htmlspecialchars(
                                $job["location"]
                                ?: "Location not specified"
                            );
                            ?>

                        </span>


                        <span>

                            💼

                            <?php
                            echo htmlspecialchars(
                                $job["employment_type"]
                            );
                            ?>

                        </span>


                        <?php if ($job["is_remote"]): ?>

                            <span>
                                🌐 Remote
                            </span>

                        <?php endif; ?>

                    </div>


                    <!-- SALARY -->

                    <?php if (
                        $job["salary_min"] !== null ||
                        $job["salary_max"] !== null
                    ): ?>

                        <div class="job-salary">

                            <?php if ($job["salary_min"] !== null): ?>

                                ₱<?php
                                echo number_format(
                                    $job["salary_min"]
                                );
                                ?>

                            <?php endif; ?>


                            <?php if (
                                $job["salary_min"] !== null &&
                                $job["salary_max"] !== null
                            ): ?>

                                -

                            <?php endif; ?>


                            <?php if ($job["salary_max"] !== null): ?>

                                ₱<?php
                                echo number_format(
                                    $job["salary_max"]
                                );
                                ?>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>


                    <!-- FOOTER -->

                    <div class="job-card-footer">

                        <span class="job-date">

                            <?php
                            echo date(
                                "M d, Y",
                                strtotime($job["created_at"])
                            );
                            ?>

                        </span>


                        <a
                            href="jobs/view.php?id=<?php echo $job["id"]; ?>"
                            class="view-job-btn"
                        >
                            View Job
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>


<!-- =========================
     EMPLOYER SECTION
========================= -->

<section class="employer-section">

    <h2>
        Are You Hiring?
    </h2>

    <p>
        Post your job and find qualified candidates.
    </p>


    <?php if (
        isset($_SESSION["user_id"]) &&
        $_SESSION["role"] === "employer"
    ): ?>

        <a
            href="employer/create-job.php"
            class="post-job-btn"
        >
            Post a Job
        </a>

    <?php else: ?>

        <a
            href="register.php"
            class="post-job-btn"
        >
            Register as Employer
        </a>

    <?php endif; ?>

</section>


<!-- =========================
     FOOTER
========================= -->

<footer class="footer">

    <p>
        &copy; <?php echo date("Y"); ?> Job Portal.
        All rights reserved.
    </p>

</footer>


</body>

</html>