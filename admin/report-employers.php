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

$employerType = trim($_GET["employer_type"] ?? "");
$accountStatus = trim($_GET["account_status"] ?? "");
$verificationStatus = trim($_GET["verification_status"] ?? "");

$where = [];
$params = [];

if (in_array($employerType, ["private", "lgu"], true)) {
    $where[] = "e.employer_type = ?";
    $params[] = $employerType;
}

if ($accountStatus === "active") {
    $where[] = "u.is_active = 1";
} elseif ($accountStatus === "inactive") {
    $where[] = "u.is_active = 0";
}

if (
    in_array(
        $verificationStatus,
        ["pending", "approved", "rejected"],
        true
    )
) {
    $where[] = "e.verification_status = ?";
    $params[] = $verificationStatus;
}

$whereSql = $where
    ? "WHERE " . implode(" AND ", $where)
    : "";

$stmt = $pdo->prepare("
    SELECT
        e.id,
        e.company_name,
        e.employer_type,
        e.verification_status,
        e.created_at,
        u.email,
        u.is_active,
        (
            SELECT COUNT(*)
            FROM jobs j
            WHERE j.employer_id = e.id
        ) AS total_jobs
    FROM employers e
    INNER JOIN users u
        ON e.user_id = u.id
    $whereSql
    ORDER BY e.created_at DESC
");

$stmt->execute($params);

$employers = $stmt->fetchAll(PDO::FETCH_ASSOC);


$pdfQuery = $_GET;
$pdfQuery["download"] = "pdf";

$pdfUrl =
    "report-employers.php?"
    . http_build_query($pdfQuery);


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
                font-size: 10px;
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
        <h2>Employer Report</h2>

        <p>Generated: <?= date("F d, Y h:i A") ?></p>

        <table>
            <thead>
                <tr>
                    <th>Employer</th>
                    <th>Type</th>
                    <th>Email</th>
                    <th>Jobs</th>
                    <th>Verification</th>
                    <th>Account</th>
                    <th>Registered</th>
                </tr>
            </thead>

            <tbody>

            <?php foreach ($employers as $employer): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars($employer["company_name"]) ?>
                    </td>

                    <td>
                        <?= $employer["employer_type"] === "lgu"
                            ? "LGU"
                            : "Private Employer"
                        ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($employer["email"]) ?>
                    </td>

                    <td>
                        <?= (int) $employer["total_jobs"] ?>
                    </td>

                    <td>
                        <?= ucfirst($employer["verification_status"]) ?>
                    </td>

                    <td>
                        <?= $employer["is_active"]
                            ? "Active"
                            : "Inactive"
                        ?>
                    </td>

                    <td>
                        <?= date(
                            "M d, Y",
                            strtotime($employer["created_at"])
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
        "employer-report.pdf",
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

    <title>Employer Report</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
        }

        .container {
            max-width: 1300px;
            margin: 35px auto;
            padding: 0 20px 50px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background: #ffffff;
            border-radius: 8px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 13px;
        }

        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
        }

        .table-box {
            overflow-x: auto;
            background: #ffffff;
            border-radius: 8px;
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

        .actions {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 800px) {
            .filters {
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
            <h1>Employer Report</h1>
            <p>Private and LGU employer accounts.</p>
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
            <label>Employer Type</label>

            <select name="employer_type">
                <option value="">All Types</option>

                <option
                    value="private"
                    <?= $employerType === "private"
                        ? "selected"
                        : ""
                    ?>
                >
                    Private Employer
                </option>

                <option
                    value="lgu"
                    <?= $employerType === "lgu"
                        ? "selected"
                        : ""
                    ?>
                >
                    LGU
                </option>
            </select>
        </div>

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
            <label>Verification</label>

            <select name="verification_status">
                <option value="">All</option>

                <option
                    value="pending"
                    <?= $verificationStatus === "pending"
                        ? "selected"
                        : ""
                    ?>
                >
                    Pending
                </option>

                <option
                    value="approved"
                    <?= $verificationStatus === "approved"
                        ? "selected"
                        : ""
                    ?>
                >
                    Approved
                </option>

                <option
                    value="rejected"
                    <?= $verificationStatus === "rejected"
                        ? "selected"
                        : ""
                    ?>
                >
                    Rejected
                </option>
            </select>
        </div>

        <div class="actions">
            <button type="submit" class="btn blue">
                Apply
            </button>

            <a
                href="report-employers.php"
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
                    <th>Employer</th>
                    <th>Type</th>
                    <th>Email</th>
                    <th>Jobs</th>
                    <th>Verification</th>
                    <th>Account</th>
                    <th>Registered</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$employers): ?>

                <tr>
                    <td colspan="7">
                        No employers found.
                    </td>
                </tr>

            <?php endif; ?>

            <?php foreach ($employers as $employer): ?>

                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $employer["company_name"]
                        ) ?>
                    </td>

                    <td>
                        <?= $employer["employer_type"] === "lgu"
                            ? "LGU"
                            : "Private Employer"
                        ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($employer["email"]) ?>
                    </td>

                    <td>
                        <?= (int) $employer["total_jobs"] ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            ucfirst(
                                $employer["verification_status"]
                            )
                        ) ?>
                    </td>

                    <td>
                        <?= $employer["is_active"]
                            ? "Active"
                            : "Inactive"
                        ?>
                    </td>

                    <td>
                        <?= date(
                            "M d, Y",
                            strtotime(
                                $employer["created_at"]
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