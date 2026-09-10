
<?php

session_start();

require_once "../config/database.php";
require_once "../services/ResumeMatcher.php";
require_once "../services/LocationMatcher.php";


/*
|--------------------------------------------------------------------------
| Applicant Only
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"])
    || $_SESSION["role"] !== "applicant"
) {
    header("Location: ../login.php");
    exit;
}


$userId = $_SESSION["user_id"];

$jobId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($jobId <= 0) {
    die("Invalid job.");
}


$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| Get Applicant
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        first_name,
        last_name
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
| Get Job
|--------------------------------------------------------------------------
| IMPORTANT:
| We use jobs.requirements for resume matching.
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.job_title,
        j.description,
        j.location,
        j.employment_type,
        j.requirements,
        j.application_deadline,

        e.company_name

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
| Get Requirements
|--------------------------------------------------------------------------
*/

$requirements = trim(
    $job["requirements"] ?? ""
);


/*
|--------------------------------------------------------------------------
| Check Application Deadline
|--------------------------------------------------------------------------
*/

if (
    !empty($job["application_deadline"])
    && strtotime($job["application_deadline"])
        < strtotime(date("Y-m-d"))
) {

    die(
        "The application deadline for this job has passed."
    );

}


/*
|--------------------------------------------------------------------------
| Check Existing Application
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id
    FROM applications
    WHERE job_id = ?
    AND applicant_id = ?
    LIMIT 1
");

$stmt->execute([
    $jobId,
    $applicantId
]);

$existingApplication = $stmt->fetch();


if ($existingApplication) {

    header(
        "Location: ../applicant/application.php?id="
        . $existingApplication["id"]
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Get Applicant Resumes
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        file_name,
        uploaded_at,
        extracted_text
    FROM resumes
    WHERE applicant_id = ?
    ORDER BY uploaded_at DESC
");

$stmt->execute([$applicantId]);

$resumes = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Submit Application
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $resumeId = isset($_POST["resume_id"])
        ? (int) $_POST["resume_id"]
        : 0;

    $coverLetter = trim(
        $_POST["cover_letter"] ?? ""
    );


    /*
    |--------------------------------------------------------------------------
    | Validate Resume
    |--------------------------------------------------------------------------
    */

    if ($resumeId <= 0) {

        $error = "Please select a resume.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Make sure resume belongs to applicant
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                file_name,
                extracted_text
            FROM resumes
            WHERE id = ?
            AND applicant_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $resumeId,
            $applicantId
        ]);

        $resume = $stmt->fetch();


        if (!$resume) {

            $error =
                "Invalid resume selected.";

        } elseif (
            empty(
                trim(
                    $resume["extracted_text"] ?? ""
                )
            )
        ) {

            $error =
                "The selected resume could not be processed. Please upload a readable PDF resume.";

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Check Resume Location
    |--------------------------------------------------------------------------
    */

    if ($error === "") {
        $resumeText = trim($resume['extracted_text'] ?? "");

        if (!LocationMatcher::isCaloocan($resumeText)) {
            $error = "You cannot apply for this Job because"
            . "your resume location does not indicate "
            . "Caloocan City.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Calculate Resume Match
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        try {

            /*
            |--------------------------------------------------------------------------
            | Get Extracted Resume Text
            |--------------------------------------------------------------------------
            */

            $resumeText = trim(
                $resume["extracted_text"] ?? ""
            );


            /*
            |--------------------------------------------------------------------------
            | Calculate Match
            |--------------------------------------------------------------------------
            */

            if ($requirements !== "") {

                $matchResult =
                    ResumeMatcher::calculateSkillsMatch(
                        $requirements,
                        $resumeText
                    );

            } else {

                /*
                |--------------------------------------------------------------------------
                | No Requirements
                |--------------------------------------------------------------------------
                */

                $matchResult = [

                    "score" => 0,

                    "matched_skills" => [],

                    "missing_skills" => []

                ];

            }


            /*
            |--------------------------------------------------------------------------
            | Start Database Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Insert Application
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO applications
                (
                    job_id,
                    applicant_id,
                    resume_id,
                    cover_letter,
                    status
                )
                VALUES (?, ?, ?, ?, 'pending')
            ");

            $stmt->execute([
                $jobId,
                $applicantId,
                $resumeId,
                $coverLetter ?: null
            ]);


            /*
            |--------------------------------------------------------------------------
            | Get Application ID
            |--------------------------------------------------------------------------
            */

            $applicationId =
                $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Prepare Match Data
            |--------------------------------------------------------------------------
            */

            $matchScore =
                $matchResult["score"] ?? 0;

            $matchedSkills =
                $matchResult["matched_skills"] ?? [];

            $missingSkills =
                $matchResult["missing_skills"] ?? [];


            $matchedSkillsText =
                implode(
                    ", ",
                    $matchedSkills
                );

            $missingSkillsText =
                implode(
                    ", ",
                    $missingSkills
                );


            /*
            |--------------------------------------------------------------------------
            | Insert Application Match
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO application_matches
                (
                    application_id,
                    match_score,
                    skills_score,
                    experience_score,
                    education_score,
                    matched_skills,
                    missing_skills
                )
                VALUES (?, ?, ?, 0, 0, ?, ?)
            ");

            $stmt->execute([

                $applicationId,

                $matchScore,

                $matchScore,

                $matchedSkillsText ?: null,

                $missingSkillsText ?: null

            ]);


            /*
            |--------------------------------------------------------------------------
            | Commit Transaction
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header(
                "Location: ../applicant/application.php?id="
                . $applicationId
            );

            exit;


        } catch (PDOException $e) {

            /*
            |--------------------------------------------------------------------------
            | Rollback
            |--------------------------------------------------------------------------
            */

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }


            /*
            |--------------------------------------------------------------------------
            | Duplicate Application
            |--------------------------------------------------------------------------
            */

            if ($e->getCode() === "23000") {

                $error =
                    "You have already applied for this job.";

            } else {

                $error =
                    "Database Error: "
                    . $e->getMessage();

            }

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();

            }

            $error =
                "Failed to process your resume. "
                . $e->getMessage();

        }

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
        Apply -
        <?= htmlspecialchars(
            $job["job_title"]
        ) ?>
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
        }


        .nav-links a {
            text-decoration: none;
            color: #333;
        }


        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }


        .back {
            display: inline-block;
            margin-bottom: 20px;

            color: #2563eb;
            text-decoration: none;
        }


        .card {
            background: white;
            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 2px 10px rgba(0,0,0,0.05);

            margin-bottom: 20px;
        }


        .job-summary {
            background: #f8fafc;
            padding: 20px;

            border-radius: 8px;

            margin-bottom: 25px;
        }


        .job-summary h2 {
            margin-bottom: 8px;
        }


        .company {
            color: #555;
            margin-bottom: 8px;
        }


        .details {
            color: #777;
            font-size: 14px;
            line-height: 1.7;
        }


        .skills {
            margin-top: 12px;
            color: #555;
        }


        h1 {
            margin-bottom: 10px;
        }


        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }


        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }


        .error {
            background: #fee2e2;
            color: #991b1b;
        }


        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
        }


        .form-group {
            margin-bottom: 22px;
        }


        select,
        textarea {
            width: 100%;

            padding: 12px;

            border: 1px solid #ccc;

            border-radius: 6px;

            font-size: 15px;
        }


        textarea {
            min-height: 180px;
            resize: vertical;
        }


        .resume-option {
            border: 1px solid #ddd;

            padding: 15px;

            border-radius: 7px;

            margin-bottom: 10px;
        }


        .resume-option label {
            cursor: pointer;
            margin: 0;
        }


        .resume-date {
            color: #777;
            font-size: 13px;
            margin-top: 5px;
        }


        button {
            width: 100%;

            padding: 13px;

            background: #2563eb;

            color: white;

            border: none;

            border-radius: 6px;

            font-size: 16px;

            cursor: pointer;
        }


        button:hover {
            background: #1d4ed8;
        }


        .no-resume {
            background: #fef3c7;

            color: #92400e;

            padding: 15px;

            border-radius: 7px;

            line-height: 1.5;
        }


        .no-resume a {
            color: #92400e;
            font-weight: bold;
        }


        @media (max-width: 700px) {

            .navbar {
                padding: 15px 20px;
            }


            .nav-links {
                gap: 10px;
                font-size: 14px;
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

        <a href="../applicant/dashboard.php">
            Dashboard
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</nav>


<div class="container">


    <a
        href="view.php?id=<?= $job["id"] ?>"
        class="back"
    >
        ← Back to Job
    </a>


    <div class="card">


        <h1>
            Apply for this Job
        </h1>


        <p class="subtitle">

            Submit your application and your resume
            will be automatically matched against
            the requirements for this position.

        </p>


        <!-- Job Summary -->

        <div class="job-summary">


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


            <div class="details">

                <?= htmlspecialchars(
                    $job["location"]
                    ?? "Not specified"
                ) ?>

                &nbsp; • &nbsp;

                <?= htmlspecialchars(
                    $job["employment_type"]
                ) ?>


                <?php if (
                    !empty(
                        $job["application_deadline"]
                    )
                ): ?>

                    <br>

                    Application Deadline:

                    <?= date(
                        "M d, Y",
                        strtotime(
                            $job["application_deadline"]
                        )
                    ) ?>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $job["requirements"]
                    )
                ): ?>

                    <div class="skills">

                        <strong>
                            Requirements:
                        </strong>

                        <?= nl2br(
                            htmlspecialchars(
                                $job["requirements"]
                            )
                        ) ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- Error -->

        <?php if ($error): ?>

            <div class="message error">

                <?= htmlspecialchars(
                    $error
                ) ?>

            </div>

        <?php endif; ?>


        <?php if (
            count($resumes) === 0
        ): ?>


            <div class="no-resume">

                You don't have a resume uploaded yet.

                <br><br>

                Please

                <a
                    href="../applicant/resume.php"
                >
                    upload your resume
                </a>

                before applying.

            </div>


        <?php else: ?>


            <form
                method="POST"
                action="apply.php?id=<?= $job["id"] ?>"
            >


                <!-- Resume -->

                <div class="form-group">


                    <label>
                        Select Resume
                    </label>


                    <?php foreach (
                        $resumes
                        as $index => $resume
                    ): ?>


                        <div class="resume-option">


                            <label>


                                <input
                                    type="radio"
                                    name="resume_id"
                                    value="<?= $resume["id"] ?>"
                                    <?= $index === 0
                                        ? "checked"
                                        : "" ?>
                                    required
                                >


                                <?= htmlspecialchars(
                                    $resume["file_name"]
                                ) ?>


                            </label>


                            <div class="resume-date">

                                Uploaded:

                                <?= date(
                                    "M d, Y h:i A",
                                    strtotime(
                                        $resume["uploaded_at"]
                                    )
                                ) ?>


                                <?php if (
                                    !empty(
                                        $resume["extracted_text"]
                                    )
                                ): ?>

                                    <br>

                                    ✓ Resume text
                                    ready for matching

                                <?php endif; ?>

                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


                <!-- Cover Letter -->

                <div class="form-group">


                    <label>
                        Cover Letter
                    </label>


                    <textarea
                        name="cover_letter"
                        placeholder="Tell the employer why you are a good fit for this position..."
                    ></textarea>


                </div>


                <!-- Submit -->

                <button
                    type="submit"
                >

                    Submit Application

                </button>


            </form>


        <?php endif; ?>


    </div>


</div>


</body>

</html>