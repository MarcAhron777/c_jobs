<?php

session_start();

require_once "../config/database.php";
require_once "../services/LocationMatcher.php";


/*
|--------------------------------------------------------------------------
| GET JOB ID
|--------------------------------------------------------------------------
*/

$jobId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($jobId <= 0) {

    die("Invalid job.");
}


/*
|--------------------------------------------------------------------------
| GET JOB
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT

        j.id,
        j.job_title,
        j.job_description,
        j.requirements,

        j.location,
        j.address,
        j.barangay,
        j.caloocan_area,
        j.latitude,
        j.longtitude,

        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.is_remote,
        j.application_deadline,
        j.created_at,

        e.id AS employer_id,
        e.company_name,
        e.company_description,
        e.company_logo,
        e.contact_person

    FROM jobs j

    INNER JOIN employers e
        ON j.employer_id = e.id

    WHERE j.id = ?
    AND j.status = 'approved'

    LIMIT 1

");


$stmt->execute([
    $jobId
]);


$job = $stmt->fetch(
    PDO::FETCH_ASSOC
);


if (!$job) {

    die("Job not found.");
}


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$isApplicant = (
    isset($_SESSION["user_id"])
    &&
    isset($_SESSION["role"])
    &&
    $_SESSION["role"] === "applicant"
);


/*
|--------------------------------------------------------------------------
| APPLICANT LOCATION
|--------------------------------------------------------------------------
|
| Kukunin natin ang saved latitude at longitude
| mula sa applicant/profile.php.
|
*/

$applicantLatitude = null;
$applicantLongitude = null;
$applicantAddress = "";

$hasApplicantLocation = false;


