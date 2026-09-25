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
| GET JOB ID
|--------------------------------------------------------------------------
*/

$jobId =
    isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($jobId <= 0) {

    header("Location: jobs.php");
    exit;
}


$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| HANDLE JOB STATUS ACTION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action =
        $_POST["action"]
        ?? "";


    $allowedActions = [
        "approve",
        "reject",
        "close",
        "pending"
    ];


    if (
        !in_array(
            $action,
            $allowedActions,
            true
        )
    ) {

        $error =
            "Invalid job action.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | GET JOB FOR VALIDATION
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT

                    j.id,
                    j.status,
                    j.address,
                    j.barangay,
                    j.caloocan_area,
                    j.latitude,
                    j.longtitude,

                    e.verification_status
                        AS employer_verification_status

                FROM jobs j

                INNER JOIN employers e
                    ON j.employer_id = e.id

                WHERE j.id = ?

                LIMIT 1
            ");


            $stmt->execute([
                $jobId
            ]);


            $jobCheck =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$jobCheck) {

                throw new Exception(
                    "Job not found."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | APPROVE JOB
            |--------------------------------------------------------------------------
            */

            if ($action === "approve") {


                /*
                |--------------------------------------------------------------------------
                | EMPLOYER MUST BE VERIFIED
                |--------------------------------------------------------------------------
                */

                if (
                    $jobCheck[
                        "employer_verification_status"
                    ] !== "approved"
                ) {

                    throw new Exception(
                        "This job cannot be approved because the employer is not verified."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | LOCATION MUST EXIST
                |--------------------------------------------------------------------------
                */

                if (
                    empty(
                        $jobCheck["address"]
                    ) ||
                    empty(
                        $jobCheck["barangay"]
                    ) ||
                    empty(
                        $jobCheck["latitude"]
                    ) ||
                    empty(
                        $jobCheck["longtitude"]
                    )
                ) {

                    throw new Exception(
                        "This job cannot be approved because the job location is incomplete."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | MUST BE NORTH OR SOUTH CALOOCAN
                |--------------------------------------------------------------------------
                */

                if (
                    !in_array(
                        $jobCheck[
                            "caloocan_area"
                        ],
                        [
                            "north",
                            "south"
                        ],
                        true
                    )
                ) {

                    throw new Exception(
                        "This job cannot be approved because the Caloocan area is invalid."
                    );
                }


                $newStatus =
                    "approved";
            }


            /*
            |--------------------------------------------------------------------------
            | REJECT JOB
            |--------------------------------------------------------------------------
            */

            elseif (
                $action === "reject"
            ) {

                $newStatus =
                    "rejected";
            }


            /*
            |--------------------------------------------------------------------------
            | CLOSE JOB
            |--------------------------------------------------------------------------
            */

            elseif (
                $action === "close"
            ) {

                $newStatus =
                    "closed";
            }


            /*
            |--------------------------------------------------------------------------
            | SET BACK TO PENDING
            |--------------------------------------------------------------------------
            */

            else {

                $newStatus =
                    "pending";
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE JOB STATUS
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE jobs

                SET status = ?

                WHERE id = ?
            ");


            $stmt->execute([
                $newStatus,
                $jobId
            ]);


            $success =
                "Job status updated to "
                . ucfirst(
                    $newStatus
                )
                . ".";


        } catch (Exception $e) {

            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET COMPLETE JOB DETAILS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        j.*,

        e.id AS employer_id,

        e.company_name,

        e.company_description,

        e.contact_person,

        e.contact_number,

        e.address AS employer_address,

        e.barangay AS employer_barangay,

        e.caloocan_area AS employer_area,

        e.verification_status
            AS employer_verification_status,

        u.email
            AS employer_email,

        (
            SELECT COUNT(*)

            FROM applications a

            WHERE a.job_id = j.id

        ) AS applicant_count

    FROM jobs j

    INNER JOIN employers e
        ON j.employer_id = e.id

    INNER JOIN users u
        ON e.user_id = u.id

    WHERE j.id = ?

    LIMIT 1
");


$stmt->execute([
    $jobId
]);


$job =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$job) {

    die(
        "Job not found."
    );
}


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
    View Job - Admin
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
        #333;
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
        1200px;

    margin:
        40px auto;

    padding:
        0 20px;
}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.page-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;

    margin-bottom:
        25px;
}


.page-header h1 {

    margin-bottom:
        7px;
}


.page-header p {

    color:
        #666;
}


/*
|--------------------------------------------------------------------------
| GRID
|--------------------------------------------------------------------------
*/

.grid {

    display:
        grid;

    grid-template-columns:
        2fr 1fr;

    gap:
        25px;

    align-items:
        start;
}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.card {

    background:
        white;

    padding:
        25px;

    border-radius:
        10px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);

    margin-bottom:
        20px;
}


.card h2 {

    margin-bottom:
        20px;
}


/*
|--------------------------------------------------------------------------
| INFORMATION
|--------------------------------------------------------------------------
*/

.info-row {

    padding:
        13px 0;

    border-bottom:
        1px solid #eee;
}


.info-row:last-child {

    border-bottom:
        none;
}


.info-label {

    color:
        #666;

    font-size:
        13px;

    margin-bottom:
        5px;
}


.info-value {

    font-size:
        14px;

    line-height:
        1.6;

    word-break:
        break-word;
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
        6px 11px;

    border-radius:
        20px;

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
| VERIFIED EMPLOYER
|--------------------------------------------------------------------------
*/

.verified {

    color:
        #166534;

    font-weight:
        bold;
}


.not-verified {

    color:
        #991b1b;

    font-weight:
        bold;
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
        6px 10px;

    border-radius:
        20px;

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
| MAP
|--------------------------------------------------------------------------
*/

#map {

    width:
        100%;

    height:
        350px;

    border:
        1px solid #ddd;

    border-radius:
        8px;

    margin-top:
        15px;
}


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

.message {

    padding:
        14px 16px;

    border-radius:
        7px;

    margin-bottom:
        20px;

    line-height:
        1.5;
}


.message-success {

    background:
        #dcfce7;

    color:
        #166534;

    border:
        1px solid #bbf7d0;
}


.message-error {

    background:
        #fee2e2;

    color:
        #991b1b;

    border:
        1px solid #fecaca;
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
        10px 15px;

    border-radius:
        6px;

    color:
        white;

    text-decoration:
        none;

    cursor:
        pointer;

    font-size:
        14px;
}


.btn-back {

    background:
        #64748b;
}


.btn-approve {

    background:
        #16a34a;
}


.btn-reject {

    background:
        #dc2626;
}


.btn-close {

    background:
        #475569;
}


.btn-pending {

    background:
        #f59e0b;
}


.btn-employer {

    background:
        #2563eb;

    margin-top:
        10px;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.actions {

    display:
        flex;

    flex-direction:
        column;

    gap:
        10px;
}


.actions form {

    width:
        100%;
}


.actions button {

    width:
        100%;
}


/*
|--------------------------------------------------------------------------
| REVIEW BOX
|--------------------------------------------------------------------------
*/

.review-box {

    border:
        2px solid #dbeafe;

    background:
        #eff6ff;
}


.review-note {

    color:
        #666;

    font-size:
        14px;

    line-height:
        1.6;

    margin-bottom:
        20px;
}


/*
|--------------------------------------------------------------------------
| LOCATION VERIFIED BOX
|--------------------------------------------------------------------------
*/

.location-check {

    margin-top:
        15px;

    padding:
        13px;

    border-radius:
        7px;

    background:
        #dcfce7;

    color:
        #166534;

    font-size:
        13px;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .grid {

        grid-template-columns:
            1fr;
    }


    .page-header {

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .navbar {

        padding:
            15px 20px;
    }


    .navbar-container {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            15px;
    }


    .nav-links {

        flex-wrap:
            wrap;

        gap:
            12px;
    }


    #map {

        height:
            280px;
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



<div class="container">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">


        <div>

            <h1>

                <?= htmlspecialchars(
                    $job[
                        "job_title"
                    ]
                ) ?>

            </h1>


            <p>
                Review complete job posting information.
            </p>

        </div>


        <a
            href="jobs.php"
            class="
                btn
                btn-back
            "
        >
            ← Back to Jobs
        </a>


    </div>



    <!-- SUCCESS -->

    <?php if ($success): ?>

        <div
            class="
                message
                message-success
            "
        >

            <?= htmlspecialchars(
                $success
            ) ?>

        </div>

    <?php endif; ?>



    <!-- ERROR -->

    <?php if ($error): ?>

        <div
            class="
                message
                message-error
            "
        >

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endif; ?>



    <div class="grid">


        <!-- =================================================
             LEFT COLUMN
        ================================================== -->

        <div>


            <!-- =================================================
                 JOB INFORMATION
            ================================================== -->

            <div class="card">


                <h2>
                    Job Information
                </h2>



                <div class="info-row">

                    <div class="info-label">
                        Job Title
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "job_title"
                            ]
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Employment Type
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "employment_type"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Job Description
                    </div>

                    <div class="info-value">

                        <?= nl2br(
                            htmlspecialchars(
                                $job[
                                    "job_description"
                                ]
                                ?: "N/A"
                            )
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Requirements
                    </div>

                    <div class="info-value">

                        <?= nl2br(
                            htmlspecialchars(
                                $job[
                                    "requirements"
                                ]
                                ?: "N/A"
                            )
                        ) ?>

                    </div>

                </div>



                <!-- SALARY -->

                <div class="info-row">

                    <div class="info-label">
                        Salary
                    </div>

                    <div class="info-value">


                        <?php if (
                            !empty(
                                $job[
                                    "salary_min"
                                ]
                            ) &&
                            !empty(
                                $job[
                                    "salary_max"
                                ]
                            )
                        ): ?>


                            ₱<?= number_format(
                                $job[
                                    "salary_min"
                                ],
                                2
                            ) ?>

                            -

                            ₱<?= number_format(
                                $job[
                                    "salary_max"
                                ],
                                2
                            ) ?>


                        <?php elseif (
                            !empty(
                                $job[
                                    "salary_min"
                                ]
                            )
                        ): ?>


                            From

                            ₱<?= number_format(
                                $job[
                                    "salary_min"
                                ],
                                2
                            ) ?>


                        <?php elseif (
                            !empty(
                                $job[
                                    "salary_max"
                                ]
                            )
                        ): ?>


                            Up to

                            ₱<?= number_format(
                                $job[
                                    "salary_max"
                                ],
                                2
                            ) ?>


                        <?php else: ?>


                            Not specified


                        <?php endif; ?>


                    </div>

                </div>



                <!-- DEADLINE -->

                <div class="info-row">

                    <div class="info-label">
                        Application Deadline
                    </div>

                    <div class="info-value">


                        <?php if (
                            !empty(
                                $job[
                                    "application_deadline"
                                ]
                            )
                        ): ?>


                            <?= date(
                                "F d, Y",
                                strtotime(
                                    $job[
                                        "application_deadline"
                                    ]
                                )
                            ) ?>


                        <?php else: ?>

                            N/A

                        <?php endif; ?>


                    </div>

                </div>



                <!-- APPLICANTS -->

                <div class="info-row">

                    <div class="info-label">
                        Applicants
                    </div>

                    <div class="info-value">

                        <?= (int)
                            $job[
                                "applicant_count"
                            ]
                        ?>

                        applicant(s)

                    </div>

                </div>


            </div>



            <!-- =================================================
                 JOB LOCATION
            ================================================== -->

            <div class="card">


                <h2>
                    Job Location
                </h2>



                <!-- READABLE LOCATION -->

                <div class="info-row">

                    <div class="info-label">
                        Location
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "location"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <!-- ADDRESS -->

                <div class="info-row">

                    <div class="info-label">
                        Complete Address
                    </div>

                    <div class="info-value">

                        <?= nl2br(
                            htmlspecialchars(
                                $job[
                                    "address"
                                ]
                                ?: "N/A"
                            )
                        ) ?>

                    </div>

                </div>



                <!-- BARANGAY -->

                <div class="info-row">

                    <div class="info-label">
                        Barangay
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "barangay"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <!-- AREA -->

                <div class="info-row">

                    <div class="info-label">
                        Caloocan Area
                    </div>

                    <div class="info-value">


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

                                CALOOCAN

                            </span>


                        <?php else: ?>

                            N/A

                        <?php endif; ?>


                    </div>

                </div>



                <!-- LAT -->

                <div class="info-row">

                    <div class="info-label">
                        Latitude
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "latitude"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <!-- LNG -->

                <div class="info-row">

                    <div class="info-label">
                        Longitude
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "longtitude"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <!-- MAP -->

                <?php if (
                    !empty(
                        $job[
                            "latitude"
                        ]
                    ) &&
                    !empty(
                        $job[
                            "longtitude"
                        ]
                    )
                ): ?>


                    <iframe
                        id="map"

                        src="https://www.google.com/maps?q=<?= urlencode(
                            $job[
                                "latitude"
                            ]
                        ) ?>,<?= urlencode(
                            $job[
                                "longtitude"
                            ]
                        ) ?>&z=17&output=embed"

                        loading="lazy"

                        allowfullscreen
                    ></iframe>


                <?php endif; ?>



                <?php if (
                    !empty(
                        $job[
                            "address"
                        ]
                    ) &&
                    !empty(
                        $job[
                            "barangay"
                        ]
                    ) &&
                    in_array(
                        $job[
                            "caloocan_area"
                        ],
                        [
                            "north",
                            "south"
                        ],
                        true
                    )
                ): ?>


                    <div class="location-check">

                        ✓ Complete Caloocan location
                        information is available.

                    </div>


                <?php endif; ?>


            </div>



            <!-- =================================================
                 EMPLOYER
            ================================================== -->

            <div class="card">


                <h2>
                    Employer Information
                </h2>



                <div class="info-row">

                    <div class="info-label">
                        Company
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "company_name"
                            ]
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Employer Verification
                    </div>

                    <div class="info-value">


                        <?php if (
                            $job[
                                "employer_verification_status"
                            ] === "approved"
                        ): ?>


                            <span class="verified">

                                ✓ VERIFIED EMPLOYER

                            </span>


                        <?php else: ?>


                            <span class="not-verified">

                                NOT VERIFIED

                            </span>


                        <?php endif; ?>


                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Contact Person
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "contact_person"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Contact Number
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "contact_number"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $job[
                                "employer_email"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <a
                    href="view-employer.php?id=<?= (int)
                        $job[
                            "employer_id"
                        ]
                    ?>"
                    class="
                        btn
                        btn-employer
                    "
                >

                    View Employer

                </a>


            </div>


        </div>



        <!-- =================================================
             RIGHT COLUMN
        ================================================== -->

        <div>


            <!-- =================================================
                 STATUS
            ================================================== -->

            <div class="card">


                <h2>
                    Job Status
                </h2>


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


                <div
                    style="
                        margin-top:18px;
                        color:#666;
                        font-size:13px;
                        line-height:1.6;
                    "
                >

                    Posted:

                    <strong>

                        <?= date(
                            "F d, Y",
                            strtotime(
                                $job[
                                    "created_at"
                                ]
                            )
                        ) ?>

                    </strong>

                </div>


            </div>



            <!-- =================================================
                 REVIEW
            ================================================== -->

            <div
                class="
                    card
                    review-box
                "
            >


                <h2>
                    Review Job
                </h2>


                <p class="review-note">

                    Check the job information,
                    verified employer, and
                    Caloocan location before
                    approving this job.

                </p>



                <div class="actions">


                    <!-- =========================================
                         APPROVE
                    ========================================== -->

                    <?php if (
                        $job[
                            "status"
                        ] !== "approved"
                    ): ?>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="approve"
                            >


                            <button
                                type="submit"
                                class="
                                    btn
                                    btn-approve
                                "
                            >

                                ✓ Approve Job

                            </button>


                        </form>


                    <?php endif; ?>



                    <!-- =========================================
                         REJECT
                    ========================================== -->

                    <?php if (
                        $job[
                            "status"
                        ] !== "rejected"
                    ): ?>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="reject"
                            >


                            <button
                                type="submit"
                                class="
                                    btn
                                    btn-reject
                                "
                            >

                                Reject Job

                            </button>


                        </form>


                    <?php endif; ?>



                    <!-- =========================================
                         CLOSE
                    ========================================== -->

                    <?php if (
                        $job[
                            "status"
                        ] !== "closed"
                    ): ?>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="close"
                            >


                            <button
                                type="submit"
                                class="
                                    btn
                                    btn-close
                                "
                            >

                                Close Job

                            </button>


                        </form>


                    <?php endif; ?>



                    <!-- =========================================
                         RETURN TO PENDING
                    ========================================== -->

                    <?php if (
                        $job[
                            "status"
                        ] !== "pending"
                    ): ?>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="action"
                                value="pending"
                            >


                            <button
                                type="submit"
                                class="
                                    btn
                                    btn-pending
                                "
                            >

                                Set to Pending

                            </button>


                        </form>


                    <?php endif; ?>


                </div>


            </div>


        </div>


    </div>


</div>


</body>

</html>