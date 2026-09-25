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

$error = "";


/*
|--------------------------------------------------------------------------
| GET EMPLOYER
|--------------------------------------------------------------------------
|
| Kukunin natin:
| - employer id
| - company
| - verified address
| - barangay
| - north/south
| - latitude
| - longitude
| - verification status
|
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        company_name,
        address,
        barangay,
        caloocan_area,
        latitude,
        longitude,
        verification_status
    FROM employers
    WHERE user_id = ?
    LIMIT 1
");


$stmt->execute([
    $userId
]);


$employer =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$employer) {

    die(
        "Employer profile not found."
    );
}


/*
|--------------------------------------------------------------------------
| EMPLOYER MUST BE VERIFIED
|--------------------------------------------------------------------------
*/

if (
    $employer["verification_status"]
    !== "approved"
) {

    header(
        "Location: jobs.php?verification_required=1"
    );

    exit;
}


$employerId =
    $employer["id"];


/*
|--------------------------------------------------------------------------
| VERIFIED EMPLOYER LOCATION
|--------------------------------------------------------------------------
*/

$verifiedAddress =
    $employer["address"]
    ?? "";

$verifiedBarangay =
    $employer["barangay"]
    ?? "";

$verifiedArea =
    $employer["caloocan_area"]
    ?? "";

$verifiedLatitude =
    $employer["latitude"]
    ?? "";

$verifiedLongitude =
    $employer["longitude"]
    ?? "";


/*
|--------------------------------------------------------------------------
| MAKE SURE VERIFIED LOCATION EXISTS
|--------------------------------------------------------------------------
*/

