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


$userId = (int) $_SESSION["user_id"];

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| GEOCODE ADDRESS
|--------------------------------------------------------------------------
|
| Converts the applicant's typed address into latitude + longitude
| using OpenStreetMap Nominatim.
|
| Latitude and Longitude are NOT shown to the applicant.
|
*/

function geocodeAddress(string $address): ?array
{
    $address = trim($address);

    if ($address === "") {
        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | Improve Search Accuracy
    |--------------------------------------------------------------------------
    */

    $searchAddress = $address;


    if (
        stripos(
            $searchAddress,
            "Caloocan"
        ) === false
    ) {

        $searchAddress .=
            ", Caloocan City";
    }


    if (
        stripos(
            $searchAddress,
            "Philippines"
        ) === false
    ) {

        $searchAddress .=
            ", Philippines";
    }


    /*
    |--------------------------------------------------------------------------
    | MAKE SURE CURL EXISTS
    |--------------------------------------------------------------------------
    */

    if (!function_exists("curl_init")) {

        throw new Exception(
            "PHP cURL is not enabled. Please enable the cURL extension in XAMPP."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | OPENSTREETMAP NOMINATIM
    |--------------------------------------------------------------------------
    */

    $query = http_build_query([
        "q" => $searchAddress,
        "format" => "jsonv2",
        "limit" => 1,
        "countrycodes" => "ph",
        "addressdetails" => 1
    ]);


    $url =
        "https://nominatim.openstreetmap.org/search?"
        . $query;


    /*
    |--------------------------------------------------------------------------
    | CURL REQUEST
    |--------------------------------------------------------------------------
    */

    $ch = curl_init();


    curl_setopt_array(
        $ch,
        [

            CURLOPT_URL =>
                $url,

            CURLOPT_RETURNTRANSFER =>
                true,

            CURLOPT_FOLLOWLOCATION =>
                true,

            CURLOPT_CONNECTTIMEOUT =>
                10,

            CURLOPT_TIMEOUT =>
                15,

            CURLOPT_HTTPHEADER => [

                "Accept: application/json",

                "User-Agent: CaloocanJobsPortal/1.0"

            ]

        ]
    );


    $response =
        curl_exec(
            $ch
        );


    /*
    |--------------------------------------------------------------------------
    | CURL ERROR
    |--------------------------------------------------------------------------
    */

    if ($response === false) {

        $curlError =
            curl_error(
                $ch
            );


        curl_close(
            $ch
        );


        error_log(
            "Geocoding CURL Error: "
            . $curlError
        );


        throw new Exception(
            "Unable to connect to the address location service. Please try again."
        );
    }


    $httpCode =
        (int)
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    curl_close(
        $ch
    );


    /*
    |--------------------------------------------------------------------------
    | HTTP ERROR
    |--------------------------------------------------------------------------
    */

    if ($httpCode !== 200) {

        error_log(
            "Geocoding HTTP Code: "
            . $httpCode
        );


        throw new Exception(
            "Unable to verify the address right now. Please try again."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | DECODE RESPONSE
    |--------------------------------------------------------------------------
    */

    $results =
        json_decode(
            $response,
            true
        );


    if (
        !is_array($results)
        ||
        empty($results)
    ) {

        return null;
    }


    $result =
        $results[0];


    if (
        !isset(
            $result["lat"],
            $result["lon"]
        )
    ) {

        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY THAT RESULT IS IN CALOOCAN
    |--------------------------------------------------------------------------
    */

    $displayName =
        $result["display_name"]
        ?? "";


    if (
        stripos(
            $displayName,
            "Caloocan"
        ) === false
    ) {

        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | VALIDATE COORDINATES
    |--------------------------------------------------------------------------
    */

    $latitude =
        (float)
        $result["lat"];


    $longitude =
        (float)
        $result["lon"];


    if (
        $latitude < -90
        ||
        $latitude > 90
    ) {

        return null;
    }


    if (
        $longitude < -180
        ||
        $longitude > 180
    ) {

        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | RETURN LOCATION
    |--------------------------------------------------------------------------
    */

    return [

        "latitude" =>
            $latitude,

        "longitude" =>
            $longitude,

        "display_name" =>
            $displayName

    ];
}


/*
|--------------------------------------------------------------------------
| GET CURRENT PROFILE
|--------------------------------------------------------------------------
|
| We get the current record BEFORE POST so we can keep the saved coordinates
| when the applicant changes something other than the address.
|
*/

$stmt =
    $pdo->prepare("

        SELECT

            id,
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


/*
|--------------------------------------------------------------------------
| UPDATE PROFILE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {


    /*
    |--------------------------------------------------------------------------
    | INPUTS
    |--------------------------------------------------------------------------
    */

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

    } elseif (
        $address === ""
    ) {

        $error =
            "Please enter your current address.";

    } else {


        try {


            /*
            |--------------------------------------------------------------------------
            | DEFAULT CURRENT COORDINATES
            |--------------------------------------------------------------------------
            |
            | If applicant did not change the address,
            | we don't need to geocode it again.
            |
            */

            $latitudeValue =
                $applicant["latitude"]
                !== null
                ?
                (float)
                $applicant["latitude"]
                :
                null;


            $longitudeValue =
                $applicant["longitude"]
                !== null
                ?
                (float)
                $applicant["longitude"]
                :
                null;


            /*
            |--------------------------------------------------------------------------
            | CHECK IF ADDRESS CHANGED
            |--------------------------------------------------------------------------
            */

            $oldAddress =
                trim(
                    $applicant["address"]
                    ?? ""
                );


            $addressChanged =
                strcasecmp(
                    $oldAddress,
                    $address
                ) !== 0;


            /*
            |--------------------------------------------------------------------------
            | GEOCODE WHEN:
            |
            | 1. Address changed
            | OR
            | 2. No coordinates have been saved yet
            |--------------------------------------------------------------------------
            */

            if (
                $addressChanged
                ||
                $latitudeValue === null
                ||
                $longitudeValue === null
            ) {


                $coordinates =
                    geocodeAddress(
                        $address
                    );


                /*
                |--------------------------------------------------------------------------
                | ADDRESS NOT FOUND
                |--------------------------------------------------------------------------
                */

                if (!$coordinates) {

                    throw new Exception(

                        "We could not locate that address in Caloocan City. "
                        . "Please enter a more complete address, including "
                        . "street, subdivision or area, barangay, and Caloocan City."

                    );
                }


                $latitudeValue =
                    $coordinates[
                        "latitude"
                    ];


                $longitudeValue =
                    $coordinates[
                        "longitude"
                    ];
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE PROFILE
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

                $address,

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


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            $success =
                "Profile updated successfully.";


            /*
            |--------------------------------------------------------------------------
            | REFRESH APPLICANT DATA
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare("

                    SELECT

                        id,
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


        } catch (
            PDOException $e
        ) {


            error_log(
                $e->getMessage()
            );


            $error =
                "Failed to update profile. Please try again.";


        } catch (
            Exception $e
        ) {


            $error =
                $e->getMessage();

        }
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
        #ffffff;

    border-bottom:
        1px solid #e5e7eb;

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

    font-size:
        14px;
}


.nav-links a:hover {

    color:
        #2563eb;
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
        980px;

    margin:
        40px auto;

    padding:
        0 20px 50px;
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

    font-size:
        14px;

    font-weight:
        600;
}


.back:hover {

    text-decoration:
        underline;
}


/*
|--------------------------------------------------------------------------
| CARD
|--------------------------------------------------------------------------
*/

.card {

    background:
        #ffffff;

    padding:
        36px;

    border-radius:
        14px;

    border:
        1px solid #e5e7eb;

    box-shadow:
        0 3px 15px
        rgba(
            0,
            0,
            0,
            0.05
        );
}


h1 {

    font-size:
        32px;

    margin-bottom:
        8px;

    color:
        #1f2937;
}


.subtitle {

    color:
        #64748b;

    margin-bottom:
        30px;

    line-height:
        1.5;
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
        22px;

    font-size:
        14px;

    line-height:
        1.5;
}


.success {

    background:
        #dcfce7;

    border:
        1px solid #bbf7d0;

    color:
        #166534;
}


.error {

    background:
        #fee2e2;

    border:
        1px solid #fecaca;

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
        22px;
}


label {

    display:
        block;

    margin-bottom:
        8px;

    font-weight:
        bold;

    color:
        #1f2937;
}


input,
textarea {

    width:
        100%;

    padding:
        14px 15px;

    border:
        1px solid #cbd5e1;

    border-radius:
        7px;

    font-size:
        15px;

    font-family:
        inherit;

    background:
        #ffffff;

    transition:
        border-color 0.2s,
        box-shadow 0.2s;
}


input:focus,
textarea:focus {

    outline:
        none;

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 3px
        rgba(
            37,
            99,
            235,
            0.10
        );
}


textarea {

    min-height:
        130px;

    resize:
        vertical;
}


.row {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        18px;
}


/*
|--------------------------------------------------------------------------
| ADDRESS
|--------------------------------------------------------------------------
*/

.address-box {

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    border-radius:
        9px;

    padding:
        20px;

    margin-bottom:
        22px;
}


.address-box h3 {

    color:
        #1e40af;

    font-size:
        17px;

    margin-bottom:
        7px;
}


.address-box p {

    color:
        #475569;

    font-size:
        14px;

    line-height:
        1.6;

    margin-bottom:
        16px;
}


.address-help {

    display:
        block;

    color:
        #64748b;

    font-size:
        13px;

    margin-top:
        8px;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.save-button {

    display:
        inline-block;

    min-width:
        150px;

    padding:
        13px 22px;

    background:
        #2563eb;

    color:
        #ffffff;

    border:
        none;

    border-radius:
        7px;

    cursor:
        pointer;

    font-size:
        15px;

    font-weight:
        bold;
}


.save-button:hover {

    background:
        #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 700px
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

        gap:
            12px;
    }


    .container {

        margin:
            25px auto;

        padding:
            0 15px 35px;
    }


    .card {

        padding:
            24px 20px;
    }


    h1 {

        font-size:
            28px;
    }


    .row {

        grid-template-columns:
            1fr;

        gap:
            0;
    }


    .save-button {

        width:
            100%;
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

        <a href="../jobs/index.php">
            Find Jobs
        </a>

        <a href="profile.php">
            Profile
        </a>

        <a href="resume.php">
            Resume
        </a>

        <a href="applications.php">
            Applications
        </a>

        <a
            href="../logout.php"
            class="logout"
        >
            Logout
        </a>

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

            Update your personal information and current address.

        </p>


        <!-- =====================================================
             SUCCESS
        ====================================================== -->

        <?php if ($success): ?>


            <div class="message success">

                ✓ <?= htmlspecialchars(
                    $success
                ) ?>

            </div>


        <?php endif; ?>


        <!-- =====================================================
             ERROR
        ====================================================== -->

        <?php if ($error): ?>


            <div class="message error">

                <?= htmlspecialchars(
                    $error
                ) ?>

            </div>


        <?php endif; ?>


        <!-- =====================================================
             FORM
        ====================================================== -->

        <form method="POST">


            <!-- =================================================
                 NAME
            ================================================== -->

            <div class="row">


                <div class="form-group">

                    <label for="first_name">
                        First Name
                    </label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"

                        value="<?= htmlspecialchars(
                            $applicant[
                                "first_name"
                            ]
                            ?? ""
                        ) ?>"

                        required
                    >

                </div>


                <div class="form-group">

                    <label for="last_name">
                        Last Name
                    </label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"

                        value="<?= htmlspecialchars(
                            $applicant[
                                "last_name"
                            ]
                            ?? ""
                        ) ?>"

                        required
                    >

                </div>


            </div>


            <!-- =================================================
                 PHONE
            ================================================== -->

            <div class="form-group">

                <label for="phone">
                    Phone
                </label>

                <input
                    type="text"
                    id="phone"
                    name="phone"

                    placeholder="Example: 09123456789"

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

            <div class="address-box">


                <h3>
                    📍 Current Address
                </h3>


                <p>

                    Enter your complete current address.
                    The system will automatically determine
                    its location for calculating the distance
                    between you and available jobs.

                </p>


                <div
                    class="form-group"
                    style="margin-bottom:0;"
                >

                    <label for="address">
                        Address
                    </label>


                    <input
                        type="text"
                        id="address"
                        name="address"

                        placeholder="Example: Phase 5, Brgy. 176, Bagong Silang, Caloocan City"

                        value="<?= htmlspecialchars(
                            $applicant[
                                "address"
                            ]
                            ?? ""
                        ) ?>"

                        required
                    >


                    <small class="address-help">

                        Please include your street,
                        subdivision or area, barangay,
                        and Caloocan City for better
                        location accuracy.

                    </small>


                </div>


            </div>


            <!-- =================================================
                 RELIGION
            ================================================== -->

            <div class="form-group">

                <label for="religion">
                    Religion
                </label>

                <input
                    type="text"
                    id="religion"
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

                <label for="education">
                    Education
                </label>

                <input
                    type="text"
                    id="education"
                    name="education"

                    placeholder="Example: Bachelor's Degree in Information Technology"

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

                <label for="bio">
                    Bio
                </label>


                <textarea
                    id="bio"
                    name="bio"
                    placeholder="Tell employers a little about yourself..."
                ><?= htmlspecialchars(
                    $applicant[
                        "bio"
                    ]
                    ?? ""
                ) ?></textarea>


            </div>


            <!-- =================================================
                 SUBMIT
            ================================================== -->

            <button
                type="submit"
                class="save-button"
            >

                Save Changes

            </button>


        </form>


    </div>


</div>


</body>

</html>