if ($isApplicant) {

    $stmt = $pdo->prepare("

        SELECT

            address,
            latitude,
            longitude

        FROM applicants

        WHERE user_id = ?

        LIMIT 1

    ");


    $stmt->execute([
        $_SESSION["user_id"]
    ]);


    $applicantLocation =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if ($applicantLocation) {

        $applicantAddress =
            $applicantLocation["address"]
            ?? "";


        $applicantLatitude =
            $applicantLocation["latitude"]
            ?? null;


        $applicantLongitude =
            $applicantLocation["longitude"]
            ?? null;


        $hasApplicantLocation = (
            is_numeric(
                $applicantLatitude
            )
            &&
            is_numeric(
                $applicantLongitude
            )
        );
    }
}


/*
|--------------------------------------------------------------------------
| JOB LOCATION STATUS
|--------------------------------------------------------------------------
*/

$hasJobLocation = (
    is_numeric(
        $job["latitude"] ?? null
    )
    &&
    is_numeric(
        $job["longtitude"] ?? null
    )
);


/*
|--------------------------------------------------------------------------
| CALCULATE DISTANCE
|--------------------------------------------------------------------------
|
| Applicant coordinates
| versus
| Job / Employer coordinates
|
*/

$distanceKm = null;


if (
    $hasApplicantLocation
    &&
    $hasJobLocation
) {

    $distanceKm =
        LocationMatcher::distanceKm(

            $applicantLatitude,

            $applicantLongitude,

            $job["latitude"],

            $job["longtitude"]

        );
}


/*
|--------------------------------------------------------------------------
| GOOGLE MAPS ROUTE
|--------------------------------------------------------------------------
|
| Walang API key required.
|
| Bubuksan nito ang Google Maps directions:
|
| Applicant location
|        ↓
| Job / Employer location
|
*/

$googleMapsRouteUrl = null;


if (
    $hasApplicantLocation
    &&
    $hasJobLocation
) {

    $origin =
        $applicantLatitude
        . ","
        . $applicantLongitude;


    $destination =
        $job["latitude"]
        . ","
        . $job["longtitude"];


    $googleMapsRouteUrl =
        "https://www.google.com/maps/dir/?api=1"
        .
        "&origin="
        .
        urlencode($origin)
        .
        "&destination="
        .
        urlencode($destination)
        .
        "&travelmode=driving";
}


/*
|--------------------------------------------------------------------------
| GOOGLE MAPS JOB LOCATION
|--------------------------------------------------------------------------
|
| Ito naman ay para kung gusto lang makita
| ang mismong job location.
|
*/

$googleMapsJobUrl = null;


if ($hasJobLocation) {

    $jobCoordinates =
        $job["latitude"]
        . ","
        . $job["longtitude"];


    $googleMapsJobUrl =
        "https://www.google.com/maps/search/?api=1"
        .
        "&query="
        .
        urlencode(
            $jobCoordinates
        );
}


/*
|--------------------------------------------------------------------------
| CHECK IF APPLICANT ALREADY APPLIED
|--------------------------------------------------------------------------
*/

$alreadyApplied = false;


if ($isApplicant) {

    $stmt = $pdo->prepare("

        SELECT
            ap.id

        FROM applications ap

        INNER JOIN applicants a
            ON ap.applicant_id = a.id

        WHERE ap.job_id = ?

        AND a.user_id = ?

        LIMIT 1

    ");


    $stmt->execute([

        $jobId,

        $_SESSION["user_id"]

    ]);


    $alreadyApplied =
        (bool) $stmt->fetch();
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

    <?= htmlspecialchars(
        $job["job_title"]
    ) ?>

    - Caloocan Job Portal

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


/*
|--------------------------------------------------------------------------
| MAIN
|--------------------------------------------------------------------------
*/

.container {

    max-width:
        1000px;

    margin:
        40px auto;

    padding:
        0 20px;
}


.back {

    display:
        inline-block;

    margin-bottom:
        20px;

    text-decoration:
        none;

    color:
        #2563eb;
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
        30px;

    border-radius:
        10px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);

    margin-bottom:
        20px;
}


h1 {

    margin-bottom:
        10px;
}


h2 {

    margin-bottom:
        15px;
}


/*
|--------------------------------------------------------------------------
| COMPANY
|--------------------------------------------------------------------------
*/

.company {

    color:
        #555;

    font-size:
        18px;

    margin-bottom:
        20px;
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
        10px;

    margin-bottom:
        20px;
}


.meta span {

    background:
        #f1f5f9;

    padding:
        8px 12px;

    border-radius:
        6px;

    font-size:
        14px;
}


.salary {

    color:
        #166534;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| SECTION
|--------------------------------------------------------------------------
*/

.section {

    margin-top:
        30px;
}


.section p {

    line-height:
        1.8;

    white-space:
        pre-line;
}


/*
|--------------------------------------------------------------------------
| JOB LOCATION
|--------------------------------------------------------------------------
*/

.job-location-card {

    margin-top:
        25px;

    background:
        #f8fafc;

    border:
        1px solid #e2e8f0;

    padding:
        20px;

    border-radius:
        10px;
}


.job-location-card h2 {

    margin-bottom:
        15px;
}


.job-address {

    line-height:
        1.7;

    color:
        #334155;
}


.job-barangay {

    font-weight:
        bold;

    font-size:
        15px;
}


.area-badge {

    display:
        inline-block;

    margin-top:
        10px;

    padding:
        6px 10px;

    border-radius:
        20px;

    font-size:
        11px;

    font-weight:
        bold;

    text-transform:
        uppercase;
}


.area-badge.north {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.area-badge.south {

    background:
        #dcfce7;

    color:
        #166534;
}


/*
|--------------------------------------------------------------------------
| DISTANCE
|--------------------------------------------------------------------------
*/

.distance-box {

    margin-top:
        15px;

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    padding:
        14px;

    border-radius:
        8px;

    color:
        #1e40af;
}


.distance-number {

    font-size:
        20px;

    font-weight:
        bold;

    margin-top:
        4px;
}


/*
|--------------------------------------------------------------------------
| ROUTE
|--------------------------------------------------------------------------
*/

.route-box {

    margin-top:
        18px;

    background:
        white;

    border:
        1px solid #e2e8f0;

    border-radius:
        8px;

    padding:
        15px;
}


.route-point {

    line-height:
        1.6;
}


.route-point strong {

    display:
        block;

    margin-bottom:
        3px;
}


.route-line {

    font-size:
        22px;

    color:
        #64748b;

    margin:
        8px 0;

    padding-left:
        8px;
}


.route-btn {

    display:
        block;

    text-align:
        center;

    margin-top:
        15px;

    padding:
        12px 15px;

    border-radius:
        7px;

    background:
        #16a34a;

    color:
        white;

    text-decoration:
        none;

    font-weight:
        bold;
}


.route-btn:hover {

    background:
        #15803d;
}


.job-map-btn {

    display:
        inline-block;

    margin-top:
        12px;

    color:
        #2563eb;

    text-decoration:
        none;

    font-weight:
        bold;

    font-size:
        14px;
}


/*
|--------------------------------------------------------------------------
| WARNING
|--------------------------------------------------------------------------
*/

.location-warning {

    margin-top:
        15px;

    background:
        #fef3c7;

    color:
        #92400e;

    border:
        1px solid #fde68a;

    padding:
        13px;

    border-radius:
        7px;

    line-height:
        1.5;
}


.location-warning a {

    color:
        #92400e;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| DATE
|--------------------------------------------------------------------------
*/

.date {

    color:
        #777;

    font-size:
        14px;
}


/*
|--------------------------------------------------------------------------
| APPLY BUTTON
|--------------------------------------------------------------------------
*/

.apply-btn {

    display:
        block;

    width:
        100%;

    text-align:
        center;

    padding:
        13px;

    background:
        #2563eb;

    color:
        white;

    text-decoration:
        none;

    border-radius:
        6px;

    font-weight:
        bold;
}


.apply-btn:hover {

    background:
        #1d4ed8;
}


.disabled-btn {

    background:
        #94a3b8;

    cursor:
        not-allowed;
}


/*
|--------------------------------------------------------------------------
| LOGIN MESSAGE
|--------------------------------------------------------------------------
*/

.login-message {

    background:
        #eff6ff;

    color:
        #1e40af;

    padding:
        15px;

    border-radius:
        7px;

    margin-bottom:
        15px;

    line-height:
        1.5;
}


.login-message a {

    color:
        #1d4ed8;

    font-weight:
        bold;
}


/*
|--------------------------------------------------------------------------
| COMPANY CARD
|--------------------------------------------------------------------------
*/

.company-card h3 {

    margin-bottom:
        10px;
}


.company-card p {

    color:
        #666;

    line-height:
        1.6;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 750px
) {

    .grid {

        grid-template-columns:
            1fr;
    }


    .navbar {

        padding:
            15px 20px;

        flex-direction:
            column;

        gap:
            15px;
    }


    .nav-links {

        flex-wrap:
            wrap;

        justify-content:
            center;
    }


    .container {

        margin:
            25px auto;
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


        <a href="index.php">
            Find Jobs
        </a>


        <?php if (
            isset($_SESSION["user_id"])
        ): ?>


            <?php if (
                $_SESSION["role"]
                === "applicant"
            ): ?>


                <a
                    href="../applicant/dashboard.php"
                >

                    Dashboard

                </a>


                <a
                    href="../applicant/profile.php"
                >

                    Profile

                </a>


            <?php elseif (
                $_SESSION["role"]
                === "employer"
            ): ?>


                <a
                    href="../employer/dashboard.php"
                >

                    Dashboard

                </a>


            <?php endif; ?>


            <a href="../logout.php">
                Logout
            </a>


        <?php else: ?>


            <a href="../login.php">
                Login
            </a>


            <a href="../register.php">
                Register
            </a>


        <?php endif; ?>


    </div>


</nav>



<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <a
        href="index.php"
        class="back"
    >

        ← Back to Jobs

    </a>



    <div class="grid">


        <!-- =====================================================
             MAIN JOB DETAILS
        ====================================================== -->

        <div>


            <div class="card">


                <!-- JOB TITLE -->

                <h1>

                    <?= htmlspecialchars(
                        $job[
                            "job_title"
                        ]
                    ) ?>

                </h1>



                <!-- COMPANY -->

                <div class="company">

                    <?= htmlspecialchars(
                        $job[
                            "company_name"
                        ]
                    ) ?>

                </div>



                <!-- META -->

                <div class="meta">


                    <span>

                        💼

                        <?= htmlspecialchars(
                            $job[
                                "employment_type"
                            ]
                            ?? "N/A"
                        ) ?>

                    </span>



                    <?php if (
                        !empty(
                            $job[
                                "is_remote"
                            ]
                        )
                    ): ?>


                        <span>

                            🌐 Remote

                        </span>


                    <?php endif; ?>



                    <?php if (
                        $job["salary_min"] !== null
                        ||
                        $job["salary_max"] !== null
                    ): ?>


                        <span class="salary">


                            <?php if (
                                $job[
                                    "salary_min"
                                ] !== null
                                &&
                                $job[
                                    "salary_max"
                                ] !== null
                            ): ?>


                                ₱<?= number_format(
                                    $job[
                                        "salary_min"
                                    ],
                                    0
                                ) ?>

                                -

                                ₱<?= number_format(
                                    $job[
                                        "salary_max"
                                    ],
                                    0
                                ) ?>


                            <?php elseif (
                                $job[
                                    "salary_min"
                                ] !== null
                            ): ?>


                                From ₱<?= number_format(
                                    $job[
                                        "salary_min"
                                    ],
                                    0
                                ) ?>


                            <?php else: ?>


                                Up to ₱<?= number_format(
                                    $job[
                                        "salary_max"
                                    ],
                                    0
                                ) ?>


                            <?php endif; ?>


                        </span>


                    <?php endif; ?>


                </div>



                <!-- POSTED DATE -->

                <div class="date">

                    Posted:

                    <?= date(
                        "F d, Y",
                        strtotime(
                            $job[
                                "created_at"
                            ]
                        )
                    ) ?>

                </div>



                <!-- DEADLINE -->

                <?php if (
                    !empty(
                        $job[
                            "application_deadline"
                        ]
                    )
                ): ?>


                    <div
                        class="date"
                        style="margin-top:8px;"
                    >

                        Application Deadline:

                        <?= date(
                            "F d, Y",
                            strtotime(
                                $job[
                                    "application_deadline"
                                ]
                            )
                        ) ?>

                    </div>


                <?php endif; ?>



                <!-- =================================================
                     LOCATION
                ================================================== -->

                <div class="job-location-card">


                    <h2>
                        📍 Job Location
                    </h2>



                    <div class="job-address">


                        <?php if (
                            !empty(
                                $job[
                                    "barangay"
                                ]
                            )
                        ): ?>


                            <div class="job-barangay">

                                <?= htmlspecialchars(
                                    $job[
                                        "barangay"
                                    ]
                                ) ?>

                            </div>


                        <?php endif; ?>



                        <?php if (
                            !empty(
                                $job[
                                    "address"
                                ]
                            )
                        ): ?>


                            <?= htmlspecialchars(
                                $job[
                                    "address"
                                ]
                            ) ?>


                        <?php elseif (
                            !empty(
                                $job[
                                    "location"
                                ]
                            )
                        ): ?>


                            <?= htmlspecialchars(
                                $job[
                                    "location"
                                ]
                            ) ?>


                        <?php else: ?>


                            Location not specified.


                        <?php endif; ?>


                    </div>



                    <!-- AREA -->

                    <?php if (
                        in_array(
                            $job[
                                "caloocan_area"
                            ]
                            ?? "",
                            [
                                "north",
                                "south"
                            ],
                            true
                        )
                    ): ?>


                        <span
                            class="
                                area-badge
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


                    <?php endif; ?>



                    <!-- =============================================
                         DISTANCE
                    ============================================== -->

                    <?php if (
                        $distanceKm !== null
                    ): ?>


                        <div class="distance-box">


                            Distance from your
                            saved location


                            <div class="distance-number">

                                📏

                                <?= number_format(
                                    $distanceKm,
                                    1
                                ) ?>

                                km away from you

                            </div>


                        </div>


                    <?php endif; ?>



                    <!-- =============================================
                         POINT TO POINT ROUTE
                    ============================================== -->

                    <?php if (
                        $googleMapsRouteUrl
                    ): ?>


                        <div class="route-box">


                            <!-- APPLICANT -->

                            <div class="route-point">


                                <strong>

                                    🟢 Your Location

                                </strong>


                                <?= htmlspecialchars(
                                    $applicantAddress
                                    !== ""
                                        ? $applicantAddress
                                        : "Saved applicant location"
                                ) ?>


                            </div>



                            <div class="route-line">

                                ↓

                            </div>



                            <!-- EMPLOYER / JOB -->

                            <div class="route-point">


                                <strong>

                                    🔴 Job Location

                                </strong>


                                <?= htmlspecialchars(
                                    $job[
                                        "address"
                                    ]
                                    ??
                                    $job[
                                        "location"
                                    ]
                                    ??
                                    "Job location"
                                ) ?>


                            </div>



                            <a
                                href="<?= htmlspecialchars(
                                    $googleMapsRouteUrl
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="route-btn"
                            >

                                🗺 View Route in Google Maps

                            </a>


                        </div>


                    <?php elseif (
                        $isApplicant
                        &&
                        !$hasApplicantLocation
                    ): ?>


                        <div class="location-warning">

                            📍 Your current location
                            has not been saved yet.

                            <br><br>

                            Go to your

                            <a
                                href="../applicant/profile.php"
                            >
                                Profile
                            </a>

                            and click

                            <strong>
                                Use My Current Location
                            </strong>

                            to see the distance and
                            Google Maps route.

                        </div>


                    <?php elseif (
                        $isApplicant
                        &&
                        !$hasJobLocation
                    ): ?>


                        <div class="location-warning">

                            The exact coordinates
                            for this job are not
                            available yet.

                        </div>


                    <?php endif; ?>



                    <!-- VIEW JOB POINT ONLY -->

                    <?php if (
                        $googleMapsJobUrl
                    ): ?>


                        <a
                            href="<?= htmlspecialchars(
                                $googleMapsJobUrl
                            ) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="job-map-btn"
                        >

                            📌 View Job Location Only

                        </a>


                    <?php endif; ?>


                </div>



                <!-- =================================================
                     DESCRIPTION
                ================================================== -->

                <div class="section">


                    <h2>

                        Job Description

                    </h2>


                    <p>

                        <?= htmlspecialchars(
                            $job[
                                "job_description"
                            ]
                            ?? ""
                        ) ?>

                    </p>


                </div>



                <!-- =================================================
                     REQUIREMENTS
                ================================================== -->

                <?php if (
                    !empty(
                        $job[
                            "requirements"
                        ]
                    )
                ): ?>


                    <div class="section">


                        <h2>

                            Requirements

                        </h2>


                        <p>

                            <?= htmlspecialchars(
                                $job[
                                    "requirements"
                                ]
                            ) ?>

                        </p>


                    </div>


                <?php endif; ?>


            </div>



            <!-- =====================================================
                 COMPANY
            ====================================================== -->

            <div class="card company-card">


                <h2>

                    About the Company

                </h2>


                <h3>

                    <?= htmlspecialchars(
                        $job[
                            "company_name"
                        ]
                    ) ?>

                </h3>



                <?php if (
                    !empty(
                        $job[
                            "company_description"
                        ]
                    )
                ): ?>


                    <p>

                        <?= htmlspecialchars(
                            $job[
                                "company_description"
                            ]
                        ) ?>

                    </p>


                <?php else: ?>


                    <p>

                        No company description
                        available.

                    </p>


                <?php endif; ?>


            </div>


        </div>



        <!-- =====================================================
             APPLY SIDEBAR
        ====================================================== -->

        <div>


            <div class="card">


                <h2>

                    Interested in this job?

                </h2>



                <?php if (
                    !isset(
                        $_SESSION[
                            "user_id"
                        ]
                    )
                ): ?>


                    <div class="login-message">


                        Please

                        <a href="../login.php">
                            login
                        </a>

                        as an applicant to
                        apply for this job.


                    </div>


                    <a
                        href="../login.php"
                        class="apply-btn"
                    >

                        Login to Apply

                    </a>



                <?php elseif (
                    $_SESSION[
                        "role"
                    ]
                    !== "applicant"
                ): ?>


                    <div class="login-message">

                        Only applicants can
                        apply for jobs.

                    </div>



                <?php elseif (
                    $alreadyApplied
                ): ?>


                    <div class="login-message">

                        You have already applied
                        for this job.

                    </div>


                    <a
                        href="../applicant/applications.php"
                        class="apply-btn"
                    >

                        View Applications

                    </a>



                <?php else: ?>


                    <a
                        href="apply.php?id=<?= (int)
                            $job[
                                "id"
                            ]
                        ?>"
                        class="apply-btn"
                    >

                        Apply Now

                    </a>


                <?php endif; ?>


            </div>


        </div>


    </div>


</div>


</body>

</html>