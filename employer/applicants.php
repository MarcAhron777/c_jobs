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

$error = "";
$success = "";

/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$jobId = isset($_GET["job_id"])
    ? (int) $_GET["job_id"]
    : 0;

$search = trim($_GET["search"] ?? "");

$page = isset($_GET["page"])
    ? max(1, (int) $_GET["page"])
    : 1;

$perPage = 10;

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
|
| Allows this page to remain usable even before the optional matching
| migration has been run.
|
*/

$matchColumns = [];

try {
    $columnStmt = $pdo->query("SHOW COLUMNS FROM application_matches");

    foreach ($columnStmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $matchColumns[] = $column["Field"];
    }
} catch (Throwable $e) {
    $matchColumns = [];
}

$tesdaScoreSelect = in_array("tesda_score", $matchColumns, true)
    ? "am.tesda_score"
    : "0 AS tesda_score";

$qualificationExplanationSelect =
    in_array("qualification_explanation", $matchColumns, true)
        ? "am.qualification_explanation"
        : "NULL AS qualification_explanation";

$matchedQualificationsSelect =
    in_array("matched_qualifications", $matchColumns, true)
        ? "am.matched_qualifications"
        : "NULL AS matched_qualifications";

$missingQualificationsSelect =
    in_array("missing_qualifications", $matchColumns, true)
        ? "am.missing_qualifications"
        : "NULL AS missing_qualifications";

$skillGapFeedbackSelect =
    in_array("skill_gap_feedback", $matchColumns, true)
        ? "am.skill_gap_feedback"
        : "NULL AS skill_gap_feedback";

/*
|--------------------------------------------------------------------------
| UPDATE APPLICATION STATUS + NOTIFICATION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $applicationId = isset($_POST["application_id"])
        ? (int) $_POST["application_id"]
        : 0;

    $status = trim($_POST["status"] ?? "");

    $allowedStatuses = [
        "pending",
        "reviewed",
        "shortlisted",
        "rejected",
        "hired"
    ];

    if (
        $applicationId <= 0 ||
        !in_array($status, $allowedStatuses, true)
    ) {
        $error = "Invalid application status.";
    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | GET APPLICATION OWNER BEFORE UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    a.id,
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

            $applicationOwner = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$applicationOwner) {
                throw new Exception(
                    "Application not found or you do not have permission to update it."
                );
            }

            $oldStatus = $applicationOwner["current_status"];

            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE applications
                SET status = ?
                WHERE id = ?
            ");

            $stmt->execute([
                $status,
                $applicationId
            ]);

            $success = "Application status updated successfully.";

            /*
            |--------------------------------------------------------------------------
            | NOTIFY APPLICANT ONLY WHEN STATUS REALLY CHANGED
            |--------------------------------------------------------------------------
            */

            if ($oldStatus !== $status) {

                $statusLabel = ucfirst($status);

                try {
                    NotificationService::create(
                        $pdo,
                        (int) $applicationOwner["applicant_user_id"],
                        "Application Status Updated",
                        "Your application for "
                            . $applicationOwner["job_title"]
                            . " is now "
                            . $statusLabel
                            . ".",
                        "application_status",
                        $applicationId
                    );
                } catch (Throwable $notificationError) {
                    error_log(
                        "Applicant notification error: "
                        . $notificationError->getMessage()
                    );
                }
            }

        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| GET EMPLOYER JOBS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        job_title
    FROM jobs
    WHERE employer_id = ?
    ORDER BY created_at DESC
");

$stmt->execute([$employerId]);

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| BUILD FILTER
|--------------------------------------------------------------------------
*/

$conditions = [
    "j.employer_id = ?"
];

$params = [
    $employerId
];

if ($jobId > 0) {
    $conditions[] = "j.id = ?";
    $params[] = $jobId;
}

