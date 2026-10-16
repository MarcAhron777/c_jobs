<?php

session_start();

require_once "../config/database.php";
require_once "../services/LocationMatcher.php";

$jobId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($jobId <= 0) {
    die("Invalid job.");
}


/*
|--------------------------------------------------------------------------
| GET JOB
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.job_title,

        COALESCE(
            NULLIF(j.job_description, ''),
            j.description
        ) AS job_description,

        j.requirements,
        j.required_skills,
        j.educational_requirement,
        j.required_degree,
        j.degree_classification,
        j.tesda_qualification,
        j.required_nc_level,

        j.job_source,
        j.source_name,
        j.source_url,
        j.lgu_job_category,

        j.location,
        j.address,
        j.barangay,
        j.caloocan_area,
        j.latitude,
        j.longtitude,

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

    WHERE
        j.id = ?
        AND j.status = 'approved'

    LIMIT 1
");

$stmt->execute([$jobId]);

$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("Job not found.");
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$isApplicant = (
    isset($_SESSION["user_id"]) &&
    isset($_SESSION["role"]) &&
    $_SESSION["role"] === "applicant"
);


/*
|--------------------------------------------------------------------------
| APPLICANT LOCATION
|--------------------------------------------------------------------------
*/

$applicantLatitude = null;
$applicantLongitude = null;
$hasApplicantLocation = false;

if ($isApplicant) {
    $stmt = $pdo->prepare("
        SELECT
            latitude,
            longitude
        FROM applicants
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $_SESSION["user_id"]
    ]);

    $applicantLocation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($applicantLocation) {
        $applicantLatitude =
            $applicantLocation["latitude"] ?? null;

        $applicantLongitude =
            $applicantLocation["longitude"] ?? null;

        $hasApplicantLocation = (
            is_numeric($applicantLatitude) &&
            is_numeric($applicantLongitude)
        );
    }
}


/*
|--------------------------------------------------------------------------
| DISTANCE
|--------------------------------------------------------------------------
*/

$hasJobLocation = (
    is_numeric($job["latitude"] ?? null) &&
    is_numeric($job["longtitude"] ?? null)
);

$distanceKm = null;

if ($hasApplicantLocation && $hasJobLocation) {
    $distanceKm = LocationMatcher::distanceKm(
        $applicantLatitude,
        $applicantLongitude,
        $job["latitude"],
        $job["longtitude"]
    );
}


/*
|--------------------------------------------------------------------------
| CHECK EXISTING APPLICATION
|--------------------------------------------------------------------------
*/

$alreadyApplied = false;
$existingApplicationId = 0;

