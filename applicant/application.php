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
$applicationId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($applicationId <= 0) {
    die("Invalid application.");
}


/*
|--------------------------------------------------------------------------
| GET APPLICANT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
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
| GET APPLICATION
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
        am.tesda_score,
        am.matched_skills,
        am.missing_skills,
        am.qualification_explanation,
        am.matched_qualifications,
        am.missing_qualifications,
        am.skill_gap_feedback,

        j.id AS job_id,
        j.job_title,

        COALESCE(
            NULLIF(j.job_description, ''),
            j.description
        ) AS description,

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

        e.company_name,
        e.company_description

    FROM applications ap

    INNER JOIN jobs j
        ON ap.job_id = j.id

    INNER JOIN employers e
        ON j.employer_id = e.id

    LEFT JOIN application_matches am
        ON ap.id = am.application_id

    WHERE
        ap.id = ?
        AND ap.applicant_id = ?

    LIMIT 1
");

$stmt->execute([
    $applicationId,
    $applicantId
]);

$application = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$application) {
    die("Application not found.");
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function clampScore($score): float
{
    return max(
        0,
        min(
            100,
            (float) ($score ?? 0)
        )
    );
}

function splitMatchValues(?string $value): array
{
    if ($value === null || trim($value) === "") {
        return [];
    }

    return array_values(
        array_filter(
            array_map(
                "trim",
                preg_split('/[,;\r\n|]+/', $value)
            )
        )
    );
}


$matchScore = clampScore(
    $application["match_score"] ?? 0
);

$skillsScore = clampScore(
    $application["skills_score"] ?? 0
);

$educationScore = clampScore(
    $application["education_score"] ?? 0
);

$tesdaScore = clampScore(
    $application["tesda_score"] ?? 0
);

$matchedSkills = splitMatchValues(
    $application["matched_skills"] ?? null
);

$missingSkills = splitMatchValues(
    $application["missing_skills"] ?? null
);

$matchedQualifications = splitMatchValues(
    $application["matched_qualifications"] ?? null
);

$missingQualifications = splitMatchValues(
    $application["missing_qualifications"] ?? null
);

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
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #333;
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

        .card {
            background: #fff;
            padding: 28px;
            margin-bottom: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        h1,
        h2,
        h3 {
            margin-bottom: 12px;
        }

        .company-name {
            color: #555;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .meta-item {
            padding: 8px 12px;
            background: #f1f5f9;
            border-radius: 6px;
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 8px 14px;
            margin-bottom: 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 13px;
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

        .date {
            color: #666;
            font-size: 14px;
        }

        .section {
            margin-top: 25px;
        }

        .section p {
            line-height: 1.7;
            white-space: pre-line;
        }

        .requirement-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 20px;
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

        .match-box {
            padding: 24px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
        }

        .match-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .match-score {
            color: #2563eb;
            font-size: 42px;
            font-weight: bold;
        }

        .progress {
            height: 11px;
            margin-bottom: 20px;
            overflow: hidden;
            background: #dbeafe;
            border-radius: 20px;
        }

        .progress-bar {
            height: 100%;
            background: #2563eb;
        }

        .score-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .score-card {
            padding: 14px;
            background: #fff;
            border: 1px solid #dbeafe;
            border-radius: 7px;
            text-align: center;
        }

        .score-card span {
            display: block;
            margin-bottom: 5px;
            color: #64748b;
            font-size: 12px;
        }

        .score-card strong {
            color: #2563eb;
            font-size: 20px;
        }

        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .chip {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .chip-good {
            background: #dcfce7;
            color: #166534;
        }

        .chip-missing {
            background: #fee2e2;
            color: #991b1b;
        }

        .explanation-box {
            padding: 16px;
            margin-top: 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            line-height: 1.7;
        }

        .skill-gap-box {
            padding: 16px;
            margin-top: 20px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 8px;
            color: #9a3412;
            line-height: 1.7;
        }

        .muted {
            color: #777;
        }

        @media (max-width: 700px) {
            .requirement-grid,
            .score-grid {
                grid-template-columns: 1fr;
            }

            .match-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>

<div class="container">

    <a
        href="applications.php"
        class="back-link"
    >
        &larr; Back to Applications
    </a>


    <div class="card">

        <h1>
            <?= htmlspecialchars($application["job_title"]) ?>
        </h1>

        <div class="company-name">
            <?= htmlspecialchars($application["company_name"]) ?>
        </div>


        <div class="meta">

            <span class="meta-item">
                <?= htmlspecialchars(
                    $application["location"] ?? "Not specified"
                ) ?>
            </span>

            <span class="meta-item">
                <?= htmlspecialchars(
                    $application["employment_type"]
                ) ?>
            </span>

            <?php if ($application["is_remote"]): ?>
                <span class="meta-item">
                    Remote
                </span>
            <?php endif; ?>

            <span class="meta-item">
                Source:
                <?= htmlspecialchars(
                    ucwords(
                        str_replace(
                            "_",
                            " ",
                            $application["job_source"]
                            ?? "private_employer"
                        )
                    )
                ) ?>
            </span>

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


    <div class="card">

        <h2>Job Description</h2>

        <div class="section">
            <p><?= htmlspecialchars(
                $application["description"]
            ) ?></p>
        </div>


        <?php if (!empty($application["requirements"])): ?>

            <div class="section">
                <h3>General Requirements</h3>

                <p><?= htmlspecialchars(
                    $application["requirements"]
                ) ?></p>
            </div>

        <?php endif; ?>


        <div class="requirement-grid">

            <div class="requirement-item">
                <span class="requirement-label">
                    Required Skills
                </span>

                <?= nl2br(
                    htmlspecialchars(
                        $application["required_skills"]
                            ?: "Not specified"
                    )
                ) ?>
            </div>


            <div class="requirement-item">
                <span class="requirement-label">
                    Educational Requirement
                </span>

                <?= htmlspecialchars(
                    $application["educational_requirement"]
                        ?: "No specific requirement"
                ) ?>
            </div>


            <div class="requirement-item">
                <span class="requirement-label">
                    Required Degree / Program
                </span>

                <?= htmlspecialchars(
                    $application["required_degree"]
                        ?: "No specific degree"
                ) ?>
            </div>


            <div class="requirement-item">
                <span class="requirement-label">
                    Degree Classification
                </span>

                <?= htmlspecialchars(
                    $application["degree_classification"]
                        ?: "Not specified"
                ) ?>
            </div>


            <div class="requirement-item">
                <span class="requirement-label">
                    TESDA Qualification
                </span>

                <?= htmlspecialchars(
                    $application["tesda_qualification"]
                        ?: "Not required"
                ) ?>
            </div>


            <div class="requirement-item">
                <span class="requirement-label">
                    Required NC Level
                </span>

                <?= htmlspecialchars(
                    $application["required_nc_level"]
                        ?: "No NC Required"
                ) ?>
            </div>

        </div>

    </div>


    <div class="card">

        <h2>Explainable Job Match</h2>

        <div class="match-box">

            <div class="match-header">

                <div>
                    <h3>Overall Match</h3>

                    <p class="muted">
                        Based on your resume skills,
                        education, and TESDA/NC qualifications.
                    </p>
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

                <div class="score-card">
                    <span>Skills</span>

                    <strong>
                        <?= number_format(
                            $skillsScore,
                            0
                        ) ?>%
                    </strong>
                </div>


                <div class="score-card">
                    <span>Education</span>

                    <strong>
                        <?= number_format(
                            $educationScore,
                            0
                        ) ?>%
                    </strong>
                </div>


                <div class="score-card">
                    <span>TESDA / NC</span>

                    <strong>
                        <?= number_format(
                            $tesdaScore,
                            0
                        ) ?>%
                    </strong>
                </div>

            </div>


            <div class="section">

                <h3>Matched Skills</h3>

                <?php if (!empty($matchedSkills)): ?>

                    <div class="chips">
                        <?php foreach ($matchedSkills as $skill): ?>

                            <span class="chip chip-good">
                                <?= htmlspecialchars($skill) ?>
                            </span>

                        <?php endforeach; ?>
                    </div>

                <?php else: ?>

                    <p class="muted">
                        No matched skills recorded.
                    </p>

                <?php endif; ?>

            </div>


            <div class="section">

                <h3>Missing Skills</h3>

                <?php if (!empty($missingSkills)): ?>

                    <div class="chips">
                        <?php foreach ($missingSkills as $skill): ?>

                            <span class="chip chip-missing">
                                <?= htmlspecialchars($skill) ?>
                            </span>

                        <?php endforeach; ?>
                    </div>

                <?php else: ?>

                    <p class="muted">
                        No missing skills detected.
                    </p>

                <?php endif; ?>

            </div>


            <div class="section">

                <h3>Matched Qualifications</h3>

                <?php if (!empty($matchedQualifications)): ?>

                    <div class="chips">
                        <?php foreach ($matchedQualifications as $qualification): ?>

                            <span class="chip chip-good">
                                <?= htmlspecialchars($qualification) ?>
                            </span>

                        <?php endforeach; ?>
                    </div>

                <?php else: ?>

                    <p class="muted">
                        No matched qualifications recorded.
                    </p>

                <?php endif; ?>

            </div>


            <div class="section">

                <h3>Missing Qualifications</h3>

                <?php if (!empty($missingQualifications)): ?>

                    <div class="chips">
                        <?php foreach ($missingQualifications as $qualification): ?>

                            <span class="chip chip-missing">
                                <?= htmlspecialchars($qualification) ?>
                            </span>

                        <?php endforeach; ?>
                    </div>

                <?php else: ?>

                    <p class="muted">
                        No missing qualifications detected.
                    </p>

                <?php endif; ?>

            </div>


            <div class="explanation-box">

                <strong>Why You Matched</strong>

                <br>

                <?php if (!empty(
                    $application["qualification_explanation"]
                )): ?>

                    <?= nl2br(
                        htmlspecialchars(
                            $application[
                                "qualification_explanation"
                            ]
                        )
                    ) ?>

                <?php else: ?>

                    No qualification explanation is
                    available for this application.

                <?php endif; ?>

            </div>


            <div class="skill-gap-box">

                <strong>Skill Gap Feedback</strong>

                <br>

                <?php if (!empty(
                    $application["skill_gap_feedback"]
                )): ?>

                    <?= nl2br(
                        htmlspecialchars(
                            $application["skill_gap_feedback"]
                        )
                    ) ?>

                <?php else: ?>

                    No skill-gap feedback is
                    available for this application.

                <?php endif; ?>

            </div>

        </div>

    </div>


    <?php if (!empty($application["cover_letter"])): ?>

        <div class="card">

            <h2>Your Cover Letter</h2>

            <div class="section">
                <p><?= htmlspecialchars(
                    $application["cover_letter"]
                ) ?></p>
            </div>

        </div>

    <?php endif; ?>

</div>

</body>
</html>