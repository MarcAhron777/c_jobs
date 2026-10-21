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

$jobSource = trim($_GET["job_source"] ?? "");
$status = trim($_GET["status"] ?? "");
$employerId = (int) ($_GET["employer_id"] ?? 0);
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");

$employers = $pdo->query("
    SELECT id, company_name
    FROM employers
    ORDER BY company_name
")->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];

if (
    in_array(
        $jobSource,
        ["private_employer", "lgu", "job_fair"],
        true
    )
) {
    $where[] = "j.job_source = ?";
    $params[] = $jobSource;
}

if (
    in_array(
        $status,
        ["pending", "approved", "rejected", "closed"],
        true
    )
) {
    $where[] = "j.status = ?";
    $params[] = $status;
}

if ($employerId > 0) {
    $where[] = "e.id = ?";
    $params[] = $employerId;
}

if ($dateFrom !== "") {
    $where[] = "DATE(j.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== "") {
    $where[] = "DATE(j.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSql = $where
    ? "WHERE " . implode(" AND ", $where)
    : "";

$stmt = $pdo->prepare("
    SELECT
        j.id,
        j.job_title,
        j.job_source,
        j.employment_type,
        j.location,
        j.status,
        j.created_at,
        e.company_name,
        e.employer_type,
        (
            SELECT COUNT(*)
            FROM applications a
            WHERE a.job_id = j.id
        ) AS total_applications
    FROM jobs j
    INNER JOIN employers e
        ON j.employer_id = e.id
    $whereSql
    ORDER BY j.created_at DESC
");

$stmt->execute($params);

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

function sourceLabel(string $source): string
{
    return match ($source) {
        "lgu" => "LGU",
        "job_fair" => "Job Fair",
        default => "Private Employer"
    };
}

$pdfQuery = $_GET;
$pdfQuery["download"] = "pdf";

$pdfUrl =
    "report-jobs.php?"
    . http_build_query($pdfQuery);


if (($_GET["download"] ?? "") === "pdf") {
    require_once "../vendor/autoload.php";

    $dompdf = new \Dompdf\Dompdf();

    ob_start();
    ?>

    <html>
    <head>
        <meta charset="UTF-8">

        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 9px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                border: 1px solid #ccc;
                padding: 6px;
            }

            th {
                background: #eee;
            }
        </style>
    </head>

    <body>

        <h1>Caloocan Job Portal</h1>
        <h2>Job Posting Report</h2>

        <p>Generated: <?= date("F d, Y h:i A") ?></p>

        <table>
            <thead>
                <tr>
                    <th>Job</th>
                    <th>Employer</th>
                    <th>Employer Type</th>
                    <th>Source</th>
                    <th>Employment</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Applications</th>
                    <th>Posted</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($jobs as $job): ?>

                <tr>
                    <td><?= htmlspecialchars($job["job_title"]) ?></td>
                    <td><?= htmlspecialchars($job["company_name"]) ?></td>

                    <td>
                        <?= $job["employer_type"] === "lgu"
                            ? "LGU"
                            : "Private"
                        ?>
                    </td>

                    <td>
                        <?= sourceLabel($job["job_source"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($job["employment_type"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($job["location"] ?: "N/A") ?>
                    </td>

                    <td>
                        <?= ucfirst($job["status"]) ?>
                    </td>

                    <td>
                        <?= (int) $job["total_applications"] ?>
                    </td>

                    <td>
                        <?= date(
                            "M d, Y",
                            strtotime($job["created_at"])
                        ) ?>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

    </body>
    </html>

    <?php

    $html = ob_get_clean();

    $dompdf->loadHtml($html);
    $dompdf->setPaper("A4", "landscape");
    $dompdf->render();

    $dompdf->stream(
        "job-posting-report.pdf",
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

    <title>Job Posting Report</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
        }

        .container {
            max-width: 1400px;
            margin: 35px auto;
            padding: 0 20px 50px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            padding: 20px;
            margin-bottom: 20px;
            background: #ffffff;
            border-radius: 8px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: bold;
        }

        input,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
        }

        .table-box {
            overflow-x: auto;
            background: #ffffff;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
        }

        .actions {
            display: flex;
            align-items: end;
            gap: 8px;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 5px;
            color: #ffffff;
            text-decoration: none;
            cursor: pointer;
        }

        .blue {
            background: #2563eb;
        }

        .gray {
            background: #64748b;
        }

        .red {
            background: #dc2626;
        }

        @media (max-width: 900px) {
            .filters {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .filters {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>

<div class="container">

    <div class="page-header">

        <div>
            <h1>Job Posting Report</h1>
            <p>Private, LGU and Job Fair postings.</p>
        </div>

        <div class="actions">

            <a
                href="<?= htmlspecialchars($pdfUrl) ?>"
                class="btn red"
            >
                Download PDF
            </a>
        </div>

    </div>

    <form method="GET" class="filters">

        <div>
            <label>Job Source</label>

            <select name="job_source">
                <option value="">All Sources</option>
                <option value="private_employer">Private Employer</option>
                <option value="lgu">LGU</option>
                <option value="job_fair">Job Fair</option>
            </select>
        </div>

        <div>
            <label>Status</label>

            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="closed">Closed</option>
            </select>
        </div>

        <div>
            <label>Employer</label>

            <select name="employer_id">
                <option value="0">All Employers</option>

                <?php foreach ($employers as $employer): ?>

                    <option
                        value="<?= (int) $employer["id"] ?>"
                        <?= $employerId === (int) $employer["id"]
                            ? "selected"
                            : ""
                        ?>
                    >
                        <?= htmlspecialchars(
                            $employer["company_name"]
                        ) ?>
                    </option>

                <?php endforeach; ?>

            </select>
        </div>

        <div>
            <label>Date From</label>

            <input
                type="date"
                name="date_from"
                value="<?= htmlspecialchars($dateFrom) ?>"
            >
        </div>

        <div>
            <label>Date To</label>

            <input
                type="date"
                name="date_to"
                value="<?= htmlspecialchars($dateTo) ?>"
            >
        </div>

        <div class="actions">
            <button type="submit" class="btn blue">
                Apply
            </button>

            <a href="report-jobs.php" class="btn gray">
                Reset
            </a>
        </div>

    </form>

    <div class="table-box">

        <table>

            <thead>
                <tr>
                    <th>Job</th>
                    <th>Employer</th>
                    <th>Employer Type</th>
                    <th>Source</th>
                    <th>Employment</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Applications</th>
                    <th>Posted</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$jobs): ?>
                <tr>
                    <td colspan="9">
                        No jobs found.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($jobs as $job): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars($job["job_title"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($job["company_name"]) ?>
                    </td>

                    <td>
                        <?= $job["employer_type"] === "lgu"
                            ? "LGU"
                            : "Private Employer"
                        ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            sourceLabel($job["job_source"])
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $job["employment_type"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $job["location"] ?: "N/A"
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            ucfirst($job["status"])
                        ) ?>
                    </td>

                    <td>
                        <?= (int) $job["total_applications"] ?>
                    </td>

                    <td>
                        <?= date(
                            "M d, Y",
                            strtotime($job["created_at"])
                        ) ?>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>