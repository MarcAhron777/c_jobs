<?php

session_start();

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"])
    ||
    $_SESSION["role"] !== "applicant"
) {

    header("Location: ../login.php");
    exit;
}


$userId =
    $_SESSION["user_id"];


$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    $firstName =
        trim(
            $_POST["first_name"]
            ?? ""
        );


    $lastName =
        trim(
            $_POST["last_name"]
            ?? ""
        );


    $phone =
        trim(
            $_POST["phone"]
            ?? ""
        );


    $address =
        trim(
            $_POST["address"]
            ?? ""
        );


    $religion =
        trim(
            $_POST["religion"]
            ?? ""
        );


    $education =
        trim(
            $_POST["education"]
            ?? ""
        );


    $bio =
        trim(
            $_POST["bio"]
            ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | LOCATION
    |--------------------------------------------------------------------------
    */

    $latitude =
        trim(
            $_POST["latitude"]
            ?? ""
        );


    $longitude =
        trim(
            $_POST["longitude"]
            ?? ""
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $firstName === ""
        ||
        $lastName === ""
    ) {

        $error =
            "First name and last name are required.";

    } else {

        try {


            /*
            |--------------------------------------------------------------------------
            | CONVERT EMPTY LOCATION TO NULL
            |--------------------------------------------------------------------------
            */

            $latitudeValue =
                is_numeric($latitude)
                ? (float) $latitude
                : null;


            $longitudeValue =
                is_numeric($longitude)
                ? (float) $longitude
                : null;


            /*
            |--------------------------------------------------------------------------
            | VALIDATE COORDINATE RANGE
            |--------------------------------------------------------------------------
            */

            if (
                $latitudeValue !== null
                &&
                (
                    $latitudeValue < -90
                    ||
                    $latitudeValue > 90
                )
            ) {

                throw new Exception(
                    "Invalid latitude."
                );
            }


            if (
                $longitudeValue !== null
                &&
                (
                    $longitudeValue < -180
                    ||
                    $longitudeValue > 180
                )
            ) {

                throw new Exception(
                    "Invalid longitude."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare("
                    UPDATE applicants

                    SET

                        first_name = ?,

                        last_name = ?,

                        phone = ?,

                        address = ?,

                        latitude = ?,

                        longitude = ?,

                        religion = ?,

                        education = ?,

                        bio = ?

                    WHERE user_id = ?
                ");


            $stmt->execute([

                $firstName,

                $lastName,

                $phone !== ""
                    ? $phone
                    : null,

                $address !== ""
                    ? $address
                    : null,

                $latitudeValue,

                $longitudeValue,

                $religion !== ""
                    ? $religion
                    : null,

                $education !== ""
                    ? $education
                    : null,

                $bio !== ""
                    ? $bio
                    : null,

                $userId

            ]);


            $success =
                "Profile updated successfully.";


        } catch (
            PDOException $e
        ) {

            $error =
                "Failed to update profile.";

        } catch (
            Exception $e
        ) {

            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| GET PROFILE
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare("
        SELECT

            first_name,

            last_name,

            phone,

            religion,

            education,

            address,

            latitude,

            longitude,

            bio,

            profile_picture

        FROM applicants

        WHERE user_id = ?

        LIMIT 1
    ");


$stmt->execute([
    $userId
]);


$applicant =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$applicant) {

    die(
        "Applicant profile not found."
    );
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
    My Profile - Caloocan Job Portal
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
        800px;

    margin:
        40px auto;

    padding:
        0 20px;
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
        rgba(
            0,
            0,
            0,
            0.05
        );
}


h1 {

    margin-bottom:
        10px;
}


.subtitle {

    color:
        #666;

    margin-bottom:
        25px;
}


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

.message {

    padding:
        12px;

    border-radius:
        6px;

    margin-bottom:
        20px;
}


.success {

    background:
        #dcfce7;

    color:
        #166534;
}


.error {

    background:
        #fee2e2;

    color:
        #991b1b;
}


/*
|--------------------------------------------------------------------------
| FORM
|--------------------------------------------------------------------------
*/

.form-group {

    margin-bottom:
        18px;
}


label {

    display:
        block;

    margin-bottom:
        7px;

    font-weight:
        bold;
}


input,
textarea {

    width:
        100%;

    padding:
        12px;

    border:
        1px solid #ccc;

    border-radius:
        6px;

    font-size:
        15px;
}


input:focus,
textarea:focus {

    outline:
        none;

    border-color:
        #2563eb;
}


textarea {

    min-height:
        120px;

    resize:
        vertical;
}


.row {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        15px;
}


/*
|--------------------------------------------------------------------------
| LOCATION
|--------------------------------------------------------------------------
*/

.location-card {

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    padding:
        18px;

    border-radius:
        8px;

    margin-bottom:
        20px;
}


.location-card h3 {

    margin-bottom:
        7px;

    color:
        #1e40af;
}


.location-card p {

    color:
        #475569;

    font-size:
        14px;

    line-height:
        1.5;

    margin-bottom:
        15px;
}


.location-btn {

    background:
        #16a34a;

    margin-bottom:
        15px;
}


.location-btn:hover {

    background:
        #15803d;
}


.location-status {

    margin-top:
        10px;

    font-size:
        14px;

    color:
        #166534;

    font-weight:
        bold;
}


.readonly-input {

    background:
        #f8fafc;
}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

button {

    padding:
        12px 20px;

    background:
        #2563eb;

    color:
        white;

    border:
        none;

    border-radius:
        6px;

    cursor:
        pointer;

    font-size:
        15px;
}


button:hover {

    background:
        #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| BACK
|--------------------------------------------------------------------------
*/

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
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 600px
) {

    .row {

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

        <a href="../index.php">Home</a>
        
        <a href="dashboard.php">Dashboard</a>
        
        <a href="../jobs/index.php">Find Jobs</a>

        <a href="profile.php">Profile</a>

        <a href="resume.php">Resume</a>

        <a href="applications.php">Applications</a>

        <a href="../logout.php" class="logout">Logout</a>

    </div>

</nav>



<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <a
        href="dashboard.php"
        class="back"
    >
        ← Back to Dashboard
    </a>



    <div class="card">


        <h1>
            My Profile
        </h1>


        <p class="subtitle">

            Update your personal information
            and current location.

        </p>



        <?php if ($success): ?>


            <div
                class="
                    message
                    success
                "
            >

                <?= htmlspecialchars(
                    $success
                ) ?>

            </div>


        <?php endif; ?>



        <?php if ($error): ?>


            <div
                class="
                    message
                    error
                "
            >

                <?= htmlspecialchars(
                    $error
                ) ?>

            </div>


        <?php endif; ?>



        <form method="POST">


            <!-- =================================================
                 NAME
            ================================================== -->

            <div class="row">


                <div class="form-group">

                    <label>
                        First Name
                    </label>


                    <input
                        type="text"
                        name="first_name"

                        value="<?= htmlspecialchars(
                            $applicant[
                                "first_name"
                            ]
                        ) ?>"

                        required
                    >

                </div>



                <div class="form-group">

                    <label>
                        Last Name
                    </label>


                    <input
                        type="text"
                        name="last_name"

                        value="<?= htmlspecialchars(
                            $applicant[
                                "last_name"
                            ]
                        ) ?>"

                        required
                    >

                </div>


            </div>



            <!-- =================================================
                 PHONE
            ================================================== -->

            <div class="form-group">

                <label>
                    Phone
                </label>


                <input
                    type="text"
                    name="phone"

                    value="<?= htmlspecialchars(
                        $applicant[
                            "phone"
                        ]
                        ?? ""
                    ) ?>"
                >

            </div>



            <!-- =================================================
                 ADDRESS
            ================================================== -->

            <div class="form-group">

                <label>
                    Address
                </label>


                <input
                    type="text"
                    name="address"

                    placeholder="Enter your current address"

                    value="<?= htmlspecialchars(
                        $applicant[
                            "address"
                        ]
                        ?? ""
                    ) ?>"
                >

            </div>



            <!-- =================================================
                 CURRENT LOCATION
            ================================================== -->

            <div class="location-card">


                <h3>
                    📍 Current Location
                </h3>


                <p>

                    Use your device location so
                    the Job Portal can calculate
                    how far job postings are from you.

                </p>



                <button
                    type="button"
                    class="location-btn"
                    id="locationButton"
                    onclick="getCurrentLocation()"
                >

                    Use My Current Location

                </button>



                <div class="row">


                    <!-- LATITUDE -->

                    <div class="form-group">

                        <label>
                            Latitude
                        </label>


                        <input
                            type="text"
                            name="latitude"
                            id="latitude"
                            class="readonly-input"

                            value="<?= htmlspecialchars(
                                $applicant[
                                    "latitude"
                                ]
                                ?? ""
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
                            name="longitude"
                            id="longitude"
                            class="readonly-input"

                            value="<?= htmlspecialchars(
                                $applicant[
                                    "longitude"
                                ]
                                ?? ""
                            ) ?>"
                        >

                    </div>


                </div>



                <div
                    id="locationStatus"
                    class="location-status"
                >

                    <?php if (
                        !empty(
                            $applicant[
                                "latitude"
                            ]
                        )
                        &&
                        !empty(
                            $applicant[
                                "longitude"
                            ]
                        )
                    ): ?>

                        ✓ Location coordinates saved.

                    <?php endif; ?>

                </div>


            </div>



            <!-- =================================================
                 RELIGION
            ================================================== -->

            <div class="form-group">

                <label>
                    Religion
                </label>


                <input
                    type="text"
                    name="religion"

                    value="<?= htmlspecialchars(
                        $applicant[
                            "religion"
                        ]
                        ?? ""
                    ) ?>"
                >

            </div>



            <!-- =================================================
                 EDUCATION
            ================================================== -->

            <div class="form-group">

                <label>
                    Education
                </label>


                <input
                    type="text"
                    name="education"

                    value="<?= htmlspecialchars(
                        $applicant[
                            "education"
                        ]
                        ?? ""
                    ) ?>"
                >

            </div>



            <!-- =================================================
                 BIO
            ================================================== -->

            <div class="form-group">

                <label>
                    Bio
                </label>


                <textarea
                    name="bio"
                    placeholder="Tell employers a little about yourself..."
                ><?= htmlspecialchars(
                    $applicant[
                        "bio"
                    ]
                    ?? ""
                ) ?></textarea>

            </div>



            <button type="submit">

                Save Changes

            </button>


        </form>


    </div>

</div>



<!-- =========================================================
     GEOLOCATION
========================================================= -->

<script>

function getCurrentLocation()
{

    const latitude =
        document.getElementById(
            "latitude"
        );


    const longitude =
        document.getElementById(
            "longitude"
        );


    const status =
        document.getElementById(
            "locationStatus"
        );


    const button =
        document.getElementById(
            "locationButton"
        );


    /*
    |--------------------------------------------------------------------------
    | GEOLOCATION SUPPORT
    |--------------------------------------------------------------------------
    */

    if (
        !navigator.geolocation
    ) {

        status.innerHTML =
            "⚠ Geolocation is not supported by your browser.";

        return;
    }


    /*
    |--------------------------------------------------------------------------
    | LOADING
    |--------------------------------------------------------------------------
    */

    button.disabled =
        true;


    button.innerHTML =
        "Getting Location...";


    status.innerHTML =
        "Detecting your current location...";


    /*
    |--------------------------------------------------------------------------
    | GET CURRENT POSITION
    |--------------------------------------------------------------------------
    */

    navigator.geolocation.getCurrentPosition(


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        function(position)
        {

            latitude.value =
                position.coords.latitude
                    .toFixed(8);


            longitude.value =
                position.coords.longitude
                    .toFixed(8);


            status.innerHTML =
                "✓ Current location detected. Click Save Changes to save it.";


            button.disabled =
                false;


            button.innerHTML =
                "Update Current Location";

        },


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        function(error)
        {

            button.disabled =
                false;


            button.innerHTML =
                "Use My Current Location";


            if (
                error.code ===
                error.PERMISSION_DENIED
            ) {

                status.innerHTML =
                    "⚠ Location permission denied. Please allow location access.";

            }


            else if (
                error.code ===
                error.POSITION_UNAVAILABLE
            ) {

                status.innerHTML =
                    "⚠ Your location is currently unavailable.";

            }


            else if (
                error.code ===
                error.TIMEOUT
            ) {

                status.innerHTML =
                    "⚠ Location request timed out. Please try again.";

            }


            else {

                status.innerHTML =
                    "⚠ Unable to detect your location.";

            }

        },


        /*
        |--------------------------------------------------------------------------
        | OPTIONS
        |--------------------------------------------------------------------------
        */

        {

            enableHighAccuracy:
                true,

            timeout:
                15000,

            maximumAge:
                60000

        }

    );

}

</script>


</body>

</html>