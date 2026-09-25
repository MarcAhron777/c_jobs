<?php

session_start();

require_once "../config/database.php";
require_once "../services/LocationMatcher.php";


/*
|--------------------------------------------------------------------------
| SEARCH INPUTS
|--------------------------------------------------------------------------
*/

$search = trim(
    $_GET["search"] ?? ""
);

$area = trim(
    $_GET["area"] ?? ""
);


/*
|--------------------------------------------------------------------------
| CURRENT USER
|--------------------------------------------------------------------------
*/

$isApplicant = (
    isset($_SESSION["user_id"]) &&
    isset($_SESSION["role"]) &&
    $_SESSION["role"] === "applicant"
);


/*
|--------------------------------------------------------------------------
| APPLICANT SAVED LOCATION
|--------------------------------------------------------------------------
|
| Hindi na tayo gumagamit ng session coordinates.
|
| Kukunin natin mismo ang latitude at longitude
| na naka-save sa applicant/profile.php.
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
            is_numeric($applicantLatitude)
            &&
            is_numeric($applicantLongitude)
        );
    }
}


/*
|--------------------------------------------------------------------------
| BUILD JOB QUERY
|--------------------------------------------------------------------------
*/

$sql = "

    SELECT

        j.id,

        j.job_title,

        j.description,

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

        e.company_name

    FROM jobs j

    INNER JOIN employers e
        ON j.employer_id = e.id

    WHERE j.status = 'approved'

";


$params = [];


/*
|--------------------------------------------------------------------------
| KEYWORD SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= "

        AND (

            j.job_title LIKE ?

            OR j.description LIKE ?

            OR e.company_name LIKE ?

        )

    ";


    $searchValue =
        "%" . $search . "%";


    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;
}


/*
|--------------------------------------------------------------------------
| NORTH / SOUTH CALOOCAN FILTER
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $area,
        [
            "north",
            "south"
        ],
        true
    )
) {

    $sql .= "

        AND j.caloocan_area = ?

    ";


    $params[] =
        $area;
}


/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "

    ORDER BY
        j.created_at DESC

";


/*
|--------------------------------------------------------------------------
| EXECUTE QUERY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    $sql
);


$stmt->execute(
    $params
);


$jobs =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

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
    Find Jobs - Caloocan Job Portal
</title>


<style>

/*
|--------------------------------------------------------------------------
| BASE
|--------------------------------------------------------------------------
*/

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
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
        #fff;

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
| PAGE HEADER
|--------------------------------------------------------------------------
*/

.page-header {

    margin-bottom:
        25px;
}


.page-header h1 {

    margin-bottom:
        8px;
}


.page-header p {

    color:
        #666;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

.search-box {

    background:
        white;

    padding:
        20px;

    border-radius:
        10px;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);

    margin-bottom:
        20px;
}


.search-form {

    display:
        grid;

    grid-template-columns:
        1fr 1fr auto;

    gap:
        10px;
}


.search-form input,
.search-form select {

    width:
        100%;

    padding:
        12px;

    border:
        1px solid #ddd;

    border-radius:
        6px;

    font-size:
        14px;

    background:
        white;
}


.search-form input:focus,
.search-form select:focus {

    outline:
        none;

    border-color:
        #2563eb;
}


.search-btn {

    padding:
        12px 20px;

    border:
        none;

    border-radius:
        6px;

    background:
        #2563eb;

    color:
        white;

    cursor:
        pointer;

    font-weight:
        bold;
}


