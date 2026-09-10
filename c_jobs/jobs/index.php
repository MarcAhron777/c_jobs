<?php

session_start();

require_once "../config/database.php";
require_once "../services/LocationMatcher.php";


/*
|--------------------------------------------------------------------------
| Search Inputs
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

$location = trim($_GET["location"] ?? "");


/*
|--------------------------------------------------------------------------
| Applicant Location Check
|--------------------------------------------------------------------------
*/

$isApplicant = (
    isset($_SESSION["user_id"])
    && $_SESSION["role"] === "applicant"
);

$isLocationEligible = true;

$resumeText = "";


if ($isApplicant) {

    /*
    |--------------------------------------------------------------------------
    | Get Latest Uploaded Resume
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT
            r.extracted_text

        FROM resumes r

        INNER JOIN applicants a
            ON r.applicant_id = a.id

        WHERE a.user_id = ?

        AND r.extracted_text IS NOT NULL

        AND r.extracted_text != ''

        ORDER BY r.uploaded_at DESC

        LIMIT 1
    ");

    $stmt->execute([
        $_SESSION["user_id"]
    ]);

    $resume = $stmt->fetch();


    /*
    |--------------------------------------------------------------------------
    | Get Resume Text
    |--------------------------------------------------------------------------
    */

    if ($resume) {

        $resumeText = trim(
            $resume["extracted_text"] ?? ""
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Check Applicant Location
    |--------------------------------------------------------------------------
    */

    if ($resumeText === "") {

        $isLocationEligible = false;

    } else {

        $isLocationEligible =
            LocationMatcher::isCaloocan(
                $resumeText
            );

    }

}


/*
|--------------------------------------------------------------------------
| Build Jobs Query
|--------------------------------------------------------------------------
|
| Only approved Caloocan jobs are displayed.
|
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        j.id,
        j.job_title,
        j.description,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.is_remote,
        j.application_deadline,
        j.created_at,

        e.company_name

    FROM jobs j

    INNER JOIN employers e
        ON j.employer_id = e.id

    WHERE j.status = 'approved'

    AND LOWER(j.location) LIKE '%caloocan%'

";


$params = [];


/*
|--------------------------------------------------------------------------
| Applicant Location Restriction
|--------------------------------------------------------------------------
|
| Applicant must have a resume that indicates Caloocan.
|
|--------------------------------------------------------------------------
*/

if (
    $isApplicant
    && !$isLocationEligible
) {

    $sql .= "
        AND 1 = 0
    ";

}


/*
|--------------------------------------------------------------------------
| Search by Keyword
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "

        AND (

            j.job_title LIKE ?

            OR j.description LIKE ?

            OR e.company_name LIKE ?

        )

    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

}


/*
|--------------------------------------------------------------------------
| Search by Location
|--------------------------------------------------------------------------
*/

if ($location !== "") {

    $sql .= "

        AND j.location LIKE ?

    ";

    $params[] = "%" . $location . "%";

}


/*
|--------------------------------------------------------------------------
| Order
|--------------------------------------------------------------------------
*/

$sql .= "

    ORDER BY j.created_at DESC

";


/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$jobs = $stmt->fetchAll();

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
        Find Jobs - Caloocan Job Portal
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


        /*
        |--------------------------------------------------------------------------
        | Navbar
        |--------------------------------------------------------------------------
        */

        .navbar {
            background: #fff;
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


        .nav-links a:hover {
            color: #2563eb;
        }


        /*
        |--------------------------------------------------------------------------
        | Container
        |--------------------------------------------------------------------------
        */

        .container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | Page Header
        |--------------------------------------------------------------------------
        */

        .page-header {
            margin-bottom: 25px;
        }


        .page-header h1 {
            margin-bottom: 8px;
        }


        .page-header p {
            color: #666;
        }


        /*
        |--------------------------------------------------------------------------
        | Search
        |--------------------------------------------------------------------------
        */

        .search-box {
            background: white;
            padding: 20px;
            border-radius: 10px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.05);

            margin-bottom: 25px;
        }


        .search-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
        }


        .search-form input {
            width: 100%;
            padding: 12px;

            border: 1px solid #ddd;
            border-radius: 6px;

            font-size: 14px;
        }


        .search-btn {
            padding: 12px 20px;

            border: none;
            border-radius: 6px;

            background: #2563eb;
            color: white;

            cursor: pointer;
            font-weight: bold;
        }


        .search-btn:hover {
            background: #1d4ed8;
        }


        /*
        |--------------------------------------------------------------------------
        | Notices
        |--------------------------------------------------------------------------
        */

        .location-notice {
            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1e40af;

            padding: 15px 18px;

            border-radius: 8px;

            margin-bottom: 20px;

            line-height: 1.5;
        }


        .warning-notice {
            background: #fef3c7;

            border: 1px solid #fde68a;

            color: #92400e;

            padding: 15px 18px;

            border-radius: 8px;

            margin-bottom: 20px;

            line-height: 1.5;
        }


        .warning-notice a {
            color: #92400e;
            font-weight: bold;
        }


        .upload-link {
            display: inline-block;

            margin-top: 12px;

            padding: 10px 16px;

            background: #2563eb;

            color: white !important;

            text-decoration: none;

            border-radius: 6px;
        }


        /*
        |--------------------------------------------------------------------------
        | Jobs Grid
        |--------------------------------------------------------------------------
        */

        .jobs-grid {
            display: grid;

            grid-template-columns:
                repeat(2, minmax(0, 1fr));

            gap: 20px;
        }


        /*
        |--------------------------------------------------------------------------
        | Job Card
        |--------------------------------------------------------------------------
        */

        .job-card {
            background: white;

            padding: 25px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.05);

            display: flex;
            flex-direction: column;
        }


        .job-card h2 {
            font-size: 20px;
            margin-bottom: 8px;
        }


        .company {
            color: #555;

            font-size: 16px;

            margin-bottom: 15px;
        }


        .job-description {
            color: #666;

            line-height: 1.6;

            margin-bottom: 15px;

            display: -webkit-box;

            -webkit-line-clamp: 3;

            -webkit-box-orient: vertical;

            overflow: hidden;
        }


        /*
        |--------------------------------------------------------------------------
        | Job Meta
        |--------------------------------------------------------------------------
        */

        .job-meta {
            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-bottom: 20px;
        }


        .job-meta span {
            background: #f1f5f9;

            padding: 7px 10px;

            border-radius: 6px;

            font-size: 13px;
        }


        /*
        |--------------------------------------------------------------------------
        | Job Footer
        |--------------------------------------------------------------------------
        */

        .job-footer {
            margin-top: auto;

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 15px;
        }


        .salary {
            font-weight: bold;

            color: #166534;
        }


        .view-job-btn {
            display: inline-block;

            padding: 10px 16px;

            background: #2563eb;

            color: white;

            text-decoration: none;

            border-radius: 6px;

            white-space: nowrap;
        }


        .view-job-btn:hover {
            background: #1d4ed8;
        }


        /*
        |--------------------------------------------------------------------------
        | No Jobs
        |--------------------------------------------------------------------------
        */

        .no-jobs {
            background: white;

            padding: 40px;

            border-radius: 10px;

            text-align: center;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.05);
        }


        .no-jobs h2 {
            margin-bottom: 10px;
        }


        .no-jobs p {
            color: #666;

            line-height: 1.6;
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 768px) {

            .navbar {
                padding: 15px 20px;

                flex-direction: column;

                gap: 15px;
            }


            .nav-links {
                flex-wrap: wrap;

                justify-content: center;
            }


            .search-form {
                grid-template-columns: 1fr;
            }


            .jobs-grid {
                grid-template-columns: 1fr;
            }


            .container {
                margin: 25px auto;
            }

        }

    </style>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| Navbar
