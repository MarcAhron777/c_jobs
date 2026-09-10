<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Employer Only
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"])
    || $_SESSION["role"] !== "employer"
) {
    header("Location: ../login.php");
    exit;
}


$userId = (int) $_SESSION["user_id"];

$applicationId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


$error = "";

$success = "";


if ($applicationId <= 0) {

    die("Invalid application.");

}


/*
|--------------------------------------------------------------------------
| Get Employer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        company_name
    FROM employers
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$employer = $stmt->fetch();


if (!$employer) {

    die("Employer profile not found.");

}


$employerId = (int) $employer["id"];


/*
|--------------------------------------------------------------------------
| Update Application Status
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $status = $_POST["status"] ?? "";


    $allowedStatuses = [
        "pending",
        "reviewed",
        "shortlisted",
        "rejected",
        "hired"
    ];


    if (
        !in_array(
            $status,
            $allowedStatuses,
            true
        )
    ) {

        $error = "Invalid application status.";

    } else {

        $stmt = $pdo->prepare("
            UPDATE applications a

            INNER JOIN jobs j
                ON a.job_id = j.id

            SET a.status = ?

            WHERE a.id = ?

            AND j.employer_id = ?
        ");


        $stmt->execute([
            $status,
            $applicationId,
            $employerId
        ]);


        if ($stmt->rowCount() > 0) {

            $success =
                "Application status updated successfully.";

        } else {

            $error =
                "Application not found or no changes were made.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| Get Application
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        a.id AS application_id,
        a.status,
        a.cover_letter,
        a.applied_at,
        a.updated_at,

        j.id AS job_id,
        j.job_title,
        j.description AS job_description,
        j.requirements,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.is_remote,
        j.application_deadline,

        ap.id AS applicant_id,
        ap.first_name,
        ap.last_name,
        ap.phone,
        ap.address,
        ap.bio,
        ap.profile_picture,

        u.email,

        r.id AS resume_id,
        r.file_name,
        r.file_path,
        r.file_type,
        r.uploaded_at,

        am.match_score,
        am.skills_score,
        am.experience_score,
        am.education_score,
        am.matched_skills,
        am.missing_skills,

        e.company_name,
        e.company_description,
        e.company_logo,
        e.contact_person,
        e.contact_number,
        e.address AS company_address

    FROM applications a

    INNER JOIN jobs j
        ON a.job_id = j.id

    INNER JOIN employers e
        ON j.employer_id = e.id

    INNER JOIN applicants ap
        ON a.applicant_id = ap.id

    INNER JOIN users u
        ON ap.user_id = u.id

    LEFT JOIN resumes r
        ON a.resume_id = r.id

    LEFT JOIN application_matches am
        ON a.id = am.application_id

    WHERE a.id = ?

    AND j.employer_id = ?

    LIMIT 1
");


$stmt->execute([
    $applicationId,
    $employerId
]);


$application = $stmt->fetch();


if (!$application) {

    die("Application not found or you do not have permission to view it.");

}


/*
|--------------------------------------------------------------------------
| Match Score
|--------------------------------------------------------------------------
*/

$matchScore =
    (float) (
        $application["match_score"]
        ?? 0
    );


if ($matchScore < 0) {
    $matchScore = 0;
}


if ($matchScore > 100) {
    $matchScore = 100;
}


/*
|--------------------------------------------------------------------------
| Status Class
|--------------------------------------------------------------------------
*/

$statusClass =
    "status-" .
    strtolower(
        $application["status"]
    );


/*
|--------------------------------------------------------------------------
| Salary
|--------------------------------------------------------------------------
*/

$salaryText = "Not specified";


if (
    $application["salary_min"] !== null
    && $application["salary_max"] !== null
) {

    $salaryText =
        "₱"
        . number_format(
            (float) $application["salary_min"],
            2
        )
        . " - ₱"
        . number_format(
            (float) $application["salary_max"],
            2
        );

} elseif (
    $application["salary_min"] !== null
) {

    $salaryText =
        "From ₱"
        . number_format(
            (float) $application["salary_min"],
            2
        );

} elseif (
    $application["salary_max"] !== null
) {

    $salaryText =
        "Up to ₱"
        . number_format(
            (float) $application["salary_max"],
            2
        );

}


