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


/*
|--------------------------------------------------------------------------
| Variables
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";

$jobId = isset($_GET["job_id"])
    ? (int) $_GET["job_id"]
    : 0;

$search = trim($_GET["search"] ?? "");

$page = isset($_GET["page"])
    ? (int) $_GET["page"]
    : 1;

if ($page < 1) {
    $page = 1;
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

$perPage = 10;


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

    $applicationId = isset($_POST["application_id"])
        ? (int) $_POST["application_id"]
        : 0;

    $status = $_POST["status"] ?? "";


    $allowedStatuses = [
        "pending",
        "reviewed",
        "shortlisted",
        "rejected",
        "hired"
    ];


    if (
        $applicationId <= 0
        || !in_array($status, $allowedStatuses, true)
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
| Get Jobs
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

$jobs = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Build Conditions
|--------------------------------------------------------------------------
*/

$conditions = [
    "j.employer_id = ?"
];

$params = [
    $employerId
];


/*
|--------------------------------------------------------------------------
| Job Filter
|--------------------------------------------------------------------------
*/

if ($jobId > 0) {

    $conditions[] = "j.id = ?";

    $params[] = $jobId;

}


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

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


$whereSql = implode(
    " AND ",
    $conditions
);


/*
|--------------------------------------------------------------------------
| Count Applicants
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


/*
|--------------------------------------------------------------------------
| Calculate Pagination
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int) ceil(
        $totalApplicants / $perPage
    )
);


if ($page > $totalPages) {
    $page = $totalPages;
}


$offset = ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| Get Applicants
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
        am.matched_skills,
        am.missing_skills

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

$applicants = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Pagination Helper
|--------------------------------------------------------------------------
*/

function buildPageUrl($pageNumber)
{
    $params = $_GET;

    $params["page"] = $pageNumber;

    return "?" . http_build_query($params);
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

<title>Applicants - Caloocan Job Portal</title>


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
    max-width: 1200px;

    margin: 40px auto;

    padding: 0 20px;
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
| Filters
|--------------------------------------------------------------------------
*/

.filters {
    background: white;

    padding: 20px;

    border-radius: 10px;

    margin-bottom: 20px;

    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);
}


.filters form {
    display: grid;

    grid-template-columns:
        1fr
        220px
        auto;

    gap: 10px;
}


