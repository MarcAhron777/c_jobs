<?php

session_start();

require_once "../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "applicant"
) {
    header("Location: ../login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| GET APPLICANT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        first_name,
        last_name,
        phone,
        address,
        bio
    FROM applicants
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant profile not found.");
}

$applicantId = (int) $applicant["id"];


/*
|--------------------------------------------------------------------------
| TOTAL APPLICATIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM applications
    WHERE applicant_id = ?
");

$stmt->execute([$applicantId]);

$totalApplications = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| ACTIVE APPLICATIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM applications
    WHERE applicant_id = ?
    AND status IN (
        'pending',
        'reviewed',
        'shortlisted'
    )
");

$stmt->execute([$applicantId]);

$activeApplications = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| LATEST RESUME
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM resumes
    WHERE applicant_id = ?
    ORDER BY uploaded_at DESC
    LIMIT 1
");

$stmt->execute([$applicantId]);

$resume = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| RECENT APPLICATIONS WITH MATCHING RESULTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        ap.id,
        ap.status,
        ap.applied_at,

        j.job_title,
        j.location,
        j.employment_type,

        e.company_name,

        am.match_score,
        am.skills_score,
        am.education_score,
        am.tesda_score,
        am.skill_gap_feedback

    FROM applications ap

    INNER JOIN jobs j
        ON ap.job_id = j.id

    INNER JOIN employers e
        ON j.employer_id = e.id

    LEFT JOIN application_matches am
        ON ap.id = am.application_id

    WHERE ap.applicant_id = ?

    ORDER BY ap.applied_at DESC

    LIMIT 5
");

$stmt->execute([$applicantId]);

$recentApplications = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        Applicant Dashboard - Caloocan Job Portal
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

        .container {
            max-width: 1200px;
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
            color: #666;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            color: #2563eb;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .card {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }

        .card h2 {
            margin-bottom: 20px;
        }

        .application-item {
            padding: 18px 0;
            border-bottom: 1px solid #eee;
        }

        .application-item:last-child {
            border-bottom: none;
        }

        .application-item h3 {
            margin-bottom: 5px;
        }

        .company {
            color: #666;
            margin-bottom: 5px;
        }

        .details {
            color: #777;
            font-size: 14px;
            margin-bottom: 10px;
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

        .match-box {
            margin-top: 12px;
            padding: 13px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
        }

        .overall-match {
            font-size: 19px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 8px;
        }

        .match-breakdown {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .match-breakdown span {
            background: #fff;
            padding: 5px 8px;
            border-radius: 5px;
            font-size: 11px;
            color: #475569;
        }

        .skill-gap {
            margin-top: 10px;
            padding: 10px;
            border-radius: 6px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 12px;
            line-height: 1.6;
        }

        .application-actions {
            margin-top: 12px;
        }

        .view-details {
            color: #2563eb;
            text-decoration: none;
            font-size: 13px;
            font-weight: bold;
        }

        .view-details:hover {
            text-decoration: underline;
        }

        .profile-info p {
            margin-bottom: 10px;
        }

        .profile-info strong {
            display: inline-block;
            width: 100px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            margin-top: 10px;
            margin-right: 5px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .resume-status {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .empty-text {
            color: #666;
            line-height: 1.6;
        }

        @media (max-width: 768px) {

            .stats {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>

<?php
require __DIR__ . "/includes/header.php";
?>


<div class="container">

    <!-- =========================================================
         WELCOME
    ========================================================== -->

    <div class="welcome">

        <h1>
            Welcome,
            <?= htmlspecialchars(
                $applicant["first_name"]
            ) ?>!
        </h1>

        <p>
            Manage your profile, qualifications,
            resume, and job applications.
        </p>

    </div>


    <!-- =========================================================
         STATISTICS
    ========================================================== -->

    <div class="stats">

        <div class="stat-card">

            <h3>
                Total Applications
            </h3>

            <div class="stat-number">
                <?= $totalApplications ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Active Applications
            </h3>

            <div class="stat-number">
                <?= $activeApplications ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>
                Resume
            </h3>

            <div class="stat-number">
                <?= $resume ? "Ready" : "None" ?>
            </div>

        </div>

    </div>


    <!-- =========================================================
         MAIN CONTENT
    ========================================================== -->

    <div class="content-grid">

        <!-- =====================================================
             RECENT APPLICATIONS
        ====================================================== -->

        <div class="card">

            <h2>
                Recent Applications
            </h2>


            <?php if (!empty($recentApplications)): ?>


                <?php foreach ($recentApplications as $application): ?>

                    <?php

                    $overallMatch = max(
                        0,
                        min(
                            100,
                            (float) (
                                $application["match_score"]
                                ?? 0
                            )
                        )
                    );

                    $skillsScore = max(
                        0,
                        min(
                            100,
                            (float) (
                                $application["skills_score"]
                                ?? 0
                            )
                        )
                    );

                    $educationScore = max(
                        0,
                        min(
                            100,
                            (float) (
                                $application["education_score"]
                                ?? 0
                            )
                        )
                    );

                    $tesdaScore = max(
                        0,
                        min(
                            100,
                            (float) (
                                $application["tesda_score"]
                                ?? 0
                            )
                        )
                    );

                    ?>


                    <div class="application-item">

                        <h3>
                            <?= htmlspecialchars(
                                $application["job_title"]
                            ) ?>
                        </h3>


                        <div class="company">

                            <?= htmlspecialchars(
                                $application["company_name"]
                            ) ?>

                        </div>


                        <div class="details">

                            <?= htmlspecialchars(
                                $application["location"]
                                ?? "Not specified"
                            ) ?>

                            &bull;

                            <?= htmlspecialchars(
                                $application["employment_type"]
                            ) ?>

                        </div>


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


                        <!-- MATCH SUMMARY -->

                        <div class="match-box">

                            <div class="overall-match">

                                Overall Match:
                                <?= number_format(
                                    $overallMatch,
                                    0
                                ) ?>%

                            </div>


                            <div class="match-breakdown">

                                <span>
                                    Skills:
                                    <?= number_format(
                                        $skillsScore,
                                        0
                                    ) ?>%
                                </span>


                                <span>
                                    Education:
                                    <?= number_format(
                                        $educationScore,
                                        0
                                    ) ?>%
                                </span>


                                <span>
                                    TESDA / NC:
                                    <?= number_format(
                                        $tesdaScore,
                                        0
                                    ) ?>%
                                </span>

                            </div>


                            <?php if (!empty(
                                $application["skill_gap_feedback"]
                            )): ?>

                                <div class="skill-gap">

                                    <strong>
                                        Skill Gap Feedback
                                    </strong>

                                    <br>

                                    <?= nl2br(
                                        htmlspecialchars(
                                            $application[
                                                "skill_gap_feedback"
                                            ]
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <div class="application-actions">

                            <a
                                href="application.php?id=<?= (int)
                                    $application["id"]
                                ?>"
                                class="view-details"
                            >
                                View Match Details
                            </a>

                        </div>

                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <p class="empty-text">
                    You haven't applied for any jobs yet.
                </p>

                <a
                    href="../jobs/index.php"
                    class="btn"
                >
                    Find Jobs
                </a>


            <?php endif; ?>

        </div>


        <!-- =====================================================
             RIGHT COLUMN
        ====================================================== -->

        <div>


            <!-- PROFILE -->

            <div class="card">

                <h2>
                    My Profile
                </h2>


                <div class="profile-info">

                    <p>

                        <strong>
                            Name:
                        </strong>

                        <?= htmlspecialchars(
                            $applicant["first_name"]
                            . " "
                            . $applicant["last_name"]
                        ) ?>

                    </p>


                    <p>

                        <strong>
                            Phone:
                        </strong>

                        <?= htmlspecialchars(
                            $applicant["phone"]
                            ?? "Not provided"
                        ) ?>

                    </p>


                    <p>

                        <strong>
                            Address:
                        </strong>

                        <?= htmlspecialchars(
                            $applicant["address"]
                            ?? "Not provided"
                        ) ?>

                    </p>

                </div>


                <a
                    href="profile.php"
                    class="btn"
                >
                    Edit Profile
                </a>


                <a
                    href="qualifications.php"
                    class="btn"
                >
                    Qualifications
                </a>

            </div>


            <!-- RESUME -->

            <div class="card">

                <h2>
                    My Resume
                </h2>


                <?php if ($resume): ?>

                    <div class="resume-status">

                        <strong>

                            <?= htmlspecialchars(
                                $resume["file_name"]
                            ) ?>

                        </strong>

                        <br>

                        Uploaded:

                        <?= date(
                            "M d, Y",
                            strtotime(
                                $resume["uploaded_at"]
                            )
                        ) ?>

                    </div>


                <?php else: ?>


                    <div class="resume-status">
                        No resume uploaded yet.
                    </div>


                <?php endif; ?>


                <a
                    href="resume.php"
                    class="btn"
                >

                    <?= $resume
                        ? "Manage Resume"
                        : "Upload Resume"
                    ?>

                </a>

            </div>

        </div>

    </div>

</div>

</body>
</html>