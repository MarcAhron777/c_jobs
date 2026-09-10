<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| ADMIN ACCESS ONLY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"])
    || $_SESSION["role"] !== "admin"
) {

    header("Location: ../login.php");
    exit;

}


$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| UPDATE JOB STATUS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $jobId = $_POST["job_id"] ?? null;
    $status = $_POST["status"] ?? null;


    $allowedStatuses = [
        "pending",
        "approved",
        "rejected",
        "closed"
    ];


    /*
    |--------------------------------------------------------------------------
    | Validate Status
    |--------------------------------------------------------------------------
    */

    if (
        !$jobId
        || !in_array($status, $allowedStatuses, true)
    ) {

        $error = "Invalid job status.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Job Location
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                location,
                status
            FROM jobs
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $jobId
        ]);

        $job = $stmt->fetch();


        if (!$job) {

            $error = "Job not found.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Caloocan Only Protection
            |--------------------------------------------------------------------------
            |
            | A job can only be approved if its location
            | is Caloocan City.
            |
            */

            if ($status === "approved") {

                $jobLocation = strtolower(
                    trim($job["location"] ?? "")
                );


                if (
                    $jobLocation === ""
                    || strpos(
                        $jobLocation,
                        "caloocan"
                    ) === false
                ) {

                    $error =
                        "This job cannot be approved because "
                        . "the job location must be Caloocan City.";

                }

            }


            /*
            |--------------------------------------------------------------------------
            | Update Status
            |--------------------------------------------------------------------------
            */

            if ($error === "") {

                try {

                    $stmt = $pdo->prepare("
                        UPDATE jobs
                        SET status = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $status,
                        $jobId
                    ]);


                    $success =
                        "Job status updated successfully.";

                } catch (PDOException $e) {

                    $error =
                        "Failed to update job status.";

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| GET ALL JOBS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("

    SELECT

        j.id,
        j.job_title,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.is_remote,
        j.status,
        j.application_deadline,
        j.created_at,

        e.company_name,

        (
            SELECT COUNT(*)
            FROM applications a
            WHERE a.job_id = j.id
        ) AS applicant_count

    FROM jobs j

    INNER JOIN employers e
        ON j.employer_id = e.id

    ORDER BY j.created_at DESC

");

$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

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


        /*
        |--------------------------------------------------------------------------
        | NAVBAR
        |--------------------------------------------------------------------------
        */

        .navbar {
            background: #111827;

            padding: 18px 50px;
        }


        .navbar-container {
            max-width: 1200px;

            margin: auto;

            display: flex;

            justify-content: space-between;

            align-items: center;
        }


        .logo {
            color: white;

            text-decoration: none;

            font-size: 22px;

            font-weight: bold;
        }


        .nav-links {
            display: flex;

            gap: 20px;

            align-items: center;
        }


        .nav-links a {
            color: white;

            text-decoration: none;

            font-size: 14px;
        }


        .nav-links a:hover {
            color: #93c5fd;
        }


        .logout {
            background: #dc2626;

            padding: 8px 14px;

            border-radius: 5px;
        }


        /*
        |--------------------------------------------------------------------------
        | CONTAINER
        |--------------------------------------------------------------------------
        */

        .container {
            max-width: 1250px;

            margin: 40px auto;

            padding: 0 20px;
        }


        .page-header {
            margin-bottom: 30px;
        }


        .page-header h1 {
            margin-bottom: 8px;
        }


        .page-header p {
            color: #666;
        }


        /*
        |--------------------------------------------------------------------------
        | MESSAGE
        |--------------------------------------------------------------------------
        */

        .message {
            padding: 14px 16px;

            border-radius: 6px;

            margin-bottom: 20px;

            line-height: 1.5;
        }


        .message-error {
            background: #fee2e2;

            color: #991b1b;

            border: 1px solid #fecaca;
        }


        .message-success {
            background: #dcfce7;

            color: #166534;

            border: 1px solid #bbf7d0;
        }


        /*
        |--------------------------------------------------------------------------
        | CALOOCAN NOTICE
        |--------------------------------------------------------------------------
        */

        .location-notice {
            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1e40af;

            padding: 15px 18px;

            border-radius: 8px;

            margin-bottom: 20px;

            line-height: 1.5;
        }


        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .table-container {
            background: white;

            border-radius: 8px;

            border: 1px solid #e5e7eb;

            overflow-x: auto;
        }


        table {
            width: 100%;

            border-collapse: collapse;
        }


        th,
        td {
            padding: 15px;

            text-align: left;

            border-bottom: 1px solid #eee;
        }


        th {
            background: #f9fafb;

            font-size: 13px;

            color: #555;
        }


        td {
            font-size: 14px;
        }


        tr:last-child td {
            border-bottom: none;
        }


        /*
        |--------------------------------------------------------------------------
        | STATUS
        |--------------------------------------------------------------------------
        */

        .status {
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


        /*
        |--------------------------------------------------------------------------
        | LOCATION
        |--------------------------------------------------------------------------
        */

        .caloocan-location {
            color: #166534;

            font-weight: bold;
        }


        .invalid-location {
            color: #991b1b;

            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | ACTION BUTTONS
        |--------------------------------------------------------------------------
        */

        .actions {
            display: flex;

            gap: 6px;

            flex-wrap: wrap;
        }


        .actions form {
            display: inline;
        }


        .btn {
            border: none;

            padding: 7px 10px;

            border-radius: 4px;

            cursor: pointer;

            font-size: 12px;

            font-weight: 600;
        }


        .btn-approve {
            background: #16a34a;

            color: white;
        }


        .btn-approve:hover {
            background: #15803d;
        }


        .btn-reject {
            background: #dc2626;

            color: white;
        }


        .btn-reject:hover {
            background: #b91c1c;
        }


        .btn-close {
            background: #6b7280;

            color: white;
        }


        .btn-close:hover {
            background: #4b5563;
        }


        .btn-pending {
            background: #f59e0b;

            color: white;
        }


        .btn-pending:hover {
            background: #d97706;
        }


        .no-jobs {
            text-align: center;

            padding: 40px;

            color: #777;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .navbar {
                padding: 15px 20px;
            }


            .navbar-container {
                flex-direction: column;

                gap: 15px;
            }


            .nav-links {
                flex-wrap: wrap;

                justify-content: center;
            }

        }

    </style>

</head>


<body>


<!--
|--------------------------------------------------------------------------
| NAVBAR
|--------------------------------------------------------------------------
-->

<nav class="navbar">

    <div class="navbar-container">


        <a
            href="dashboard.php"
            class="logo"
        >
            Job Portal Admin
        </a>


        <div class="nav-links">

            <a href="dashboard.php">
                Dashboard
            </a>


            <a href="jobs.php">
                Jobs
            </a>


            <a href="employers.php">
                Employers
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

    </div>

</nav>



<!--
|--------------------------------------------------------------------------
| MAIN CONTENT
|--------------------------------------------------------------------------
-->

<div class="container">


    <div class="page-header">

        <h1>
            Manage Jobs
        </h1>

        <p>
            Review, approve, reject, and close employer job postings.
        </p>

    </div>



    <!--
    |--------------------------------------------------------------------------
    | SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    -->

    <?php if ($success): ?>

        <div class="message message-success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>



    <!--
    |--------------------------------------------------------------------------
    | ERROR MESSAGE
    |--------------------------------------------------------------------------
    -->

    <?php if ($error): ?>

        <div class="message message-error">

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>



    <!--
    |--------------------------------------------------------------------------
    | LOCATION NOTICE
    |--------------------------------------------------------------------------
    -->

    <div class="location-notice">

        <strong>
            📍 Caloocan City Job Portal
        </strong>

        <br>

        Only job postings located in
        <strong>Caloocan City</strong>
        can be approved and shown to applicants.

    </div>



    <!--
    |--------------------------------------------------------------------------
    | JOB TABLE
    |--------------------------------------------------------------------------
    -->

    <div class="table-container">

        <table>


            <thead>

                <tr>

                    <th>
                        Job Title
                    </th>

                    <th>
                        Company
                    </th>

                    <th>
                        Location
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Applicants
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Posted
                    </th>

                    <th>
                        Actions
                    </th>

                </tr>

            </thead>



            <tbody>


                <?php if (empty($jobs)): ?>


                    <tr>

                        <td
                            colspan="8"
                            class="no-jobs"
                        >

                            No jobs found.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($jobs as $job): ?>


                        <?php

                        $jobLocation = strtolower(
                            trim($job["location"] ?? "")
                        );


                        $isCaloocan = (
                            $jobLocation !== ""
                            && strpos(
                                $jobLocation,
                                "caloocan"
                            ) !== false
                        );


                        $statusClass = match (
                            $job["status"]
                        ) {

                            "pending" =>
                                "status-pending",

                            "approved" =>
                                "status-approved",

                            "rejected" =>
                                "status-rejected",

                            "closed" =>
                                "status-closed",

                            default =>
                                ""

                        };

                        ?>


                        <tr>


                            <!-- JOB TITLE -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $job["job_title"]
                                    ) ?>

                                </strong>

                            </td>



                            <!-- COMPANY -->

                            <td>

                                <?= htmlspecialchars(
                                    $job["company_name"]
                                ) ?>

                            </td>



                            <!-- LOCATION -->

                            <td>

                                <?php if ($isCaloocan): ?>

                                    <span class="caloocan-location">

                                        📍
                                        <?= htmlspecialchars(
                                            $job["location"]
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="invalid-location">

                                        ⚠️
                                        <?= htmlspecialchars(
                                            $job["location"]
                                            ?: "N/A"
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <?php if ($job["is_remote"]): ?>

                                    <br>

                                    <small>
                                        Remote
                                    </small>

                                <?php endif; ?>

                            </td>



                            <!-- EMPLOYMENT TYPE -->

                            <td>

                                <?= htmlspecialchars(
                                    $job["employment_type"]
                                ) ?>

                            </td>



                            <!-- APPLICANTS -->

                            <td>

                                <?= (int)$job["applicant_count"] ?>

                            </td>



                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status <?= $statusClass ?>"
                                >

                                    <?= htmlspecialchars(
                                        ucfirst(
                                            $job["status"]
                                        )
                                    ) ?>

                                </span>

                            </td>



                            <!-- DATE -->

                            <td>

                                <?= date(
                                    "M d, Y",
                                    strtotime(
                                        $job["created_at"]
                                    )
                                ) ?>

                            </td>



                            <!-- ACTIONS -->

                            <td>

                                <div class="actions">


                                    <!--
                                    |--------------------------------------------------------------------------
                                    | APPROVE
                                    |--------------------------------------------------------------------------
                                    -->

                                    <?php if (
                                        $job["status"] !== "approved"
                                        && $isCaloocan
                                    ): ?>


                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="job_id"
                                                value="<?= (int)$job["id"] ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="status"
                                                value="approved"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-approve"
                                            >

                                                Approve

                                            </button>

                                        </form>


                                    <?php endif; ?>



                                    <!--
                                    |--------------------------------------------------------------------------
                                    | REJECT
                                    |--------------------------------------------------------------------------
                                    -->

                                    <?php if (
                                        $job["status"] !== "rejected"
                                    ): ?>


                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="job_id"
                                                value="<?= (int)$job["id"] ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="status"
                                                value="rejected"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-reject"
                                            >

                                                Reject

                                            </button>

                                        </form>


                                    <?php endif; ?>



                                    <!--
                                    |--------------------------------------------------------------------------
                                    | CLOSE
                                    |--------------------------------------------------------------------------
                                    -->

                                    <?php if (
                                        $job["status"] !== "closed"
                                    ): ?>


                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="job_id"
                                                value="<?= (int)$job["id"] ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="status"
                                                value="closed"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-close"
                                            >

                                                Close

                                            </button>

                                        </form>


                                    <?php endif; ?>



                                    <!--
                                    |--------------------------------------------------------------------------
                                    | SET BACK TO PENDING
                                    |--------------------------------------------------------------------------
                                    -->

                                    <?php if (
                                        $job["status"] !== "pending"
                                    ): ?>


                                        <form method="POST">

                                            <input
                                                type="hidden"
                                                name="job_id"
                                                value="<?= (int)$job["id"] ?>"
                                            >


                                            <input
                                                type="hidden"
                                                name="status"
                                                value="pending"
                                            >


                                            <button
                                                type="submit"
                                                class="btn btn-pending"
                                            >

                                                Pending

                                            </button>

                                        </form>


                                    <?php endif; ?>


                                </div>

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