<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| ADMIN ONLY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "admin"
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

    if (
        !in_array(
            $table,
            $allowedTables,
            true
        )
    ) {
        return [];
    }

    try {

        $stmt = $pdo->query(
            "SHOW COLUMNS FROM `$table`"
        );

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    } catch (PDOException $e) {

        return [];
    }
}


/*
|--------------------------------------------------------------------------
| DETECT ACCOUNT STATUS COLUMN
|--------------------------------------------------------------------------
*/

$userColumns =
    getTableColumns(
        $pdo,
        "users"
    );


$userColumnNames = [];


foreach (
    $userColumns
    as
    $column
) {

    if (
        !empty(
            $column["Field"]
        )
    ) {

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


foreach (
    $statusCandidates
    as
    $candidate
) {

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

    /*
    |--------------------------------------------------------------------------
    | NO STATUS COLUMN
    |--------------------------------------------------------------------------
    */

    if ($column === null) {

        return "'active'";
    }


    /*
    |--------------------------------------------------------------------------
    | BOOLEAN STATUS
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | TEXT STATUS
    |--------------------------------------------------------------------------
    */

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
| APPLICATION FILTERS
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


/*
|--------------------------------------------------------------------------
| ACCOUNT FILTERS
|--------------------------------------------------------------------------
*/

$employerAccountFilter =
    trim(
        $_GET[
            "employer_account_status"
        ]
        ?? ""
    );


$applicantAccountFilter =
    trim(
        $_GET[
            "applicant_account_status"
        ]
        ?? ""
    );


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


/*
|--------------------------------------------------------------------------
| EMPLOYER FILTER DATA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        id,
        company_name

    FROM employers

    ORDER BY
        company_name ASC
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
        job_title

    FROM jobs

    ORDER BY
        job_title ASC
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
        ")
        ->fetchColumn();


$totalEmployers =
    (int) $pdo
        ->query("
            SELECT COUNT(*)
            FROM employers
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

        u.id AS user_id,
        u.email,

        $accountStatusExpression
            AS account_status

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

        u.id AS user_id,
        u.email,

        $accountStatusExpression
            AS account_status

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
| ACCOUNT STATUS COUNTS
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

    $normalizedStatus =
        normalizeAccountStatus(
            $account[
                "account_status"
            ]
            ?? null
        );


    $employerStatusCounts[
        $normalizedStatus
    ]++;
}


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

    $normalizedStatus =
        normalizeAccountStatus(
            $account[
                "account_status"
            ]
            ?? null
        );


    $applicantStatusCounts[
        $normalizedStatus
    ]++;
}


/*
|--------------------------------------------------------------------------
| FILTER EMPLOYER ACCOUNT TABLE
|--------------------------------------------------------------------------
*/

$employerAccounts =
    $allEmployerAccounts;


if (
    $employerAccountFilter !== ""
) {

    $employerAccounts =
        array_values(
            array_filter(
                $allEmployerAccounts,

                function (
                    $account
                ) use (
                    $employerAccountFilter
                ) {

                    return
                        normalizeAccountStatus(
                            $account[
                                "account_status"
                            ]
                            ?? null
                        )
                        ===
                        $employerAccountFilter;
                }
            )
        );
}


/*
|--------------------------------------------------------------------------
| FILTER APPLICANT ACCOUNT TABLE
|--------------------------------------------------------------------------
*/

$applicantAccounts =
    $allApplicantAccounts;


if (
    $applicantAccountFilter !== ""
) {

    $applicantAccounts =
        array_values(
            array_filter(
                $allApplicantAccounts,

                function (
                    $account
                ) use (
                    $applicantAccountFilter
                ) {

                    return
                        normalizeAccountStatus(
                            $account[
                                "account_status"
                            ]
                            ?? null
                        )
                        ===
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


$whereSql = "";


if (
    count(
        $conditions
    ) > 0
) {

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

        e.id AS employer_id,
        e.company_name,

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


$stmt->execute(
    $params
);


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


foreach (
    $applications
    as
    $application
) {

    $statusValue =
        strtolower(
            $application["status"]
            ?? ""
        );


    if (
        isset(
            $applicationStatusCounts[
                $statusValue
            ]
        )
    ) {

        $applicationStatusCounts[
            $statusValue
        ]++;
    }


    if (
        $application[
            "match_score"
        ] !== null
    ) {

        $score =
            (float) $application[
                "match_score"
            ];


        $score =
            max(
                0,
                min(
                    100,
                    $score
                )
            );


        $totalMatchScore +=
            $score;

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
        ? $totalMatchScore /
            $matchCount
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


function getApplicationStatusClass(
    string $status
): string {

    return
        "status-"
        . strtolower(
            $status
        );
}


function getAccountStatusClass(
    string $status
): string {

    if ($status === "active") {

        return "account-active";
    }


    if ($status === "deactivated") {

        return "account-deactivated";
    }


    return "account-inactive";
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
    Manage Reports - Job Portal Admin
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
        #222;
}


/*
|--------------------------------------------------------------------------
| NAVBAR
|--------------------------------------------------------------------------
*/

.navbar {

    background:
        #111827;

    min-height:
        80px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;
}


.navbar-inner {

    width:
        100%;

    max-width:
        1320px;

    padding:
        0 20px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        30px;
}


.logo {

    color:
        white;

    font-size:
        24px;

    font-weight:
        bold;

    white-space:
        nowrap;
}


.nav-links {

    display:
        flex;

    align-items:
        center;

    gap:
        24px;
}


.nav-links a {

    color:
        white;

    text-decoration:
        none;

    font-size:
        15px;

    white-space:
        nowrap;
}


.nav-links a:hover,
.nav-links a.active {

    color:
        #93c5fd;
}


.nav-links a.active {

    font-weight:
        bold;
}


.nav-links .logout {

    background:
        #ef2b2d;

    color:
        white;

    padding:
        11px 18px;

    border-radius:
        5px;
}


.nav-links .logout:hover {

    background:
        #dc2626;

    color:
        white;
}


/*
|--------------------------------------------------------------------------
| CONTAINER
|--------------------------------------------------------------------------
*/

.container {

    max-width:
        1320px;

    margin:
        40px auto;

    padding:
        0 20px 60px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.page-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;

    margin-bottom:
        28px;
}


.page-header h1 {

    font-size:
        36px;

    margin-bottom:
        8px;
}


.page-header p {

    color:
        #666;

    font-size:
        17px;
}


.print-btn {

    border:
        none;

    background:
        #111827;

    color:
        white;

    padding:
        12px 20px;

    border-radius:
        6px;

    cursor:
        pointer;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
*/

.summary-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            4,
            minmax(
                0,
                1fr
            )
        );

    gap:
        22px;
}


.summary-card {

    background:
        white;

    border:
        1px solid #dfe3e8;

    border-radius:
        9px;

    padding:
        28px;

    min-height:
        125px;
}


.summary-title {

    color:
        #555;

    font-size:
        15px;

    font-weight:
        600;

    margin-bottom:
        12px;
}


.summary-number {

    font-size:
        32px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| SECTION
|--------------------------------------------------------------------------
*/

.section-card {

    background:
        white;

    border:
        1px solid #dfe3e8;

    border-radius:
        9px;

    padding:
        28px;

    margin-top:
        28px;
}


.section-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        flex-start;

    gap:
        20px;

    margin-bottom:
        22px;
}


.section-header h2 {

    font-size:
        24px;

    margin-bottom:
        6px;
}


.section-header p {

    color:
        #666;

    font-size:
        14px;
}


/*
|--------------------------------------------------------------------------
| ACCOUNT STATUS SUMMARY
|--------------------------------------------------------------------------
*/

.account-summary-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap:
        20px;
}


.account-group {

    background:
        #f8fafc;

    border:
        1px solid #dfe3e8;

    border-radius:
        8px;

    padding:
        22px;
}


.account-group h3 {

    margin-bottom:
        18px;
}


.account-stats {

    display:
        grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap:
        12px;
}


.account-stat {

    background:
        white;

    border:
        1px solid #e5e7eb;

    border-radius:
        7px;

    padding:
        16px;
}


.account-stat-label {

    color:
        #666;

    font-size:
        12px;

    margin-bottom:
        7px;
}


.account-stat-number {

    font-size:
        25px;

    font-weight:
        bold;
}


.text-active {

    color:
        #15803d;
}


.text-inactive {

    color:
        #b45309;
}


.text-deactivated {

    color:
        #dc2626;
}


/*
|--------------------------------------------------------------------------
| TABLE TOOLBAR
|--------------------------------------------------------------------------
*/

.table-toolbar {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        flex-end;

    gap:
        15px;

    margin-bottom:
        18px;

    padding:
        15px;

    background:
        #f8fafc;

    border:
        1px solid #e5e7eb;

    border-radius:
        7px;
}


.toolbar-left {

    display:
        flex;

    align-items:
        flex-end;

    gap:
        10px;

    flex-wrap:
        wrap;
}


.result-count {

    color:
        #666;

    font-size:
        13px;

    padding-bottom:
        12px;
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-group label {

    display:
        block;

    margin-bottom:
        7px;

    font-size:
        13px;

    font-weight:
        bold;
}


.form-control {

    height:
        44px;

    min-width:
        220px;

    padding:
        0 12px;

    border:
        1px solid #cfd5dc;

    border-radius:
        6px;

    background:
        white;

    font-size:
        14px;
}


.form-control:focus {

    outline:
        none;

    border-color:
        #2563eb;
}


.filter-btn {

    height:
        44px;

    padding:
        0 18px;

    border:
        none;

    border-radius:
        5px;

    background:
        #2563eb;

    color:
        white;

    font-weight:
        bold;

    cursor:
        pointer;
}


.filter-btn:hover {

    background:
        #1d4ed8;
}


.clear-btn {

    height:
        44px;

    padding:
        0 18px;

    display:
        inline-flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #e5e7eb;

    color:
        #333;

    border-radius:
        5px;

    font-size:
        14px;

    font-weight:
        bold;

    text-decoration:
        none;
}


/*
|--------------------------------------------------------------------------
| APPLICATION FILTERS
|--------------------------------------------------------------------------
*/

.application-filter-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            5,
            minmax(
                0,
                1fr
            )
        );

    gap:
        15px;
}


.application-filter-grid
.form-control {

    min-width:
        0;

    width:
        100%;
}


.filter-actions {

    display:
        flex;

    gap:
        12px;

    margin-top:
        18px;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {

    width:
        100%;

    overflow-x:
        auto;
}


.report-table {

    width:
        100%;

    border-collapse:
        collapse;

    min-width:
        950px;
}


.report-table th {

    background:
        #f8fafc;

    border-bottom:
        1px solid #dfe3e8;

    padding:
        14px 12px;

    text-align:
        left;

    font-size:
        13px;

    white-space:
        nowrap;
}


.report-table td {

    padding:
        14px 12px;

    border-bottom:
        1px solid #eceff3;

    font-size:
        13px;

    color:
        #444;

    vertical-align:
        top;
}


.report-table tbody tr:hover {

    background:
        #fafafa;
}


.name {

    font-weight:
        bold;

    color:
        #111;
}


.skill-text {

    max-width:
        250px;

    color:
        #666;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| ACCOUNT BADGES
|--------------------------------------------------------------------------
*/

.account-badge {

    display:
        inline-block;

    padding:
        6px 11px;

    border-radius:
        20px;

    font-size:
        11px;

    font-weight:
        bold;

    text-transform:
        capitalize;
}


.account-active {

    background:
        #dcfce7;

    color:
        #166534;
}


.account-inactive {

    background:
        #fef3c7;

    color:
        #92400e;
}


.account-deactivated {

    background:
        #fee2e2;

    color:
        #991b1b;
}


/*
|--------------------------------------------------------------------------
| APPLICATION STATUS
|--------------------------------------------------------------------------
*/

.status {

    display:
        inline-block;

    padding:
        6px 10px;

    border-radius:
        20px;

    font-size:
        11px;

    font-weight:
        bold;

    text-transform:
        capitalize;
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


/*
|--------------------------------------------------------------------------
| STATUS / MATCH SUMMARY
|--------------------------------------------------------------------------
*/

.status-grid,
.match-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            5,
            minmax(
                0,
                1fr
            )
        );

    gap:
        14px;
}


.status-card,
.match-card {

    background:
        #f8fafc;

    border:
        1px solid #dfe3e8;

    border-radius:
        8px;

    padding:
        20px;
}


.status-label,
.match-label {

    color:
        #6b7280;

    font-size:
        13px;

    margin-bottom:
        9px;
}


.status-number,
.match-number {

    font-size:
        28px;

    font-weight:
        bold;
}


.match-strong-text {

    color:
        #15803d;
}


.match-good-text {

    color:
        #2563eb;
}


.match-fair-text {

    color:
        #b45309;
}


.match-low-text {

    color:
        #dc2626;
}


/*
|--------------------------------------------------------------------------
| MATCH BADGES
|--------------------------------------------------------------------------
*/

.match-badge {

    display:
        inline-block;

    padding:
        6px 10px;

    border-radius:
        20px;

    font-size:
        11px;

    font-weight:
        bold;

    white-space:
        nowrap;
}


.match-strong {

    background:
        #dcfce7;

    color:
        #166534;
}


.match-good {

    background:
        #dbeafe;

    color:
        #1d4ed8;
}


.match-fair {

    background:
        #fef3c7;

    color:
        #92400e;
}


.match-low {

    background:
        #fee2e2;

    color:
        #991b1b;
}


/*
|--------------------------------------------------------------------------
| NOTICE
|--------------------------------------------------------------------------
*/

.notice {

    padding:
        13px 15px;

    margin-bottom:
        20px;

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
        1.5;
}


.empty-state {

    padding:
        40px 20px;

    text-align:
        center;

    color:
        #666;
}


.print-header {

    display:
        none;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 1100px
) {

    .navbar-inner {

        flex-direction:
            column;

        align-items:
            flex-start;

        padding-top:
            18px;

        padding-bottom:
            18px;
    }


    .nav-links {

        flex-wrap:
            wrap;
    }


    .summary-grid {

        grid-template-columns:
            repeat(
                2,
                1fr
            );
    }


    .account-summary-grid {

        grid-template-columns:
            1fr;
    }


    .application-filter-grid {

        grid-template-columns:
            repeat(
                2,
                1fr
            );
    }


    .status-grid,
    .match-grid {

        grid-template-columns:
            repeat(
                2,
                1fr
            );
    }
}


@media (
    max-width: 650px
) {

    .container {

        margin:
            25px auto;

        padding:
            0 15px 40px;
    }


    .page-header,
    .section-header,
    .table-toolbar {

        flex-direction:
            column;

        align-items:
            stretch;
    }


    .page-header h1 {

        font-size:
            28px;
    }


    .summary-grid,
    .account-stats,
    .application-filter-grid,
    .status-grid,
    .match-grid {

        grid-template-columns:
            1fr;
    }


    .toolbar-left,
    .filter-actions {

        flex-direction:
            column;

        align-items:
            stretch;
    }


    .form-control,
    .filter-btn,
    .clear-btn {

        width:
            100%;

        min-width:
            0;
    }
}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    .navbar,
    .print-btn,
    .table-toolbar,
    .application-filter-section {

        display:
            none !important;
    }


    body {

        background:
            white;
    }


    .container {

        max-width:
            none;

        width:
            100%;

        margin:
            0;

        padding:
            0;
    }


    .page-header {

        display:
            none;
    }


    .print-header {

        display:
            block;

        text-align:
            center;

        margin-bottom:
            20px;
    }


    .summary-card,
    .section-card {

        break-inside:
            avoid;
    }


    .report-table {

        min-width:
            100%;
    }
}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <div class="navbar-inner">


        <div class="logo">

            Job Portal Admin

        </div>


        <div class="nav-links">


            <a href="dashboard.php">
                Dashboard
            </a>


            <a href="jobs.php">
                Manage Jobs
            </a>


            <a href="employers.php">
                Manage Employers
            </a>


            <a href="applicants.php">
                Manage Applicants
            </a>


            <a
                href="reports.php"
                class="active"
            >
                Manage Reports
            </a>


            <a
                href="../logout.php"
                class="logout"
            >
                Logout
            </a>


        </div>

    </div>

</nav>


<div class="container">


    <!-- PRINT HEADER -->

    <div class="print-header">

        <h1>
            Caloocan Job Portal
        </h1>

        <p>
            Administrative Reports
        </p>

        <p>
            Generated:
            <?= date(
                "F d, Y h:i A"
            ) ?>
        </p>

    </div>


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">


        <div>

            <h1>
                Reports & Analytics
            </h1>

            <p>
                Monitor jobs, accounts,
                applications and resume
                matching results.
            </p>

        </div>


        <button
            type="button"
            class="print-btn"
            onclick="window.print();"
        >
            Print Report
        </button>


    </div>


    <!-- =====================================================
         GENERAL SUMMARY
    ====================================================== -->

    <div class="summary-grid">


        <div class="summary-card">

            <div class="summary-title">
                Total Users
            </div>

            <div class="summary-number">
                <?= number_format(
                    $totalUsers
                ) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Employers
            </div>

            <div class="summary-number">
                <?= number_format(
                    $totalEmployers
                ) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Applicants
            </div>

            <div class="summary-number">
                <?= number_format(
                    $totalApplicants
                ) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Total Jobs
            </div>

            <div class="summary-number">
                <?= number_format(
                    $totalJobs
                ) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Pending Jobs
            </div>

            <div class="summary-number">
                <?= number_format(
                    $pendingJobs
                ) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Approved Jobs
            </div>

            <div class="summary-number">
                <?= number_format(
                    $approvedJobs
                ) ?>
            </div>

        </div>


        <div class="summary-card">

            <div class="summary-title">
                Applications
            </div>

            <div class="summary-number">
                <?= number_format(
                    $totalApplications
                ) ?>
            </div>

        </div>


    </div>


    <!-- =====================================================
         ACCOUNT STATUS SUMMARY
    ====================================================== -->

    <div class="section-card">


        <div class="section-header">

            <div>

                <h2>
                    Account Status Summary
                </h2>

                <p>
                    Active, inactive and
                    deactivated accounts.
                </p>

            </div>

        </div>


        <?php if (
            $statusColumn === null
        ): ?>


            <div class="notice">

                No account status column was
                detected in the users table.

                All accounts are temporarily
                interpreted as Active.

            </div>


        <?php endif; ?>


        <div class="account-summary-grid">


            <div class="account-group">


                <h3>
                    Employer Accounts
                </h3>


                <div class="account-stats">


                    <div class="account-stat">

                        <div class="account-stat-label">
                            Active
                        </div>

                        <div class="
                            account-stat-number
                            text-active
                        ">

                            <?= $employerStatusCounts[
                                "active"
                            ] ?>

                        </div>

                    </div>


                    <div class="account-stat">

                        <div class="account-stat-label">
                            Inactive
                        </div>

                        <div class="
                            account-stat-number
                            text-inactive
                        ">

                            <?= $employerStatusCounts[
                                "inactive"
                            ] ?>

                        </div>

                    </div>


                    <div class="account-stat">

                        <div class="account-stat-label">
                            Deactivated
                        </div>

                        <div class="
                            account-stat-number
                            text-deactivated
                        ">

                            <?= $employerStatusCounts[
                                "deactivated"
                            ] ?>

                        </div>

                    </div>


                </div>

            </div>


            <div class="account-group">


                <h3>
                    Applicant Accounts
                </h3>


                <div class="account-stats">


                    <div class="account-stat">

                        <div class="account-stat-label">
                            Active
                        </div>

                        <div class="
                            account-stat-number
                            text-active
                        ">

                            <?= $applicantStatusCounts[
                                "active"
                            ] ?>

                        </div>

                    </div>


                    <div class="account-stat">

                        <div class="account-stat-label">
                            Inactive
                        </div>

                        <div class="
                            account-stat-number
                            text-inactive
                        ">

                            <?= $applicantStatusCounts[
                                "inactive"
                            ] ?>

                        </div>

                    </div>


                    <div class="account-stat">

                        <div class="account-stat-label">
                            Deactivated
                        </div>

                        <div class="
                            account-stat-number
                            text-deactivated
                        ">

                            <?= $applicantStatusCounts[
                                "deactivated"
                            ] ?>

                        </div>

                    </div>


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
                    Registered employer
                    account status.
                </p>

            </div>

        </div>


        <!-- EMPLOYER FILTER -->

        <div class="table-toolbar">


            <form
                method="GET"
                action="reports.php"
                class="toolbar-left"
            >


                <!-- Preserve applicant filter -->

                <input
                    type="hidden"
                    name="applicant_account_status"
                    value="<?= htmlspecialchars(
                        $applicantAccountFilter
                    ) ?>"
                >


                <!-- Preserve application filters -->

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


                <div class="form-group">

                    <label>
                        Account Status
                    </label>


                    <select
                        name="employer_account_status"
                        class="form-control"
                    >

                        <option value="">
                            All Status
                        </option>


                        <option
                            value="active"

                            <?= $employerAccountFilter ===
                                "active"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Active
                        </option>


                        <option
                            value="inactive"

                            <?= $employerAccountFilter ===
                                "inactive"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Inactive
                        </option>


                        <option
                            value="deactivated"

                            <?= $employerAccountFilter ===
                                "deactivated"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Deactivated
                        </option>


                    </select>

                </div>


                <button
                    type="submit"
                    class="filter-btn"
                >
                    Filter
                </button>


                <a
                    href="reports.php"
                    class="clear-btn"
                >
                    Clear
                </a>


            </form>


            <div class="result-count">

                <?= number_format(
                    count(
                        $employerAccounts
                    )
                ) ?>

                employer(s) found

            </div>


        </div>


        <div class="table-wrapper">


            <table class="report-table">


                <thead>

                    <tr>

                        <th>#</th>

                        <th>
                            Company
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Account Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    count(
                        $employerAccounts
                    ) > 0
                ): ?>


                    <?php $row = 1; ?>


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

                        ?>


                        <tr>


                            <td>
                                <?= $row++ ?>
                            </td>


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

                                <?= htmlspecialchars(
                                    $account[
                                        "email"
                                    ]
                                    ?? "-"
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="
                                        account-badge
                                        <?= htmlspecialchars(
                                            getAccountStatusClass(
                                                $accountStatus
                                            )
                                        ) ?>
                                    "
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $accountStatus
                                        )
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="4"
                            class="empty-state"
                        >

                            No employer accounts
                            found for this status.

                        </td>

                    </tr>


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
                    Registered applicant
                    account status.
                </p>

            </div>

        </div>


        <!-- APPLICANT FILTER -->

        <div class="table-toolbar">


            <form
                method="GET"
                action="reports.php"
                class="toolbar-left"
            >


                <!-- Preserve employer account filter -->

                <input
                    type="hidden"
                    name="employer_account_status"
                    value="<?= htmlspecialchars(
                        $employerAccountFilter
                    ) ?>"
                >


                <!-- Preserve application filters -->

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


                <div class="form-group">

                    <label>
                        Account Status
                    </label>


                    <select
                        name="applicant_account_status"
                        class="form-control"
                    >

                        <option value="">
                            All Status
                        </option>


                        <option
                            value="active"

                            <?= $applicantAccountFilter ===
                                "active"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Active
                        </option>


                        <option
                            value="inactive"

                            <?= $applicantAccountFilter ===
                                "inactive"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Inactive
                        </option>


                        <option
                            value="deactivated"

                            <?= $applicantAccountFilter ===
                                "deactivated"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Deactivated
                        </option>


                    </select>

                </div>


                <button
                    type="submit"
                    class="filter-btn"
                >
                    Filter
                </button>


                <a
                    href="reports.php"
                    class="clear-btn"
                >
                    Clear
                </a>


            </form>


            <div class="result-count">

                <?= number_format(
                    count(
                        $applicantAccounts
                    )
                ) ?>

                applicant(s) found

            </div>


        </div>


        <div class="table-wrapper">


            <table class="report-table">


                <thead>

                    <tr>

                        <th>#</th>

                        <th>
                            Applicant
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Account Status
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (
                    count(
                        $applicantAccounts
                    ) > 0
                ): ?>


                    <?php $row = 1; ?>


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
                                <?= $row++ ?>
                            </td>


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
                                            .
                                            (
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
                                    $account[
                                        "email"
                                    ]
                                    ?? "-"
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="
                                        account-badge
                                        <?= htmlspecialchars(
                                            getAccountStatusClass(
                                                $accountStatus
                                            )
                                        ) ?>
                                    "
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $accountStatus
                                        )
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="4"
                            class="empty-state"
                        >

                            No applicant accounts
                            found for this status.

                        </td>

                    </tr>


                <?php endif; ?>


                </tbody>


            </table>


        </div>


    </div>


    <!-- =====================================================
         APPLICATION FILTERS
    ====================================================== -->

    <div class="
        section-card
        application-filter-section
    ">


        <div class="section-header">

            <div>

                <h2>
                    Application Report Filters
                </h2>

                <p>
                    Filter applications by
                    date, employer, job and status.
                </p>

            </div>

        </div>


        <form
            method="GET"
            action="reports.php"
        >


            <!-- Preserve account filters -->

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


            <div class="application-filter-grid">


                <div class="form-group">

                    <label>
                        Date From
                    </label>

                    <input
                        type="date"
                        name="date_from"
                        class="form-control"

                        value="<?= htmlspecialchars(
                            $dateFrom
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Date To
                    </label>

                    <input
                        type="date"
                        name="date_to"
                        class="form-control"

                        value="<?= htmlspecialchars(
                            $dateTo
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Employer
                    </label>


                    <select
                        name="employer_id"
                        class="form-control"
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
                                value="<?= (int) $employer["id"] ?>"

                                <?= $employerId ===
                                    (int) $employer["id"]
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $employer[
                                        "company_name"
                                    ]
                                ) ?>

                            </option>


                        <?php endforeach; ?>


                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Job
                    </label>


                    <select
                        name="job_id"
                        class="form-control"
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
                                value="<?= (int) $job["id"] ?>"

                                <?= $jobId ===
                                    (int) $job["id"]
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $job[
                                        "job_title"
                                    ]
                                ) ?>

                            </option>


                        <?php endforeach; ?>


                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Application Status
                    </label>


                    <select
                        name="status"
                        class="form-control"
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


            </div>


            <div class="filter-actions">


                <button
                    type="submit"
                    class="filter-btn"
                >
                    Generate Report
                </button>


                <a
                    href="reports.php"
                    class="clear-btn"
                >
                    Clear Filters
                </a>


            </div>


        </form>


    </div>


    <!-- =====================================================
         APPLICATION STATUS SUMMARY
    ====================================================== -->

    <div class="section-card">


        <div class="section-header">

            <div>

                <h2>
                    Application Status Summary
                </h2>

                <p>
                    Based on current
                    application filters.
                </p>

            </div>

        </div>


        <div class="status-grid">


            <div class="status-card">

                <div class="status-label">
                    Pending
                </div>

                <div class="status-number">

                    <?= $applicationStatusCounts[
                        "pending"
                    ] ?>

                </div>

            </div>


            <div class="status-card">

                <div class="status-label">
                    Reviewed
                </div>

                <div class="status-number">

                    <?= $applicationStatusCounts[
                        "reviewed"
                    ] ?>

                </div>

            </div>


            <div class="status-card">

                <div class="status-label">
                    Shortlisted
                </div>

                <div class="status-number">

                    <?= $applicationStatusCounts[
                        "shortlisted"
                    ] ?>

                </div>

            </div>


            <div class="status-card">

                <div class="status-label">
                    Rejected
                </div>

                <div class="status-number">

                    <?= $applicationStatusCounts[
                        "rejected"
                    ] ?>

                </div>

            </div>


            <div class="status-card">

                <div class="status-label">
                    Hired
                </div>

                <div class="status-number">

                    <?= $applicationStatusCounts[
                        "hired"
                    ] ?>

                </div>

            </div>


        </div>


    </div>


    <!-- =====================================================
         RESUME MATCH SUMMARY
    ====================================================== -->

    <div class="section-card">


        <div class="section-header">

            <div>

                <h2>
                    Resume Match Summary
                </h2>

                <p>
                    Applicant Skills and
                    Competencies compared
                    with job requirements.
                </p>

            </div>

        </div>


        <div class="match-grid">


            <div class="match-card">

                <div class="match-label">
                    Average Match
                </div>

                <div class="
                    match-number
                    match-good-text
                ">

                    <?= number_format(
                        $averageMatchScore,
                        0
                    ) ?>%

                </div>

            </div>


            <div class="match-card">

                <div class="match-label">
                    Strong Match (80-100%)
                </div>

                <div class="
                    match-number
                    match-strong-text
                ">

                    <?= $strongMatch ?>

                </div>

            </div>


            <div class="match-card">

                <div class="match-label">
                    Good Match (60-79%)
                </div>

                <div class="
                    match-number
                    match-good-text
                ">

                    <?= $goodMatch ?>

                </div>

            </div>


            <div class="match-card">

                <div class="match-label">
                    Fair Match (40-59%)
                </div>

                <div class="
                    match-number
                    match-fair-text
                ">

                    <?= $fairMatch ?>

                </div>

            </div>


            <div class="match-card">

                <div class="match-label">
                    Low Match (0-39%)
                </div>

                <div class="
                    match-number
                    match-low-text
                ">

                    <?= $lowMatch ?>

                </div>

            </div>


        </div>


    </div>


    <!-- =====================================================
         APPLICATION TABLE
    ====================================================== -->

    <div class="section-card">


        <div class="section-header">

            <div>

                <h2>
                    Application Report
                </h2>

                <p>

                    <?= number_format(
                        count(
                            $applications
                        )
                    ) ?>

                    application(s) found.

                </p>

            </div>

        </div>


        <?php if (
            count(
                $applications
            ) > 0
        ): ?>


            <div class="table-wrapper">


                <table class="report-table">


                    <thead>

                        <tr>

                            <th>#</th>

                            <th>
                                Applicant
                            </th>

                            <th>
                                Job
                            </th>

                            <th>
                                Employer
                            </th>

                            <th>
                                Match
                            </th>

                            <th>
                                Match Level
                            </th>

                            <th>
                                Matched Skills
                            </th>

                            <th>
                                Missing Skills
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Applied Date
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php $row = 1; ?>


                    <?php foreach (
                        $applications
                        as
                        $application
                    ): ?>


                        <?php

                        $score =
                            (float) (
                                $application[
                                    "match_score"
                                ]
                                ?? 0
                            );


                        $score =
                            max(
                                0,
                                min(
                                    100,
                                    $score
                                )
                            );


                        $matchLabel =
                            getMatchLabel(
                                $score
                            );


                        $matchClass =
                            getMatchClass(
                                $score
                            );


                        $applicationStatusClass =
                            getApplicationStatusClass(
                                $application[
                                    "status"
                                ]
                            );

                        ?>


                        <tr>


                            <td>
                                <?= $row++ ?>
                            </td>


                            <td>

                                <div class="name">

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

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $application[
                                        "job_title"
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $application[
                                        "company_name"
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    <?= number_format(
                                        $score,
                                        0
                                    ) ?>%

                                </strong>

                            </td>


                            <td>

                                <span
                                    class="
                                        match-badge
                                        <?= htmlspecialchars(
                                            $matchClass
                                        ) ?>
                                    "
                                >

                                    <?= htmlspecialchars(
                                        $matchLabel
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <div class="skill-text">

                                    <?= !empty(
                                        $application[
                                            "matched_skills"
                                        ]
                                    )
                                        ? htmlspecialchars(
                                            $application[
                                                "matched_skills"
                                            ]
                                        )
                                        : "-"
                                    ?>

                                </div>

                            </td>


                            <td>

                                <div class="skill-text">

                                    <?= !empty(
                                        $application[
                                            "missing_skills"
                                        ]
                                    )
                                        ? htmlspecialchars(
                                            $application[
                                                "missing_skills"
                                            ]
                                        )
                                        : "-"
                                    ?>

                                </div>

                            </td>


                            <td>

                                <span
                                    class="
                                        status
                                        <?= htmlspecialchars(
                                            $applicationStatusClass
                                        ) ?>
                                    "
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $application[
                                                "status"
                                            ]
                                        )
                                    ) ?>

                                </span>

                            </td>


                            <td>

                                <?= date(
                                    "M d, Y",
                                    strtotime(
                                        $application[
                                            "applied_at"
                                        ]
                                    )
                                ) ?>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>


        <?php else: ?>


            <div class="empty-state">

                No applications found
                for the selected filters.

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>