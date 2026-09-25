<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| AUTH CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "employer"
) {
    header("Location: ../login.php");
    exit;
}


$userId = $_SESSION["user_id"];


/*
|--------------------------------------------------------------------------
| GET EMPLOYER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        company_name,
        verification_status
    FROM employers
    WHERE user_id = ?
    LIMIT 1
");


$stmt->execute([
    $userId
]);


$employer = $stmt->fetch();


if (!$employer) {

    die(
        "Employer profile not found."
    );
}


$employerId =
    $employer["id"];


/*
|--------------------------------------------------------------------------
| EMPLOYER VERIFICATION STATUS
|--------------------------------------------------------------------------
*/

$verificationStatus =
    $employer[
        "verification_status"
    ]
    ?? "unverified";


$isVerified =
    $verificationStatus
    === "approved";


/*
|--------------------------------------------------------------------------
| URL MESSAGES
|--------------------------------------------------------------------------
*/

$created =
    isset(
        $_GET["created"]
    );


$verificationRequired =
    isset(
        $_GET[
            "verification_required"
        ]
    );


/*
|--------------------------------------------------------------------------
| GET JOBS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        j.id,
        j.job_title,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.status,
        j.application_deadline,
        j.created_at,

        (
            SELECT COUNT(*)
            FROM applications a
            WHERE a.job_id = j.id
        ) AS applicant_count

    FROM jobs j

    WHERE j.employer_id = ?

    ORDER BY j.created_at DESC
");


$stmt->execute([
    $employerId
]);


$jobs =
    $stmt->fetchAll();

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
    My Jobs - Caloocan Job Portal
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
        white;

    border-bottom:
        1px solid #ddd;

    padding:
        16px 40px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;
}


.logo {

    font-size:
        24px;

    font-weight:
        bold;

    color:
        #2563eb;
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

    text-decoration:
        none;

    color:
        #333;
}


.nav-links a:hover {

    color:
        #2563eb;
}


.nav-links .active {

    color:
        #2563eb;

    font-weight:
        bold;
}


.logout {

    color:
        #dc2626 !important;
}


/*
|--------------------------------------------------------------------------
| CONTAINER
|--------------------------------------------------------------------------
*/

