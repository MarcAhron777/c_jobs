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

$totalUsers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM users
    WHERE role != 'admin'
")->fetchColumn();

$totalEmployers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM employers
")->fetchColumn();

$totalPrivateEmployers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM employers
    WHERE employer_type = 'private'
")->fetchColumn();

$totalLguEmployers = (int) $pdo->query("
    SELECT COUNT(*)
    FROM employers
    WHERE employer_type = 'lgu'
")->fetchColumn();

$totalApplicants = (int) $pdo->query("
    SELECT COUNT(*)
    FROM applicants
")->fetchColumn();

$totalJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
")->fetchColumn();

$privateJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE job_source = 'private_employer'
")->fetchColumn();

$lguJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE job_source = 'lgu'
")->fetchColumn();

$jobFairJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE job_source = 'job_fair'
")->fetchColumn();

$pendingJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE status = 'pending'
")->fetchColumn();

$approvedJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE status = 'approved'
")->fetchColumn();

$rejectedJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE status = 'rejected'
")->fetchColumn();

$closedJobs = (int) $pdo->query("
    SELECT COUNT(*)
    FROM jobs
    WHERE status = 'closed'
")->fetchColumn();

$totalApplications = (int) $pdo->query("
    SELECT COUNT(*)
    FROM applications
")->fetchColumn();


/*
|--------------------------------------------------------------------------
| PDF
|--------------------------------------------------------------------------
*/

if (($_GET["download"] ?? "") === "pdf") {
    require_once "../vendor/autoload.php";

    $dompdf = new \Dompdf\Dompdf();

    ob_start();
    ?>

    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">

        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
            }

            h1 {
                margin-bottom: 5px;
            }

            .date {
                margin-bottom: 20px;
                color: #555;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                border: 1px solid #ccc;
                padding: 9px;
                text-align: left;
            }

            th {
                background: #eeeeee;
            }
        </style>
    </head>

    <body>

        <h1>Caloocan Job Portal</h1>
        <h2>System Overview Report</h2>

        <div class="date">
            Generated: <?= date("F d, Y h:i A") ?>
        </div>

        <table>

            <tr>
                <th>Report Item</th>
                <th>Total</th>
            </tr>

            <tr><td>Total Users</td><td><?= $totalUsers ?></td></tr>
            <tr><td>Total Employers</td><td><?= $totalEmployers ?></td></tr>
            <tr><td>Private Employers</td><td><?= $totalPrivateEmployers ?></td></tr>
            <tr><td>LGU Employers</td><td><?= $totalLguEmployers ?></td></tr>
            <tr><td>Total Applicants</td><td><?= $totalApplicants ?></td></tr>
            <tr><td>Total Jobs</td><td><?= $totalJobs ?></td></tr>
            <tr><td>Private Employer Jobs</td><td><?= $privateJobs ?></td></tr>
            <tr><td>LGU Jobs</td><td><?= $lguJobs ?></td></tr>
            <tr><td>Job Fair Jobs</td><td><?= $jobFairJobs ?></td></tr>
            <tr><td>Pending Jobs</td><td><?= $pendingJobs ?></td></tr>
            <tr><td>Approved Jobs</td><td><?= $approvedJobs ?></td></tr>
            <tr><td>Rejected Jobs</td><td><?= $rejectedJobs ?></td></tr>
            <tr><td>Closed Jobs</td><td><?= $closedJobs ?></td></tr>
            <tr><td>Total Applications</td><td><?= $totalApplications ?></td></tr>

        </table>

    </body>
    </html>

    <?php

    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "portrait");
    $dompdf->render();

    $dompdf->stream(
        "system-overview-report.pdf",
        ["Attachment" => true]
    );

    exit;
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

    <title>System Overview Report</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #222;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px 50px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .page-header p {
            color: #64748b;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            border-radius: 6px;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-blue {
            background: #2563eb;
        }

        .btn-red {
            background: #dc2626;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .card {
            padding: 22px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 9px;
        }

        .card h3 {
            margin: 0 0 10px;
            color: #64748b;
            font-size: 13px;
        }

        .number {
            font-size: 28px;
            font-weight: bold;
        }

        @media (max-width: 900px) {
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .stats {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>

<div class="container">

    <div class="page-header">

        <div>
            <h1>System Overview Report</h1>

            <p>
                Overall system statistics for the Caloocan Job Portal.
            </p>
        </div>

        <div class="actions">
            <a href="reports.php" class="btn btn-blue">
                Back to Reports
            </a>

            <a
                href="report-overview.php?download=pdf"
                class="btn btn-red"
            >
                Download PDF
            </a>
        </div>

    </div>

    <div class="stats">

        <?php
        $stats = [
            "Total Users" => $totalUsers,
            "Employers" => $totalEmployers,
            "Private Employers" => $totalPrivateEmployers,
            "LGU Employers" => $totalLguEmployers,
            "Applicants" => $totalApplicants,
            "Total Jobs" => $totalJobs,
            "Private Jobs" => $privateJobs,
            "LGU Jobs" => $lguJobs,
            "Job Fair Jobs" => $jobFairJobs,
            "Pending Jobs" => $pendingJobs,
            "Approved Jobs" => $approvedJobs,
            "Rejected Jobs" => $rejectedJobs,
            "Closed Jobs" => $closedJobs,
            "Applications" => $totalApplications
        ];
        ?>

        <?php foreach ($stats as $label => $value): ?>

            <div class="card">
                <h3><?= htmlspecialchars($label) ?></h3>

                <div class="number">
                    <?= number_format($value) ?>
                </div>
            </div>

        <?php endforeach; ?>

    </div>

</div>

</body>
</html>