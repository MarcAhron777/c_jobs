<?php

session_start();

require_once "../config/database.php";
require_once "../services/ResumeMatcher.php";
require_once "../services/LocationMatcher.php";
require_once "../services/NotificationService.php";
require_once "../services/QualificationMatcher.php";
require_once "../services/SkillGapService.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "applicant"
) {
    header("Location: ../login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$jobId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$error = "";

if ($jobId <= 0) {
    die("Invalid job.");
}


/*
|--------------------------------------------------------------------------
| GET APPLICANT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        user_id,
        first_name,
        last_name
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
$applicantName = trim(
    ($applicant["first_name"] ?? "") . " " . ($applicant["last_name"] ?? "")
);


/*
|--------------------------------------------------------------------------
| GET JOB
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.employer_id,
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

        j.location,
        j.employment_type,
        j.application_deadline,
        j.job_source,
        j.source_name,

        e.company_name,
        e.user_id AS employer_user_id

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
    die("Job not found or this job is not currently available.");
}


/*
|--------------------------------------------------------------------------
| APPLICATION DEADLINE
|--------------------------------------------------------------------------
*/

if (!empty($job["application_deadline"])) {
    $deadline = strtotime($job["application_deadline"]);
    $today = strtotime(date("Y-m-d"));

    if ($deadline !== false && $deadline < $today) {
        die("The application deadline for this job has passed.");
    }
}


/*
|--------------------------------------------------------------------------
| EXISTING APPLICATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM applications
    WHERE job_id = ?
    AND applicant_id = ?
    LIMIT 1
");

$stmt->execute([
    $jobId,
    $applicantId
]);

$existingApplication = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existingApplication) {
    header(
        "Location: ../applicant/application.php?id=" .
        (int) $existingApplication["id"]
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| GET EDUCATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        educational_attainment,
        degree_program,
        school_name,
        graduation_year,
        current_year_level
    FROM applicant_education
    WHERE applicant_id = ?
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$applicantId]);

$education = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$education) {
    $education = null;
}


/*
|--------------------------------------------------------------------------
| GET TESDA QUALIFICATIONS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        qualification_name,
        nc_level,
        skills_acquired,
        training_center,
        year_completed
    FROM applicant_tesda_qualifications
    WHERE applicant_id = ?
    ORDER BY id DESC
");

$stmt->execute([$applicantId]);

$tesdaQualifications = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| GET RESUMES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        file_name,
        file_path,
        file_type,
        extracted_text,
        uploaded_at
    FROM resumes
    WHERE applicant_id = ?
    ORDER BY uploaded_at DESC
");

$stmt->execute([$applicantId]);

$resumes = $stmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| APPLY
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $resumeId = isset($_POST["resume_id"])
        ? (int) $_POST["resume_id"]
        : 0;

    $coverLetter = trim($_POST["cover_letter"] ?? "");
    $resume = null;
    $resumeText = "";

    if ($resumeId <= 0) {
        $error = "Please select a resume.";
    }


    /*
    |--------------------------------------------------------------------------
    | GET SELECTED RESUME
    |--------------------------------------------------------------------------
    */

    if ($error === "") {
        $stmt = $pdo->prepare("
            SELECT
                id,
                applicant_id,
                file_name,
                file_path,
                file_type,
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

        $resume = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$resume) {
            $error = "Invalid resume selected.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK RESUME TEXT
    |--------------------------------------------------------------------------
    */

    if ($error === "") {
        $resumeText = trim($resume["extracted_text"] ?? "");

        if ($resumeText === "") {
            $error =
                "The selected resume does not contain readable text. " .
                "Please upload a readable PDF resume.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | CALOOCAN CHECK
    |--------------------------------------------------------------------------
    */

    if ($error === "") {
        if (!LocationMatcher::isCaloocan($resumeText)) {
            $error =
                "You cannot apply for this job because your resume " .
                "does not indicate a Caloocan City address.";
        }
    }


    /*
    |--------------------------------------------------------------------------
    | MATCHING
    |--------------------------------------------------------------------------
    */

    if ($error === "") {
        try {
            /*
            |--------------------------------------------------------------------------
            | SKILLS
            |--------------------------------------------------------------------------
            */

            $skillRequirements = trim($job["required_skills"] ?? "");

            /*
             * Compatibility for older jobs.
             * If required_skills is empty, use the old requirements field.
             */
            if ($skillRequirements === "") {
                $skillRequirements = trim($job["requirements"] ?? "");
            }

            if ($skillRequirements !== "") {
                $skillsResult = ResumeMatcher::calculateSkillsMatch(
                    $skillRequirements,
                    $resumeText
                );

                $resumeSkills = $skillsResult["resume_skills"] ?? [];

                if (count($resumeSkills) === 0) {
                    throw new Exception(
                        'Your resume does not contain a readable "SKILLS AND COMPETENCIES" section. ' .
                        "Please update your resume before applying."
                    );
                }
            } else {
                $skillsResult = [
                    "score" => 100,
                    "matched_skills" => [],
                    "missing_skills" => [],
                    "resume_skills" => []
                ];
            }

            $skillsScore = max(
                0,
                min(
                    100,
                    (float) ($skillsResult["score"] ?? 0)
                )
            );

            $matchedSkills = $skillsResult["matched_skills"] ?? [];
            $missingSkills = $skillsResult["missing_skills"] ?? [];


            /*
            |--------------------------------------------------------------------------
            | EDUCATION + TESDA / NC
            |--------------------------------------------------------------------------
            */

            $qualificationResult = QualificationMatcher::calculate(
                $job,
                $education,
                $tesdaQualifications
            );

            $educationScore = max(
                0,
                min(
                    100,
                    (float) ($qualificationResult["education_score"] ?? 0)
                )
            );

            $tesdaScore = max(
                0,
                min(
                    100,
                    (float) ($qualificationResult["tesda_score"] ?? 0)
                )
            );

            $matchedQualifications =
                $qualificationResult["matched_qualifications"] ?? [];

            $missingQualifications =
                $qualificationResult["missing_qualifications"] ?? [];

            $qualificationExplanation = trim(
                $qualificationResult["explanation"] ?? ""
            );


            /*
            |--------------------------------------------------------------------------
            | DYNAMIC WEIGHTING
            |--------------------------------------------------------------------------
            |
            | Skills    = 50
            | Education = 25
            | TESDA     = 25
            |
            | A category is only included if the employer actually specified it.
            |
            */

            $hasSkills = $skillRequirements !== "";

            $hasEducation =
                trim($job["educational_requirement"] ?? "") !== "" ||
                trim($job["required_degree"] ?? "") !== "" ||
                trim($job["degree_classification"] ?? "") !== "";

            $requiredNcLevel = trim($job["required_nc_level"] ?? "");

            $hasTesda =
                trim($job["tesda_qualification"] ?? "") !== "" ||
                (
                    $requiredNcLevel !== "" &&
                    $requiredNcLevel !== "No NC Required"
                );

            $weightedTotal = 0;
            $weightTotal = 0;

            if ($hasSkills) {
                $weightedTotal += $skillsScore * 50;
                $weightTotal += 50;
            }

            if ($hasEducation) {
                $weightedTotal += $educationScore * 25;
                $weightTotal += 25;
            }

            if ($hasTesda) {
                $weightedTotal += $tesdaScore * 25;
                $weightTotal += 25;
            }

            if ($weightTotal > 0) {
                $overallScore = round(
                    $weightedTotal / $weightTotal,
                    2
                );
            } else {
                $overallScore = 0;
            }


            /*
            |--------------------------------------------------------------------------
            | SKILL GAP FEEDBACK
            |--------------------------------------------------------------------------
            */

            $skillGapFeedback = SkillGapService::build(
                $missingSkills,
                $missingQualifications
            );


            /*
            |--------------------------------------------------------------------------
            | CONVERT ARRAYS TO TEXT
            |--------------------------------------------------------------------------
            */

            $matchedSkillsText = !empty($matchedSkills)
                ? implode(", ", $matchedSkills)
                : null;

            $missingSkillsText = !empty($missingSkills)
                ? implode(", ", $missingSkills)
                : null;

            $matchedQualificationsText = !empty($matchedQualifications)
                ? implode("\n", $matchedQualifications)
                : null;

            $missingQualificationsText = !empty($missingQualifications)
                ? implode("\n", $missingQualifications)
                : null;


            /*
            |--------------------------------------------------------------------------
            | SAVE APPLICATION
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO applications (
                    job_id,
                    applicant_id,
                    resume_id,
                    cover_letter,
                    status
                )
                VALUES (
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

            $applicationId = (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | SAVE MATCH RESULT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO application_matches (
                    application_id,
                    match_score,
                    skills_score,
                    experience_score,
                    education_score,
                    tesda_score,
                    matched_skills,
                    missing_skills,
                    qualification_explanation,
                    matched_qualifications,
                    missing_qualifications,
                    skill_gap_feedback
                )
                VALUES (
                    ?,
                    ?,
                    ?,
                    0,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ");

            $stmt->execute([
                $applicationId,
                $overallScore,
                $skillsScore,
                $educationScore,
                $tesdaScore,
                $matchedSkillsText,
                $missingSkillsText,
                $qualificationExplanation !== ""
                    ? $qualificationExplanation
                    : null,
                $matchedQualificationsText,
                $missingQualificationsText,
                $skillGapFeedback !== ""
                    ? $skillGapFeedback
                    : null
            ]);

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | EMPLOYER NOTIFICATION
            |--------------------------------------------------------------------------
            */

            $employerUserId = (int) ($job["employer_user_id"] ?? 0);

            if ($employerUserId > 0) {
                try {
                    NotificationService::create(
                        $pdo,
                        $employerUserId,
                        "New Job Application",
                        $applicantName .
                        " applied for " .
                        $job["job_title"] .
                        ".",
                        "application",
                        $applicationId
                    );
                } catch (Throwable $notificationError) {
                    error_log(
                        "Application notification error: " .
                        $notificationError->getMessage()
                    );
                }
            }

            header(
                "Location: ../applicant/application.php?id=" .
                $applicationId
            );
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ((string) $e->getCode() === "23000") {
                $error = "You have already applied for this job.";
            } else {
                $error = $e->getMessage();
            }
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
        Apply - <?= htmlspecialchars($job["job_title"]) ?>
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
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px 50px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2563eb;
            text-decoration: none;
        }

        .card {
            background: #fff;
            padding: 28px;
            margin-bottom: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .card h1,
        .card h2 {
            margin-bottom: 12px;
        }

        .company {
            color: #666;
            margin-bottom: 15px;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }

        .meta span {
            padding: 7px 10px;
            background: #f1f5f9;
            border-radius: 6px;
            font-size: 13px;
        }

        .qualification-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 20px;
        }

        .qualification-item {
            padding: 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .qualification-label {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 12px;
            font-weight: bold;
        }

        .alert {
            padding: 14px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        select,
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-family: inherit;
            font-size: 14px;
        }

        textarea {
            min-height: 170px;
            resize: vertical;
        }

        select:focus,
        textarea:focus {
            outline: none;
            border-color: #2563eb;
        }

        .help-text {
            display: block;
            margin-top: 7px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .apply-btn {
            width: 100%;
            padding: 13px 18px;
            border: 0;
            border-radius: 7px;
            background: #2563eb;
            color: #fff;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .apply-btn:hover {
            background: #1d4ed8;
        }

        .empty-message {
            color: #64748b;
            line-height: 1.6;
        }

        .upload-link {
            display: inline-block;
            margin-top: 15px;
            color: #2563eb;
            font-weight: bold;
            text-decoration: none;
        }

        @media (max-width: 700px) {
            .navbar {
                padding: 15px 20px;
                flex-direction: column;
                gap: 12px;
            }

            .nav-links {
                justify-content: center;
                flex-wrap: wrap;
            }

            .qualification-grid {
                grid-template-columns: 1fr;
            }

            .card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">
    <a href="../index.php" class="logo">
        JobPortal
    </a>

    <div class="nav-links">
        <a href="../index.php">Home</a>
        <a href="index.php">Jobs</a>
        <a href="../applicant/dashboard.php">Dashboard</a>
        <a href="../applicant/applications.php">Applications</a>
        <a href="../logout.php">Logout</a>
    </div>
</nav>

<div class="container">

    <a
        href="view.php?id=<?= (int) $job["id"] ?>"
        class="back-link"
    >
        &larr; Back to Job
    </a>

    <?php if ($error !== ""): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>


    <div class="card">
        <h1>
            <?= htmlspecialchars($job["job_title"]) ?>
        </h1>

        <div class="company">
            <?= htmlspecialchars($job["company_name"]) ?>
        </div>

        <div class="meta">
            <span>
                <?= htmlspecialchars($job["employment_type"]) ?>
            </span>

            <span>
                <?= htmlspecialchars(
                    $job["location"] ?? "Caloocan City"
                ) ?>
            </span>

            <span>
                Source:
                <?= htmlspecialchars(
                    ucwords(
                        str_replace(
                            "_",
                            " ",
                            $job["job_source"] ?? "private_employer"
                        )
                    )
                ) ?>
            </span>
        </div>


        <div class="qualification-grid">

            <div class="qualification-item">
                <span class="qualification-label">
                    Required Skills
                </span>

                <?= nl2br(
                    htmlspecialchars(
                        $job["required_skills"] ?: "Not specified"
                    )
                ) ?>
            </div>


            <div class="qualification-item">
                <span class="qualification-label">
                    Educational Requirement
                </span>

                <?= htmlspecialchars(
                    $job["educational_requirement"]
                        ?: "No specific requirement"
                ) ?>
            </div>


            <div class="qualification-item">
                <span class="qualification-label">
                    Degree / Program
                </span>

                <?= htmlspecialchars(
                    $job["required_degree"]
                        ?: "No specific degree"
                ) ?>
            </div>


            <div class="qualification-item">
                <span class="qualification-label">
                    Degree Classification
                </span>

                <?= htmlspecialchars(
                    $job["degree_classification"]
                        ?: "Not specified"
                ) ?>
            </div>


            <div class="qualification-item">
                <span class="qualification-label">
                    TESDA Qualification
                </span>

                <?= htmlspecialchars(
                    $job["tesda_qualification"]
                        ?: "Not required"
                ) ?>
            </div>


            <div class="qualification-item">
                <span class="qualification-label">
                    Required NC Level
                </span>

                <?= htmlspecialchars(
                    $job["required_nc_level"]
                        ?: "No NC Required"
                ) ?>
            </div>

        </div>
    </div>


    <div class="card">
        <h2>Submit Application</h2>

        <?php if (empty($resumes)): ?>

            <p class="empty-message">
                You need to upload a readable PDF resume
                before applying for this job.
            </p>

            <a
                href="../applicant/resume.php"
                class="upload-link"
            >
                Upload Resume
            </a>

        <?php else: ?>

            <form method="POST">

                <div class="form-group">
                    <label for="resume_id">
                        Select Resume *
                    </label>

                    <select
                        name="resume_id"
                        id="resume_id"
                        required
                    >
                        <option value="">
                            Select a resume
                        </option>

                        <?php foreach ($resumes as $resumeOption): ?>
                            <option
                                value="<?= (int) $resumeOption["id"] ?>"
                                <?= (
                                    isset($_POST["resume_id"]) &&
                                    (int) $_POST["resume_id"] ===
                                    (int) $resumeOption["id"]
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                <?= htmlspecialchars(
                                    $resumeOption["file_name"]
                                ) ?>
                                -
                                <?= date(
                                    "M d, Y",
                                    strtotime(
                                        $resumeOption["uploaded_at"]
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <small class="help-text">
                        Skills are compared using the
                        SKILLS AND COMPETENCIES section of your resume.
                        Education and TESDA matching use the qualifications
                        saved in your applicant account.
                    </small>
                </div>


                <div class="form-group">
                    <label for="cover_letter">
                        Cover Letter
                    </label>

                    <textarea
                        name="cover_letter"
                        id="cover_letter"
                        placeholder="Optional message to the employer..."
                    ><?= htmlspecialchars(
                        $_POST["cover_letter"] ?? ""
                    ) ?></textarea>
                </div>


                <button
                    type="submit"
                    class="apply-btn"
                >
                    Submit Application
                </button>

            </form>

        <?php endif; ?>
    </div>

</div>

</body>
</html>