|--------------------------------------------------------------------------
-->

<nav class="navbar">

    <div class="logo">
        JobPortal
    </div>


    <div class="nav-links">

        <?php if ($isApplicant): ?>

            <a href="../applicant/dashboard.php">
                Dashboard
            </a>

            <a href="../applicant/applications.php">
                Applications
            </a>

        <?php elseif (
            isset($_SESSION["role"])
            && $_SESSION["role"] === "employer"
        ): ?>

            <a href="../employer/dashboard.php">
                Dashboard
            </a>

        <?php elseif (
            isset($_SESSION["role"])
            && $_SESSION["role"] === "admin"
        ): ?>

            <a href="../admin/dashboard.php">
                Dashboard
            </a>

        <?php else: ?>

            <a href="../login.php">
                Login
            </a>

            <a href="../register.php">
                Register
            </a>

        <?php endif; ?>


        <a href="../index.php">
            Home
        </a>


        <?php if (isset($_SESSION["user_id"])): ?>

            <a href="../logout.php">
                Logout
            </a>

        <?php endif; ?>

    </div>

</nav>


<!--
|--------------------------------------------------------------------------
| Main Container
|--------------------------------------------------------------------------
-->

<div class="container">


    <!--
    |--------------------------------------------------------------------------
    | Page Header
    |--------------------------------------------------------------------------
    -->

    <div class="page-header">

        <h1>
            Find Jobs
        </h1>

        <p>
            Find job opportunities available
            in Caloocan City.
        </p>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    -->

    <div class="search-box">

        <form
            method="GET"
            action="index.php"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Job title, keyword or company"
                value="<?= htmlspecialchars($search) ?>"
            >


            <input
                type="text"
                name="location"
                placeholder="Location"
                value="<?= htmlspecialchars($location) ?>"
            >


            <button
                type="submit"
                class="search-btn"
            >
                Search
            </button>

        </form>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Applicant Location Notice
    |--------------------------------------------------------------------------
    -->

    <?php if ($isApplicant): ?>


        <?php if ($resumeText === ""): ?>

            <div class="warning-notice">

                <strong>
                    Resume Required
                </strong>

                <br>

                Please upload a resume containing
                your location to see available jobs
                in Caloocan City.


                <br>


                <a
                    href="../applicant/resume.php"
                    class="upload-link"
                >
                    Upload Resume
                </a>

            </div>


        <?php elseif (!$isLocationEligible): ?>

            <div class="warning-notice">

                <strong>
                    No Jobs Available in Your Area
                </strong>

                <br>

                Based on the location found in your
                uploaded resume, you are not currently
                eligible to view Caloocan City jobs.

            </div>


        <?php else: ?>

            <div class="location-notice">

                ✓ Your resume location matches
                <strong>Caloocan City</strong>.

                Showing available Caloocan jobs.

            </div>

        <?php endif; ?>

    <?php endif; ?>


    <!--
    |--------------------------------------------------------------------------
    | Jobs Result
    |--------------------------------------------------------------------------
    -->

    <?php if (!empty($jobs)): ?>


        <div class="jobs-grid">


            <?php foreach ($jobs as $job): ?>


                <div class="job-card">


                    <h2>

                        <?= htmlspecialchars(
                            $job["job_title"]
                        ) ?>

                    </h2>


                    <div class="company">

                        <?= htmlspecialchars(
                            $job["company_name"]
                        ) ?>

                    </div>


                    <div class="job-description">

                        <?= htmlspecialchars(
                            $job["description"]
                        ) ?>

                    </div>


                    <div class="job-meta">


                        <span>

                            📍

                            <?= htmlspecialchars(
                                $job["location"]
                            ) ?>

                        </span>


                        <span>

                            💼

                            <?= htmlspecialchars(
                                $job["employment_type"]
                            ) ?>

                        </span>


                        <?php if ($job["is_remote"]): ?>

                            <span>

                                🌐 Remote

                            </span>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $job["application_deadline"]
                            )
                        ): ?>

                            <span>

                                📅 Deadline:

                                <?= date(
                                    "M d, Y",
                                    strtotime(
                                        $job["application_deadline"]
                                    )
                                ) ?>

                            </span>

                        <?php endif; ?>


                    </div>


                    <div class="job-footer">


                        <div class="salary">


                            <?php if (
                                $job["salary_min"] !== null
                                &&
                                $job["salary_max"] !== null
                            ): ?>

                                ₱<?= number_format(
                                    $job["salary_min"],
                                    2
                                ) ?>

                                -

                                ₱<?= number_format(
                                    $job["salary_max"],
                                    2
                                ) ?>


                            <?php elseif (
                                $job["salary_min"] !== null
                            ): ?>

                                From ₱<?= number_format(
                                    $job["salary_min"],
                                    2
                                ) ?>


                            <?php elseif (
                                $job["salary_max"] !== null
                            ): ?>

                                Up to ₱<?= number_format(
                                    $job["salary_max"],
                                    2
                                ) ?>


                            <?php else: ?>

                                Salary not specified

                            <?php endif; ?>


                        </div>


                        <a
                            href="view.php?id=<?= (int) $job["id"] ?>"
                            class="view-job-btn"
                        >
                            View Job
                        </a>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <!--
        |--------------------------------------------------------------------------
        | No Jobs Found
        |--------------------------------------------------------------------------
        -->

        <div class="no-jobs">


            <?php if (
                $isApplicant
                && $resumeText === ""
            ): ?>


                <h2>
                    Upload Your Resume First
                </h2>


                <p>

                    We need your uploaded resume
                    to determine whether you are
                    located in Caloocan City.

                </p>


                <a
                    href="../applicant/resume.php"
                    class="upload-link"
                >
                    Upload Resume
                </a>


            <?php elseif (
                $isApplicant
                && !$isLocationEligible
            ): ?>


                <h2>
                    No Jobs Available in Your Area
                </h2>


                <p>

                    The location found in your resume
                    does not match Caloocan City.

                </p>


            <?php else: ?>


                <h2>
                    No Jobs Found
                </h2>


                <p>

                    Try searching using a different
                    job title or keyword.

                </p>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>


</body>

</html>