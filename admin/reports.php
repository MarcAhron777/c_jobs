<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| ADMIN ACCESS ONLY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET TABLE COLUMNS
|--------------------------------------------------------------------------
*/

function getTableColumns(PDO $pdo, string $table): array
{
    $allowedTables = [
        "users",
        "employers",
        "applicants",
        "jobs",
        "applications",
        "application_matches"
    ];

    if (!in_array($table, $allowedTables, true)) {
        return [];
    }

    try {
        $stmt = $pdo->query(
            "SHOW COLUMNS FROM `$table`"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        return [];
    }
}


/*
|--------------------------------------------------------------------------
| DETECT USER ACCOUNT STATUS COLUMN
|--------------------------------------------------------------------------
*/

$userColumns =
    getTableColumns(
        $pdo,
        "users"
    );

$userColumnNames = [];

foreach ($userColumns as $column) {
    if (!empty($column["Field"])) {
        $userColumnNames[] =
            $column["Field"];
    }
}

$statusColumn = null;

$statusCandidates = [
    "account_status",
    "status",
    "user_status",
    "is_active",
    "active"
];

foreach ($statusCandidates as $candidate) {
    if (
        in_array(
            $candidate,
            $userColumnNames,
            true
        )
    ) {
        $statusColumn =
            $candidate;

        break;
    }
}


/*
|--------------------------------------------------------------------------
| BUILD ACCOUNT STATUS SQL
|--------------------------------------------------------------------------
*/

function buildAccountStatusExpression(
    ?string $column
): string {
    if ($column === null) {
        return "'active'";
    }

    if (
        $column === "is_active" ||
        $column === "active"
    ) {
        return "
            CASE
                WHEN u.`$column` = 1
                    THEN 'active'
                ELSE 'inactive'
            END
        ";
    }

    return "
        CASE

            WHEN LOWER(
                COALESCE(
                    u.`$column`,
                    'active'
                )
            ) IN (
                'active',
                'activated',
                'enabled',
                '1'
            )
            THEN 'active'

            WHEN LOWER(
                COALESCE(
                    u.`$column`,
                    ''
                )
            ) IN (
                'deactivated',
                'deactive',
                'disabled',
                'blocked',
                'suspended'
            )
            THEN 'deactivated'

            WHEN LOWER(
                COALESCE(
                    u.`$column`,
                    ''
                )
            ) IN (
                'inactive',
                '0'
            )
            THEN 'inactive'

            ELSE LOWER(
                COALESCE(
                    u.`$column`,
                    'active'
                )
            )

        END
    ";
}

$accountStatusExpression =
    buildAccountStatusExpression(
        $statusColumn
    );


/*
|--------------------------------------------------------------------------
| FILTER VALUES
|--------------------------------------------------------------------------
*/

$dateFrom =
    trim(
        $_GET["date_from"]
        ?? ""
    );

$dateTo =
    trim(
        $_GET["date_to"]
        ?? ""
    );

$employerId =
    isset($_GET["employer_id"])
        ? (int) $_GET["employer_id"]
        : 0;

$jobId =
    isset($_GET["job_id"])
        ? (int) $_GET["job_id"]
        : 0;

$applicationStatus =
    trim(
        $_GET["status"]
        ?? ""
    );

$employerAccountFilter =
    trim(
        $_GET["employer_account_status"]
        ?? ""
    );

$applicantAccountFilter =
    trim(
        $_GET["applicant_account_status"]
        ?? ""
    );

$employerTypeFilter =
    trim(
        $_GET["employer_type"]
        ?? ""
    );

$jobSourceFilter =
    trim(
        $_GET["job_source"]
        ?? ""
    );


/*
|--------------------------------------------------------------------------
| VALID FILTER VALUES
|--------------------------------------------------------------------------
*/

$allowedApplicationStatuses = [
    "",
    "pending",
    "reviewed",
    "shortlisted",
    "rejected",
    "hired"
];

if (
    !in_array(
        $applicationStatus,
        $allowedApplicationStatuses,
        true
    )
) {
    $applicationStatus = "";
}


$allowedAccountFilters = [
    "",
    "active",
    "inactive",
    "deactivated"
];

if (
    !in_array(
        $employerAccountFilter,
        $allowedAccountFilters,
        true
    )
) {
    $employerAccountFilter = "";
}

if (
    !in_array(
        $applicantAccountFilter,
        $allowedAccountFilters,
        true
    )
) {
    $applicantAccountFilter = "";
}


$allowedEmployerTypes = [
    "",
    "private",
    "lgu"
];

if (
    !in_array(
        $employerTypeFilter,
        $allowedEmployerTypes,
        true
    )
) {
    $employerTypeFilter = "";
}


$allowedJobSources = [
    "",
    "private_employer",
    "lgu",
    "job_fair"
];

if (
    !in_array(
        $jobSourceFilter,
        $allowedJobSources,
        true
    )
) {
    $jobSourceFilter = "";
}


/*
|--------------------------------------------------------------------------
| EMPLOYER FILTER DATA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        company_name,
        employer_type
    FROM employers
    ORDER BY company_name ASC
");

$employers =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| JOB FILTER DATA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        job_title,
        job_source
    FROM jobs
    ORDER BY job_title ASC
");

$jobs =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| GENERAL COUNTS
|--------------------------------------------------------------------------
*/

$totalUsers =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM users
            WHERE role != 'admin'
        ")
        ->fetchColumn();


$totalEmployers =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM employers
        ")
        ->fetchColumn();


$totalPrivateEmployers =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM employers
            WHERE employer_type = 'private'
        ")
        ->fetchColumn();


$totalLguEmployers =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM employers
            WHERE employer_type = 'lgu'
        ")
        ->fetchColumn();


$totalApplicants =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM applicants
        ")
        ->fetchColumn();


$totalJobs =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM jobs
        ")
        ->fetchColumn();


$privateJobs =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM jobs
            WHERE job_source = 'private_employer'
        ")
        ->fetchColumn();


$lguJobs =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM jobs
            WHERE job_source = 'lgu'
        ")
        ->fetchColumn();


$jobFairJobs =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM jobs
            WHERE job_source = 'job_fair'
        ")
        ->fetchColumn();


$pendingJobs =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM jobs
            WHERE status = 'pending'
        ")
        ->fetchColumn();


$approvedJobs =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM jobs
            WHERE status = 'approved'
        ")
        ->fetchColumn();


$rejectedJobs =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM jobs
            WHERE status = 'rejected'
        ")
        ->fetchColumn();


$closedJobs =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM jobs
            WHERE status = 'closed'
        ")
        ->fetchColumn();


$totalApplications =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM applications
        ")
        ->fetchColumn();


/*
|--------------------------------------------------------------------------
| EMPLOYER ACCOUNTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        e.id,
        e.company_name,
        e.employer_type,
        e.verification_status,
        e.created_at,

        u.id AS user_id,
        u.email,

        $accountStatusExpression
            AS account_status,

        (
            SELECT COUNT(*)
            FROM jobs j
            WHERE j.employer_id = e.id
        ) AS total_jobs

    FROM employers e

    INNER JOIN users u
        ON e.user_id = u.id

    ORDER BY
        e.company_name ASC
");

$allEmployerAccounts =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| APPLICANT ACCOUNTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        ap.id,
        ap.first_name,
        ap.last_name,
        ap.created_at,

        u.id AS user_id,
        u.email,

        $accountStatusExpression
            AS account_status,

        (
            SELECT COUNT(*)
            FROM applications a
            WHERE a.applicant_id = ap.id
        ) AS total_applications

    FROM applicants ap

    INNER JOIN users u
        ON ap.user_id = u.id

    ORDER BY
        ap.first_name ASC,
        ap.last_name ASC
");

$allApplicantAccounts =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| NORMALIZE ACCOUNT STATUS
|--------------------------------------------------------------------------
*/

function normalizeAccountStatus(
    ?string $status
): string {
    $status =
        strtolower(
            trim(
                $status
                ?? "active"
            )
        );

    if (
        in_array(
            $status,
            [
                "active",
                "inactive",
                "deactivated"
            ],
            true
        )
    ) {
        return $status;
    }

    return "inactive";
}


/*
|--------------------------------------------------------------------------
| EMPLOYER ACCOUNT COUNTS
|--------------------------------------------------------------------------
*/

$employerStatusCounts = [
    "active" => 0,
    "inactive" => 0,
    "deactivated" => 0
];

foreach (
    $allEmployerAccounts
    as
    $account
) {
    $status =
        normalizeAccountStatus(
            $account["account_status"]
            ?? null
        );

    $employerStatusCounts[$status]++;
}


/*
|--------------------------------------------------------------------------
| APPLICANT ACCOUNT COUNTS
|--------------------------------------------------------------------------
*/

$applicantStatusCounts = [
    "active" => 0,
    "inactive" => 0,
    "deactivated" => 0
];

foreach (
    $allApplicantAccounts
    as
    $account
) {
    $status =
        normalizeAccountStatus(
            $account["account_status"]
            ?? null
        );

    $applicantStatusCounts[$status]++;
}


/*
|--------------------------------------------------------------------------
| FILTER EMPLOYER ACCOUNTS
|--------------------------------------------------------------------------
*/

$employerAccounts =
    $allEmployerAccounts;

if (
    $employerAccountFilter !== "" ||
    $employerTypeFilter !== ""
) {
    $employerAccounts =
        array_values(
            array_filter(
                $allEmployerAccounts,

                function (
                    array $account
                ) use (
                    $employerAccountFilter,
                    $employerTypeFilter
                ): bool {
                    if (
                        $employerAccountFilter !== "" &&
                        normalizeAccountStatus(
                            $account["account_status"]
                            ?? null
                        ) !== $employerAccountFilter
                    ) {
                        return false;
                    }

                    if (
                        $employerTypeFilter !== "" &&
                        ($account["employer_type"] ?? "private")
                        !==
                        $employerTypeFilter
                    ) {
                        return false;
                    }

                    return true;
                }
            )
        );
}


/*
|--------------------------------------------------------------------------
| FILTER APPLICANT ACCOUNTS
|--------------------------------------------------------------------------
*/

$applicantAccounts =
    $allApplicantAccounts;

if ($applicantAccountFilter !== "") {
    $applicantAccounts =
        array_values(
            array_filter(
                $allApplicantAccounts,

                function (
                    array $account
                ) use (
                    $applicantAccountFilter
                ): bool {
                    return normalizeAccountStatus(
                        $account["account_status"]
                        ?? null
                    ) ===
                    $applicantAccountFilter;
                }
            )
        );
}


/*
|--------------------------------------------------------------------------
| APPLICATION FILTER CONDITIONS
|--------------------------------------------------------------------------
*/

$conditions = [];
$params = [];

if ($dateFrom !== "") {
    $conditions[] =
        "DATE(a.applied_at) >= ?";

    $params[] =
        $dateFrom;
}

if ($dateTo !== "") {
    $conditions[] =
        "DATE(a.applied_at) <= ?";

    $params[] =
        $dateTo;
}

if ($employerId > 0) {
    $conditions[] =
        "e.id = ?";

    $params[] =
        $employerId;
}

if ($jobId > 0) {
    $conditions[] =
        "j.id = ?";

    $params[] =
        $jobId;
}

if ($applicationStatus !== "") {
    $conditions[] =
        "a.status = ?";

    $params[] =
        $applicationStatus;
}

if ($jobSourceFilter !== "") {
    $conditions[] =
        "j.job_source = ?";

    $params[] =
        $jobSourceFilter;
}

$whereSql = "";

if (count($conditions) > 0) {
    $whereSql =
        " WHERE "
        . implode(
            " AND ",
            $conditions
        );
}


/*
|--------------------------------------------------------------------------
| APPLICATION REPORT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        a.id AS application_id,
        a.status,
        a.applied_at,

        ap.id AS applicant_id,
        ap.first_name,
        ap.last_name,

        j.id AS job_id,
        j.job_title,
        j.job_source,

        e.id AS employer_id,
        e.company_name,
        e.employer_type,

        am.match_score,
        am.skills_score,
        am.matched_skills,
        am.missing_skills

    FROM applications a

    INNER JOIN applicants ap
        ON a.applicant_id = ap.id

    INNER JOIN jobs j
        ON a.job_id = j.id

    INNER JOIN employers e
        ON j.employer_id = e.id

    LEFT JOIN application_matches am
        ON a.id = am.application_id

    $whereSql

    ORDER BY
        a.applied_at DESC
");

$stmt->execute($params);

$applications =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| APPLICATION SUMMARY
|--------------------------------------------------------------------------
*/

$applicationStatusCounts = [
    "pending" => 0,
    "reviewed" => 0,
    "shortlisted" => 0,
    "rejected" => 0,
    "hired" => 0
];

$strongMatch = 0;
$goodMatch = 0;
$fairMatch = 0;
$lowMatch = 0;

$totalMatchScore = 0;
$matchCount = 0;

foreach ($applications as $application) {
    $status =
        strtolower(
            $application["status"]
            ?? ""
        );

    if (
        isset(
            $applicationStatusCounts[$status]
        )
    ) {
        $applicationStatusCounts[$status]++;
    }

    if (
        $application["match_score"]
        !== null
    ) {
        $score =
            (float)
            $application["match_score"];

        $score =
            max(
                0,
                min(
                    100,
                    $score
                )
            );

        $totalMatchScore += $score;
        $matchCount++;

        if ($score >= 80) {
            $strongMatch++;
        } elseif ($score >= 60) {
            $goodMatch++;
        } elseif ($score >= 40) {
            $fairMatch++;
        } else {
            $lowMatch++;
        }
    }
}

$averageMatchScore =
    $matchCount > 0
        ? $totalMatchScore / $matchCount
        : 0;


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function getMatchLabel(
    float $score
): string {
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


function getMatchClass(
    float $score
): string {
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


function getAccountStatusClass(
    string $status
): string {
    return match ($status) {
        "active" =>
            "account-active",

        "deactivated" =>
            "account-deactivated",

        default =>
            "account-inactive"
    };
}


function getEmployerTypeLabel(
    ?string $type
): string {
    return $type === "lgu"
        ? "LGU"
        : "Private Employer";
}


function getJobSourceLabel(
    ?string $source
): string {
    return match ($source) {
        "lgu" => "LGU",
        "job_fair" => "Job Fair",
        default => "Private Employer"
    };
}


function statusBadgeClass(
    ?string $status
): string {
    return match ($status) {
        "hired" => "status-hired",
        "shortlisted" => "status-shortlisted",
        "reviewed" => "status-reviewed",
        "rejected" => "status-rejected",
        default => "status-pending"
    };
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
    Reports - Admin
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
    color: #222;
}

.container {
    max-width: 1400px;
    margin: 40px auto;
    padding: 0 20px 60px;
}

.page-header {
    margin-bottom: 30px;
}

.page-header h1 {
    margin-bottom: 8px;
}

.page-header p {
    color: #64748b;
    line-height: 1.6;
}

.section-card {
    margin-bottom: 25px;
    padding: 25px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
    margin-bottom: 20px;
}

.section-header h2 {
    margin-bottom: 6px;
    font-size: 20px;
}

.section-header p {
    color: #64748b;
    font-size: 14px;
    line-height: 1.5;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}

.stat-card {
    padding: 20px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
}

.stat-title {
    margin-bottom: 8px;
    color: #64748b;
    font-size: 13px;
}

.stat-number {
    font-size: 27px;
    font-weight: bold;
}

.filter-form {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    align-items: end;
}

.form-group label {
    display: block;
    margin-bottom: 7px;
    font-size: 13px;
    font-weight: bold;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 10px 11px;
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 5px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #2563eb;
}

.filter-actions {
    display: flex;
    gap: 8px;
}

.btn {
    display: inline-block;
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    color: #ffffff;
    font-size: 13px;
    text-decoration: none;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
}

.btn-primary:hover {
    background: #1d4ed8;
}

.btn-gray {
    background: #64748b;
}

.btn-gray:hover {
    background: #475569;
}

.table-container {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    text-align: left;
    vertical-align: middle;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #475569;
    font-weight: bold;
}

tbody tr:hover {
    background: #fafafa;
}

.name {
    font-weight: bold;
}

.secondary {
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
}

.account-badge,
.status-badge,
.match-badge,
.source-badge,
.type-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: bold;
    white-space: nowrap;
}

.account-active {
    background: #dcfce7;
    color: #166534;
}

.account-inactive {
    background: #fef3c7;
    color: #92400e;
}

.account-deactivated {
    background: #fee2e2;
    color: #991b1b;
}

.type-private {
    background: #e0e7ff;
    color: #3730a3;
}

.type-lgu {
    background: #dbeafe;
    color: #1e40af;
}

.source-private {
    background: #e0e7ff;
    color: #3730a3;
}

.source-lgu {
    background: #dbeafe;
    color: #1e40af;
}

.source-job-fair {
    background: #f3e8ff;
    color: #7e22ce;
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
    background: #ede9fe;
    color: #6d28d9;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-hired {
    background: #dcfce7;
    color: #166534;
}

.match-strong {
    background: #dcfce7;
    color: #166534;
}

.match-good {
    background: #dbeafe;
    color: #1e40af;
}

.match-fair {
    background: #fef3c7;
    color: #92400e;
}

.match-low {
    background: #fee2e2;
    color: #991b1b;
}

.match-score {
    font-size: 16px;
    font-weight: bold;
}

.empty {
    padding: 35px;
    text-align: center;
    color: #64748b;
}

.report-note {
    padding: 14px 16px;
    margin-bottom: 20px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 7px;
    color: #1e40af;
    font-size: 13px;
    line-height: 1.6;
}

.verification {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: bold;
}

.verification-approved {
    background: #dcfce7;
    color: #166534;
}

.verification-pending {
    background: #fef3c7;
    color: #92400e;
}

.verification-rejected {
    background: #fee2e2;
    color: #991b1b;
}

@media (max-width: 1100px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .filter-form {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 650px) {

    .container {
        margin: 25px auto;
        padding: 0 15px 40px;
    }

    .stats-grid,
    .filter-form {
        grid-template-columns: 1fr;
    }

    .section-card {
        padding: 18px;
    }

    .section-header {
        flex-direction: column;
    }

}

</style>

</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>


<div class="container">

    <div class="page-header">

        <h1>
            Reports
        </h1>

        <p>
            View system statistics, employer and applicant
            account reports, job sources, and application
            matching results.
        </p>

    </div>


    <!-- =====================================================
         GENERAL SYSTEM REPORT
    ====================================================== -->

    <div class="section-card">

        <div class="section-header">

            <div>

                <h2>
                    System Overview
                </h2>

                <p>
                    Summary of registered users,
                    jobs and applications.
                </p>

            </div>

        </div>


        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-title">
                    Total Users
                </div>
                <div class="stat-number">
                    <?= number_format($totalUsers) ?>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-title">
                    Employers
                </div>
                <div class="stat-number">
                    <?= number_format($totalEmployers) ?>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-title">
                    Applicants
                </div>
                <div class="stat-number">
                    <?= number_format($totalApplicants) ?>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-title">
                    Jobs
                </div>
                <div class="stat-number">
                    <?= number_format($totalJobs) ?>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-title">
                    Applications
                </div>
                <div class="stat-number">
                    <?= number_format($totalApplications) ?>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-title">
                    Pending Jobs
                </div>
                <div class="stat-number">
                    <?= number_format($pendingJobs) ?>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-title">
                    Approved Jobs
                </div>
                <div class="stat-number">
                    <?= number_format($approvedJobs) ?>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-title">
                    Rejected Jobs
                </div>
                <div class="stat-number">
                    <?= number_format($rejectedJobs) ?>
                </div>
            </div>


            <div class="stat-card">
                <div class="stat-title">
                    Closed Jobs
                </div>
                <div class="stat-number">
                    <?= number_format($closedJobs) ?>
                </div>
            </div>

        </div>

    </div>


    <!-- =====================================================
         EMPLOYER / SOURCE REPORT
    ====================================================== -->

    <div class="section-card">

        <div class="section-header">

            <div>

                <h2>
                    Employer and Job Source Summary
                </h2>

                <p>
                    Distribution of Private Employer
                    and LGU accounts and job postings.
                </p>

            </div>

        </div>


        <div class="stats-grid">

            <div class="stat-card">

                <div class="stat-title">
                    Private Employers
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $totalPrivateEmployers
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    LGU Employers
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $totalLguEmployers
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Private Employer Jobs
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $privateJobs
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    LGU Jobs
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $lguJobs
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Job Fair Jobs
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $jobFairJobs
                    ) ?>
                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         EMPLOYER ACCOUNT REPORT
    ====================================================== -->

    <div class="section-card">

        <div class="section-header">

            <div>

                <h2>
                    Employer Account Report
                </h2>

                <p>
                    Registered employer account status
                    and employer classification.
                </p>

            </div>

        </div>


        <div class="report-note">

            Active:
            <?= number_format(
                $employerStatusCounts["active"]
            ) ?>

            &nbsp; | &nbsp;

            Inactive:
            <?= number_format(
                $employerStatusCounts["inactive"]
            ) ?>

            &nbsp; | &nbsp;

            Deactivated:
            <?= number_format(
                $employerStatusCounts["deactivated"]
            ) ?>

        </div>


        <form
            method="GET"
            class="filter-form"
        >

            <div class="form-group">

                <label>
                    Account Status
                </label>

                <select
                    name="employer_account_status"
                >

                    <option value="">
                        All Statuses
                    </option>

                    <option
                        value="active"
                        <?= $employerAccountFilter === "active"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $employerAccountFilter === "inactive"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Inactive
                    </option>

                    <option
                        value="deactivated"
                        <?= $employerAccountFilter === "deactivated"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Deactivated
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Employer Type
                </label>

                <select
                    name="employer_type"
                >

                    <option value="">
                        All Employer Types
                    </option>

                    <option
                        value="private"
                        <?= $employerTypeFilter === "private"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Private Employer
                    </option>

                    <option
                        value="lgu"
                        <?= $employerTypeFilter === "lgu"
                            ? "selected"
                            : ""
                        ?>
                    >
                        LGU
                    </option>

                </select>

            </div>


            <!-- PRESERVE OTHER FILTERS -->

            <input
                type="hidden"
                name="applicant_account_status"
                value="<?= htmlspecialchars(
                    $applicantAccountFilter
                ) ?>"
            >

            <input
                type="hidden"
                name="date_from"
                value="<?= htmlspecialchars(
                    $dateFrom
                ) ?>"
            >

            <input
                type="hidden"
                name="date_to"
                value="<?= htmlspecialchars(
                    $dateTo
                ) ?>"
            >

            <input
                type="hidden"
                name="employer_id"
                value="<?= (int) $employerId ?>"
            >

            <input
                type="hidden"
                name="job_id"
                value="<?= (int) $jobId ?>"
            >

            <input
                type="hidden"
                name="status"
                value="<?= htmlspecialchars(
                    $applicationStatus
                ) ?>"
            >

            <input
                type="hidden"
                name="job_source"
                value="<?= htmlspecialchars(
                    $jobSourceFilter
                ) ?>"
            >


            <div class="filter-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply
                </button>

                <a
                    href="reports.php"
                    class="btn btn-gray"
                >
                    Reset
                </a>

            </div>

        </form>


        <br>


        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Employer</th>
                        <th>Type</th>
                        <th>Email</th>
                        <th>Jobs</th>
                        <th>Verification</th>
                        <th>Account Status</th>
                        <th>Registered</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (
                    empty(
                        $employerAccounts
                    )
                ): ?>

                    <tr>
                        <td
                            colspan="7"
                            class="empty"
                        >
                            No employer accounts found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $employerAccounts
                        as
                        $account
                    ): ?>

                        <?php
                        $accountStatus =
                            normalizeAccountStatus(
                                $account[
                                    "account_status"
                                ]
                                ?? null
                            );

                        $type =
                            $account[
                                "employer_type"
                            ]
                            ?? "private";

                        $verificationStatus =
                            $account[
                                "verification_status"
                            ]
                            ?? "pending";
                        ?>

                        <tr>

                            <td>

                                <div class="name">
                                    <?= htmlspecialchars(
                                        $account[
                                            "company_name"
                                        ]
                                    ) ?>
                                </div>

                            </td>


                            <td>

                                <span
                                    class="type-badge <?= $type === "lgu"
                                        ? "type-lgu"
                                        : "type-private"
                                    ?>"
                                >
                                    <?= htmlspecialchars(
                                        getEmployerTypeLabel(
                                            $type
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $account["email"]
                                    ?? "-"
                                ) ?>
                            </td>


                            <td>
                                <?= number_format(
                                    (int)
                                    $account[
                                        "total_jobs"
                                    ]
                                ) ?>
                            </td>


                            <td>

                                <span
                                    class="verification verification-<?= htmlspecialchars(
                                        $verificationStatus
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $verificationStatus
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <span
                                    class="account-badge <?= htmlspecialchars(
                                        getAccountStatusClass(
                                            $accountStatus
                                        )
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $accountStatus
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <?= !empty(
                                    $account["created_at"]
                                )
                                    ? date(
                                        "M d, Y",
                                        strtotime(
                                            $account[
                                                "created_at"
                                            ]
                                        )
                                    )
                                    : "-"
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         APPLICANT ACCOUNT REPORT
    ====================================================== -->

    <div class="section-card">

        <div class="section-header">

            <div>

                <h2>
                    Applicant Account Report
                </h2>

                <p>
                    Registered applicant accounts
                    and application activity.
                </p>

            </div>

        </div>


        <div class="report-note">

            Active:
            <?= number_format(
                $applicantStatusCounts["active"]
            ) ?>

            &nbsp; | &nbsp;

            Inactive:
            <?= number_format(
                $applicantStatusCounts["inactive"]
            ) ?>

            &nbsp; | &nbsp;

            Deactivated:
            <?= number_format(
                $applicantStatusCounts[
                    "deactivated"
                ]
            ) ?>

        </div>


        <form
            method="GET"
            class="filter-form"
        >

            <div class="form-group">

                <label>
                    Applicant Account Status
                </label>

                <select
                    name="applicant_account_status"
                >

                    <option value="">
                        All Statuses
                    </option>

                    <option
                        value="active"
                        <?= $applicantAccountFilter === "active"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Active
                    </option>

                    <option
                        value="inactive"
                        <?= $applicantAccountFilter === "inactive"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Inactive
                    </option>

                    <option
                        value="deactivated"
                        <?= $applicantAccountFilter === "deactivated"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Deactivated
                    </option>

                </select>

            </div>


            <!-- PRESERVE OTHER FILTERS -->

            <input
                type="hidden"
                name="employer_account_status"
                value="<?= htmlspecialchars(
                    $employerAccountFilter
                ) ?>"
            >

            <input
                type="hidden"
                name="employer_type"
                value="<?= htmlspecialchars(
                    $employerTypeFilter
                ) ?>"
            >

            <input
                type="hidden"
                name="date_from"
                value="<?= htmlspecialchars(
                    $dateFrom
                ) ?>"
            >

            <input
                type="hidden"
                name="date_to"
                value="<?= htmlspecialchars(
                    $dateTo
                ) ?>"
            >

            <input
                type="hidden"
                name="employer_id"
                value="<?= (int) $employerId ?>"
            >

            <input
                type="hidden"
                name="job_id"
                value="<?= (int) $jobId ?>"
            >

            <input
                type="hidden"
                name="status"
                value="<?= htmlspecialchars(
                    $applicationStatus
                ) ?>"
            >

            <input
                type="hidden"
                name="job_source"
                value="<?= htmlspecialchars(
                    $jobSourceFilter
                ) ?>"
            >


            <div class="filter-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply
                </button>

                <a
                    href="reports.php"
                    class="btn btn-gray"
                >
                    Reset
                </a>

            </div>

        </form>


        <br>


        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Applicant</th>
                        <th>Email</th>
                        <th>Applications</th>
                        <th>Account Status</th>
                        <th>Registered</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (
                    empty(
                        $applicantAccounts
                    )
                ): ?>

                    <tr>
                        <td
                            colspan="5"
                            class="empty"
                        >
                            No applicant accounts found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $applicantAccounts
                        as
                        $account
                    ): ?>

                        <?php
                        $accountStatus =
                            normalizeAccountStatus(
                                $account[
                                    "account_status"
                                ]
                                ?? null
                            );
                        ?>

                        <tr>

                            <td>

                                <div class="name">

                                    <?= htmlspecialchars(
                                        trim(
                                            (
                                                $account[
                                                    "first_name"
                                                ]
                                                ?? ""
                                            )
                                            . " "
                                            . (
                                                $account[
                                                    "last_name"
                                                ]
                                                ?? ""
                                            )
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <td>
                                <?= htmlspecialchars(
                                    $account["email"]
                                    ?? "-"
                                ) ?>
                            </td>


                            <td>
                                <?= number_format(
                                    (int)
                                    $account[
                                        "total_applications"
                                    ]
                                ) ?>
                            </td>


                            <td>

                                <span
                                    class="account-badge <?= htmlspecialchars(
                                        getAccountStatusClass(
                                            $accountStatus
                                        )
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $accountStatus
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <?= !empty(
                                    $account["created_at"]
                                )
                                    ? date(
                                        "M d, Y",
                                        strtotime(
                                            $account[
                                                "created_at"
                                            ]
                                        )
                                    )
                                    : "-"
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>


    <!-- =====================================================
         APPLICATION REPORT FILTERS
    ====================================================== -->

    <div class="section-card">

        <div class="section-header">

            <div>

                <h2>
                    Application Report Filters
                </h2>

                <p>
                    Filter applications by date,
                    employer, job, job source
                    and application status.
                </p>

            </div>

        </div>


        <form
            method="GET"
            class="filter-form"
        >

            <div class="form-group">

                <label for="date_from">
                    Date From
                </label>

                <input
                    type="date"
                    name="date_from"
                    id="date_from"
                    value="<?= htmlspecialchars(
                        $dateFrom
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label for="date_to">
                    Date To
                </label>

                <input
                    type="date"
                    name="date_to"
                    id="date_to"
                    value="<?= htmlspecialchars(
                        $dateTo
                    ) ?>"
                >

            </div>


            <div class="form-group">

                <label for="employer_id">
                    Employer
                </label>

                <select
                    name="employer_id"
                    id="employer_id"
                >

                    <option value="0">
                        All Employers
                    </option>

                    <?php foreach (
                        $employers
                        as
                        $employer
                    ): ?>

                        <option
                            value="<?= (int)
                                $employer["id"]
                            ?>"
                            <?= $employerId ===
                                (int)
                                $employer["id"]
                                ? "selected"
                                : ""
                            ?>
                        >
                            <?= htmlspecialchars(
                                $employer[
                                    "company_name"
                                ]
                            ) ?>

                            (
                            <?= htmlspecialchars(
                                getEmployerTypeLabel(
                                    $employer[
                                        "employer_type"
                                    ]
                                    ?? "private"
                                )
                            ) ?>
                            )

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="job_id">
                    Job
                </label>

                <select
                    name="job_id"
                    id="job_id"
                >

                    <option value="0">
                        All Jobs
                    </option>

                    <?php foreach (
                        $jobs
                        as
                        $job
                    ): ?>

                        <option
                            value="<?= (int)
                                $job["id"]
                            ?>"
                            <?= $jobId ===
                                (int)
                                $job["id"]
                                ? "selected"
                                : ""
                            ?>
                        >
                            <?= htmlspecialchars(
                                $job["job_title"]
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label for="job_source">
                    Job Source
                </label>

                <select
                    name="job_source"
                    id="job_source"
                >

                    <option value="">
                        All Sources
                    </option>

                    <option
                        value="private_employer"
                        <?= $jobSourceFilter ===
                            "private_employer"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Private Employer
                    </option>

                    <option
                        value="lgu"
                        <?= $jobSourceFilter === "lgu"
                            ? "selected"
                            : ""
                        ?>
                    >
                        LGU
                    </option>

                    <option
                        value="job_fair"
                        <?= $jobSourceFilter ===
                            "job_fair"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Job Fair
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label for="status">
                    Application Status
                </label>

                <select
                    name="status"
                    id="status"
                >

                    <option value="">
                        All Statuses
                    </option>

                    <option
                        value="pending"
                        <?= $applicationStatus ===
                            "pending"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Pending
                    </option>

                    <option
                        value="reviewed"
                        <?= $applicationStatus ===
                            "reviewed"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Reviewed
                    </option>

                    <option
                        value="shortlisted"
                        <?= $applicationStatus ===
                            "shortlisted"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Shortlisted
                    </option>

                    <option
                        value="rejected"
                        <?= $applicationStatus ===
                            "rejected"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Rejected
                    </option>

                    <option
                        value="hired"
                        <?= $applicationStatus ===
                            "hired"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Hired
                    </option>

                </select>

            </div>


            <!-- PRESERVE ACCOUNT FILTERS -->

            <input
                type="hidden"
                name="employer_account_status"
                value="<?= htmlspecialchars(
                    $employerAccountFilter
                ) ?>"
            >

            <input
                type="hidden"
                name="applicant_account_status"
                value="<?= htmlspecialchars(
                    $applicantAccountFilter
                ) ?>"
            >

            <input
                type="hidden"
                name="employer_type"
                value="<?= htmlspecialchars(
                    $employerTypeFilter
                ) ?>"
            >


            <div class="filter-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Apply Filters
                </button>

                <a
                    href="reports.php"
                    class="btn btn-gray"
                >
                    Reset
                </a>

            </div>

        </form>

    </div>


    <!-- =====================================================
         APPLICATION SUMMARY
    ====================================================== -->

    <div class="section-card">

        <div class="section-header">

            <div>

                <h2>
                    Application Summary
                </h2>

                <p>
                    Summary is based on the
                    currently filtered applications.
                </p>

            </div>

        </div>


        <div class="stats-grid">

            <div class="stat-card">

                <div class="stat-title">
                    Applications
                </div>

                <div class="stat-number">
                    <?= number_format(
                        count($applications)
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Pending
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $applicationStatusCounts[
                            "pending"
                        ]
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Reviewed
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $applicationStatusCounts[
                            "reviewed"
                        ]
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Shortlisted
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $applicationStatusCounts[
                            "shortlisted"
                        ]
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Hired
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $applicationStatusCounts[
                            "hired"
                        ]
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Rejected
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $applicationStatusCounts[
                            "rejected"
                        ]
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Average Match
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $averageMatchScore,
                        1
                    ) ?>%
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Strong Matches
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $strongMatch
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Good Matches
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $goodMatch
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Fair Matches
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $fairMatch
                    ) ?>
                </div>

            </div>


            <div class="stat-card">

                <div class="stat-title">
                    Low Matches
                </div>

                <div class="stat-number">
                    <?= number_format(
                        $lowMatch
                    ) ?>
                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         APPLICATION REPORT
    ====================================================== -->

    <div class="section-card">

        <div class="section-header">

            <div>

                <h2>
                    Application Report
                </h2>

                <p>
                    <?= number_format(
                        count($applications)
                    ) ?>
                    application(s) found.
                </p>

            </div>

        </div>


        <div class="table-container">

            <table>

                <thead>

                    <tr>
                        <th>Applicant</th>
                        <th>Job</th>
                        <th>Employer</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Match</th>
                        <th>Skills</th>
                        <th>Applied</th>
                    </tr>

                </thead>

                <tbody>

                <?php if (
                    empty(
                        $applications
                    )
                ): ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty"
                        >
                            No applications found
                            for the selected filters.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach (
                        $applications
                        as
                        $application
                    ): ?>

                        <?php
                        $score =
                            $application[
                                "match_score"
                            ];

                        $source =
                            $application[
                                "job_source"
                            ]
                            ?? "private_employer";

                        $sourceClass =
                            match ($source) {
                                "lgu" =>
                                    "source-lgu",

                                "job_fair" =>
                                    "source-job-fair",

                                default =>
                                    "source-private"
                            };
                        ?>

                        <tr>

                            <td>

                                <div class="name">

                                    <?= htmlspecialchars(
                                        trim(
                                            $application[
                                                "first_name"
                                            ]
                                            . " "
                                            . $application[
                                                "last_name"
                                            ]
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <div class="name">
                                    <?= htmlspecialchars(
                                        $application[
                                            "job_title"
                                        ]
                                    ) ?>
                                </div>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $application[
                                        "company_name"
                                    ]
                                ) ?>

                                <div class="secondary">

                                    <?= htmlspecialchars(
                                        getEmployerTypeLabel(
                                            $application[
                                                "employer_type"
                                            ]
                                            ?? "private"
                                        )
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="source-badge <?= $sourceClass ?>"
                                >
                                    <?= htmlspecialchars(
                                        getJobSourceLabel(
                                            $source
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <span
                                    class="status-badge <?= statusBadgeClass(
                                        $application[
                                            "status"
                                        ]
                                        ?? "pending"
                                    ) ?>"
                                >
                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $application[
                                                "status"
                                            ]
                                            ?? "pending"
                                        )
                                    ) ?>
                                </span>

                            </td>


                            <td>

                                <?php if (
                                    $score !== null
                                ): ?>

                                    <?php
                                    $numericScore =
                                        max(
                                            0,
                                            min(
                                                100,
                                                (float) $score
                                            )
                                        );
                                    ?>

                                    <div class="match-score">

                                        <?= number_format(
                                            $numericScore,
                                            1
                                        ) ?>%

                                    </div>

                                    <span
                                        class="match-badge <?= getMatchClass(
                                            $numericScore
                                        ) ?>"
                                    >
                                        <?= htmlspecialchars(
                                            getMatchLabel(
                                                $numericScore
                                            )
                                        ) ?>
                                    </span>

                                <?php else: ?>

                                    <span class="secondary">
                                        Not calculated
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (
                                    $application[
                                        "skills_score"
                                    ] !== null
                                ): ?>

                                    <?= number_format(
                                        (float)
                                        $application[
                                            "skills_score"
                                        ],
                                        1
                                    ) ?>%

                                <?php else: ?>

                                    -

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= !empty(
                                    $application[
                                        "applied_at"
                                    ]
                                )
                                    ? date(
                                        "M d, Y h:i A",
                                        strtotime(
                                            $application[
                                                "applied_at"
                                            ]
                                        )
                                    )
                                    : "-"
                                ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</div>


</body>
</html>