.container {

    max-width:
        1100px;

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

.header {

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


.header h1 {

    margin-bottom:
        5px;
}


.header p {

    color:
        #666;
}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.btn {

    display:
        inline-block;

    padding:
        10px 15px;

    background:
        #2563eb;

    color:
        white;

    text-decoration:
        none;

    border-radius:
        6px;

    border:
        none;

    font-size:
        14px;
}


.btn:hover {

    background:
        #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| DISABLED BUTTON
|--------------------------------------------------------------------------
*/

.btn-disabled {

    background:
        #94a3b8;

    cursor:
        not-allowed;

    opacity:
        0.75;
}


.btn-disabled:hover {

    background:
        #94a3b8;
}


/*
|--------------------------------------------------------------------------
| SUCCESS MESSAGE
|--------------------------------------------------------------------------
*/

.message {

    background:
        #dcfce7;

    color:
        #166534;

    padding:
        14px 16px;

    border:
        1px solid #bbf7d0;

    border-radius:
        7px;

    margin-bottom:
        20px;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| VERIFICATION WARNING
|--------------------------------------------------------------------------
*/

.verification-warning {

    background:
        #fef3c7;

    color:
        #92400e;

    padding:
        18px;

    border:
        1px solid #fde68a;

    border-radius:
        8px;

    margin-bottom:
        25px;

    line-height:
        1.5;
}


.verification-warning h3 {

    margin-bottom:
        8px;
}


.verification-warning p {

    margin-top:
        5px;
}


.verification-link {

    display:
        inline-block;

    margin-top:
        12px;

    color:
        #92400e;

    font-weight:
        bold;

    text-decoration:
        underline;
}


/*
|--------------------------------------------------------------------------
| VERIFIED MESSAGE
|--------------------------------------------------------------------------
*/

.verified-message {

    background:
        #dcfce7;

    color:
        #166534;

    padding:
        14px 16px;

    border:
        1px solid #bbf7d0;

    border-radius:
        8px;

    margin-bottom:
        20px;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| JOB CARD
|--------------------------------------------------------------------------
*/

.job-card {

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
        15px;
}


.job-card h2 {

    margin-bottom:
        8px;
}


/*
|--------------------------------------------------------------------------
| META
|--------------------------------------------------------------------------
*/

.meta {

    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        8px;

    margin:
        12px 0;
}


.meta span {

    background:
        #f1f5f9;

    padding:
        6px 10px;

    border-radius:
        5px;

    font-size:
        13px;
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
        6px 12px;

    border-radius:
        20px;

    font-size:
        12px;

    font-weight:
        bold;

    margin-bottom:
        15px;
}


.pending {

    background:
        #fef3c7;

    color:
        #92400e;
}


.approved {

    background:
        #dcfce7;

    color:
        #166534;
}


.rejected {

    background:
        #fee2e2;

    color:
        #991b1b;
}


.closed {

    background:
        #e2e8f0;

    color:
        #475569;
}


/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/

.actions {

    display:
        flex;

    gap:
        8px;

    flex-wrap:
        wrap;
}


.action {

    display:
        inline-block;

    padding:
        8px 12px;

    border-radius:
        5px;

    text-decoration:
        none;

    background:
        #2563eb;

    color:
        white;

    font-size:
        13px;
}


.edit {

    background:
        #64748b;
}


.applicants {

    background:
        #059669;
}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.empty {

    background:
        white;

    padding:
        50px 25px;

    text-align:
        center;

    border-radius:
        10px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .header {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            15px;
    }


    .navbar {

        padding:
            15px 20px;

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

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">


    <div class="logo">
        JobPortal
    </div>


    <div class="nav-links">


        <a href="../index.php">
            Home
        </a>


        <a href="dashboard.php">
            Dashboard
        </a>


        <a href="profile.php">
            Profile
        </a>


        <a
            href="jobs.php"
            class="active"
        >
            My Jobs
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


</nav>



<div class="container">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="header">


        <div>

            <h1>
                My Jobs
            </h1>


            <p>
                Manage your job postings.
            </p>

        </div>



        <!-- =================================================
             POST JOB BUTTON
        ================================================== -->


        <?php if ($isVerified): ?>


            <a
                href="create-job.php"
                class="btn"
            >
                + Post a Job
            </a>


        <?php else: ?>


            <span
                class="
                    btn
                    btn-disabled
                "
                title="Employer verification is required."
            >

                🔒 Post a Job

            </span>


        <?php endif; ?>


    </div>



    <!-- =====================================================
         CREATED SUCCESS
    ====================================================== -->

    <?php if ($created): ?>


        <div class="message">

            ✓ Job created successfully and submitted
            for admin approval.

        </div>


    <?php endif; ?>



    <!-- =====================================================
         DIRECT CREATE-JOB ACCESS BLOCK MESSAGE
    ====================================================== -->

    <?php if ($verificationRequired): ?>


        <div class="verification-warning">

            <h3>
                Employer Verification Required
            </h3>


            <p>

                You cannot post a job until your
                employer account has been approved
                by the administrator.

            </p>


            <p>

                Current verification status:

                <strong>

                    <?= strtoupper(
                        htmlspecialchars(
                            $verificationStatus
                        )
                    ) ?>

                </strong>

            </p>


            <a
                href="profile.php"
                class="verification-link"
            >
                View Verification Status
            </a>

        </div>


    <?php endif; ?>



    <!-- =====================================================
         NORMAL VERIFICATION WARNING
    ====================================================== -->

    <?php if (!$isVerified): ?>


        <div class="verification-warning">


            <h3>
                🔒 Employer Verification Required
            </h3>


            <p>

                Your employer account must be
                verified before you can post new
                job openings.

            </p>


            <p>

                Current status:

                <strong>

                    <?= strtoupper(
                        htmlspecialchars(
                            $verificationStatus
                        )
                    ) ?>

                </strong>

            </p>



            <?php if (
                $verificationStatus === "pending"
            ): ?>


                <p>

                    Your profile is currently
                    waiting for admin review.

                </p>


            <?php elseif (
                $verificationStatus === "rejected"
            ): ?>


                <p>

                    Your employer verification was
                    rejected. Please check your
                    profile and admin notes.

                </p>


            <?php else: ?>


                <p>

                    Complete your employer profile,
                    location, and verification
                    documents first.

                </p>


            <?php endif; ?>



            <a
                href="profile.php"
                class="verification-link"
            >
                Go to Employer Profile
            </a>


        </div>


    <?php else: ?>


        <div class="verified-message">

            ✓ Your employer account is verified.
            You can post new job openings.

        </div>


    <?php endif; ?>



    <!-- =====================================================
         JOB LIST
    ====================================================== -->

    <?php if (
        count($jobs) > 0
    ): ?>


        <?php foreach (
            $jobs
            as $job
        ): ?>


            <div class="job-card">


                <!-- JOB TITLE -->

                <h2>

                    <?= htmlspecialchars(
                        $job[
                            "job_title"
                        ]
                    ) ?>

                </h2>



                <!-- META -->

                <div class="meta">


                    <!-- LOCATION -->

                    <span>

                        📍

                        <?= htmlspecialchars(
                            $job[
                                "location"
                            ]
                            ?? "Not specified"
                        ) ?>

                    </span>



                    <!-- EMPLOYMENT TYPE -->

                    <span>

                        💼

                        <?= htmlspecialchars(
                            $job[
                                "employment_type"
                            ]
                        ) ?>

                    </span>



                    <!-- APPLICANTS -->

                    <span>

                        👤

                        <?= (int)
                            $job[
                                "applicant_count"
                            ]
                        ?>

                        applicant(s)

                    </span>



                    <!-- SALARY -->

                    <?php if (
                        !empty(
                            $job[
                                "salary_min"
                            ]
                        ) ||
                        !empty(
                            $job[
                                "salary_max"
                            ]
                        )
                    ): ?>


                        <span>

                            💰

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


                            <?php else: ?>


                                Up to
                                ₱<?= number_format(
                                    $job[
                                        "salary_max"
                                    ],
                                    2
                                ) ?>


                            <?php endif; ?>

                        </span>


                    <?php endif; ?>


                </div>



                <!-- JOB STATUS -->

                <span
                    class="
                        status
                        <?= htmlspecialchars(
                            $job[
                                "status"
                            ]
                        ) ?>
                    "
                >

                    <?= ucfirst(
                        htmlspecialchars(
                            $job[
                                "status"
                            ]
                        )
                    ) ?>

                </span>



                <!-- POSTED DATE -->

                <div
                    style="
                        color:#777;
                        font-size:14px;
                        margin-bottom:15px;
                    "
                >

                    Posted:

                    <?= date(
                        "M d, Y",
                        strtotime(
                            $job[
                                "created_at"
                            ]
                        )
                    ) ?>

                </div>



                <!-- APPLICATION DEADLINE -->

                <?php if (
                    !empty(
                        $job[
                            "application_deadline"
                        ]
                    )
                ): ?>


                    <div
                        style="
                            color:#777;
                            font-size:14px;
                            margin-bottom:15px;
                        "
                    >

                        Application Deadline:

                        <?= date(
                            "M d, Y",
                            strtotime(
                                $job[
                                    "application_deadline"
                                ]
                            )
                        ) ?>

                    </div>


                <?php endif; ?>



                <!-- ACTIONS -->

                <div class="actions">


                    <?php if (
                        $job[
                            "status"
                        ]
                        !== "closed"
                    ): ?>


                        <a
                            href="edit-job.php?id=<?= (int)
                                $job[
                                    "id"
                                ]
                            ?>"
                            class="
                                action
                                edit
                            "
                        >

                            Edit

                        </a>


                    <?php endif; ?>



                    <?php if (
                        $job[
                            "applicant_count"
                        ] > 0
                    ): ?>


                        <a
                            href="applicants.php?job_id=<?= (int)
                                $job[
                                    "id"
                                ]
                            ?>"
                            class="
                                action
                                applicants
                            "
                        >

                            View Applicants

                        </a>


                    <?php endif; ?>


                </div>


            </div>


        <?php endforeach; ?>


    <?php else: ?>


        <!-- =================================================
             NO JOBS
        ================================================== -->

        <div class="empty">


            <h2>
                No Jobs Yet
            </h2>


            <?php if ($isVerified): ?>


                <p
                    style="
                        margin:10px 0 20px;
                        color:#666;
                    "
                >

                    Create your first job posting.

                </p>


                <a
                    href="create-job.php"
                    class="btn"
                >
                    + Post a Job
                </a>


            <?php else: ?>


                <p
                    style="
                        margin:10px 0 20px;
                        color:#666;
                        line-height:1.5;
                    "
                >

                    Your employer account must
                    be verified before you can
                    create your first job posting.

                </p>


                <span
                    class="
                        btn
                        btn-disabled
                    "
                >

                    🔒 Verification Required

                </span>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>


</body>

</html>