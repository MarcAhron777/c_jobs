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

$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");
$employerId = (int) ($_GET["employer_id"] ?? 0);
$jobId = (int) ($_GET["job_id"] ?? 0);
$jobSource = trim($_GET["job_source"] ?? "");
$status = trim($_GET["status"] ?? "");

$employers = $pdo->query("
    SELECT id, company_name
    FROM employers
    ORDER BY company_name
")->fetchAll(PDO::FETCH_ASSOC);

$jobs = $pdo->query("
    SELECT id, job_title
    FROM jobs
    ORDER BY job_title
")->fetchAll(PDO::FETCH_ASSOC);

$where = [];
$params = [];

if ($dateFrom !== "") {
    $where[] = "DATE(a.applied_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== "") {
    $where[] = "DATE(a.applied_at) <= ?";
    $params[] = $dateTo;
}

if ($employerId > 0) {
    $where[] = "e.id = ?";
    $params[] = $employerId;
}

if ($jobId > 0) {
    $where[] = "j.id = ?";
    $params[] = $jobId;
}

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
        [
            "pending",
            "reviewed",
            "shortlisted",
            "rejected",
            "hired"
        ],
        true
    )
) {
    $where[] = "a.status = ?";
    $params[] = $status;
}

$whereSql = $where
    ? "WHERE " . implode(" AND ", $where)
    : "";

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.status,
        a.applied_at,

        ap.first_name,
        ap.last_name,

        j.job_title,
        j.job_source,

        e.company_name,

        am.match_score,
        am.skills_score,
        am.education_score,
        am.tesda_score

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

    ORDER BY a.applied_at DESC
");

$stmt->execute($params);

$applications =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdfQuery = $_GET;
$pdfQuery["download"] = "pdf";

$pdfUrl =
    "report-applications.php?"
    . http_build_query($pdfQuery);


function applicationSourceLabel(string $source): string
{
    return match ($source) {
        "lgu" => "LGU",
        "job_fair" => "Job Fair",
        default => "Private Employer"
    };
}


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

    <html>
    <head>
        <meta charset="UTF-8">

        <style>
            body {
                font-family: DejaVu Sans, sans-serif;
                font-size: 8px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                border: 1px solid #ccc;
                padding: 5px;
            }

            th {
                background: #eeeeee;
            }
        </style>
    </head>

    <body>

        <h1>Caloocan Job Portal</h1>
        <h2>Application & Matching Report</h2>

        <p>
            Generated: <?= date("F d, Y h:i A") ?>
        </p>

        <table>
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Job</th>
                    <th>Employer</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Overall</th>
                    <th>Skills</th>
                    <th>Education</th>
                    <th>TESDA</th>
                    <th>Applied</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($applications as $application): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $application["first_name"]
                            . " "
                            . $application["last_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $application["job_title"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $application["company_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= applicationSourceLabel(
                            $application["job_source"]
                        ) ?>
                    </td>

                    <td>
                        <?= ucfirst($application["status"]) ?>
                    </td>

                    <td>
                        <?= $application["match_score"] !== null
                            ? number_format(
                                $application["match_score"],
                                1
                            ) . "%"
                            : "-"
                        ?>
                    </td>

                    <td>
                        <?= $application["skills_score"] !== null
                            ? number_format(
                                $application["skills_score"],
                                1
                            ) . "%"
                            : "-"
                        ?>
                    </td>

                    <td>
                        <?= $application["education_score"] !== null
                            ? number_format(
                                $application["education_score"],
                                1
                            ) . "%"
                            : "-"
                        ?>
                    </td>

                    <td>
                        <?= $application["tesda_score"] !== null
                            ? number_format(
                                $application["tesda_score"],
                                1
                            ) . "%"
                            : "-"
                        ?>
                    </td>

                    <td>
                        <?= date(
                            "M d, Y",
                            strtotime($application["applied_at"])
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
        "application-matching-report.pdf",
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

    <title>Application & Matching Report</title>

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
            max-width: 1450px;
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
            grid-template-columns: repeat(3, 1fr);
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
            padding: 11px;
            border-bottom: 1px solid #eee;
            text-align: left;
            font-size: 12px;
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
            <h1>Application & Matching Report</h1>

            <p>
                Application status and explainable
                matching results.
            </p>
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

        <div>
            <label>Employer</label>

            <select name="employer_id">

                <option value="0">
                    All Employers
                </option>

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
            <label>Job</label>

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
        </div>

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
            <label>Application Status</label>

            <select name="status">
                <option value="">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="reviewed">Reviewed</option>
                <option value="shortlisted">Shortlisted</option>
                <option value="rejected">Rejected</option>
                <option value="hired">Hired</option>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn blue">
                Apply Filters
            </button>

            <a
                href="report-applications.php"
                class="btn gray"
            >
                Reset
            </a>
        </div>

    </form>

    <div class="table-box">

        <table>

            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Job</th>
                    <th>Employer</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Overall Match</th>
                    <th>Skills</th>
                    <th>Education</th>
                    <th>TESDA / NC</th>
                    <th>Applied</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$applications): ?>

                <tr>
                    <td colspan="10">
                        No applications found.
                    </td>
                </tr>

            <?php endif; ?>

            <?php foreach ($applications as $application): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $application["first_name"]
                            . " "
                            . $application["last_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $application["job_title"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $application["company_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            applicationSourceLabel(
                                $application["job_source"]
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            ucfirst(
                                $application["status"]
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= $application["match_score"] !== null
                            ? number_format(
                                $application["match_score"],
                                1
                            ) . "%"
                            : "-"
                        ?>
                    </td>

                    <td>
                        <?= $application["skills_score"] !== null
                            ? number_format(
                                $application["skills_score"],
                                1
                            ) . "%"
                            : "-"
                        ?>
                    </td>

                    <td>
                        <?= $application["education_score"] !== null
                            ? number_format(
                                $application["education_score"],
                                1
                            ) . "%"
                            : "-"
                        ?>
                    </td>

                    <td>
                        <?= $application["tesda_score"] !== null
                            ? number_format(
                                $application["tesda_score"],
                                1
                            ) . "%"
                            : "-"
                        ?>
                    </td>

                    <td>
                        <?= date(
                            "M d, Y",
                            strtotime(
                                $application["applied_at"]
                            )
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