if ($isApplicant) {
    $stmt = $pdo->prepare("
        SELECT ap.id
        FROM applications ap

        INNER JOIN applicants a
            ON ap.applicant_id = a.id

        WHERE
            ap.job_id = ?
            AND a.user_id = ?

        LIMIT 1
    ");

    $stmt->execute([
        $jobId,
        $_SESSION["user_id"]
    ]);

    $existingApplication = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingApplication) {
        $alreadyApplied = true;
        $existingApplicationId =
            (int) $existingApplication["id"];
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
        <?= htmlspecialchars($job["job_title"]) ?>
        - Caloocan Job Portal
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
            color: #333;
        }

        .navbar {
            background: #fff;
            border-bottom: 1px solid #ddd;
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: #2563eb;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-links a {
            color: #333;
            text-decoration: none;
        }

        .nav-links a:hover {
            color: #2563eb;
        }

        .container {
            max-width: 1050px;
            margin: 40px auto;
            padding: 0 20px 50px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .page-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            align-items: start;
        }

        .card {
            padding: 28px;
            margin-bottom: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        h1,
        h2,
        h3 {
            margin-bottom: 12px;
        }

        .company-name {
            margin-bottom: 18px;
            color: #555;
            font-size: 18px;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        .meta span {
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 6px;
            font-size: 14px;
        }

        .salary {
            margin-bottom: 20px;
            color: #166534;
            font-size: 18px;
            font-weight: bold;
        }

        .section {
            margin-top: 28px;
        }

        .section p {
            line-height: 1.8;
            white-space: pre-line;
        }

        .requirement-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .requirement-item {
            padding: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .requirement-label {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
        }

        .source-box {
            padding: 15px;
            margin-top: 20px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            color: #166534;
            line-height: 1.7;
        }

        .location-box {
            padding: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            line-height: 1.7;
        }

        .area-badge {
            display: inline-block;
            padding: 5px 9px;
            margin-top: 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .north {
            background: #dbeafe;
            color: #1e40af;
        }

        .south {
            background: #dcfce7;
            color: #166534;
        }

        .distance {
            padding-top: 12px;
            margin-top: 12px;
            border-top: 1px solid #e2e8f0;
            color: #2563eb;
            font-weight: bold;
        }

        .notice {
            padding: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            line-height: 1.6;
        }

        .apply-btn {
            display: block;
            width: 100%;
            padding: 13px 16px;
            margin-top: 15px;
            background: #2563eb;
            border-radius: 7px;
            color: #fff;
            font-weight: bold;
            text-align: center;
            text-decoration: none;
        }

        .apply-btn:hover {
            background: #1d4ed8;
        }

        .deadline {
            margin-top: 15px;
            color: #555;
            font-size: 14px;
        }

        @media (max-width: 800px) {
            .page-grid,
            .requirement-grid {
                grid-template-columns: 1fr;
            }

            .navbar {
                padding: 15px 20px;
                flex-direction: column;
                gap: 12px;
            }

            .nav-links {
                justify-content: center;
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">

    <a
        href="../index.php"
        class="logo"
    >
        JobPortal
    </a>

    <div class="nav-links">

        <a href="../index.php">
            Home
        </a>

        <a href="index.php">
            Jobs
        </a>

        <?php if ($isApplicant): ?>

            <a href="../applicant/dashboard.php">
                Dashboard
            </a>

            <a href="../applicant/applications.php">
                Applications
            </a>

        <?php elseif (
            isset($_SESSION["role"]) &&
            $_SESSION["role"] === "employer"
        ): ?>

            <a href="../employer/dashboard.php">
                Dashboard
            </a>

        <?php endif; ?>


        <?php if (isset($_SESSION["user_id"])): ?>

            <a href="../logout.php">
                Logout
            </a>

        <?php else: ?>

            <a href="../login.php">
                Login
            </a>

        <?php endif; ?>

    </div>

</nav>


<div class="container">

    <a
        href="index.php"
        class="back-link"
    >
        &larr; Back to Jobs
    </a>


    <div class="page-grid">

        <main>

            <div class="card">

                <h1>
                    <?= htmlspecialchars(
                        $job["job_title"]
                    ) ?>
                </h1>

                <div class="company-name">
                    <?= htmlspecialchars(
                        $job["company_name"]
                    ) ?>
                </div>


                <div class="meta">

                    <span>
                        <?= htmlspecialchars(
                            $job["employment_type"]
                        ) ?>
                    </span>

                    <?php if ($job["is_remote"]): ?>
                        <span>Remote</span>
                    <?php endif; ?>

                    <?php if (!empty($job["caloocan_area"])): ?>
                        <span>
                            <?= ucfirst(
                                htmlspecialchars(
                                    $job["caloocan_area"]
                                )
                            ) ?>
                            Caloocan
                        </span>
                    <?php endif; ?>

                </div>


                <?php if (
                    $job["salary_min"] !== null ||
                    $job["salary_max"] !== null
                ): ?>

                    <div class="salary">

                        <?php if ($job["salary_min"] !== null): ?>
                            ₱<?= number_format(
                                (float) $job["salary_min"],
                                2
                            ) ?>
                        <?php endif; ?>


                        <?php if (
                            $job["salary_min"] !== null &&
                            $job["salary_max"] !== null
                        ): ?>
                            -
                        <?php endif; ?>


                        <?php if ($job["salary_max"] !== null): ?>
                            ₱<?= number_format(
                                (float) $job["salary_max"],
                                2
                            ) ?>
                        <?php endif; ?>

                    </div>

                <?php endif; ?>


                <div class="section">

                    <h2>Job Description</h2>

                    <p><?= htmlspecialchars(
                        $job["job_description"]
                    ) ?></p>

                </div>


                <?php if (!empty($job["requirements"])): ?>

                    <div class="section">

                        <h2>General Requirements</h2>

                        <p><?= htmlspecialchars(
                            $job["requirements"]
                        ) ?></p>

                    </div>

                <?php endif; ?>


                <div class="section">

                    <h2>
                        Qualification Requirements
                    </h2>


                    <div class="requirement-grid">

                        <div class="requirement-item">

                            <span class="requirement-label">
                                Required Skills
                            </span>

                            <?= nl2br(
                                htmlspecialchars(
                                    $job["required_skills"]
                                        ?: "Not specified"
                                )
                            ) ?>

                        </div>


                        <div class="requirement-item">

                            <span class="requirement-label">
                                Educational Requirement
                            </span>

                            <?= htmlspecialchars(
                                $job["educational_requirement"]
                                    ?: "No specific requirement"
                            ) ?>

                        </div>


                        <div class="requirement-item">

                            <span class="requirement-label">
                                Degree / Program
                            </span>

                            <?= htmlspecialchars(
                                $job["required_degree"]
                                    ?: "No specific degree"
                            ) ?>

                        </div>


                        <div class="requirement-item">

                            <span class="requirement-label">
                                Degree Classification
                            </span>

                            <?= htmlspecialchars(
                                $job["degree_classification"]
                                    ?: "Not specified"
                            ) ?>

                        </div>


                        <div class="requirement-item">

                            <span class="requirement-label">
                                TESDA Qualification
                            </span>

                            <?= htmlspecialchars(
                                $job["tesda_qualification"]
                                    ?: "Not required"
                            ) ?>

                        </div>


                        <div class="requirement-item">

                            <span class="requirement-label">
                                Required NC Level
                            </span>

                            <?= htmlspecialchars(
                                $job["required_nc_level"]
                                    ?: "No NC Required"
                            ) ?>

                        </div>

                    </div>

                </div>


                <?php if (
                    ($job["job_source"] ?? "private_employer")
                    !== "private_employer"
                ): ?>

                    <div class="source-box">

                        <strong>
                            Opportunity Source
                        </strong>

                        <br>

                        Type:
                        <?= htmlspecialchars(
                            ucwords(
                                str_replace(
                                    "_",
                                    " ",
                                    $job["job_source"]
                                )
                            )
                        ) ?>


                        <?php if (!empty($job["source_name"])): ?>

                            <br>

                            Source:
                            <?= htmlspecialchars(
                                $job["source_name"]
                            ) ?>

                        <?php endif; ?>


                        <?php if (!empty($job["lgu_job_category"])): ?>

                            <br>

                            Category:
                            <?= htmlspecialchars(
                                $job["lgu_job_category"]
                            ) ?>

                        <?php endif; ?>

                    </div>

                <?php endif; ?>

            </div>


            <div class="card">

                <h2>Job Location</h2>

                <div class="location-box">

                    <strong>
                        <?= htmlspecialchars(
                            $job["barangay"]
                                ?: "Caloocan City"
                        ) ?>
                    </strong>

                    <br>

                    <?= htmlspecialchars(
                        $job["address"]
                            ?: (
                                $job["location"]
                                ?: "Caloocan City"
                            )
                    ) ?>


                    <?php if (!empty($job["caloocan_area"])): ?>

                        <br>

                        <span
                            class="area-badge <?= htmlspecialchars(
                                $job["caloocan_area"]
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                strtoupper(
                                    $job["caloocan_area"]
                                )
                            ) ?>
                            CALOOCAN
                        </span>

                    <?php endif; ?>


                    <?php if ($distanceKm !== null): ?>

                        <div class="distance">
                            <?= number_format(
                                $distanceKm,
                                2
                            ) ?>
                            km away from your saved location
                        </div>

                    <?php elseif ($isApplicant): ?>

                        <div class="distance">
                            Distance unavailable.
                            Please check your saved
                            applicant location.
                        </div>

                    <?php endif; ?>

                </div>

            </div>

        </main>


        <aside>

            <div class="card">

                <h2>
                    Interested in this job?
                </h2>


                <?php if (!isset($_SESSION["user_id"])): ?>

                    <div class="notice">
                        Please login as an applicant
                        to submit an application.
                    </div>

                    <a
                        href="../login.php"
                        class="apply-btn"
                    >
                        Login to Apply
                    </a>


                <?php elseif (
                    ($_SESSION["role"] ?? "")
                    !== "applicant"
                ): ?>

                    <div class="notice">
                        Only applicant accounts
                        can apply for jobs.
                    </div>


                <?php elseif ($alreadyApplied): ?>

                    <div class="notice">
                        You have already applied
                        for this job.
                    </div>

                    <a
                        href="../applicant/application.php?id=<?= $existingApplicationId ?>"
                        class="apply-btn"
                    >
                        View Application
                    </a>


                <?php else: ?>

                    <div class="notice">
                        Your resume skills,
                        education, and TESDA/NC
                        qualifications will be
                        compared with this job's
                        requirements.
                    </div>

                    <a
                        href="apply.php?id=<?= (int) $job["id"] ?>"
                        class="apply-btn"
                    >
                        Apply Now
                    </a>

                <?php endif; ?>


                <?php if (!empty($job["application_deadline"])): ?>

                    <div class="deadline">

                        Application deadline:

                        <strong>
                            <?= date(
                                "F d, Y",
                                strtotime(
                                    $job["application_deadline"]
                                )
                            ) ?>
                        </strong>

                    </div>

                <?php endif; ?>

            </div>

        </aside>

    </div>

</div>

</body>
</html>