<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| ADMIN ACCESS ONLY
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
| GET ALL JOBS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("

    SELECT

        j.id,
        j.job_title,
        j.location,
        j.address,
        j.barangay,
        j.caloocan_area,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.status,
        j.application_deadline,
        j.created_at,

        e.company_name,
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

    ORDER BY j.created_at DESC

");


$jobs =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
*/

function jobStatusClass($status)
{

    return match ($status) {

        "approved" =>
            "status-approved",

        "rejected" =>
            "status-rejected",

        "closed" =>
            "status-closed",

        default =>
            "status-pending"
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

/*
|--------------------------------------------------------------------------
| BASE
|--------------------------------------------------------------------------
*/

* {

    box-sizing:
        border-box;

    margin:
        0;

    padding:
        0;
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

    padding:
        18px 50px;
}


.navbar-container {

    max-width:
        1200px;

    margin:
        auto;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;
}


.logo {

    color:
        white;

    text-decoration:
        none;

    font-size:
        22px;

    font-weight:
        bold;
}


.nav-links {

    display:
        flex;

    gap:
        20px;

    align-items:
        center;
}


.nav-links a {

    color:
        white;

    text-decoration:
        none;

    font-size:
        14px;
}


.nav-links a:hover {

    color:
        #93c5fd;
}


.logout {

    background:
        #dc2626;

    padding:
        8px 14px;

    border-radius:
        5px;
}


/*
|--------------------------------------------------------------------------
| CONTAINER
|--------------------------------------------------------------------------
*/

.container {

    max-width:
        1250px;

    margin:
        40px auto;

    padding:
        0 20px;
}


.page-header {

    margin-bottom:
        30px;
}


.page-header h1 {

    margin-bottom:
        8px;
}


.page-header p {

    color:
        #666;
}


/*
|--------------------------------------------------------------------------
| NOTICE
|--------------------------------------------------------------------------
*/

.location-notice {

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    color:
        #1e40af;

    padding:
        15px 18px;

    border-radius:
        8px;

    margin-bottom:
        20px;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-container {

    background:
        white;

    border-radius:
        8px;

    border:
        1px solid #e5e7eb;

    overflow-x:
        auto;
}


table {

    width:
        100%;

    border-collapse:
        collapse;
}


th,
td {

    padding:
        15px;

    text-align:
        left;

    border-bottom:
        1px solid #eee;

    vertical-align:
        middle;
}


th {

    background:
        #f9fafb;

    font-size:
        13px;

    color:
        #555;
}


td {

    font-size:
        14px;
}


tr:last-child td {

    border-bottom:
        none;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status {

    display:
        inline-block;

    padding:
        5px 10px;

    border-radius:
        15px;

    font-size:
        12px;

    font-weight:
        bold;
}


.status-pending {

    background:
        #fef3c7;

    color:
        #92400e;
}


.status-approved {

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


.status-closed {

    background:
        #e5e7eb;

    color:
        #374151;
}


/*
|--------------------------------------------------------------------------
| AREA
|--------------------------------------------------------------------------
*/

.area {

    display:
        inline-block;

    padding:
        5px 10px;

    border-radius:
        15px;

    font-size:
        12px;

    font-weight:
        bold;
}


.north {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.south {

    background:
        #dcfce7;

    color:
        #166534;
}


/*
|--------------------------------------------------------------------------
| EMPLOYER
|--------------------------------------------------------------------------
*/

.verified-employer {

    color:
        #166534;

    font-size:
        12px;

    font-weight:
        bold;
}


.unverified-employer {

    color:
        #991b1b;

    font-size:
        12px;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.btn {

    display:
        inline-block;

    border:
        none;

    padding:
        8px 13px;

    border-radius:
        5px;

    cursor:
        pointer;

    font-size:
        12px;

    font-weight:
        600;

    text-decoration:
        none;
}


.btn-view {

    background:
        #2563eb;

    color:
        white;
}


.btn-view:hover {

    background:
        #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.no-jobs {

    text-align:
        center;

    padding:
        40px;

    color:
        #777;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .navbar {

        padding:
            15px 20px;
    }


    .navbar-container {

        flex-direction:
            column;

        gap:
            15px;

        align-items:
            flex-start;
    }


    .nav-links {

        flex-wrap:
            wrap;

        gap:
            12px;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

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
                Manage Jobs
            </a>

            <a href="employers.php">
                Manage Employers
            </a>

            <a href="applicants.php">
                Manage Applicants
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



<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <div class="page-header">

        <h1>
            Manage Jobs
        </h1>

        <p>
            Review employer job postings and manage their status.
        </p>

    </div>



    <div class="location-notice">

        <strong>
            📍 Caloocan City Job Portal
        </strong>

        <br>

        Click
        <strong>View</strong>
        to review the complete job information,
        employer verification, and location before approval.

    </div>



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
                        Barangay
                    </th>

                    <th>
                        Area
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
                        Action
                    </th>

                </tr>

            </thead>



            <tbody>


            <?php if (
                empty($jobs)
            ): ?>


                <tr>

                    <td
                        colspan="9"
                        class="no-jobs"
                    >

                        No jobs found.

                    </td>

                </tr>


            <?php else: ?>


                <?php foreach (
                    $jobs
                    as $job
                ): ?>


                    <tr>


                        <!-- JOB TITLE -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $job[
                                        "job_title"
                                    ]
                                ) ?>

                            </strong>

                        </td>



                        <!-- COMPANY -->

                        <td>

                            <?= htmlspecialchars(
                                $job[
                                    "company_name"
                                ]
                            ) ?>


                            <br>


                            <?php if (
                                $job[
                                    "employer_verification_status"
                                ] === "approved"
                            ): ?>

                                <span class="verified-employer">
                                    ✓ Verified Employer
                                </span>

                            <?php else: ?>

                                <span class="unverified-employer">
                                    Not Verified
                                </span>

                            <?php endif; ?>

                        </td>



                        <!-- BARANGAY -->

                        <td>

                            <?= htmlspecialchars(
                                $job[
                                    "barangay"
                                ]
                                ?: "N/A"
                            ) ?>

                        </td>



                        <!-- AREA -->

                        <td>


                            <?php if (
                                !empty(
                                    $job[
                                        "caloocan_area"
                                    ]
                                )
                            ): ?>


                                <span
                                    class="
                                        area
                                        <?= htmlspecialchars(
                                            $job[
                                                "caloocan_area"
                                            ]
                                        ) ?>
                                    "
                                >

                                    <?= strtoupper(
                                        htmlspecialchars(
                                            $job[
                                                "caloocan_area"
                                            ]
                                        )
                                    ) ?>

                                </span>


                            <?php else: ?>

                                N/A

                            <?php endif; ?>


                        </td>



                        <!-- TYPE -->

                        <td>

                            <?= htmlspecialchars(
                                $job[
                                    "employment_type"
                                ]
                                ?: "N/A"
                            ) ?>

                        </td>



                        <!-- APPLICANTS -->

                        <td>

                            <?= (int)
                                $job[
                                    "applicant_count"
                                ]
                            ?>

                        </td>



                        <!-- STATUS -->

                        <td>

                            <span
                                class="
                                    status
                                    <?= jobStatusClass(
                                        $job[
                                            "status"
                                        ]
                                    ) ?>
                                "
                            >

                                <?= strtoupper(
                                    htmlspecialchars(
                                        $job[
                                            "status"
                                        ]
                                    )
                                ) ?>

                            </span>

                        </td>



                        <!-- POSTED -->

                        <td>

                            <?= date(
                                "M d, Y",
                                strtotime(
                                    $job[
                                        "created_at"
                                    ]
                                )
                            ) ?>

                        </td>



                        <!-- ACTION -->

                        <td>

                            <a
                                href="view-job.php?id=<?= (int)
                                    $job[
                                        "id"
                                    ]
                                ?>"
                                class="
                                    btn
                                    btn-view
                                "
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