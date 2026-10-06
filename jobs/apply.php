<?php

session_start();

require_once "../config/database.php";
require_once "../services/ResumeMatcher.php";
require_once "../services/LocationMatcher.php";


/*
|--------------------------------------------------------------------------
| APPLICANT ONLY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "applicant"
) {
    header("Location: ../login.php");
    exit;
}


$userId =
    (int) $_SESSION["user_id"];


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
| GET APPLICANT
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

$stmt->execute([
    $userId
]);

$applicant =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$applicant) {
    die("Applicant profile not found.");
}


$applicantId =
    (int) $applicant["id"];


/*
|--------------------------------------------------------------------------
| GET JOB
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

    WHERE
        j.id = ?
        AND j.status = 'approved'
        AND LOWER(j.location) LIKE '%caloocan%'

    LIMIT 1
");

$stmt->execute([
    $jobId
]);

$job =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$job) {
    die("Job not found.");
}


/*
|--------------------------------------------------------------------------
| GET REQUIREMENTS
|--------------------------------------------------------------------------
*/

$requirements =
    trim(
        $job["requirements"]
        ?? ""
    );


/*
|--------------------------------------------------------------------------
| CHECK APPLICATION DEADLINE
|--------------------------------------------------------------------------
*/

if (
    !empty(
        $job["application_deadline"]
    )
    &&
    strtotime(
        $job["application_deadline"]
    )
    <
    strtotime(
        date("Y-m-d")
    )
) {

    die(
        "The application deadline for this job has passed."
    );
}


