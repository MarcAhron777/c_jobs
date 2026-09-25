<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "applicant") {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION["user_id"];

$applicationId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($applicationId <= 0) {
    die("Invalid application.");
}


/*
|--------------------------------------------------------------------------
| Get applicant ID
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM applicants
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$applicant = $stmt->fetch();

if (!$applicant) {
    die("Applicant profile not found.");
}

$applicantId = $applicant["id"];


/*
|--------------------------------------------------------------------------
| Get application
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ap.id,
        ap.cover_letter,
        ap.status,
        ap.applied_at,
        ap.updated_at,

        am.match_score,
        am.skills_score,
        am.experience_score,
        am.education_score,
        am.matched_skills,
        am.missing_skills,

        j.id AS job_id,
        j.job_title,
        j.description,
        j.requirements,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.is_remote,

        e.company_name,
        e.company_description

    FROM applications ap

    INNER JOIN jobs j
        ON ap.job_id = j.id

    INNER JOIN employers e
        ON j.employer_id = e.id

    LEFT JOIN application_matches am
        ON ap.id = am.application_id

    WHERE ap.id = ?
    AND ap.applicant_id = ?

    LIMIT 1
");

$stmt->execute([
    $applicationId,
    $applicantId
]);

$application = $stmt->fetch();

if (!$application) {
    die("Application not found.");
}

$matchScore = $application["match_score"] ?? 0;
$skillsScore = $application["skills_score"] ?? 0;
$experienceScore = $application["experience_score"] ?? 0;
$educationScore = $application["education_score"] ?? 0;

$matchedSkillsList = [];

if (!empty($application["matched_skills"])) {
    $matchedSkillsList = array_filter(
        array_map(
            "trim",
            explode(",", $application["matched_skills"])
        )
    );
}

$missingSkillsList = [];

if (!empty($application["missing_skills"])) {
    $missingSkillsList = array_filter(
        array_map(
            "trim",
            explode(",", $application["missing_skills"])
        )
    );
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
        Application Details - Caloocan Job Portal
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
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
        }

        .nav-links a:hover {
            color: #2563eb;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .back:hover {
            text-decoration: underline;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        h1 {
            margin-bottom: 10px;
        }

        h2 {
            margin-bottom: 15px;
        }

        h3 {
            margin-bottom: 10px;
        }

        .company {
            color: #555;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .meta span {
            background: #f1f5f9;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: bold;
            margin-bottom: 20px;
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

        .section {
            margin-top: 25px;
        }

        .section p {
            line-height: 1.7;
            white-space: pre-line;
        }

        .date {
            color: #666;
            font-size: 14px;
        }

        .match-box {
            background: #f8fafc;
            padding: 25px;
            border-radius: 10px;
            margin-top: 15px;
            text-align: center;
        }

        .match-score {
            font-size: 42px;
            font-weight: bold;
            color: #2563eb;
        }

        .match-label {
            color: #666;
            margin-top: 5px;
        }

        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .matched-skill {
            background: #dcfce7;
            color: #166534;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        .missing-skill {
            background: #fee2e2;
            color: #991b1b;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 14px;
        }

        .breakdown {
            margin-top: 20px;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .breakdown-item:last-child {
            border-bottom: none;
        }

        .breakdown-value {
            font-weight: bold;
            color: #2563eb;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .btn:hover {
            background: #1d4ed8;
        }

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

            .container {
                margin: 25px auto;
            }

            .card {
                padding: 20px;
            }

            .breakdown-item {
                gap: 15px;
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

        <a href="applications.php">
            Applications
        </a>

        <a href="../jobs/index.php">
            Find Jobs
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</nav>


<div class="container">

    <a
        href="applications.php"
        class="back"
    >
        ← Back to Applications
    </a>


    <!--
    |--------------------------------------------------------------------------
    | Application Summary
    |--------------------------------------------------------------------------
    -->

    <div class="card">

        <h1>
            <?= htmlspecialchars(
                $application["job_title"]
            ) ?>
        </h1>

        <div class="company">

            <?= htmlspecialchars(
                $application["company_name"]
            ) ?>

        </div>


        <div class="meta">

            <span>

                📍

                <?= htmlspecialchars(
                    $application["location"] ?? "Not specified"
                ) ?>

            </span>


            <span>

                💼

                <?= htmlspecialchars(
                    $application["employment_type"]
                ) ?>

            </span>


            <?php if ($application["is_remote"]): ?>

                <span>
                    🌐 Remote
                </span>

            <?php endif; ?>

        </div>


        <div>

            <span
                class="status <?= htmlspecialchars(
                    $application["status"]
                ) ?>"
            >

                <?= ucfirst(
                    htmlspecialchars(
                        $application["status"]
                    )
                ) ?>

            </span>

        </div>


        <div class="date">

            Applied:

            <?= date(
                "F d, Y h:i A",
                strtotime(
                    $application["applied_at"]
                )
            ) ?>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Job Description
    |--------------------------------------------------------------------------
    -->

    <div class="card">

        <h2>
            Job Description
        </h2>

        <div class="section">

            <p>
                <?= htmlspecialchars(
                    $application["description"]
                ) ?>
            </p>

        </div>


        <?php if (!empty($application["requirements"])): ?>

            <div class="section">

                <h2>
                    Requirements
                </h2>

                <p>
                    <?= htmlspecialchars(
                        $application["requirements"]
                    ) ?>
                </p>

            </div>

        <?php endif; ?>


        <?php if (
            $application["salary_min"] !== null ||
            $application["salary_max"] !== null
        ): ?>

            <div class="section">

                <h2>
                    Salary
                </h2>

                <p>

                    <?php if (
                        $application["salary_min"] !== null
                    ): ?>

                        ₱<?= number_format(
                            $application["salary_min"],
                            2
                        ) ?>

                    <?php endif; ?>


                    <?php if (
                        $application["salary_min"] !== null &&
                        $application["salary_max"] !== null
                    ): ?>

                        -

                    <?php endif; ?>


                    <?php if (
                        $application["salary_max"] !== null
                    ): ?>

                        ₱<?= number_format(
                            $application["salary_max"],
                            2
                        ) ?>

                    <?php endif; ?>

                </p>

            </div>

        <?php endif; ?>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Resume Match Result
    |--------------------------------------------------------------------------
    -->

    <div class="card">

        <h2>
            Resume Match Result
        </h2>


        <div class="match-box">

            <div class="match-score">

                <?= number_format(
                    $matchScore,
                    2
                ) ?>%

            </div>

            <div class="match-label">

                Overall Resume Match

            </div>

        </div>


        <!-- Matched Skills -->

        <div class="section">

            <h2>
                Matched Skills
            </h2>


            <?php if (!empty($matchedSkillsList)): ?>

                <div class="skills-container">

                    <?php foreach (
                        $matchedSkillsList as $skill
                    ): ?>

                        <span class="matched-skill">

                            ✓
                            <?= htmlspecialchars(
                                $skill
                            ) ?>

                        </span>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p>
                    No matched skills found.
                </p>

            <?php endif; ?>

        </div>


        <!-- Missing Skills -->

        <div class="section">

            <h2>
                Missing Skills
            </h2>


            <?php if (!empty($missingSkillsList)): ?>

                <div class="skills-container">

                    <?php foreach (
                        $missingSkillsList as $skill
                    ): ?>

                        <span class="missing-skill">

                            ✕
                            <?= htmlspecialchars(
                                $skill
                            ) ?>

                        </span>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p>
                    Great! No missing skills detected.
                </p>

            <?php endif; ?>

        </div>


        <!-- Match Breakdown -->

        <div class="section">

            <h2>
                Match Breakdown
            </h2>


            <div class="breakdown">

                <div class="breakdown-item">

                    <span>
                        Skills Match
                    </span>

                    <span class="breakdown-value">

                        <?= number_format(
                            $skillsScore,
                            2
                        ) ?>%

                    </span>

                </div>


                <div class="breakdown-item">

                    <span>
                        Experience Match
                    </span>

                    <span class="breakdown-value">

                        <?= number_format(
                            $experienceScore,
                            2
                        ) ?>%

                    </span>

                </div>


                <div class="breakdown-item">

                    <span>
                        Education Match
                    </span>

                    <span class="breakdown-value">

                        <?= number_format(
                            $educationScore,
                            2
                        ) ?>%

                    </span>

                </div>

            </div>

        </div>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Cover Letter
    |--------------------------------------------------------------------------
    -->

    <div class="card">

        <h2>
            Your Cover Letter
        </h2>


        <?php if (
            !empty($application["cover_letter"])
        ): ?>

            <p
                style="
                    line-height:1.7;
                    white-space:pre-line;
                "
            >

                <?= htmlspecialchars(
                    $application["cover_letter"]
                ) ?>

            </p>

        <?php else: ?>

            <p>
                No cover letter was submitted.
            </p>

        <?php endif; ?>

    </div>


    <!--
    |--------------------------------------------------------------------------
    | Company
    |--------------------------------------------------------------------------
    -->

    <div class="card">

        <h2>
            Company
        </h2>


        <h3>

            <?= htmlspecialchars(
                $application["company_name"]
            ) ?>

        </h3>


        <?php if (
            !empty(
                $application["company_description"]
            )
        ): ?>

            <p
                style="
                    line-height:1.7;
                    margin-top:10px;
                "
            >

                <?= htmlspecialchars(
                    $application["company_description"]
                ) ?>

            </p>

        <?php else: ?>

            <p>
                No company description available.
            </p>

        <?php endif; ?>

    </div>


</div>

</body>

</html>