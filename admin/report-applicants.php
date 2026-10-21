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

$accountStatus = trim($_GET["account_status"] ?? "");
$dateFrom = trim($_GET["date_from"] ?? "");
$dateTo = trim($_GET["date_to"] ?? "");

$where = [];
$params = [];

if ($accountStatus === "active") {
    $where[] = "u.is_active = 1";
} elseif ($accountStatus === "inactive") {
    $where[] = "u.is_active = 0";
}

if ($dateFrom !== "") {
    $where[] = "DATE(a.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== "") {
    $where[] = "DATE(a.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSql = $where
    ? "WHERE " . implode(" AND ", $where)
    : "";

$stmt = $pdo->prepare("
    SELECT
        a.id,
        a.first_name,
        a.last_name,
        a.phone,
        a.address,
        a.created_at,
        u.email,
        u.is_active,
        (
            SELECT COUNT(*)
            FROM applications app
            WHERE app.applicant_id = a.id
        ) AS total_applications
    FROM applicants a
    INNER JOIN users u
        ON a.user_id = u.id
    $whereSql
    ORDER BY a.created_at DESC
");

$stmt->execute($params);

$applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdfQuery = $_GET;
$pdfQuery["download"] = "pdf";

$pdfUrl =
    "report-applicants.php?"
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
                font-size: 10px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
            }

            th,
            td {
                border: 1px solid #ccc;
                padding: 7px;
            }

            th {
                background: #eee;
            }
        </style>
    </head>

    <body>

        <h1>Caloocan Job Portal</h1>
        <h2>Applicant Report</h2>

        <p>Generated: <?= date("F d, Y h:i A") ?></p>

        <table>
            <thead>
                <tr>
                    <th>Applicant</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Applications</th>
                    <th>Status</th>
                    <th>Registered</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($applicants as $applicant): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $applicant["first_name"]
                            . " "
                            . $applicant["last_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($applicant["email"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $applicant["phone"] ?: "N/A"
                        ) ?>
                    </td>

                    <td>
                        <?= (int) $applicant["total_applications"] ?>
                    </td>

                    <td>
                        <?= $applicant["is_active"]
                            ? "Active"
                            : "Inactive"
                        ?>
                    </td>

                    <td>
                        <?= date(
                            "M d, Y",
                            strtotime($applicant["created_at"])
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
        "applicant-report.pdf",
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

    <title>Applicant Report</title>

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
            max-width: 1250px;
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
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
            font-size: 13px;
        }

        th {
            background: #f8fafc;
        }

        .actions {
            display: flex;
            gap: 8px;
            align-items: end;
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border: none;
            border-radius: 5px;
            color: white;
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

        @media (max-width: 800px) {
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
            <h1>Applicant Report</h1>
            <p>Applicant accounts and application activity.</p>
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
            <label>Account Status</label>

            <select name="account_status">
                <option value="">All</option>

                <option
                    value="active"
                    <?= $accountStatus === "active"
                        ? "selected"
                        : ""
                    ?>
                >
                    Active
                </option>

                <option
                    value="inactive"
                    <?= $accountStatus === "inactive"
                        ? "selected"
                        : ""
                    ?>
                >
                    Inactive
                </option>
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

            <a
                href="report-applicants.php"
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
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Applications</th>
                    <th>Status</th>
                    <th>Registered</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$applicants): ?>
                <tr>
                    <td colspan="6">
                        No applicants found.
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($applicants as $applicant): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $applicant["first_name"]
                            . " "
                            . $applicant["last_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($applicant["email"]) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $applicant["phone"] ?: "N/A"
                        ) ?>
                    </td>

                    <td>
                        <?= (int) $applicant["total_applications"] ?>
                    </td>

                    <td>
                        <?= $applicant["is_active"]
                            ? "Active"
                            : "Inactive"
                        ?>
                    </td>

                    <td>
                        <?= date(
                            "M d, Y",
                            strtotime($applicant["created_at"])
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