/*
|--------------------------------------------------------------------------
| CHECK EXISTING APPLICATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id

    FROM applications

    WHERE
        job_id = ?
        AND applicant_id = ?

    LIMIT 1
");

$stmt->execute([
    $jobId,
    $applicantId
]);

$existingApplication =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if ($existingApplication) {

    header(
        "Location: ../applicant/application.php?id="
        . $existingApplication["id"]
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| GET APPLICANT RESUMES
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

$stmt->execute([
    $applicantId
]);

$resumes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| SUBMIT APPLICATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {

    $resumeId =
        isset($_POST["resume_id"])
        ? (int) $_POST["resume_id"]
        : 0;


    $coverLetter =
        trim(
            $_POST["cover_letter"]
            ?? ""
        );


    $resume = null;


    /*
    |--------------------------------------------------------------------------
    | VALIDATE RESUME
    |--------------------------------------------------------------------------
    */

    if ($resumeId <= 0) {

        $error =
            "Please select a resume.";

    } else {

        $stmt = $pdo->prepare("
            SELECT

                id,
                file_name,
                extracted_text

            FROM resumes

            WHERE
                id = ?
                AND applicant_id = ?

            LIMIT 1
        ");


        $stmt->execute([
            $resumeId,
            $applicantId
        ]);


        $resume =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$resume) {

            $error =
                "Invalid resume selected.";

        } elseif (
            empty(
                trim(
                    $resume[
                        "extracted_text"
                    ]
                    ?? ""
                )
            )
        ) {

            $error =
                "The selected resume could not be processed. "
                . "Please upload a readable PDF resume.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | GET RESUME TEXT
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $resumeText =
            trim(
                $resume[
                    "extracted_text"
                ]
                ?? ""
            );
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK RESUME LOCATION
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        if (
            !LocationMatcher::isCaloocan(
                $resumeText
            )
        ) {

            $error =
                "You cannot apply for this job because "
                . "your resume location does not indicate "
                . "Caloocan City.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE RESUME MATCH
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        try {


            /*
            |--------------------------------------------------------------------------
            | MATCH REQUIREMENTS VS SKILLS SECTION ONLY
            |--------------------------------------------------------------------------
            */

            if ($requirements !== "") {

                $matchResult =
                    ResumeMatcher::calculateSkillsMatch(
                        $requirements,
                        $resumeText
                    );


                /*
                |--------------------------------------------------------------------------
                | CHECK THAT SKILLS SECTION WAS FOUND
                |--------------------------------------------------------------------------
                */

                $resumeSkills =
                    $matchResult[
                        "resume_skills"
                    ]
                    ?? [];


                if (
                    count($resumeSkills) === 0
                ) {

                    throw new Exception(
                        "Your resume does not contain a readable "
                        . "\"SKILLS AND COMPETENCIES\" section. "
                        . "Please use the provided resume template "
                        . "or update your resume before applying."
                    );
                }

            } else {

                $matchResult = [

                    "score" => 0,

                    "matched_skills" => [],

                    "missing_skills" => [],

                    "resume_skills" => []

                ];
            }


            /*
            |--------------------------------------------------------------------------
            | PREPARE MATCH RESULT
            |--------------------------------------------------------------------------
            */

            $matchScore =
                (float) (
                    $matchResult[
                        "score"
                    ]
                    ?? 0
                );


            if ($matchScore < 0) {
                $matchScore = 0;
            }


            if ($matchScore > 100) {
                $matchScore = 100;
            }


            $matchedSkills =
                $matchResult[
                    "matched_skills"
                ]
                ?? [];


            $missingSkills =
                $matchResult[
                    "missing_skills"
                ]
                ?? [];


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
            | START TRANSACTION
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | INSERT APPLICATION
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

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    'pending'
                )
            ");


            $stmt->execute([

                $jobId,

                $applicantId,

                $resumeId,

                $coverLetter !== ""
                    ? $coverLetter
                    : null

            ]);


            /*
            |--------------------------------------------------------------------------
            | GET APPLICATION ID
            |--------------------------------------------------------------------------
            */

            $applicationId =
                (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | INSERT MATCH RESULT
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

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    0,
                    0,
                    ?,
                    ?
                )
            ");


            $stmt->execute([

                $applicationId,

                $matchScore,

                $matchScore,

                $matchedSkillsText !== ""
                    ? $matchedSkillsText
                    : null,

                $missingSkillsText !== ""
                    ? $missingSkillsText
                    : null

            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | REDIRECT
            |--------------------------------------------------------------------------
            */

            header(
                "Location: ../applicant/application.php?id="
                . $applicationId
            );

            exit;


        } catch (PDOException $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            if (
                $e->getCode()
                === "23000"
            ) {

                $error =
                    "You have already applied for this job.";

            } else {

                error_log(
                    $e->getMessage()
                );


                $error =
                    "A database error occurred while processing "
                    . "your application. Please try again.";
            }


        } catch (Exception $e) {

            if (
                $pdo->inTransaction()
            ) {

                $pdo->rollBack();
            }


            $error =
                $e->getMessage();
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
    box-sizing:
        border-box;

    margin:
        0;

    padding:
        0;
}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        #f5f7fb;

    color:
        #333;
}


.navbar {

    background:
        white;

    border-bottom:
        1px solid #ddd;

    padding:
        16px 40px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;
}


.logo {

    font-size:
        24px;

    font-weight:
        bold;

    color:
        #2563eb;
}


.nav-links {

    display:
        flex;

    gap:
        20px;

    align-items:
        center;
}


.nav-links a {

    text-decoration:
        none;

    color:
        #333;
}


.nav-links a:hover {

    color:
        #2563eb;
}


.container {

    max-width:
        800px;

    margin:
        40px auto;

    padding:
        0 20px 50px;
}


.back {

    display:
        inline-block;

    margin-bottom:
        20px;

    color:
        #2563eb;

    text-decoration:
        none;
}


.back:hover {

    text-decoration:
        underline;
}


.card {

    background:
        white;

    padding:
        30px;

    border-radius:
        10px;

    box-shadow:
        0 2px 10px
        rgba(
            0,
            0,
            0,
            0.05
        );

    margin-bottom:
        20px;
}


.job-summary {

    background:
        #f8fafc;

    padding:
        20px;

    border-radius:
        8px;

    margin-bottom:
        25px;
}


.job-summary h2 {

    margin-bottom:
        8px;
}


.company {

    color:
        #555;

    margin-bottom:
        8px;
}


.details {

    color:
        #777;

    font-size:
        14px;

    line-height:
        1.7;
}


.skills {

    margin-top:
        12px;

    color:
        #555;

    line-height:
        1.6;
}


h1 {

    margin-bottom:
        10px;
}


.subtitle {

    color:
        #666;

    margin-bottom:
        25px;

    line-height:
        1.5;
}


.message {

    padding:
        14px;

    border-radius:
        6px;

    margin-bottom:
        20px;

    line-height:
        1.5;
}


.error {

    background:
        #fee2e2;

    color:
        #991b1b;

    border:
        1px solid #fecaca;
}


label {

    display:
        block;

    font-weight:
        bold;

    margin-bottom:
        8px;
}


.form-group {

    margin-bottom:
        22px;
}


textarea {

    width:
        100%;

    padding:
        12px;

    border:
        1px solid #ccc;

    border-radius:
        6px;

    font-size:
        15px;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    min-height:
        180px;

    resize:
        vertical;
}


.resume-option {

    border:
        1px solid #ddd;

    padding:
        15px;

    border-radius:
        7px;

    margin-bottom:
        10px;
}


.resume-option:hover {

    border-color:
        #93c5fd;

    background:
        #f8fbff;
}


.resume-option label {

    cursor:
        pointer;

    margin:
        0;
}


.resume-date {

    color:
        #777;

    font-size:
        13px;

    margin-top:
        5px;
}


button {

    width:
        100%;

    padding:
        13px;

    background:
        #2563eb;

    color:
        white;

    border:
        none;

    border-radius:
        6px;

    font-size:
        16px;

    cursor:
        pointer;
}


button:hover {

    background:
        #1d4ed8;
}


.no-resume {

    background:
        #fef3c7;

    color:
        #92400e;

    padding:
        15px;

    border-radius:
        7px;

    line-height:
        1.5;
}


.no-resume a {

    color:
        #92400e;

    font-weight:
        bold;
}


.match-info {

    margin-bottom:
        22px;

    padding:
        15px;

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    border-radius:
        7px;

    color:
        #1e40af;

    font-size:
        13px;

    line-height:
        1.6;
}


@media (
    max-width: 700px
) {

    .navbar {

        padding:
            15px 20px;

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            15px;
    }


    .nav-links {

        gap:
            10px;

        flex-wrap:
            wrap;

        font-size:
            14px;
    }


    .container {

        margin:
            25px auto;

        padding:
            0 15px 35px;
    }


    .card {

        padding:
            20px;
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

        <a href="../applicant/resume.php">
            Resume
        </a>

        <a href="../applicant/applications.php">
            Applications
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>


</nav>


<div class="container">


    <a
        href="view.php?id=<?= (int) $job["id"] ?>"
        class="back"
    >
        ← Back to Job
    </a>


    <div class="card">


        <h1>
            Apply for this Job
        </h1>


        <p class="subtitle">

            Submit your application and your
            skills will be automatically matched
            against the employer's job requirements.

        </p>


        <div class="job-summary">


            <h2>

                <?= htmlspecialchars(
                    $job[
                        "job_title"
                    ]
                ) ?>

            </h2>


            <div class="company">

                <?= htmlspecialchars(
                    $job[
                        "company_name"
                    ]
                ) ?>

            </div>


            <div class="details">


                <?= htmlspecialchars(
                    $job[
                        "location"
                    ]
                    ?? "Not specified"
                ) ?>


                &nbsp; • &nbsp;


                <?= htmlspecialchars(
                    $job[
                        "employment_type"
                    ]
                ) ?>


                <?php if (
                    !empty(
                        $job[
                            "application_deadline"
                        ]
                    )
                ): ?>


                    <br>

                    Application Deadline:

                    <?= date(
                        "M d, Y",
                        strtotime(
                            $job[
                                "application_deadline"
                            ]
                        )
                    ) ?>


                <?php endif; ?>


                <?php if (
                    !empty(
                        $job[
                            "requirements"
                        ]
                    )
                ): ?>


                    <div class="skills">

                        <strong>
                            Requirements:
                        </strong>

                        <br>

                        <?= nl2br(
                            htmlspecialchars(
                                $job[
                                    "requirements"
                                ]
                            )
                        ) ?>

                    </div>


                <?php endif; ?>


            </div>


        </div>


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

                You don't have a resume
                uploaded yet.

                <br><br>

                Please

                <a href="../applicant/resume.php">

                    download the resume template
                    and upload your completed resume

                </a>

                before applying.

            </div>


        <?php else: ?>


            <div class="match-info">

                <strong>
                    Resume Matching:
                </strong>

                Only the
                <strong>
                    Skills and Competencies
                </strong>
                section of your resume will be
                used for skills matching.

            </div>


            <form
                method="POST"
                action="apply.php?id=<?= (int) $job["id"] ?>"
            >


                <div class="form-group">


                    <label>
                        Select Resume
                    </label>


                    <?php foreach (
                        $resumes
                        as
                        $index => $resume
                    ): ?>


                        <div class="resume-option">


                            <label>


                                <input
                                    type="radio"

                                    name="resume_id"

                                    value="<?= (int) $resume["id"] ?>"

                                    <?= $index === 0
                                        ? "checked"
                                        : ""
                                    ?>

                                    required
                                >


                                <?= htmlspecialchars(
                                    $resume[
                                        "file_name"
                                    ]
                                ) ?>


                            </label>


                            <div class="resume-date">


                                Uploaded:

                                <?= date(
                                    "M d, Y h:i A",
                                    strtotime(
                                        $resume[
                                            "uploaded_at"
                                        ]
                                    )
                                ) ?>


                                <?php if (
                                    !empty(
                                        $resume[
                                            "extracted_text"
                                        ]
                                    )
                                ): ?>

                                    <br>

                                    ✓ Resume text ready for matching

                                <?php endif; ?>


                            </div>


                        </div>


                    <?php endforeach; ?>


                </div>


                <div class="form-group">


                    <label>
                        Cover Letter
                    </label>


                    <textarea
                        name="cover_letter"
                        placeholder="Tell the employer why you are a good fit for this position..."
                    ></textarea>


                </div>


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