if (
    $verifiedAddress === "" ||
    $verifiedBarangay === "" ||
    $verifiedArea === "" ||
    $verifiedLatitude === "" ||
    $verifiedLongitude === ""
) {

    header(
        "Location: profile.php"
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$jobTitle = "";

$jobDescription = "";

$employmentType = "";

$salaryMin = "";

$salaryMax = "";

$applicationDeadline = "";

$requirements = "";


/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {


        /*
        |--------------------------------------------------------------------------
        | JOB INPUT
        |--------------------------------------------------------------------------
        */

        $jobTitle =
            trim(
                $_POST["job_title"]
                ?? ""
            );


        $jobDescription =
            trim(
                $_POST["job_description"]
                ?? ""
            );


        $employmentType =
            trim(
                $_POST["employment_type"]
                ?? ""
            );


        $salaryMin =
            trim(
                $_POST["salary_min"]
                ?? ""
            );


        $salaryMax =
            trim(
                $_POST["salary_max"]
                ?? ""
            );


        $applicationDeadline =
            trim(
                $_POST["application_deadline"]
                ?? ""
            );


        $requirements =
            trim(
                $_POST["requirements"]
                ?? ""
            );


        /*
        |--------------------------------------------------------------------------
        | SERVER-SIDE LOCATION
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | Hindi natin kukunin ang address galing sa hidden input.
        |
        | Gagamitin natin mismo ang nasa employers table.
        |
        | Para hindi kayang baguhin ng employer gamit
        | browser developer tools.
        |
        */

        $jobAddress =
            $verifiedAddress;

        $jobBarangay =
            $verifiedBarangay;

        $jobArea =
            $verifiedArea;

        $jobLatitude =
            $verifiedLatitude;

        $jobLongitude =
            $verifiedLongitude;


        /*
        |--------------------------------------------------------------------------
        | LOCATION DISPLAY
        |--------------------------------------------------------------------------
        |
        | Existing jobs.php mo gumagamit ng:
        |
        | j.location
        |
        | Kaya ise-save din natin ang readable location.
        |
        */

        $location =
            $jobBarangay
            . ", "
            . strtoupper($jobArea)
            . " Caloocan";


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($jobTitle === "") {

            throw new Exception(
                "Job title is required."
            );
        }


        if ($jobDescription === "") {

            throw new Exception(
                "Job description is required."
            );
        }


        $allowedEmploymentTypes = [
            "Full-time",
            "Part-time",
            "Contract",
            "Temporary",
            "Internship"
        ];


        if (
            !in_array(
                $employmentType,
                $allowedEmploymentTypes,
                true
            )
        ) {

            throw new Exception(
                "Please select a valid employment type."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | SALARY VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $salaryMin !== "" &&
            !is_numeric($salaryMin)
        ) {

            throw new Exception(
                "Minimum salary must be a valid number."
            );
        }


        if (
            $salaryMax !== "" &&
            !is_numeric($salaryMax)
        ) {

            throw new Exception(
                "Maximum salary must be a valid number."
            );
        }


        if (
            $salaryMin !== "" &&
            $salaryMax !== "" &&
            (float) $salaryMin >
            (float) $salaryMax
        ) {

            throw new Exception(
                "Minimum salary cannot be greater than maximum salary."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY SALARY = NULL
        |--------------------------------------------------------------------------
        */

        $salaryMinValue =
            $salaryMin !== ""
            ? $salaryMin
            : null;


        $salaryMaxValue =
            $salaryMax !== ""
            ? $salaryMax
            : null;


        /*
        |--------------------------------------------------------------------------
        | DEADLINE VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($applicationDeadline === "") {

            throw new Exception(
                "Application deadline is required."
            );
        }


        if (
            strtotime(
                $applicationDeadline
            ) === false
        ) {

            throw new Exception(
                "Invalid application deadline."
            );
        }


        if (
            strtotime(
                $applicationDeadline
            ) <
            strtotime(
                date("Y-m-d")
            )
        ) {

            throw new Exception(
                "Application deadline cannot be in the past."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | INSERT JOB
        |--------------------------------------------------------------------------
        |
        | New job starts as PENDING.
        |
        | Admin still needs to approve the job posting.
        |
        */

        $stmt = $pdo->prepare("
            INSERT INTO jobs (

                employer_id,

                job_title,

                job_description,

                requirements,

                location,

                address,

                barangay,

                caloocan_area,

                latitude,

                longtitude,

                employment_type,

                salary_min,

                salary_max,

                application_deadline,

                status,

                created_at

            )
            VALUES (

                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'pending',
                NOW()

            )
        ");


        $stmt->execute([

            $employerId,

            $jobTitle,

            $jobDescription,

            $requirements,

            $location,

            $jobAddress,

            $jobBarangay,

            $jobArea,

            $jobLatitude,

            $jobLongitude,

            $employmentType,

            $salaryMinValue,

            $salaryMaxValue,

            $applicationDeadline

        ]);


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        header(
            "Location: jobs.php?created=1"
        );

        exit;


    } catch (Exception $e) {

        $error =
            $e->getMessage();
    }
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
    Post a Job - Caloocan Job Portal
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
        950px;

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

    line-height:
        1.5;
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
        7px;
}


.card-description {

    color:
        #666;

    font-size:
        14px;

    line-height:
        1.5;

    margin-bottom:
        22px;
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-grid {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        18px;
}


.form-group {

    margin-bottom:
        18px;
}


.form-group.full {

    grid-column:
        1 / -1;
}


label {

    display:
        block;

    font-size:
        14px;

    font-weight:
        bold;

    margin-bottom:
        7px;
}


input[type="text"],
input[type="number"],
input[type="date"],
select,
textarea {

    width:
        100%;

    padding:
        11px 12px;

    border:
        1px solid #d1d5db;

    border-radius:
        6px;

    font-family:
        Arial,
        sans-serif;

    font-size:
        14px;

    background:
        white;
}


input:focus,
select:focus,
textarea:focus {

    outline:
        none;

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 2px
        rgba(37,99,235,0.08);
}


textarea {

    min-height:
        130px;

    resize:
        vertical;
}


/*
|--------------------------------------------------------------------------
| READ ONLY
|--------------------------------------------------------------------------
*/

.readonly {

    background:
        #f8fafc !important;

    color:
        #475569;
}


/*
|--------------------------------------------------------------------------
| ALERT
|--------------------------------------------------------------------------
*/

.alert {

    padding:
        14px 16px;

    border-radius:
        8px;

    margin-bottom:
        20px;

    line-height:
        1.5;
}


.alert-error {

    background:
        #fee2e2;

    color:
        #991b1b;

    border:
        1px solid #fecaca;
}


/*
|--------------------------------------------------------------------------
| VERIFIED LOCATION
|--------------------------------------------------------------------------
*/

.location-box {

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    border-radius:
        8px;

    padding:
        18px;

    margin-bottom:
        20px;
}


.location-box h3 {

    color:
        #1e40af;

    margin-bottom:
        8px;
}


.location-box p {

    color:
        #475569;

    font-size:
        14px;

    line-height:
        1.5;
}


.verified-badge {

    display:
        inline-block;

    background:
        #dcfce7;

    color:
        #166534;

    padding:
        5px 10px;

    border-radius:
        20px;

    font-size:
        12px;

    font-weight:
        bold;

    margin-top:
        10px;
}


/*
|--------------------------------------------------------------------------
| AREA BADGE
|--------------------------------------------------------------------------
*/

.area-badge {

    display:
        inline-block;

    padding:
        7px 12px;

    border-radius:
        20px;

    font-size:
        13px;

    font-weight:
        bold;
}


.area-north {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.area-south {

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
        10px;
}


/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.actions {

    display:
        flex;

    justify-content:
        flex-end;

    gap:
        10px;

    flex-wrap:
        wrap;
}


.btn {

    display:
        inline-block;

    padding:
        11px 17px;

    border:
        none;

    border-radius:
        6px;

    background:
        #2563eb;

    color:
        white;

    text-decoration:
        none;

    cursor:
        pointer;

    font-size:
        14px;
}


.btn:hover {

    background:
        #1d4ed8;
}


.btn-secondary {

    background:
        #64748b;
}


.btn-secondary:hover {

    background:
        #475569;
}


/*
|--------------------------------------------------------------------------
| NOTE
|--------------------------------------------------------------------------
*/

.note {

    background:
        #f8fafc;

    border:
        1px solid #e2e8f0;

    padding:
        15px;

    border-radius:
        8px;

    color:
        #555;

    font-size:
        13px;

    line-height:
        1.6;

    margin-top:
        10px;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .form-grid {

        grid-template-columns:
            1fr;
    }


    .form-group.full {

        grid-column:
            auto;
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


    #map {

        height:
            280px;
    }


    .actions {

        justify-content:
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
         PAGE HEADER
    ====================================================== -->

    <div class="page-header">

        <h1>
            Post a Job
        </h1>


        <p>

            Create a new job opening for

            <strong>

                <?= htmlspecialchars(
                    $employer[
                        "company_name"
                    ]
                ) ?>

            </strong>.

        </p>

    </div>



    <!-- =====================================================
         ERROR
    ====================================================== -->

    <?php if ($error): ?>


        <div class="alert alert-error">

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>


    <?php endif; ?>



    <form method="POST">


        <!-- =================================================
             JOB DETAILS
        ================================================== -->

        <div class="card">


            <h2>
                Job Details
            </h2>


            <p class="card-description">

                Enter the information about
                the job opening.

            </p>



            <div class="form-grid">


                <!-- JOB TITLE -->

                <div class="form-group full">

                    <label>
                        Job Title *
                    </label>


                    <input
                        type="text"
                        name="job_title"
                        required

                        placeholder="Example: Sales Associate"

                        value="<?= htmlspecialchars(
                            $jobTitle
                        ) ?>"
                    >

                </div>



                <!-- EMPLOYMENT TYPE -->

                <div class="form-group">

                    <label>
                        Employment Type *
                    </label>


                    <select
                        name="employment_type"
                        required
                    >

                        <option value="">
                            Select employment type
                        </option>


                        <option
                            value="Full-time"

                            <?= $employmentType ===
                                "Full-time"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Full-time
                        </option>


                        <option
                            value="Part-time"

                            <?= $employmentType ===
                                "Part-time"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Part-time
                        </option>


                        <option
                            value="Contract"

                            <?= $employmentType ===
                                "Contract"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Contract
                        </option>


                        <option
                            value="Temporary"

                            <?= $employmentType ===
                                "Temporary"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Temporary
                        </option>


                        <option
                            value="Internship"

                            <?= $employmentType ===
                                "Internship"
                                ? "selected"
                                : ""
                            ?>
                        >
                            Internship
                        </option>

                    </select>

                </div>



                <!-- APPLICATION DEADLINE -->

                <div class="form-group">

                    <label>
                        Application Deadline *
                    </label>


                    <input
                        type="date"
                        name="application_deadline"
                        required

                        min="<?= date("Y-m-d") ?>"

                        value="<?= htmlspecialchars(
                            $applicationDeadline
                        ) ?>"
                    >

                </div>



                <!-- SALARY MIN -->

                <div class="form-group">

                    <label>
                        Minimum Salary
                    </label>


                    <input
                        type="number"
                        name="salary_min"

                        min="0"
                        step="0.01"

                        placeholder="Example: 18000"

                        value="<?= htmlspecialchars(
                            $salaryMin
                        ) ?>"
                    >

                </div>



                <!-- SALARY MAX -->

                <div class="form-group">

                    <label>
                        Maximum Salary
                    </label>


                    <input
                        type="number"
                        name="salary_max"

                        min="0"
                        step="0.01"

                        placeholder="Example: 25000"

                        value="<?= htmlspecialchars(
                            $salaryMax
                        ) ?>"
                    >

                </div>



                <!-- DESCRIPTION -->

                <div class="form-group full">

                    <label>
                        Job Description *
                    </label>


                    <textarea
                        name="job_description"
                        required
                        placeholder="Describe the responsibilities and duties of the position..."
                    ><?= htmlspecialchars(
                        $jobDescription
                    ) ?></textarea>

                </div>



                <!-- REQUIREMENTS -->

                <div class="form-group full">

                    <label>
                        Requirements
                    </label>


                    <textarea
                        name="requirements"
                        placeholder="Example: High school graduate, good communication skills, willing to work on-site..."
                    ><?= htmlspecialchars(
                        $requirements
                    ) ?></textarea>

                </div>


            </div>


        </div>



        <!-- =================================================
             VERIFIED JOB LOCATION
        ================================================== -->

        <div class="card">


            <h2>
                Job Location
            </h2>


            <p class="card-description">

                This job will use the verified
                business location from your
                employer profile.

            </p>



            <div class="location-box">


                <h3>
                    ✓ Verified Employer Location
                </h3>


                <p>

                    This location was submitted
                    through your employer profile
                    and approved as part of your
                    employer verification.

                </p>


                <span class="verified-badge">

                    VERIFIED LOCATION

                </span>


            </div>



            <!-- COMPLETE ADDRESS -->

            <div class="form-group">

                <label>
                    Complete Address
                </label>


                <textarea
                    class="readonly"
                    readonly
                ><?= htmlspecialchars(
                    $verifiedAddress
                ) ?></textarea>

            </div>



            <div class="form-grid">


                <!-- BARANGAY -->

                <div class="form-group">

                    <label>
                        Barangay
                    </label>


                    <input
                        type="text"
                        class="readonly"
                        readonly

                        value="<?= htmlspecialchars(
                            $verifiedBarangay
                        ) ?>"
                    >

                </div>



                <!-- AREA -->

                <div class="form-group">

                    <label>
                        Caloocan Area
                    </label>


                    <div>

                        <span
                            class="
                                area-badge

                                <?= $verifiedArea ===
                                    "north"
                                    ? "area-north"
                                    : "area-south"
                                ?>
                            "
                        >

                            <?= strtoupper(
                                htmlspecialchars(
                                    $verifiedArea
                                )
                            ) ?>

                            CALOOCAN

                        </span>

                    </div>

                </div>



                <!-- LATITUDE -->

                <div class="form-group">

                    <label>
                        Latitude
                    </label>


                    <input
                        type="text"
                        class="readonly"
                        readonly

                        value="<?= htmlspecialchars(
                            $verifiedLatitude
                        ) ?>"
                    >

                </div>



                <!-- LONGITUDE -->

                <div class="form-group">

                    <label>
                        Longitude
                    </label>


                    <input
                        type="text"
                        class="readonly"
                        readonly

                        value="<?= htmlspecialchars(
                            $verifiedLongitude
                        ) ?>"
                    >

                </div>


            </div>



            <!-- MAP -->

            <iframe
                id="map"

                src="https://www.google.com/maps?q=<?= urlencode(
                    $verifiedLatitude
                ) ?>,<?= urlencode(
                    $verifiedLongitude
                ) ?>&z=17&output=embed"

                loading="lazy"

                allowfullscreen
            ></iframe>



            <div class="note">

                <strong>
                    Note:
                </strong>

                The job location is automatically
                taken from your verified employer
                profile.

                If your business location changes,
                update your Employer Profile and
                submit it for verification again.

            </div>


        </div>



        <!-- =================================================
             SUBMIT
        ================================================== -->

        <div class="card">


            <div class="actions">


                <a
                    href="jobs.php"
                    class="
                        btn
                        btn-secondary
                    "
                >
                    Cancel
                </a>


                <button
                    type="submit"
                    class="btn"
                >

                    Submit Job for Approval

                </button>


            </div>


        </div>


    </form>


</div>


</body>

</html>