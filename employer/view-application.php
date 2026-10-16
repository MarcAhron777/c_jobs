<?php

session_start();

require_once "../config/database.php";
require_once "../services/NotificationService.php";

/*
|--------------------------------------------------------------------------
| EMPLOYER ONLY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "employer"
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
| GET EMPLOYER
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

$employer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employer) {
    die("Employer profile not found.");
}

$employerId = (int) $employer["id"];

/*
|--------------------------------------------------------------------------
| APPLICATION MATCH COLUMN CHECK
|--------------------------------------------------------------------------
*/

$matchColumns = [];

try {

    $columnStmt =
        $pdo->query(
            "SHOW COLUMNS FROM application_matches"
        );

    foreach (
        $columnStmt->fetchAll(
            PDO::FETCH_ASSOC
        ) as $column
    ) {
        $matchColumns[] =
            $column["Field"];
    }

} catch (Throwable $e) {

    $matchColumns = [];
}


$tesdaScoreSelect =
    in_array(
        "tesda_score",
        $matchColumns,
        true
    )
        ? "am.tesda_score"
        : "0 AS tesda_score";


$qualificationExplanationSelect =
    in_array(
        "qualification_explanation",
        $matchColumns,
        true
    )
        ? "am.qualification_explanation"
        : "NULL AS qualification_explanation";


$matchedQualificationsSelect =
    in_array(
        "matched_qualifications",
        $matchColumns,
        true
    )
        ? "am.matched_qualifications"
        : "NULL AS matched_qualifications";


$missingQualificationsSelect =
    in_array(
        "missing_qualifications",
        $matchColumns,
        true
    )
        ? "am.missing_qualifications"
        : "NULL AS missing_qualifications";


$skillGapFeedbackSelect =
    in_array(
        "skill_gap_feedback",
        $matchColumns,
        true
    )
        ? "am.skill_gap_feedback"
        : "NULL AS skill_gap_feedback";


