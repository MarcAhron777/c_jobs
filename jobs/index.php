<?php

session_start();

require_once "../config/database.php";
require_once "../services/LocationMatcher.php";


/*
|--------------------------------------------------------------------------
| FILTERS
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");
$area = trim($_GET["area"] ?? "");
$source = trim($_GET["source"] ?? "");


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
*/

$applicantLatitude = null;
$applicantLongitude = null;
$hasApplicantLocation = false;

if ($isApplicant) {
    $stmt = $pdo->prepare("
        SELECT
            latitude,
            longitude
        FROM applicants
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $_SESSION["user_id"]
    ]);

    $applicantLocation = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($applicantLocation) {
        $applicantLatitude =
            $applicantLocation["latitude"] ?? null;

        $applicantLongitude =
            $applicantLocation["longitude"] ?? null;

        $hasApplicantLocation = (
            is_numeric($applicantLatitude) &&
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

        COALESCE(
            NULLIF(j.job_description, ''),
            j.description
        ) AS description,

        j.required_skills,
        j.educational_requirement,
        j.required_degree,
        j.tesda_qualification,
        j.required_nc_level,

        j.job_source,
        j.source_name,
        j.lgu_job_category,

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
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== "") {
    $sql .= "
        AND (
            j.job_title LIKE ?
            OR j.job_description LIKE ?
            OR j.description LIKE ?
            OR j.required_skills LIKE ?
            OR j.tesda_qualification LIKE ?
            OR e.company_name LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
}


/*
|--------------------------------------------------------------------------
| AREA FILTER
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $area,
        ["north", "south"],
        true
    )
) {
    $sql .= "
        AND j.caloocan_area = ?
    ";

    $params[] = $area;
}


/*
|--------------------------------------------------------------------------
| SOURCE FILTER
|--------------------------------------------------------------------------
*/

if (
    in_array(
        $source,
        [
            "private_employer",
            "lgu",
            "job_fair"
        ],
        true
    )
) {
    $sql .= "
        AND j.job_source = ?
    ";

    $params[] = $source;
}


/*
|--------------------------------------------------------------------------
| ORDER
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY j.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

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
        Find Jobs - Caloocan Job Portal
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
            color: #333;
        }

        .navbar {
            background: #fff;
            border-bottom: 1px solid #ddd;
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            color: #2563eb;
            font-size: 24px;
            font-weight: bold;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-links a {
            color: #333;
            text-decoration: none;
        }

        .nav-links a:hover {
            color: #2563eb;
        }

        .container {
            max-width: 1150px;
            margin: 40px auto;
            padding: 0 20px 50px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin-bottom: 8px;
        }

        .page-header p {
            color: #666;
            line-height: 1.5;
        }

        .search-card {
            padding: 20px;
            margin-bottom: 20px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .search-form {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr auto;
            gap: 10px;
        }

        .search-form input,
        .search-form select {
            width: 100%;
            padding: 12px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .search-form input:focus,
        .search-form select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .search-btn {
            padding: 12px 20px;
            background: #2563eb;
            border: none;
            border-radius: 6px;
            color: #fff;
            cursor: pointer;
            font-weight: bold;
        }

        .search-btn:hover {
            background: #1d4ed8;
        }

        .location-notice {
            padding: 15px 18px;
            margin-bottom: 25px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.6;
        }

        .location-ready {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .location-missing {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .jobs-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .job-card {
            display: flex;
            flex-direction: column;
            padding: 25px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .job-title {
            margin-bottom: 8px;
            font-size: 20px;
        }

        .company {
            margin-bottom: 12px;
            color: #555;
            font-size: 16px;
        }

        .source-badge {
            display: inline-block;
            align-self: flex-start;
            padding: 5px 9px;
            margin-bottom: 12px;
            background: #ede9fe;
            border-radius: 15px;
            color: #5b21b6;
            font-size: 11px;
            font-weight: bold;
        }

        .job-description {
            display: -webkit-box;
            margin-bottom: 15px;
            overflow: hidden;
            color: #666;
            line-height: 1.6;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        .location-box {
            padding: 14px;
            margin-bottom: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .barangay {
            font-weight: bold;
        }

        .address {
            margin-top: 4px;
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
        }

        .area-badge {
            display: inline-block;
            padding: 5px 9px;
            margin-top: 8px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: bold;
        }

        .north {
            background: #dbeafe;
            color: #1e40af;
        }

        .south {
            background: #dcfce7;
            color: #166534;
        }

        .distance {
            padding-top: 10px;
            margin-top: 10px;
            border-top: 1px solid #e2e8f0;
            color: #2563eb;
            font-size: 14px;
            font-weight: bold;
        }

        .qualification-box {
            padding: 12px;
            margin-bottom: 15px;
            background: #f8fafc;
            border-radius: 7px;
            color: #475569;
            font-size: 12px;
            line-height: 1.7;
        }

        .job-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }

        .job-meta span {
            padding: 6px 9px;
            background: #f1f5f9;
            border-radius: 5px;
            font-size: 12px;
        }

        .salary {
            color: #166534;
            font-weight: bold;
        }

        .job-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding-top: 15px;
            margin-top: auto;
            border-top: 1px solid #eee;
        }

        .job-date {
            color: #777;
            font-size: 12px;
        }

        .view-btn {
            display: inline-block;
            padding: 9px 15px;
            background: #2563eb;
            border-radius: 6px;
            color: #fff;
            font-size: 13px;
            font-weight: bold;
            text-decoration: none;
        }

        .view-btn:hover {
            background: #1d4ed8;
        }

        .empty {
            grid-column: 1 / -1;
            padding: 50px 25px;
            background: #fff;
            border-radius: 10px;
            text-align: center;
        }

        .empty h2 {
            margin-bottom: 8px;
        }

        .empty p {
            color: #666;
        }

        @media (max-width: 850px) {
            .jobs-grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 15px 20px;
                flex-direction: column;
                gap: 12px;
            }

            .nav-links {
                justify-content: center;
                flex-wrap: wrap;
            }

            .search-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<nav class="navbar">

    <a
        href="../index.php"
        class="logo"
    >
        JobPortal
    </a>

    <div class="nav-links">

        <a href="../index.php">
            Home
        </a>

        <a href="index.php">
            Jobs
        </a>


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

        <?php endif; ?>


        <?php if (isset($_SESSION["user_id"])): ?>

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


<div class="container">

    <div class="page-header">

        <h1>Find Jobs</h1>

        <p>
            Search available job opportunities
            in North and South Caloocan.
        </p>

    </div>


    <div class="search-card">

        <form
            method="GET"
            action="index.php"
            class="search-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Job title, skill, TESDA qualification or company"
                value="<?= htmlspecialchars($search) ?>"
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


            <select name="source">

                <option value="">
                    All Sources
                </option>

                <option
                    value="private_employer"
                    <?= $source === "private_employer"
                        ? "selected"
                        : ""
                    ?>
                >
                    Private Employer
                </option>

                <option
                    value="lgu"
                    <?= $source === "lgu"
                        ? "selected"
                        : ""
                    ?>
                >
                    LGU Opportunity
                </option>

                <option
                    value="job_fair"
                    <?= $source === "job_fair"
                        ? "selected"
                        : ""
                    ?>
                >
                    Job Fair
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


    <?php if ($isApplicant): ?>

        <?php if ($hasApplicantLocation): ?>

            <div class="
                location-notice
                location-ready
            ">
                <strong>
                    Your saved location is ready.
                </strong>

                Distance from your saved location
                will be shown when available.
            </div>

        <?php else: ?>

            <div class="
                location-notice
                location-missing
            ">
                <strong>
                    Your saved location is incomplete.
                </strong>

                Update your applicant profile
                to enable job-distance calculation.
            </div>

        <?php endif; ?>

    <?php endif; ?>


    <div class="jobs-grid">

        <?php if (empty($jobs)): ?>

            <div class="empty">

                <h2>No jobs found</h2>

                <p>
                    Try another keyword,
                    area, or job source.
                </p>

            </div>

        <?php else: ?>


            <?php foreach ($jobs as $job): ?>

                <?php
                $distanceKm = null;

                if (
                    $hasApplicantLocation &&
                    is_numeric(
                        $job["latitude"] ?? null
                    ) &&
                    is_numeric(
                        $job["longtitude"] ?? null
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

                $sourceLabel = ucwords(
                    str_replace(
                        "_",
                        " ",
                        $job["job_source"]
                        ?? "private_employer"
                    )
                );
                ?>


                <article class="job-card">

                    <h2 class="job-title">
                        <?= htmlspecialchars(
                            $job["job_title"]
                        ) ?>
                    </h2>


                    <div class="company">
                        <?= htmlspecialchars(
                            $job["company_name"]
                        ) ?>
                    </div>


                    <div class="source-badge">

                        <?= htmlspecialchars(
                            $sourceLabel
                        ) ?>

                        <?php if (!empty($job["source_name"])): ?>
                            -
                            <?= htmlspecialchars(
                                $job["source_name"]
                            ) ?>
                        <?php endif; ?>

                    </div>


                    <div class="job-description">
                        <?= htmlspecialchars(
                            $job["description"]
                        ) ?>
                    </div>


                    <div class="location-box">

                        <div class="barangay">
                            <?= htmlspecialchars(
                                $job["barangay"]
                                    ?: "Caloocan City"
                            ) ?>
                        </div>

                        <div class="address">
                            <?= htmlspecialchars(
                                $job["address"]
                                    ?: (
                                        $job["location"]
                                        ?: "Caloocan City"
                                    )
                            ) ?>
                        </div>


                        <?php if (!empty($job["caloocan_area"])): ?>

                            <span
                                class="area-badge <?= htmlspecialchars(
                                    $job["caloocan_area"]
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    strtoupper(
                                        $job["caloocan_area"]
                                    )
                                ) ?>
                                CALOOCAN
                            </span>

                        <?php endif; ?>


                        <?php if ($distanceKm !== null): ?>

                            <div class="distance">
                                <?= number_format(
                                    $distanceKm,
                                    2
                                ) ?>
                                km away from you
                            </div>

                        <?php endif; ?>

                    </div>


                    <?php if (
                        !empty($job["educational_requirement"]) ||
                        !empty($job["required_degree"]) ||
                        !empty($job["tesda_qualification"])
                    ): ?>

                        <div class="qualification-box">

                            <?php if (!empty(
                                $job["educational_requirement"]
                            )): ?>

                                <strong>Education:</strong>

                                <?= htmlspecialchars(
                                    $job["educational_requirement"]
                                ) ?>

                                <br>

                            <?php endif; ?>


                            <?php if (!empty(
                                $job["required_degree"]
                            )): ?>

                                <strong>Degree:</strong>

                                <?= htmlspecialchars(
                                    $job["required_degree"]
                                ) ?>

                                <br>

                            <?php endif; ?>


                            <?php if (!empty(
                                $job["tesda_qualification"]
                            )): ?>

                                <strong>TESDA:</strong>

                                <?= htmlspecialchars(
                                    $job["tesda_qualification"]
                                ) ?>

                                <?php if (!empty(
                                    $job["required_nc_level"]
                                )): ?>

                                    (
                                    <?= htmlspecialchars(
                                        $job["required_nc_level"]
                                    ) ?>
                                    )

                                <?php endif; ?>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>


                    <div class="job-meta">

                        <span>
                            <?= htmlspecialchars(
                                $job["employment_type"]
                            ) ?>
                        </span>


                        <?php if ($job["is_remote"]): ?>

                            <span>
                                Remote
                            </span>

                        <?php endif; ?>


                        <?php if (
                            $job["salary_min"] !== null ||
                            $job["salary_max"] !== null
                        ): ?>

                            <span class="salary">

                                <?php if (
                                    $job["salary_min"] !== null
                                ): ?>

                                    ₱<?= number_format(
                                        (float) $job["salary_min"]
                                    ) ?>

                                <?php endif; ?>


                                <?php if (
                                    $job["salary_min"] !== null &&
                                    $job["salary_max"] !== null
                                ): ?>
                                    -
                                <?php endif; ?>


                                <?php if (
                                    $job["salary_max"] !== null
                                ): ?>

                                    ₱<?= number_format(
                                        (float) $job["salary_max"]
                                    ) ?>

                                <?php endif; ?>

                            </span>

                        <?php endif; ?>

                    </div>


                    <div class="job-footer">

                        <span class="job-date">
                            Posted
                            <?= date(
                                "M d, Y",
                                strtotime(
                                    $job["created_at"]
                                )
                            ) ?>
                        </span>


                        <a
                            href="view.php?id=<?= (int) $job["id"] ?>"
                            class="view-btn"
                        >
                            View Job
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>

</body>
</html>