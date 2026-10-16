<?php

session_start();

require_once "../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET JOBS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT
        j.id,
        j.job_title,
        j.job_source,
        j.location,
        j.address,
        j.barangay,
        j.caloocan_area,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.educational_requirement,
        j.tesda_qualification,
        j.required_nc_level,
        j.status,
        j.application_deadline,
        j.created_at,

        e.company_name,
        e.employer_type,

        e.verification_status
            AS employer_verification_status,

        (
            SELECT COUNT(*)
            FROM applications a
            WHERE a.job_id = j.id
        ) AS applicant_count

    FROM jobs j

    INNER JOIN employers e
        ON j.employer_id = e.id

    ORDER BY
        FIELD(
            j.status,
            'pending',
            'approved',
            'rejected',
            'closed'
        ),
        j.created_at DESC
");

$jobs =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


function jobStatusClass(string $status): string
{
    return match ($status) {
        "approved" => "status-approved",
        "rejected" => "status-rejected",
        "closed" => "status-closed",
        default => "status-pending"
    };
}

function sourceLabel(string $source): string
{
    return match ($source) {
        "lgu" => "LGU",
        "job_fair" => "Job Fair",
        default => "Private Employer"
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
    Manage Jobs - Admin
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
    padding: 0 20px 50px;
}

.page-header {
    margin-bottom: 25px;
}

.page-header h1 {
    margin-bottom: 8px;
}

.page-header p {
    color: #666;
}

.notice {
    margin-bottom: 20px;
    padding: 15px 18px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    color: #1e40af;
    line-height: 1.6;
}

.table-container {
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th,
td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #eee;
    vertical-align: middle;
}

th {
    background: #f9fafb;
    font-size: 13px;
}

td {
    font-size: 14px;
}

.status,
.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #dcfce7;
    color: #166534;
}

.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}

.status-closed {
    background: #e5e7eb;
    color: #374151;
}

.source-private {
    background: #e0e7ff;
    color: #3730a3;
}

.source-lgu {
    background: #dbeafe;
    color: #1e40af;
}

.source-jobfair {
    background: #f3e8ff;
    color: #7e22ce;
}

.verified {
    color: #166534;
    font-size: 12px;
    font-weight: bold;
}

.unverified {
    color: #991b1b;
    font-size: 12px;
    font-weight: bold;
}

.btn {
    display: inline-block;
    padding: 8px 13px;
    background: #2563eb;
    color: #ffffff;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
}

.btn:hover {
    background: #1d4ed8;
}

.empty {
    padding: 40px;
    text-align: center;
    color: #64748b;
}

.small {
    margin-top: 5px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}
</style>

</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>

<div class="container">

    <div class="page-header">

        <h1>
            Manage Jobs
        </h1>

        <p>
            Review, approve, reject and close employer job postings.
        </p>

    </div>

    <div class="notice">
        Job source is automatically determined by the employer account.
        Private employer accounts create Private Employer jobs while verified
        LGU employer accounts create LGU jobs.
    </div>

    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Job</th>
                    <th>Employer</th>
                    <th>Source</th>
                    <th>Qualification</th>
                    <th>Location</th>
                    <th>Applicants</th>
                    <th>Status</th>
                    <th>Posted</th>
                    <th>Action</th>
                </tr>

            </thead>

            <tbody>

            <?php if (empty($jobs)): ?>

                <tr>
                    <td colspan="9" class="empty">
                        No jobs found.
                    </td>
                </tr>

            <?php else: ?>

                <?php foreach ($jobs as $job): ?>

                    <?php
                    $source =
                        $job["job_source"]
                        ?? "private_employer";

                    $sourceClass =
                        $source === "lgu"
                            ? "source-lgu"
                            : (
                                $source === "job_fair"
                                    ? "source-jobfair"
                                    : "source-private"
                            );
                    ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $job["job_title"]
                                ) ?>
                            </strong>

                            <div class="small">
                                <?= htmlspecialchars(
                                    $job["employment_type"]
                                ) ?>
                            </div>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $job["company_name"]
                            ) ?>

                            <div>

                                <?php if (
                                    $job["employer_verification_status"]
                                    === "approved"
                                ): ?>

                                    <span class="verified">
                                        Verified
                                    </span>

                                <?php else: ?>

                                    <span class="unverified">
                                        Not Verified
                                    </span>

                                <?php endif; ?>

                            </div>

                        </td>

                        <td>

                            <span class="badge <?= $sourceClass ?>">
                                <?= htmlspecialchars(
                                    sourceLabel($source)
                                ) ?>
                            </span>

                        </td>

                        <td>

                            <?php if (
                                !empty(
                                    $job["educational_requirement"]
                                )
                            ): ?>

                                <div class="small">
                                    Education:
                                    <?= htmlspecialchars(
                                        $job["educational_requirement"]
                                    ) ?>
                                </div>

                            <?php endif; ?>


                            <?php if (
                                !empty(
                                    $job["tesda_qualification"]
                                )
                            ): ?>

                                <div class="small">
                                    TESDA:
                                    <?= htmlspecialchars(
                                        $job["tesda_qualification"]
                                    ) ?>

                                    <?= htmlspecialchars(
                                        $job["required_nc_level"]
                                        ?? ""
                                    ) ?>
                                </div>

                            <?php endif; ?>


                            <?php if (
                                empty(
                                    $job["educational_requirement"]
                                ) &&
                                empty(
                                    $job["tesda_qualification"]
                                )
                            ): ?>

                                <span class="small">
                                    Skills-based
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?= htmlspecialchars(
                                $job["location"]
                                ?: "Caloocan City"
                            ) ?>

                        </td>

                        <td>
                            <?= (int)
                                $job["applicant_count"]
                            ?>
                        </td>

                        <td>

                            <span
                                class="status <?= jobStatusClass(
                                    $job["status"]
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    ucfirst(
                                        $job["status"]
                                    )
                                ) ?>
                            </span>

                        </td>

                        <td>

                            <?= date(
                                "M d, Y",
                                strtotime(
                                    $job["created_at"]
                                )
                            ) ?>

                        </td>

                        <td>

                            <a
                                href="view-job.php?id=<?= (int)
                                    $job["id"]
                                ?>"
                                class="btn"
                            >
                                View
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>