.search-btn:hover {

    background:
        #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| LOCATION NOTICE
|--------------------------------------------------------------------------
*/

.location-notice {

    padding:
        15px 18px;

    border-radius:
        8px;

    margin-bottom:
        25px;

    line-height:
        1.6;

    font-size:
        14px;
}


.location-ready {

    background:
        #dcfce7;

    border:
        1px solid #bbf7d0;

    color:
        #166534;
}


.location-missing {

    background:
        #fef3c7;

    border:
        1px solid #fde68a;

    color:
        #92400e;
}


.location-notice a {

    font-weight:
        bold;

    color:
        inherit;
}


/*
|--------------------------------------------------------------------------
| JOBS GRID
|--------------------------------------------------------------------------
*/

.jobs-grid {

    display:
        grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0, 1fr)
        );

    gap:
        20px;
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

    display:
        flex;

    flex-direction:
        column;
}


.job-card h2 {

    font-size:
        20px;

    margin-bottom:
        8px;
}


.company {

    color:
        #555;

    font-size:
        16px;

    margin-bottom:
        15px;
}


.job-description {

    color:
        #666;

    line-height:
        1.6;

    margin-bottom:
        15px;

    display:
        -webkit-box;

    -webkit-line-clamp:
        3;

    -webkit-box-orient:
        vertical;

    overflow:
        hidden;
}


/*
|--------------------------------------------------------------------------
| LOCATION
|--------------------------------------------------------------------------
*/

.job-location {

    background:
        #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius:
        8px;

    padding:
        14px;

    margin-bottom:
        15px;
}


.job-location-address {

    font-size:
        14px;

    line-height:
        1.6;

    color:
        #334155;
}


.job-barangay {

    font-weight:
        bold;
}


.job-area {

    display:
        inline-block;

    margin-top:
        8px;

    padding:
        5px 9px;

    border-radius:
        15px;

    font-size:
        11px;

    font-weight:
        bold;
}