/*
|--------------------------------------------------------------------------
| UPDATE APPLICATION STATUS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {

    $status =
        trim(
            $_POST["status"]
            ?? ""
        );


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

        $error =
            "Invalid application status.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | GET CURRENT APPLICATION OWNER
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare("

                    SELECT
                        a.status AS current_status,
                        ap.user_id AS applicant_user_id,
                        j.job_title

                    FROM applications a

                    INNER JOIN jobs j
                        ON a.job_id = j.id

                    INNER JOIN applicants ap
                        ON a.applicant_id = ap.id

                    WHERE
                        a.id = ?
                        AND j.employer_id = ?

                    LIMIT 1

                ");


            $stmt->execute([
                $applicationId,
                $employerId
            ]);


            $applicationOwner =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$applicationOwner) {

                throw new Exception(
                    "Application not found or you do not have permission to update it."
                );
            }


            $oldStatus =
                $applicationOwner[
                    "current_status"
                ];


            /*
            |--------------------------------------------------------------------------
            | UPDATE STATUS
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare("

                    UPDATE applications

                    SET status = ?

                    WHERE id = ?

                ");


            $stmt->execute([
                $status,
                $applicationId
            ]);


            $success =
                "Application status updated successfully.";


            /*
            |--------------------------------------------------------------------------
            | APPLICANT NOTIFICATION
            |--------------------------------------------------------------------------
            */

            if (
                $oldStatus !== $status
            ) {

                try {

                    NotificationService::create(
                        $pdo,
                        (int) $applicationOwner[
                            "applicant_user_id"
                        ],
                        "Application Status Updated",
                        "Your application for "
                            . $applicationOwner[
                                "job_title"
                            ]
                            . " is now "
                            . ucfirst($status)
                            . ".",
                        "application_status",
                        $applicationId
                    );

                } catch (
                    Throwable $notificationError
                ) {

                    error_log(
                        "Applicant notification error: "
                        . $notificationError->getMessage()
                    );
                }
            }

        } catch (
            Throwable $e
        ) {

            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET APPLICATION
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare("

        SELECT

            a.id AS application_id,
            a.status,
            a.cover_letter,
            a.applied_at,
            a.updated_at,

            j.id AS job_id,
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
            j.location,
            j.employment_type,
            j.salary_min,
            j.salary_max,
            j.is_remote,
            j.application_deadline,

            ap.id AS applicant_id,
            ap.user_id AS applicant_user_id,
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
            $tesdaScoreSelect,
            am.matched_skills,
            am.missing_skills,
            $qualificationExplanationSelect,
            $matchedQualificationsSelect,
            $missingQualificationsSelect,
            $skillGapFeedbackSelect,

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

        WHERE
            a.id = ?
            AND j.employer_id = ?

        LIMIT 1

    ");


$stmt->execute([
    $applicationId,
    $employerId
]);


$application =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$application) {

    die(
        "Application not found or you do not have permission to view it."
    );
}


/*
|--------------------------------------------------------------------------
| APPLICANT EDUCATION
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare("

        SELECT
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


$stmt->execute([
    (int) $application[
        "applicant_id"
    ]
]);


$education =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| APPLICANT TESDA
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare("

        SELECT
            qualification_name,
            nc_level,
            skills_acquired,
            training_center,
            year_completed

        FROM applicant_tesda_qualifications

        WHERE applicant_id = ?

        ORDER BY id DESC

    ");


$stmt->execute([
    (int) $application[
        "applicant_id"
    ]
]);


$tesdaQualifications =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function clampScore(
    $score
): float {

    return max(
        0,
        min(
            100,
            (float) (
                $score
                ?? 0
            )
        )
    );
}


function splitMatchItems(
    ?string $value
): array {

    if (
        $value === null ||
        trim($value) === ""
    ) {
        return [];
    }

    return array_values(
        array_filter(
            array_map(
                "trim",
                preg_split(
                    '/[,;\r\n]+/',
                    $value
                )
            )
        )
    );
}


$matchScore =
    clampScore(
        $application[
            "match_score"
        ]
        ?? 0
    );


$skillsScore =
    clampScore(
        $application[
            "skills_score"
        ]
        ?? 0
    );


$educationScore =
    clampScore(
        $application[
            "education_score"
        ]
        ?? 0
    );


$tesdaScore =
    clampScore(
        $application[
            "tesda_score"
        ]
        ?? 0
    );


$experienceScore =
    clampScore(
        $application[
            "experience_score"
        ]
        ?? 0
    );


$matchedSkills =
    splitMatchItems(
        $application[
            "matched_skills"
        ]
        ?? null
    );


$missingSkills =
    splitMatchItems(
        $application[
            "missing_skills"
        ]
        ?? null
    );


$matchedQualifications =
    splitMatchItems(
        $application[
            "matched_qualifications"
        ]
        ?? null
    );


$missingQualifications =
    splitMatchItems(
        $application[
            "missing_qualifications"
        ]
        ?? null
    );


$statusClass =
    "status-"
    . strtolower(
        $application["status"]
    );


$salaryText =
    "Not specified";


if (
    $application["salary_min"]
    !== null
    &&
    $application["salary_max"]
    !== null
) {

    $salaryText =
        "₱"
        . number_format(
            (float)
            $application[
                "salary_min"
            ],
            2
        )
        . " - ₱"
        . number_format(
            (float)
            $application[
                "salary_max"
            ],
            2
        );

} elseif (
    $application["salary_min"]
    !== null
) {

    $salaryText =
        "From ₱"
        . number_format(
            (float)
            $application[
                "salary_min"
            ],
            2
        );

} elseif (
    $application["salary_max"]
    !== null
) {

    $salaryText =
        "Up to ₱"
        . number_format(
            (float)
            $application[
                "salary_max"
            ],
            2
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
    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        #f5f7fb;

    color:
        #333;

    line-height:
        1.6;
}

.container {
    max-width:
        1150px;

    margin:
        40px auto;

    padding:
        0 20px 50px;
}

.back-link {
    display:
        inline-block;

    margin-bottom:
        20px;

    color:
        #2563eb;

    text-decoration:
        none;
}

.page-header {
    background:
        white;

    padding:
        25px;

    border-radius:
        12px;

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

.page-header h1 {
    font-size:
        28px;

    margin-bottom:
        5px;
}

.company {
    color:
        #666;
}

.status {
    display:
        inline-block;

    padding:
        7px 13px;

    border-radius:
        20px;

    font-size:
        12px;

    font-weight:
        bold;

    margin-top:
        15px;
}

.status-pending {
    background:
        #fef3c7;

    color:
        #92400e;
}

.status-reviewed {
    background:
        #dbeafe;

    color:
        #1e40af;
}

.status-shortlisted {
    background:
        #dcfce7;

    color:
        #166534;
}

.status-rejected {
    background:
        #fee2e2;

    color:
        #991b1b;
}

.status-hired {
    background:
        #d1fae5;

    color:
        #065f46;
}

.message {
    padding:
        12px 15px;

    border-radius:
        6px;

    margin-bottom:
        20px;
}

.success {
    background:
        #dcfce7;

    color:
        #166534;
}

.error {
    background:
        #fee2e2;

    color:
        #991b1b;
}

.content-grid {
    display:
        grid;

    grid-template-columns:
        minmax(0, 2fr)
        minmax(300px, 1fr);

    gap:
        20px;

    align-items:
        start;
}

.card {
    background:
        white;

    padding:
        25px;

    border-radius:
        12px;

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

.card h2 {
    font-size:
        19px;

    margin-bottom:
        15px;

    color:
        #111827;
}

.card h3 {
    font-size:
        15px;

    margin-bottom:
        7px;
}

.profile-name {
    font-size:
        24px;

    font-weight:
        bold;

    margin-bottom:
        8px;
}

.profile-item {
    margin-bottom:
        7px;

    color:
        #555;
}

.profile-item strong {
    color:
        #333;
}

.match-box {
    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    border-radius:
        10px;

    padding:
        20px;
}

.match-header {
    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        12px;
}

.match-title {
    font-size:
        17px;

    font-weight:
        bold;

    color:
        #1e3a8a;
}

.match-score {
    font-size:
        32px;

    font-weight:
        bold;

    color:
        #2563eb;
}

.progress {
    height:
        12px;

    background:
        #dbeafe;

    border-radius:
        20px;

    overflow:
        hidden;

    margin-bottom:
        20px;
}

.progress-bar {
    height:
        100%;

    background:
        #2563eb;
}

.score-grid {
    display:
        grid;

    grid-template-columns:
        repeat(
            4,
            1fr
        );

    gap:
        10px;
}

.score-item {
    background:
        white;

    border:
        1px solid #ddd;

    border-radius:
        7px;

    padding:
        12px;

    text-align:
        center;
}

.score-item span {
    display:
        block;

    font-size:
        12px;

    color:
        #777;

    margin-bottom:
        3px;
}

.score-item strong {
    font-size:
        18px;

    color:
        #2563eb;
}

.match-section {
    margin-top:
        20px;
}

.match-section h3 {
    margin-bottom:
        10px;
}

.skill-list {
    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        7px;
}

.skill {
    padding:
        6px 10px;

    border-radius:
        20px;

    font-size:
        12px;
}

.skill-matched {
    background:
        #dcfce7;

    color:
        #166534;
}

.skill-missing {
    background:
        #fee2e2;

    color:
        #991b1b;
}

.explanation-box,
.gap-box {
    margin-top:
        20px;

    padding:
        15px;

    border-radius:
        8px;

    line-height:
        1.7;
}

.explanation-box {
    background:
        #f8fafc;

    border:
        1px solid #e2e8f0;

    color:
        #475569;
}

.gap-box {
    background:
        #fff7ed;

    border:
        1px solid #fed7aa;

    color:
        #9a3412;
}

.job-info {
    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        15px;
}

.job-info-item {
    background:
        #f8fafc;

    border:
        1px solid #e5e7eb;

    padding:
        13px;

    border-radius:
        7px;
}

.job-info-item span {
    display:
        block;

    color:
        #777;

    font-size:
        12px;

    margin-bottom:
        3px;
}

.requirement-list {
    margin-top:
        18px;

    padding-top:
        18px;

    border-top:
        1px solid #e5e7eb;
}

.requirement-item {
    margin-bottom:
        10px;
}

.requirement-item strong {
    display:
        inline-block;

    min-width:
        170px;
}

.tesda-record {
    padding:
        12px;

    background:
        #f8fafc;

    border:
        1px solid #e5e7eb;

    border-radius:
        7px;

    margin-bottom:
        10px;
}

.resume-box {
    background:
        #f8fafc;

    border:
        1px solid #ddd;

    border-radius:
        8px;

    padding:
        15px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        15px;
}

.resume-btn {
    display:
        inline-block;

    padding:
        9px 14px;

    background:
        #64748b;

    color:
        white;

    text-decoration:
        none;

    border-radius:
        6px;

    font-size:
        13px;
}

.status-form select {
    width:
        100%;

    padding:
        11px;

    border:
        1px solid #ccc;

    border-radius:
        6px;

    margin-bottom:
        10px;

    background:
        white;
}

.update-btn {
    width:
        100%;

    padding:
        11px;

    background:
        #2563eb;

    color:
        white;

    border:
        none;

    border-radius:
        6px;

    cursor:
        pointer;

    font-weight:
        bold;
}

.cover-letter {
    white-space:
        pre-line;

    color:
        #555;
}

.muted {
    color:
        #777;
}

@media (
    max-width: 900px
) {

    .content-grid {
        grid-template-columns:
            1fr;
    }

    .score-grid {
        grid-template-columns:
            repeat(
                2,
                1fr
            );
    }
}

@media (
    max-width: 600px
) {

    .score-grid,
    .job-info {
        grid-template-columns:
            1fr;
    }

    .resume-box {
        flex-direction:
            column;

        align-items:
            flex-start;
    }
}

</style>

</head>

<body>

<?php
require __DIR__
    . "/includes/header.php";
?>

<div class="container">

    <a
        href="applicants.php"
        class="back-link"
    >
        ← Back to Applicants
    </a>


    <?php if ($success): ?>

        <div class="message success">

            <?= htmlspecialchars(
                $success
            ) ?>

        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="message error">

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endif; ?>


    <div class="page-header">

        <h1>

            <?= htmlspecialchars(
                $application[
                    "first_name"
                ]
                . " "
                . $application[
                    "last_name"
                ]
            ) ?>

        </h1>

        <div class="company">

            Applied for

            <strong>

                <?= htmlspecialchars(
                    $application[
                        "job_title"
                    ]
                ) ?>

            </strong>

            at

            <strong>

                <?= htmlspecialchars(
                    $application[
                        "company_name"
                    ]
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
                    $application[
                        "status"
                    ]
                )
            ) ?>

        </span>

    </div>


    <div class="content-grid">


        <!-- LEFT -->

        <div>


            <div class="card">

                <h2>
                    Applicant Information
                </h2>

                <div class="profile-name">

                    <?= htmlspecialchars(
                        $application[
                            "first_name"
                        ]
                        . " "
                        . $application[
                            "last_name"
                        ]
                    ) ?>

                </div>

                <div class="profile-item">

                    <strong>
                        Email:
                    </strong>

                    <?= htmlspecialchars(
                        $application[
                            "email"
                        ]
                    ) ?>

                </div>

                <?php if (!empty(
                    $application[
                        "phone"
                    ]
                )): ?>

                    <div class="profile-item">

                        <strong>
                            Phone:
                        </strong>

                        <?= htmlspecialchars(
                            $application[
                                "phone"
                            ]
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (!empty(
                    $application[
                        "address"
                    ]
                )): ?>

                    <div class="profile-item">

                        <strong>
                            Address:
                        </strong>

                        <?= htmlspecialchars(
                            $application[
                                "address"
                            ]
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (!empty(
                    $application[
                        "bio"
                    ]
                )): ?>

                    <div style="margin-top:20px;">

                        <h3>
                            Bio
                        </h3>

                        <p>

                            <?= nl2br(
                                htmlspecialchars(
                                    $application[
                                        "bio"
                                    ]
                                )
                            ) ?>

                        </p>

                    </div>

                <?php endif; ?>

            </div>


            <!-- EXPLAINABLE MATCH -->

            <div class="card">

                <h2>
                    Explainable Job Match
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
                            style="width:<?= $matchScore ?>%;"
                        ></div>

                    </div>


                    <div class="score-grid">

                        <div class="score-item">

                            <span>
                                Skills
                            </span>

                            <strong>

                                <?= number_format(
                                    $skillsScore,
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
                                    $educationScore,
                                    0
                                ) ?>%

                            </strong>

                        </div>


                        <div class="score-item">

                            <span>
                                TESDA / NC
                            </span>

                            <strong>

                                <?= number_format(
                                    $tesdaScore,
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
                                    $experienceScore,
                                    0
                                ) ?>%

                            </strong>

                        </div>

                    </div>


                    <div class="match-section">

                        <h3>
                            Matched Skills
                        </h3>

                        <?php if (
                            count(
                                $matchedSkills
                            ) > 0
                        ): ?>

                            <div class="skill-list">

                                <?php foreach (
                                    $matchedSkills
                                    as
                                    $skill
                                ): ?>

                                    <span class="
                                        skill
                                        skill-matched
                                    ">

                                        <?= htmlspecialchars(
                                            $skill
                                        ) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <p class="muted">
                                No matched skills recorded.
                            </p>

                        <?php endif; ?>

                    </div>


                    <div class="match-section">

                        <h3>
                            Missing Skills
                        </h3>

                        <?php if (
                            count(
                                $missingSkills
                            ) > 0
                        ): ?>

                            <div class="skill-list">

                                <?php foreach (
                                    $missingSkills
                                    as
                                    $skill
                                ): ?>

                                    <span class="
                                        skill
                                        skill-missing
                                    ">

                                        <?= htmlspecialchars(
                                            $skill
                                        ) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <p class="muted">
                                No missing skills recorded.
                            </p>

                        <?php endif; ?>

                    </div>


                    <div class="match-section">

                        <h3>
                            Matched Qualifications
                        </h3>

                        <?php if (
                            count(
                                $matchedQualifications
                            ) > 0
                        ): ?>

                            <div class="skill-list">

                                <?php foreach (
                                    $matchedQualifications
                                    as
                                    $qualification
                                ): ?>

                                    <span class="
                                        skill
                                        skill-matched
                                    ">

                                        <?= htmlspecialchars(
                                            $qualification
                                        ) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <p class="muted">
                                No matched qualification
                                details recorded yet.
                            </p>

                        <?php endif; ?>

                    </div>


                    <div class="match-section">

                        <h3>
                            Missing Qualifications
                        </h3>

                        <?php if (
                            count(
                                $missingQualifications
                            ) > 0
                        ): ?>

                            <div class="skill-list">

                                <?php foreach (
                                    $missingQualifications
                                    as
                                    $qualification
                                ): ?>

                                    <span class="
                                        skill
                                        skill-missing
                                    ">

                                        <?= htmlspecialchars(
                                            $qualification
                                        ) ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <p class="muted">
                                No missing qualification
                                details recorded.
                            </p>

                        <?php endif; ?>

                    </div>


                    <?php if (!empty(
                        $application[
                            "qualification_explanation"
                        ]
                    )): ?>

                        <div class="explanation-box">

                            <strong>
                                Why the applicant matched
                            </strong>

                            <br>

                            <?= nl2br(
                                htmlspecialchars(
                                    $application[
                                        "qualification_explanation"
                                    ]
                                )
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty(
                        $application[
                            "skill_gap_feedback"
                        ]
                    )): ?>

                        <div class="gap-box">

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

            </div>


            <!-- EDUCATION + TESDA -->

            <div class="card">

                <h2>
                    Applicant Qualifications
                </h2>


                <?php if ($education): ?>

                    <div class="requirement-list">

                        <div class="requirement-item">

                            <strong>
                                Educational Attainment:
                            </strong>

                            <?= htmlspecialchars(
                                $education[
                                    "educational_attainment"
                                ]
                            ) ?>

                        </div>


                        <?php if (!empty(
                            $education[
                                "degree_program"
                            ]
                        )): ?>

                            <div class="requirement-item">

                                <strong>
                                    Degree / Program:
                                </strong>

                                <?= htmlspecialchars(
                                    $education[
                                        "degree_program"
                                    ]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty(
                            $education[
                                "school_name"
                            ]
                        )): ?>

                            <div class="requirement-item">

                                <strong>
                                    School:
                                </strong>

                                <?= htmlspecialchars(
                                    $education[
                                        "school_name"
                                    ]
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php else: ?>

                    <p class="muted">
                        Applicant has not added
                        educational qualifications yet.
                    </p>

                <?php endif; ?>


                <div style="margin-top:25px;">

                    <h3>
                        TESDA / Technical Qualifications
                    </h3>

                    <?php if (
                        count(
                            $tesdaQualifications
                        ) > 0
                    ): ?>

                        <?php foreach (
                            $tesdaQualifications
                            as
                            $tesda
                        ): ?>

                            <div class="tesda-record">

                                <strong>

                                    <?= htmlspecialchars(
                                        $tesda[
                                            "qualification_name"
                                        ]
                                    ) ?>

                                </strong>

                                <br>

                                NC Level:

                                <?= htmlspecialchars(
                                    $tesda[
                                        "nc_level"
                                    ]
                                ) ?>


                                <?php if (!empty(
                                    $tesda[
                                        "skills_acquired"
                                    ]
                                )): ?>

                                    <br>

                                    Skills:

                                    <?= htmlspecialchars(
                                        $tesda[
                                            "skills_acquired"
                                        ]
                                    ) ?>

                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <p class="muted">
                            No TESDA qualification
                            has been added.
                        </p>

                    <?php endif; ?>

                </div>

            </div>


            <!-- JOB REQUIREMENTS -->

            <div class="card">

                <h2>
                    Job & Qualification Requirements
                </h2>


                <div class="job-info">

                    <div class="job-info-item">

                        <span>
                            Employment Type
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $application[
                                    "employment_type"
                                ]
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
                            Location
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $application[
                                    "location"
                                ]
                                ?? "Not specified"
                            ) ?>

                        </strong>

                    </div>


                    <div class="job-info-item">

                        <span>
                            Job Source
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                ucwords(
                                    str_replace(
                                        "_",
                                        " ",
                                        $application[
                                            "job_source"
                                        ]
                                        ?? "private_employer"
                                    )
                                )
                            ) ?>

                        </strong>

                    </div>

                </div>


                <div class="requirement-list">

                    <?php if (!empty(
                        $application[
                            "required_skills"
                        ]
                    )): ?>

                        <div class="requirement-item">

                            <strong>
                                Required Skills:
                            </strong>

                            <?= nl2br(
                                htmlspecialchars(
                                    $application[
                                        "required_skills"
                                    ]
                                )
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty(
                        $application[
                            "educational_requirement"
                        ]
                    )): ?>

                        <div class="requirement-item">

                            <strong>
                                Education:
                            </strong>

                            <?= htmlspecialchars(
                                $application[
                                    "educational_requirement"
                                ]
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty(
                        $application[
                            "required_degree"
                        ]
                    )): ?>

                        <div class="requirement-item">

                            <strong>
                                Required Degree:
                            </strong>

                            <?= htmlspecialchars(
                                $application[
                                    "required_degree"
                                ]
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty(
                        $application[
                            "degree_classification"
                        ]
                    )): ?>

                        <div class="requirement-item">

                            <strong>
                                Degree Classification:
                            </strong>

                            <?= htmlspecialchars(
                                $application[
                                    "degree_classification"
                                ]
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty(
                        $application[
                            "tesda_qualification"
                        ]
                    )): ?>

                        <div class="requirement-item">

                            <strong>
                                TESDA Qualification:
                            </strong>

                            <?= htmlspecialchars(
                                $application[
                                    "tesda_qualification"
                                ]
                            ) ?>

                        </div>

                    <?php endif; ?>


                    <?php if (!empty(
                        $application[
                            "required_nc_level"
                        ]
                    )): ?>

                        <div class="requirement-item">

                            <strong>
                                Required NC Level:
                            </strong>

                            <?= htmlspecialchars(
                                $application[
                                    "required_nc_level"
                                ]
                            ) ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <!-- COVER LETTER -->

            <div class="card">

                <h2>
                    Cover Letter
                </h2>

                <?php if (!empty(
                    $application[
                        "cover_letter"
                    ]
                )): ?>

                    <div class="cover-letter">

                        <?= htmlspecialchars(
                            $application[
                                "cover_letter"
                            ]
                        ) ?>

                    </div>

                <?php else: ?>

                    <p class="muted">
                        No cover letter submitted.
                    </p>

                <?php endif; ?>

            </div>

        </div>


        <!-- RIGHT -->

        <div>


            <div class="card">

                <h2>
                    Update Application Status
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

                        ?>


                        <?php foreach (
                            $statuses
                            as
                            $status
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $status
                                ) ?>"

                                <?= $application[
                                    "status"
                                ] === $status
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= ucfirst(
                                    htmlspecialchars(
                                        $status
                                    )
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


            <div class="card">

                <h2>
                    Resume
                </h2>

                <?php if (!empty(
                    $application[
                        "file_path"
                    ]
                )): ?>

                    <div class="resume-box">

                        <div>

                            <strong>

                                <?= htmlspecialchars(
                                    $application[
                                        "file_name"
                                    ]
                                ) ?>

                            </strong>

                            <?php if (!empty(
                                $application[
                                    "uploaded_at"
                                ]
                            )): ?>

                                <div class="muted">

                                    Uploaded:

                                    <?= date(
                                        "M d, Y",
                                        strtotime(
                                            $application[
                                                "uploaded_at"
                                            ]
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>

                        <a
                            href="../<?= htmlspecialchars(
                                $application[
                                    "file_path"
                                ]
                            ) ?>"
                            target="_blank"
                            class="resume-btn"
                        >
                            View Resume
                        </a>

                    </div>

                <?php else: ?>

                    <p class="muted">
                        No resume attached.
                    </p>

                <?php endif; ?>

            </div>


            <div class="card">

                <h2>
                    Company
                </h2>

                <div class="profile-item">

                    <strong>
                        Company:
                    </strong>

                    <?= htmlspecialchars(
                        $application[
                            "company_name"
                        ]
                    ) ?>

                </div>

                <?php if (!empty(
                    $application[
                        "contact_person"
                    ]
                )): ?>

                    <div class="profile-item">

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


                <?php if (!empty(
                    $application[
                        "contact_number"
                    ]
                )): ?>

                    <div class="profile-item">

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

            </div>

        </div>

    </div>

</div>

</body>
</html>