if ($search !== "") {

    $conditions[] = "
        (
            CONCAT(ap.first_name, ' ', ap.last_name) LIKE ?
            OR ap.first_name LIKE ?
            OR ap.last_name LIKE ?
            OR j.job_title LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}

$whereSql = implode(" AND ", $conditions);

/*
|--------------------------------------------------------------------------
| COUNT
|--------------------------------------------------------------------------
*/

$countSql = "
    SELECT COUNT(*)
    FROM applications a
    INNER JOIN jobs j
        ON a.job_id = j.id
    INNER JOIN applicants ap
        ON a.applicant_id = ap.id
    WHERE $whereSql
";

$stmt = $pdo->prepare($countSql);
$stmt->execute($params);

$totalApplicants = (int) $stmt->fetchColumn();

$totalPages = max(
    1,
    (int) ceil($totalApplicants / $perPage)
);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

/*
|--------------------------------------------------------------------------
| GET APPLICANTS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        a.id AS application_id,
        a.status,
        a.cover_letter,
        a.applied_at,

        j.id AS job_id,
        j.job_title,

        ap.id AS applicant_id,
        ap.first_name,
        ap.last_name,
        ap.phone,
        ap.address,
        ap.bio,

        r.id AS resume_id,
        r.file_name,
        r.file_path,

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
        $skillGapFeedbackSelect

    FROM applications a

    INNER JOIN jobs j
        ON a.job_id = j.id

    INNER JOIN applicants ap
        ON a.applicant_id = ap.id

    LEFT JOIN resumes r
        ON a.resume_id = r.id

    LEFT JOIN application_matches am
        ON a.id = am.application_id

    WHERE $whereSql

    ORDER BY
        COALESCE(am.match_score, 0) DESC,
        a.applied_at DESC

    LIMIT $perPage
    OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function buildPageUrl(int $pageNumber): string
{
    $params = $_GET;
    $params["page"] = $pageNumber;

    return "?" . http_build_query($params);
}

function getMatchLabel(float $score): string
{
    if ($score >= 80) {
        return "Strong Match";
    }

    if ($score >= 60) {
        return "Good Match";
    }

    if ($score >= 40) {
        return "Fair Match";
    }

    return "Low Match";
}

function getMatchClass(float $score): string
{
    if ($score >= 80) {
        return "match-strong";
    }

    if ($score >= 60) {
        return "match-good";
    }

    if ($score >= 40) {
        return "match-fair";
    }

    return "match-low";
}

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
    Applicants - Caloocan Job Portal
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

.header {
    margin-bottom: 25px;
}

.header h1 {
    margin-bottom: 8px;
}

.header p {
    color: #666;
}

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

.filters {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);
}

.filters form {
    display: grid;
    grid-template-columns: 1fr 220px auto;
    gap: 10px;
}

.input,
.filters select {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
    background: white;
}

.filter-btn {
    padding: 11px 18px;
    background: #2563eb;
    border: none;
    color: white;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.clear-btn {
    display: inline-block;
    margin-top: 10px;
    padding: 9px 15px;
    background: #e5e7eb;
    color: #333;
    border-radius: 6px;
    text-decoration: none;
    font-size: 13px;
}

.result-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    color: #666;
    font-size: 14px;
}

.applicant-grid {
    display: grid;
    grid-template-columns:
        repeat(2, minmax(0, 1fr));
    gap: 20px;
}

.applicant-card {
    background: white;
    border-radius: 12px;
    padding: 20px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);

    border: 1px solid #eee;

    display: flex;
    flex-direction: column;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 15px;
}

.applicant-name {
    font-size: 19px;
    font-weight: bold;
    color: #111827;
}

.job-title {
    margin-top: 5px;
    color: #555;
    font-size: 14px;
}

.applied-date {
    margin-top: 7px;
    color: #888;
    font-size: 12px;
}

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: bold;
    white-space: nowrap;
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

.info-list {
    margin-bottom: 15px;
}

.info-item {
    font-size: 13px;
    color: #666;
    margin-bottom: 5px;
}

.info-item strong {
    color: #333;
}

.match-box {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    padding: 14px;
    margin-bottom: 15px;
}

.match-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
}

.match-left {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.match-label {
    font-size: 13px;
    font-weight: bold;
    color: #1e3a8a;
}

.match-category {
    font-size: 11px;
    font-weight: bold;
}

.match-strong {
    color: #15803d;
}

.match-good {
    color: #2563eb;
}

.match-fair {
    color: #b45309;
}

.match-low {
    color: #dc2626;
}

.match-score {
    font-size: 22px;
    font-weight: bold;
    color: #2563eb;
}

.progress {
    height: 8px;
    width: 100%;
    background: #dbeafe;
    border-radius: 20px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: #2563eb;
    border-radius: 20px;
}

.score-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 7px;
    margin-top: 12px;
}

.score-card {
    background: white;
    border: 1px solid #dbeafe;
    border-radius: 6px;
    padding: 9px;
    text-align: center;
}

.score-card span {
    display: block;
    color: #64748b;
    font-size: 10px;
    margin-bottom: 3px;
}

.score-card strong {
    color: #1d4ed8;
    font-size: 14px;
}

.detail-block {
    margin-top: 10px;
    font-size: 12px;
    color: #555;
    line-height: 1.6;
}

.detail-block strong {
    color: #222;
}

.explanation-box {
    margin-top: 12px;
    padding: 10px;
    border-radius: 6px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    font-size: 12px;
    line-height: 1.6;
    color: #475569;
}

.gap-box {
    margin-top: 10px;
    padding: 10px;
    border-radius: 6px;
    background: #fff7ed;
    border: 1px solid #fed7aa;
    color: #9a3412;
    font-size: 12px;
    line-height: 1.6;
}

.status-form {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.status-form-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
}

.status-form select {
    width: 100%;
    padding: 9px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.status-btn {
    border: none;
    background: #0f766e;
    color: white;
    padding: 9px 13px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.card-footer {
    margin-top: auto;
    padding-top: 15px;
    border-top: 1px solid #eee;

    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.resume-link {
    color: #64748b;
    text-decoration: none;
    font-size: 13px;
}

.view-btn {
    display: inline-block;
    padding: 9px 15px;
    background: #2563eb;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: bold;
}

.empty {
    background: white;
    padding: 60px 30px;
    text-align: center;
    border-radius: 10px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);
}

.pagination-wrapper {
    margin-top: 30px;
    display: flex;
    justify-content: center;
}

.pagination {
    display: flex;
    gap: 6px;
    align-items: center;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    min-width: 38px;
    height: 38px;
    padding: 0 12px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 6px;
    text-decoration: none;
    border: 1px solid #ddd;
    background: white;
    color: #333;
    font-size: 14px;
}

.pagination .active {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
    font-weight: bold;
}

.pagination .disabled {
    color: #aaa;
    background: #f3f4f6;
}

@media (max-width: 850px) {

    .applicant-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {

    .filters form {
        grid-template-columns: 1fr;
    }

    .result-summary {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }

    .score-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 500px) {

    .card-header,
    .card-footer {
        flex-direction: column;
        align-items: stretch;
    }
}

</style>

</head>

<body>

<?php
require __DIR__ . "/includes/header.php";
?>

<div class="container">

    <div class="header">

        <h1>
            Applicants
        </h1>

        <p>
            Review applicants, qualification matching,
            and skill-gap results.
        </p>

    </div>

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


    <div class="filters">

        <form method="GET">

            <input
                type="text"
                name="search"
                class="input"
                placeholder="Search applicant or job..."
                value="<?= htmlspecialchars($search) ?>"
            >

            <select name="job_id">

                <option value="0">
                    All Jobs
                </option>

                <?php foreach ($jobs as $job): ?>

                    <option
                        value="<?= (int) $job["id"] ?>"
                        <?= $jobId === (int) $job["id"]
                            ? "selected"
                            : ""
                        ?>
                    >
                        <?= htmlspecialchars($job["job_title"]) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <button
                type="submit"
                class="filter-btn"
            >
                Search / Filter
            </button>

        </form>

        <?php if ($search !== "" || $jobId > 0): ?>

            <a
                href="applicants.php"
                class="clear-btn"
            >
                Clear Filters
            </a>

        <?php endif; ?>

    </div>


    <div class="result-summary">

        <div>
            <strong>
                <?= $totalApplicants ?>
            </strong>
            applicant(s) found
        </div>

        <?php if ($totalApplicants > 0): ?>

            <div>
                Showing
                <strong><?= $offset + 1 ?></strong>
                -
                <strong>
                    <?= min(
                        $offset + $perPage,
                        $totalApplicants
                    ) ?>
                </strong>
                of
                <strong>
                    <?= $totalApplicants ?>
                </strong>
            </div>

        <?php endif; ?>

    </div>


    <?php if (count($applicants) > 0): ?>

        <div class="applicant-grid">

            <?php foreach ($applicants as $applicant): ?>

                <?php

                $matchScore = clampScore(
                    $applicant["match_score"] ?? 0
                );

                $skillsScore = clampScore(
                    $applicant["skills_score"] ?? 0
                );

                $educationScore = clampScore(
                    $applicant["education_score"] ?? 0
                );

                $tesdaScore = clampScore(
                    $applicant["tesda_score"] ?? 0
                );

                $matchLabel = getMatchLabel(
                    $matchScore
                );

                $matchClass = getMatchClass(
                    $matchScore
                );

                $statusClass =
                    "status-"
                    . strtolower(
                        $applicant["status"]
                    );

                ?>

                <div class="applicant-card">

                    <div class="card-header">

                        <div>

                            <div class="applicant-name">

                                <?= htmlspecialchars(
                                    $applicant["first_name"]
                                    . " "
                                    . $applicant["last_name"]
                                ) ?>

                            </div>

                            <div class="job-title">

                                <?= htmlspecialchars(
                                    $applicant["job_title"]
                                ) ?>

                            </div>

                            <div class="applied-date">

                                Applied:
                                <?= date(
                                    "M d, Y",
                                    strtotime(
                                        $applicant["applied_at"]
                                    )
                                ) ?>

                            </div>

                        </div>

                        <span
                            class="status <?= htmlspecialchars(
                                $statusClass
                            ) ?>"
                        >
                            <?= ucfirst(
                                htmlspecialchars(
                                    $applicant["status"]
                                )
                            ) ?>
                        </span>

                    </div>


                    <div class="info-list">

                        <?php if (!empty($applicant["phone"])): ?>

                            <div class="info-item">

                                <strong>Phone:</strong>

                                <?= htmlspecialchars(
                                    $applicant["phone"]
                                ) ?>

                            </div>

                        <?php endif; ?>

                        <?php if (!empty($applicant["address"])): ?>

                            <div class="info-item">

                                <strong>Address:</strong>

                                <?= htmlspecialchars(
                                    $applicant["address"]
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="match-box">

                        <div class="match-top">

                            <div class="match-left">

                                <span class="match-label">
                                    Explainable Job Match
                                </span>

                                <span
                                    class="match-category <?= htmlspecialchars(
                                        $matchClass
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        $matchLabel
                                    ) ?>
                                </span>

                            </div>

                            <span class="match-score">

                                <?= number_format(
                                    $matchScore,
                                    0
                                ) ?>%

                            </span>

                        </div>

                        <div class="progress">

                            <div
                                class="progress-bar"
                                style="width:<?= $matchScore ?>%;"
                            ></div>

                        </div>


                        <div class="score-grid">

                            <div class="score-card">

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

                            <div class="score-card">

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

                            <div class="score-card">

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

                        </div>


                        <?php if (!empty($applicant["matched_skills"])): ?>

                            <div class="detail-block">

                                <strong>
                                    Matched Skills:
                                </strong>

                                <?= htmlspecialchars(
                                    $applicant["matched_skills"]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty($applicant["missing_skills"])): ?>

                            <div class="detail-block">

                                <strong>
                                    Missing Skills:
                                </strong>

                                <?= htmlspecialchars(
                                    $applicant["missing_skills"]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty(
                            $applicant["matched_qualifications"]
                        )): ?>

                            <div class="detail-block">

                                <strong>
                                    Matched Qualifications:
                                </strong>

                                <?= htmlspecialchars(
                                    $applicant[
                                        "matched_qualifications"
                                    ]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty(
                            $applicant["missing_qualifications"]
                        )): ?>

                            <div class="detail-block">

                                <strong>
                                    Missing Qualifications:
                                </strong>

                                <?= htmlspecialchars(
                                    $applicant[
                                        "missing_qualifications"
                                    ]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty(
                            $applicant["qualification_explanation"]
                        )): ?>

                            <div class="explanation-box">

                                <strong>
                                    Why this applicant matched:
                                </strong>

                                <br>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $applicant[
                                            "qualification_explanation"
                                        ]
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (!empty(
                            $applicant["skill_gap_feedback"]
                        )): ?>

                            <div class="gap-box">

                                <strong>
                                    Skill Gap Feedback:
                                </strong>

                                <br>

                                <?= nl2br(
                                    htmlspecialchars(
                                        $applicant[
                                            "skill_gap_feedback"
                                        ]
                                    )
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <form
                        method="POST"
                        class="status-form"
                    >

                        <input
                            type="hidden"
                            name="application_id"
                            value="<?= (int)
                                $applicant["application_id"]
                            ?>"
                        >

                        <div class="status-form-row">

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

                                <?php foreach ($statuses as $status): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $status
                                        ) ?>"
                                        <?= $applicant["status"] === $status
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
                                class="status-btn"
                            >
                                Update
                            </button>

                        </div>

                    </form>


                    <div class="card-footer">

                        <?php if (!empty(
                            $applicant["file_path"]
                        )): ?>

                            <a
                                href="../<?= htmlspecialchars(
                                    $applicant["file_path"]
                                ) ?>"
                                target="_blank"
                                class="resume-link"
                            >
                                View Resume
                            </a>

                        <?php else: ?>

                            <span class="resume-link">
                                No Resume
                            </span>

                        <?php endif; ?>


                        <a
                            href="view-application.php?id=<?= (int)
                                $applicant["application_id"]
                            ?>"
                            class="view-btn"
                        >
                            View Details
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <?php if ($totalPages > 1): ?>

            <div class="pagination-wrapper">

                <div class="pagination">

                    <?php if ($page > 1): ?>

                        <a href="<?= htmlspecialchars(
                            buildPageUrl(
                                $page - 1
                            )
                        ) ?>">
                            Previous
                        </a>

                    <?php else: ?>

                        <span class="disabled">
                            Previous
                        </span>

                    <?php endif; ?>


                    <?php
                    $startPage = max(
                        1,
                        $page - 2
                    );

                    $endPage = min(
                        $totalPages,
                        $page + 2
                    );
                    ?>


                    <?php for (
                        $pageNumber = $startPage;
                        $pageNumber <= $endPage;
                        $pageNumber++
                    ): ?>

                        <?php if ($pageNumber === $page): ?>

                            <span class="active">
                                <?= $pageNumber ?>
                            </span>

                        <?php else: ?>

                            <a href="<?= htmlspecialchars(
                                buildPageUrl(
                                    $pageNumber
                                )
                            ) ?>">
                                <?= $pageNumber ?>
                            </a>

                        <?php endif; ?>

                    <?php endfor; ?>


                    <?php if ($page < $totalPages): ?>

                        <a href="<?= htmlspecialchars(
                            buildPageUrl(
                                $page + 1
                            )
                        ) ?>">
                            Next
                        </a>

                    <?php else: ?>

                        <span class="disabled">
                            Next
                        </span>

                    <?php endif; ?>

                </div>

            </div>

        <?php endif; ?>


    <?php else: ?>

        <div class="empty">

            <h2>
                No Applicants Found
            </h2>

            <p>
                No applications match your
                current search or job filter.
            </p>

        </div>

    <?php endif; ?>

</div>

</body>
</html>