.input,
select {
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


.filter-btn:hover {
    background: #1d4ed8;
}


.clear-btn {
    padding: 11px 18px;

    background: #e5e7eb;

    color: #333;

    border-radius: 6px;

    text-decoration: none;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| Result Summary
|--------------------------------------------------------------------------
*/

.result-summary {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 15px;

    color: #666;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| Grid View
|--------------------------------------------------------------------------
*/

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
        0 2px 10px rgba(0,0,0,0.05);

    border: 1px solid #eee;

    display: flex;

    flex-direction: column;
}


.applicant-card:hover {
    box-shadow:
        0 5px 18px rgba(0,0,0,0.08);
}


/*
|--------------------------------------------------------------------------
| Card Header
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Applicant Info
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| Match
|--------------------------------------------------------------------------
*/

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

    margin-bottom: 8px;
}


.match-label {
    font-size: 13px;

    font-weight: bold;

    color: #1e3a8a;
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


/*
|--------------------------------------------------------------------------
| Skills
|--------------------------------------------------------------------------
*/

.skills {
    font-size: 12px;

    color: #666;

    margin-top: 10px;

    line-height: 1.5;
}


.skills strong {
    color: #333;
}


/*
|--------------------------------------------------------------------------
| Card Footer
|--------------------------------------------------------------------------
*/

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


.resume-link:hover {
    text-decoration: underline;
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


.view-btn:hover {
    background: #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {
    background: white;

    padding: 60px 30px;

    text-align: center;

    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);
}


.empty h2 {
    margin-bottom: 10px;
}


.empty p {
    color: #666;
}


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/

.pagination-wrapper {
    margin-top: 30px;

    display: flex;

    justify-content: center;

    align-items: center;

    gap: 6px;

    flex-wrap: wrap;
}


.pagination {
    display: flex;

    gap: 6px;

    align-items: center;
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


.pagination a:hover {
    background: #eff6ff;

    border-color: #2563eb;

    color: #2563eb;
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


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 850px) {

    .applicant-grid {
        grid-template-columns: 1fr;
    }

}


@media (max-width: 650px) {

    .navbar {
        padding: 15px 20px;

        flex-direction: column;

        gap: 12px;
    }


    .filters form {
        grid-template-columns: 1fr;
    }


    .result-summary {
        flex-direction: column;

        align-items: flex-start;

        gap: 5px;
    }

}


@media (max-width: 500px) {

    .card-header {
        flex-direction: column;
    }


    .card-footer {
        flex-direction: column;

        align-items: stretch;
    }


    .view-btn {
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

        <a href="../index.php">
            Home
        </a>


        <a href="dashboard.php">
            Dashboard
        </a>


        <a
            href="profile.php"
            class="active"
        >
            Profile
        </a>


        <a href="jobs.php">
            My Jobs
        </a>


        <a href="applicants.php">
            Applicants
        </a>


        <a
            href="../logout.php"
            class="logout"
        >
            Logout
        </a>

    </div>

</nav>


<div class="container">


    <div class="header">

        <h1>
            Applicants
        </h1>

        <p>
            Review applicants and see their resume match results.
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


    <!-- Filters -->

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

                        <?= htmlspecialchars(
                            $job["job_title"]
                        ) ?>

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


        <?php if (
            $search !== ""
            || $jobId > 0
        ): ?>

            <div style="margin-top:10px;">

                <a
                    href="applicants.php"
                    class="clear-btn"
                >
                    Clear Filters
                </a>

            </div>

        <?php endif; ?>

    </div>


    <!-- Result Summary -->

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

                <strong>
                    <?= $offset + 1 ?>
                </strong>

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

                $matchScore =
                    (float) (
                        $applicant["match_score"]
                        ?? 0
                    );

                if ($matchScore < 0) {
                    $matchScore = 0;
                }

                if ($matchScore > 100) {
                    $matchScore = 100;
                }


                $statusClass =
                    "status-" .
                    strtolower(
                        $applicant["status"]
                    );

                ?>


                <div class="applicant-card">


                    <!-- Header -->

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
                                    "M d, Y h:i A",
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


                    <!-- Applicant Info -->

                    <div class="info-list">

                        <?php if (
                            !empty($applicant["phone"])
                        ): ?>

                            <div class="info-item">

                                <strong>
                                    Phone:
                                </strong>

                                <?= htmlspecialchars(
                                    $applicant["phone"]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty($applicant["address"])
                        ): ?>

                            <div class="info-item">

                                <strong>
                                    Address:
                                </strong>

                                <?= htmlspecialchars(
                                    $applicant["address"]
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- Match Score -->

                    <div class="match-box">

                        <div class="match-top">

                            <span class="match-label">

                                Resume Match

                            </span>


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
                                style="width: <?= $matchScore ?>%;"
                            ></div>

                        </div>


                        <?php if (
                            !empty(
                                $applicant["matched_skills"]
                            )
                        ): ?>

                            <div class="skills">

                                <strong>
                                    Matched:
                                </strong>

                                <?= htmlspecialchars(
                                    $applicant["matched_skills"]
                                ) ?>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty(
                                $applicant["missing_skills"]
                            )
                        ): ?>

                            <div class="skills">

                                <strong>
                                    Missing:
                                </strong>

                                <?= htmlspecialchars(
                                    $applicant["missing_skills"]
                                ) ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- Footer -->

                    <div class="card-footer">


                        <?php if (
                            !empty(
                                $applicant["file_path"]
                            )
                        ): ?>

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

                            <span
                                style="
                                    color:#999;
                                    font-size:13px;
                                "
                            >
                                No Resume
                            </span>

                        <?php endif; ?>


                        <a
                            href="view-application.php?id=<?= (int) $applicant["application_id"] ?>"
                            class="view-btn"
                        >
                            View Application
                        </a>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


        <!-- Pagination -->

        <?php if ($totalPages > 1): ?>

            <div class="pagination-wrapper">

                <div class="pagination">


                    <?php if ($page > 1): ?>

                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl(
                                    $page - 1
                                )
                            ) ?>"
                        >
                            Previous
                        </a>

                    <?php else: ?>

                        <span class="disabled">
                            Previous
                        </span>

                    <?php endif; ?>


                    <?php

                    $startPage =
                        max(
                            1,
                            $page - 2
                        );

                    $endPage =
                        min(
                            $totalPages,
                            $page + 2
                        );

                    ?>


                    <?php if ($startPage > 1): ?>

                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl(1)
                            ) ?>"
                        >
                            1
                        </a>


                        <?php if ($startPage > 2): ?>

                            <span>
                                ...
                            </span>

                        <?php endif; ?>

                    <?php endif; ?>


                    <?php for (
                        $i = $startPage;
                        $i <= $endPage;
                        $i++
                    ): ?>


                        <?php if ($i === $page): ?>

                            <span class="active">
                                <?= $i ?>
                            </span>

                        <?php else: ?>

                            <a
                                href="<?= htmlspecialchars(
                                    buildPageUrl($i)
                                ) ?>"
                            >
                                <?= $i ?>
                            </a>

                        <?php endif; ?>


                    <?php endfor; ?>


                    <?php if ($endPage < $totalPages): ?>


                        <?php if (
                            $endPage < $totalPages - 1
                        ): ?>

                            <span>
                                ...
                            </span>

                        <?php endif; ?>


                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl(
                                    $totalPages
                                )
                            ) ?>"
                        >
                            <?= $totalPages ?>
                        </a>

                    <?php endif; ?>


                    <?php if (
                        $page < $totalPages
                    ): ?>

                        <a
                            href="<?= htmlspecialchars(
                                buildPageUrl(
                                    $page + 1
                                )
                            ) ?>"
                        >
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
                No applicants match your current search or filter.
            </p>

        </div>


    <?php endif; ?>


</div>


</body>

</html>