/*
|--------------------------------------------------------------------------
| Matched Skills
|--------------------------------------------------------------------------
*/

$matchedSkills = [];

if (!empty($application["matched_skills"])) {

    $matchedSkills = array_filter(
        array_map(
            "trim",
            preg_split(
                "/[,;\r\n]+/",
                $application["matched_skills"]
            )
        )
    );

}


/*
|--------------------------------------------------------------------------
| Missing Skills
|--------------------------------------------------------------------------
*/

$missingSkills = [];

if (!empty($application["missing_skills"])) {

    $missingSkills = array_filter(
        array_map(
            "trim",
            preg_split(
                "/[,;\r\n]+/",
                $application["missing_skills"]
            )
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
    View Application - Caloocan Job Portal
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

    line-height: 1.6;
}


/*
|--------------------------------------------------------------------------
| Navbar
|--------------------------------------------------------------------------
*/

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
| Back
|--------------------------------------------------------------------------
*/

.back-link {
    display: inline-block;

    margin-bottom: 20px;

    color: #2563eb;

    text-decoration: none;

    font-size: 14px;
}


.back-link:hover {
    text-decoration: underline;
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.page-header {
    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);

    margin-bottom: 20px;
}


.page-header h1 {
    font-size: 28px;

    margin-bottom: 5px;
}


.page-header .company {
    color: #666;

    font-size: 16px;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

.status {
    display: inline-block;

    padding: 7px 13px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: bold;

    margin-top: 15px;
}


.status-pending {
    background: #fef3c7;

    color: #92400e;
}


.status-reviewed {
    background: #dbeafe;

    color: #1e40af;
}


.status-shortlisted {
    background: #dcfce7;

    color: #166534;
}


.status-rejected {
    background: #fee2e2;

    color: #991b1b;
}


.status-hired {
    background: #d1fae5;

    color: #065f46;
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

.message {
    padding: 12px 15px;

    border-radius: 6px;

    margin-bottom: 20px;
}


.success {
    background: #dcfce7;

    color: #166534;
}


.error {
    background: #fee2e2;

    color: #991b1b;
}


/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

.content-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 2fr)
        minmax(280px, 1fr);

    gap: 20px;

    align-items: start;
}


/*
|--------------------------------------------------------------------------
| Card
|--------------------------------------------------------------------------
*/

.card {
    background: white;

    padding: 25px;

    border-radius: 12px;

    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);

    margin-bottom: 20px;
}


.card h2 {
    font-size: 19px;

    margin-bottom: 15px;

    color: #111827;
}


.card h3 {
    font-size: 15px;

    margin-bottom: 7px;

    color: #111827;
}


.card p {
    color: #555;

    white-space: pre-line;
}


/*
|--------------------------------------------------------------------------
| Applicant Profile
|--------------------------------------------------------------------------
*/

.profile-name {
    font-size: 24px;

    font-weight: bold;

    margin-bottom: 8px;
}


.profile-item {
    margin-bottom: 7px;

    color: #555;
}


.profile-item strong {
    color: #333;
}


/*
|--------------------------------------------------------------------------
| Match Box
|--------------------------------------------------------------------------
*/

.match-box {
    background: #eff6ff;

    border: 1px solid #bfdbfe;

    border-radius: 10px;

    padding: 20px;
}


.match-header {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 12px;
}


.match-title {
    font-size: 17px;

    font-weight: bold;

    color: #1e3a8a;
}


.match-score {
    font-size: 32px;

    font-weight: bold;

    color: #2563eb;
}


.progress {
    height: 12px;

    width: 100%;

    background: #dbeafe;

    border-radius: 20px;

    overflow: hidden;

    margin-bottom: 20px;
}


.progress-bar {
    height: 100%;

    background: #2563eb;

    border-radius: 20px;
}


/*
|--------------------------------------------------------------------------
| Score Details
|--------------------------------------------------------------------------
*/

.score-grid {
    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 10px;
}


.score-item {
    background: white;

    border: 1px solid #ddd;

    border-radius: 7px;

    padding: 12px;

    text-align: center;
}


.score-item span {
    display: block;

    font-size: 12px;

    color: #777;

    margin-bottom: 3px;
}


.score-item strong {
    font-size: 18px;

    color: #2563eb;
}


/*
|--------------------------------------------------------------------------
| Skills
|--------------------------------------------------------------------------
*/

.skill-section {
    margin-top: 20px;
}


.skill-section h3 {
    margin-bottom: 10px;
}


.skill-list {
    display: flex;

    flex-wrap: wrap;

    gap: 7px;
}


.skill {
    padding: 6px 10px;

    border-radius: 20px;

    font-size: 12px;
}


.skill-matched {
    background: #dcfce7;

    color: #166534;
}


.skill-missing {
    background: #fee2e2;

    color: #991b1b;
}


/*
|--------------------------------------------------------------------------
| Job Info
|--------------------------------------------------------------------------
*/

.job-info {
    display: grid;

    grid-template-columns:
        1fr 1fr;

    gap: 15px;
}


.job-info-item {
    background: #f8fafc;

    border: 1px solid #e5e7eb;

    padding: 13px;

    border-radius: 7px;
}


.job-info-item span {
    display: block;

    color: #777;

    font-size: 12px;

    margin-bottom: 3px;
}


.job-info-item strong {
    font-size: 14px;

    color: #333;
}


/*
|--------------------------------------------------------------------------
| Resume
|--------------------------------------------------------------------------
*/

.resume-box {
    background: #f8fafc;

    border: 1px solid #ddd;

    border-radius: 8px;

    padding: 15px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;
}


.resume-name {
    font-weight: bold;

    font-size: 14px;
}


.resume-type {
    color: #888;

    font-size: 12px;

    margin-top: 3px;
}


.resume-btn {
    display: inline-block;

    padding: 9px 14px;

    background: #64748b;

    color: white;

    text-decoration: none;

    border-radius: 6px;

    font-size: 13px;

    white-space: nowrap;
}


/*
|--------------------------------------------------------------------------
| Status Update
|--------------------------------------------------------------------------
*/

.status-form select {
    width: 100%;

    padding: 11px;

    border: 1px solid #ccc;

    border-radius: 6px;

    margin-bottom: 10px;

    background: white;
}


.update-btn {
    width: 100%;

    padding: 11px;

    background: #2563eb;

    color: white;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    font-weight: bold;
}


.update-btn:hover {
    background: #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| Company
|--------------------------------------------------------------------------
*/

.company-item {
    margin-bottom: 10px;

    font-size: 14px;

    color: #555;
}


.company-item strong {
    color: #333;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .content-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 600px) {

    .navbar {
        padding: 15px 20px;

        flex-direction: column;

        gap: 12px;
    }


    .page-header h1 {
        font-size: 23px;
    }


    .score-grid {
        grid-template-columns: 1fr;
    }


    .job-info {
        grid-template-columns: 1fr;
    }


    .resume-box {
        flex-direction: column;

        align-items: flex-start;
    }


    .resume-btn {
        width: 100%;

        text-align: center;
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


    <a
        href="applicants.php"
        class="back-link"
    >
        ← Back to Applicants
    </a>


    <?php if ($success): ?>

        <div class="message success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="message error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- Page Header -->

    <div class="page-header">

        <h1>

            <?= htmlspecialchars(
                $application["first_name"]
                . " "
                . $application["last_name"]
            ) ?>

        </h1>


        <div class="company">

            Applied for:

            <strong>

                <?= htmlspecialchars(
                    $application["job_title"]
                ) ?>

            </strong>

            at

            <strong>

                <?= htmlspecialchars(
                    $application["company_name"]
                ) ?>

            </strong>

        </div>


        <span
            class="status <?= htmlspecialchars(
                $statusClass
            ) ?>"
        >

            <?= ucfirst(
                htmlspecialchars(
                    $application["status"]
                )
            ) ?>

        </span>

    </div>


    <div class="content-grid">


        <!-- LEFT COLUMN -->

        <div>


            <!-- Applicant Information -->

            <div class="card">

                <h2>
                    Applicant Information
                </h2>


                <div class="profile-name">

                    <?= htmlspecialchars(
                        $application["first_name"]
                        . " "
                        . $application["last_name"]
                    ) ?>

                </div>


                <div class="profile-item">

                    <strong>
                        Email:
                    </strong>

                    <?= htmlspecialchars(
                        $application["email"]
                    ) ?>

                </div>


                <?php if (
                    !empty($application["phone"])
                ): ?>

                    <div class="profile-item">

                        <strong>
                            Phone:
                        </strong>

                        <?= htmlspecialchars(
                            $application["phone"]
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    !empty($application["address"])
                ): ?>

                    <div class="profile-item">

                        <strong>
                            Address:
                        </strong>

                        <?= htmlspecialchars(
                            $application["address"]
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    !empty($application["bio"])
                ): ?>

                    <div style="margin-top:20px;">

                        <h3>
                            Bio
                        </h3>

                        <p>

                            <?= htmlspecialchars(
                                $application["bio"]
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>

            </div>


            <!-- Resume Match -->

            <div class="card">

                <h2>
                    Resume Match Result
                </h2>


                <div class="match-box">

                    <div class="match-header">

                        <div class="match-title">

                            Overall Match

                        </div>


                        <div class="match-score">

                            <?= number_format(
                                $matchScore,
                                0
                            ) ?>%

                        </div>

                    </div>


                    <div class="progress">

                        <div
                            class="progress-bar"
                            style="width: <?= $matchScore ?>%;"
                        ></div>

                    </div>


                    <div class="score-grid">

                        <div class="score-item">

                            <span>
                                Skills
                            </span>

                            <strong>

                                <?= number_format(
                                    (float) (
                                        $application[
                                            "skills_score"
                                        ] ?? 0
                                    ),
                                    0
                                ) ?>%

                            </strong>

                        </div>


                        <div class="score-item">

                            <span>
                                Experience
                            </span>

                            <strong>

                                <?= number_format(
                                    (float) (
                                        $application[
                                            "experience_score"
                                        ] ?? 0
                                    ),
                                    0
                                ) ?>%

                            </strong>

                        </div>


                        <div class="score-item">

                            <span>
                                Education
                            </span>

                            <strong>

                                <?= number_format(
                                    (float) (
                                        $application[
                                            "education_score"
                                        ] ?? 0
                                    ),
                                    0
                                ) ?>%

                            </strong>

                        </div>

                    </div>


                    <!-- Matched Skills -->

                    <div class="skill-section">

                        <h3>
                            ✓ Matched Skills
                        </h3>


                        <?php if (
                            count($matchedSkills) > 0
                        ): ?>

                            <div class="skill-list">

                                <?php foreach (
                                    $matchedSkills
                                    as $skill
                                ): ?>

                                    <span
                                        class="skill skill-matched"
                                    >

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

                    <div class="skill-section">

                        <h3>
                            ✗ Missing Skills
                        </h3>


                        <?php if (
                            count($missingSkills) > 0
                        ): ?>

                            <div class="skill-list">

                                <?php foreach (
                                    $missingSkills
                                    as $skill
                                ): ?>

                                    <span
                                        class="skill skill-missing"
                                    >

                                        <?= htmlspecialchars(
                                            $skill
                                        ) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <p>
                                No missing skills.
                            </p>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- Cover Letter -->

            <div class="card">

                <h2>
                    Cover Letter
                </h2>


                <?php if (
                    !empty(
                        $application["cover_letter"]
                    )
                ): ?>

                    <p>

                        <?= htmlspecialchars(
                            $application["cover_letter"]
                        ) ?>

                    </p>

                <?php else: ?>

                    <p>
                        No cover letter provided.
                    </p>

                <?php endif; ?>

            </div>


            <!-- Resume -->

            <div class="card">

                <h2>
                    Resume
                </h2>


                <?php if (
                    !empty(
                        $application["file_path"]
                    )
                ): ?>

                    <div class="resume-box">

                        <div>

                            <div class="resume-name">

                                <?= htmlspecialchars(
                                    $application["file_name"]
                                ) ?>

                            </div>


                            <?php if (
                                !empty(
                                    $application["file_type"]
                                )
                            ): ?>

                                <div class="resume-type">

                                    <?= htmlspecialchars(
                                        $application["file_type"]
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <a
                            href="../<?= htmlspecialchars(
                                $application["file_path"]
                            ) ?>"
                            target="_blank"
                            class="resume-btn"
                        >

                            View Resume

                        </a>

                    </div>

                <?php else: ?>

                    <p>
                        No resume attached to this application.
                    </p>

                <?php endif; ?>

            </div>


            <!-- Job Details -->

            <div class="card">

                <h2>
                    Job Details
                </h2>


                <div class="job-info">


                    <div class="job-info-item">

                        <span>
                            Job Title
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $application["job_title"]
                            ) ?>

                        </strong>

                    </div>


                    <div class="job-info-item">

                        <span>
                            Location
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $application["location"]
                            ) ?>

                        </strong>

                    </div>


                    <div class="job-info-item">

                        <span>
                            Employment Type
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $application["employment_type"]
                            ) ?>

                        </strong>

                    </div>


                    <div class="job-info-item">

                        <span>
                            Salary
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $salaryText
                            ) ?>

                        </strong>

                    </div>


                    <div class="job-info-item">

                        <span>
                            Remote
                        </span>

                        <strong>

                            <?= $application["is_remote"]
                                ? "Yes"
                                : "No"
                            ?>

                        </strong>

                    </div>


                    <div class="job-info-item">

                        <span>
                            Applied Date
                        </span>

                        <strong>

                            <?= date(
                                "M d, Y h:i A",
                                strtotime(
                                    $application[
                                        "applied_at"
                                    ]
                                )
                            ) ?>

                        </strong>

                    </div>

                </div>


                <div style="margin-top:20px;">

                    <h3>
                        Job Description
                    </h3>

                    <p>

                        <?= htmlspecialchars(
                            $application[
                                "job_description"
                            ]
                        ) ?>

                    </p>

                </div>


                <div style="margin-top:20px;">

                    <h3>
                        Requirements
                    </h3>

                    <p>

                        <?= htmlspecialchars(
                            $application[
                                "requirements"
                            ]
                        ) ?>

                    </p>

                </div>

            </div>


        </div>


        <!-- RIGHT COLUMN -->

        <div>


            <!-- Update Status -->

            <div class="card">

                <h2>
                    Application Status
                </h2>


                <form
                    method="POST"
                    class="status-form"
                >

                    <select name="status">

                        <?php

                        $statuses = [
                            "pending",
                            "reviewed",
                            "shortlisted",
                            "rejected",
                            "hired"
                        ];

                        foreach (
                            $statuses
                            as $status
                        ):

                        ?>

                            <option
                                value="<?= $status ?>"
                                <?= $application[
                                    "status"
                                ] === $status
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= ucfirst(
                                    $status
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>


                    <button
                        type="submit"
                        class="update-btn"
                    >

                        Update Status

                    </button>

                </form>

            </div>


            <!-- Company -->

            <div class="card">

                <h2>
                    Company Information
                </h2>


                <div class="company-item">

                    <strong>
                        Company:
                    </strong>

                    <?= htmlspecialchars(
                        $application[
                            "company_name"
                        ]
                    ) ?>

                </div>


                <?php if (
                    !empty(
                        $application[
                            "contact_person"
                        ]
                    )
                ): ?>

                    <div class="company-item">

                        <strong>
                            Contact Person:
                        </strong>

                        <?= htmlspecialchars(
                            $application[
                                "contact_person"
                            ]
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $application[
                            "contact_number"
                        ]
                    )
                ): ?>

                    <div class="company-item">

                        <strong>
                            Contact:
                        </strong>

                        <?= htmlspecialchars(
                            $application[
                                "contact_number"
                            ]
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $application[
                            "company_address"
                        ]
                    )
                ): ?>

                    <div class="company-item">

                        <strong>
                            Address:
                        </strong>

                        <?= htmlspecialchars(
                            $application[
                                "company_address"
                            ]
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $application[
                            "company_description"
                        ]
                    )
                ): ?>

                    <div style="margin-top:15px;">

                        <h3>
                            About Company
                        </h3>

                        <p>

                            <?= htmlspecialchars(
                                $application[
                                    "company_description"
                                ]
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>

            </div>


        </div>


    </div>


</div>


</body>

</html>