.job-area.north {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.job-area.south {

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

.distance {

    margin-top:
        10px;

    padding-top:
        10px;

    border-top:
        1px solid #e2e8f0;

    color:
        #2563eb;

    font-size:
        14px;

    font-weight:
        bold;
}


.distance-near {

    color:
        #15803d;
}


.distance-unavailable {

    margin-top:
        10px;

    padding-top:
        10px;

    border-top:
        1px solid #e2e8f0;

    color:
        #64748b;

    font-size:
        13px;
}


/*
|--------------------------------------------------------------------------
| JOB META
|--------------------------------------------------------------------------
*/

.job-meta {

    display:
        flex;

    flex-wrap:
        wrap;

    gap:
        8px;

    margin-bottom:
        20px;
}


.job-meta span {

    background:
        #f1f5f9;

    padding:
        7px 10px;

    border-radius:
        6px;

    font-size:
        13px;
}


/*
|--------------------------------------------------------------------------
| JOB FOOTER
|--------------------------------------------------------------------------
*/

.job-footer {

    margin-top:
        auto;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        15px;
}


.salary {

    font-weight:
        bold;

    color:
        #166534;
}


.view-job-btn {

    display:
        inline-block;

    padding:
        10px 16px;

    background:
        #2563eb;

    color:
        white;

    text-decoration:
        none;

    border-radius:
        6px;

    white-space:
        nowrap;
}


.view-job-btn:hover {

    background:
        #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| NO JOBS
|--------------------------------------------------------------------------
*/

.no-jobs {

    background:
        white;

    padding:
        40px;

    border-radius:
        10px;

    text-align:
        center;

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);
}


.no-jobs h2 {

    margin-bottom:
        10px;
}


.no-jobs p {

    color:
        #666;

    line-height:
        1.6;
}


/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 768px
) {

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


    .search-form {

        grid-template-columns:
            1fr;
    }


    .jobs-grid {

        grid-template-columns:
            1fr;
    }


    .container {

        margin:
            25px auto;
    }


    .job-footer {

        flex-direction:
            column;

        align-items:
            flex-start;
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


        <?php if ($isApplicant): ?>


            <a href="../applicant/dashboard.php">
                Dashboard
            </a>


            <a href="../applicant/applications.php">
                Applications
            </a>


            <a href="../applicant/profile.php">
                Profile
            </a>


        <?php elseif (
            isset($_SESSION["role"]) &&
            $_SESSION["role"] === "employer"
        ): ?>


            <a href="../employer/dashboard.php">
                Dashboard
            </a>


        <?php elseif (
            isset($_SESSION["role"]) &&
            $_SESSION["role"] === "admin"
        ): ?>


            <a href="../admin/dashboard.php">
                Dashboard
            </a>


        <?php else: ?>


            <a href="../login.php">
                Login
            </a>


            <a href="../register.php">
                Register
            </a>


        <?php endif; ?>


        <a href="../index.php">
            Home
        </a>


        <?php if (
            isset($_SESSION["user_id"])
        ): ?>


            <a href="../logout.php">
                Logout
            </a>


        <?php endif; ?>


    </div>

</nav>



<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <!-- HEADER -->

    <div class="page-header">

        <h1>
            Find Jobs
        </h1>

        <p>
            Search job opportunities in
            North and South Caloocan and
            see how far each job is from
            your saved location.
        </p>

    </div>



    <!-- =====================================================
         SEARCH
    ====================================================== -->

    <div class="search-box">


        <form
            method="GET"
            action="index.php"
            class="search-form"
        >


            <input
                type="text"
                name="search"
                placeholder="Job title, keyword or company"
                value="<?= htmlspecialchars(
                    $search
                ) ?>"
            >



            <select name="area">


                <option value="">
                    All Caloocan Areas
                </option>


                <option
                    value="north"
                    <?= $area === "north"
                        ? "selected"
                        : ""
                    ?>
                >
                    North Caloocan
                </option>


                <option
                    value="south"
                    <?= $area === "south"
                        ? "selected"
                        : ""
                    ?>
                >
                    South Caloocan
                </option>


            </select>



            <button
                type="submit"
                class="search-btn"
            >
                Search
            </button>


        </form>

    </div>



    <!-- =====================================================
         APPLICANT SAVED LOCATION STATUS
    ====================================================== -->

    <?php if ($isApplicant): ?>


        <?php if (
            $hasApplicantLocation
        ): ?>


            <div
                class="
                    location-notice
                    location-ready
                "
            >

                <strong>
                    ✓ Your location is ready
                </strong>

                <br>

                <?php if (
                    $applicantAddress !== ""
                ): ?>

                    Your saved address:

                    <strong>

                        <?= htmlspecialchars(
                            $applicantAddress
                        ) ?>

                    </strong>

                    <br>

                <?php endif; ?>

                Distances below are automatically
                calculated from your saved profile
                location to each job location.

            </div>


        <?php else: ?>


            <div
                class="
                    location-notice
                    location-missing
                "
            >

                <strong>
                    📍 Location not yet set
                </strong>

                <br>

                Add your current location in your
                applicant profile to see how many
                kilometers each job is from you.

                <br>

                <a
                    href="../applicant/profile.php"
                >
                    Update Profile →
                </a>

            </div>


        <?php endif; ?>


    <?php endif; ?>



    <!-- =====================================================
         JOBS
    ====================================================== -->

    <?php if (
        !empty($jobs)
    ): ?>


        <div class="jobs-grid">


            <?php foreach (
                $jobs
                as $job
            ): ?>


                <?php

                /*
                |--------------------------------------------------------------------------
                | CALCULATE DISTANCE
                |--------------------------------------------------------------------------
                |
                | Applicant saved coordinates
                | versus
                | Job / employer coordinates.
                |
                */

                $distanceKm = null;


                if (
                    $hasApplicantLocation
                    &&
                    is_numeric(
                        $job["latitude"]
                    )
                    &&
                    is_numeric(
                        $job["longtitude"]
                    )
                ) {

                    $distanceKm =
                        LocationMatcher::distanceKm(

                            $applicantLatitude,

                            $applicantLongitude,

                            $job["latitude"],

                            $job["longtitude"]

                        );
                }

                ?>


                <div class="job-card">


                    <!-- TITLE -->

                    <h2>

                        <?= htmlspecialchars(
                            $job[
                                "job_title"
                            ]
                        ) ?>

                    </h2>



                    <!-- COMPANY -->

                    <div class="company">

                        <?= htmlspecialchars(
                            $job[
                                "company_name"
                            ]
                        ) ?>

                    </div>



                    <!-- DESCRIPTION -->

                    <div class="job-description">

                        <?= htmlspecialchars(
                            $job[
                                "description"
                            ]
                            ?? ""
                        ) ?>

                    </div>



                    <!-- =================================================
                         LOCATION
                    ================================================== -->

                    <div class="job-location">


                        <div class="job-location-address">


                            📍


                            <?php if (
                                !empty(
                                    $job[
                                        "barangay"
                                    ]
                                )
                            ): ?>


                                <span class="job-barangay">

                                    <?= htmlspecialchars(
                                        $job[
                                            "barangay"
                                        ]
                                    ) ?>

                                </span>


                            <?php endif; ?>



                            <?php if (
                                !empty(
                                    $job[
                                        "address"
                                    ]
                                )
                            ): ?>


                                <br>


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


                                <br>


                                <?= htmlspecialchars(
                                    $job[
                                        "location"
                                    ]
                                ) ?>


                            <?php endif; ?>


                        </div>



                        <!-- AREA -->

                        <?php if (
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


                            <span
                                class="
                                    job-area
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



                        <!-- =================================================
                             DISTANCE
                        ================================================== -->

                        <?php if (
                            $distanceKm !== null
                        ): ?>


                            <div
                                class="
                                    distance
                                    <?= $distanceKm <= 5
                                        ? "distance-near"
                                        : ""
                                    ?>
                                "
                            >

                                📏

                                <?= number_format(
                                    $distanceKm,
                                    1
                                ) ?>

                                km away from you

                            </div>


                        <?php elseif (
                            $isApplicant
                        ): ?>


                            <div
                                class="
                                    distance-unavailable
                                "
                            >


                                <?php if (
                                    !$hasApplicantLocation
                                ): ?>

                                    Add your location
                                    in your profile to
                                    see the distance.

                                <?php else: ?>

                                    Distance unavailable
                                    for this job.

                                <?php endif; ?>


                            </div>


                        <?php endif; ?>


                    </div>



                    <!-- =================================================
                         JOB META
                    ================================================== -->

                    <div class="job-meta">


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
                            !empty(
                                $job[
                                    "application_deadline"
                                ]
                            )
                        ): ?>


                            <span>

                                📅 Deadline:

                                <?= date(
                                    "M d, Y",
                                    strtotime(
                                        $job[
                                            "application_deadline"
                                        ]
                                    )
                                ) ?>

                            </span>


                        <?php endif; ?>


                    </div>



                    <!-- =================================================
                         FOOTER
                    ================================================== -->

                    <div class="job-footer">


                        <div class="salary">


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
                                $job[
                                    "salary_min"
                                ] !== null
                            ): ?>


                                From ₱<?= number_format(
                                    $job[
                                        "salary_min"
                                    ],
                                    2
                                ) ?>


                            <?php elseif (
                                $job[
                                    "salary_max"
                                ] !== null
                            ): ?>


                                Up to ₱<?= number_format(
                                    $job[
                                        "salary_max"
                                    ],
                                    2
                                ) ?>


                            <?php else: ?>


                                Salary not specified


                            <?php endif; ?>


                        </div>



                        <a
                            href="view.php?id=<?= (int)
                                $job[
                                    "id"
                                ]
                            ?>"
                            class="view-job-btn"
                        >

                            View Job

                        </a>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="no-jobs">


            <h2>
                No Jobs Found
            </h2>


            <p>

                No approved jobs matched
                your search.

                Try another keyword or
                select another Caloocan area.

            </p>


        </div>


    <?php endif; ?>


</div>


</body>

</html>