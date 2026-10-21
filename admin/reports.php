<?php

session_start();

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
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

    <title>Reports - Admin</title>

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
            max-width: 1200px;
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
        }

        .report-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .report-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 25px;
        }

        .report-card h2 {
            margin-bottom: 10px;
            font-size: 20px;
        }

        .report-card p {
            min-height: 48px;
            margin-bottom: 20px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #2563eb;
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        @media (max-width: 700px) {
            .report-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>

<div class="container">

    <div class="page-header">
        <h1>Reports</h1>
        <p>Select the report you want to view or download.</p>
    </div>

    <div class="report-grid">

        <div class="report-card">
            <h2>System Overview</h2>
            <p>
                Overall users, employers, applicants, jobs,
                applications and job source statistics.
            </p>

            <a href="report-overview.php" class="btn">
                View Report
            </a>
        </div>

        <div class="report-card">
            <h2>Employer Report</h2>
            <p>
                Private and LGU employers, verification status,
                account status and jobs posted.
            </p>

            <a href="report-employers.php" class="btn">
                View Report
            </a>
        </div>

        <div class="report-card">
            <h2>Applicant Report</h2>
            <p>
                Applicant accounts, registration dates
                and total job applications.
            </p>

            <a href="report-applicants.php" class="btn">
                View Report
            </a>
        </div>

        <div class="report-card">
            <h2>Job Posting Report</h2>
            <p>
                Private, LGU and Job Fair postings,
                job status and application activity.
            </p>

            <a href="report-jobs.php" class="btn">
                View Report
            </a>
        </div>

        <div class="report-card">
            <h2>Application & Matching Report</h2>
            <p>
                Applicant status, job matching score,
                skills, education and TESDA matching.
            </p>

            <a href="report-applications.php" class="btn">
                View Report
            </a>
        </div>

    </div>

</div>

</body>
</html>