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


$userId = (int) $_SESSION["user_id"];

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

$uploadDir = "../uploads/verification/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}


/*
|--------------------------------------------------------------------------
| DOCUMENT UPLOAD FUNCTION
|--------------------------------------------------------------------------
*/

function uploadDocument($file, $uploadDir)
{
    if (
        !isset($file) ||
        $file["error"] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        throw new Exception(
            "There was an error uploading the file."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | MAX FILE SIZE: 5MB
    |--------------------------------------------------------------------------
    */

    if ($file["size"] > 5 * 1024 * 1024) {
        throw new Exception(
            "File size must not exceed 5MB."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | MIME TYPE
    |--------------------------------------------------------------------------
    */

    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "application/pdf" => "pdf"
    ];


    $finfo = new finfo(
        FILEINFO_MIME_TYPE
    );


    $mimeType =
        $finfo->file(
            $file["tmp_name"]
        );


    if (
        !isset(
            $allowedTypes[$mimeType]
        )
    ) {
        throw new Exception(
            "Only JPG, PNG, and PDF files are allowed."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | RANDOM FILE NAME
    |--------------------------------------------------------------------------
    */

    $extension =
        $allowedTypes[$mimeType];


    $filename =
        bin2hex(
            random_bytes(16)
        )
        . "."
        . $extension;


    $destination =
        $uploadDir
        . $filename;


    if (
        !move_uploaded_file(
            $file["tmp_name"],
            $destination
        )
    ) {
        throw new Exception(
            "Unable to save uploaded file."
        );
    }


    return $filename;
}


/*
|--------------------------------------------------------------------------
| STATUS HELPERS
|--------------------------------------------------------------------------
*/

function verificationStatusClass($status)
{
    switch ($status) {

        case "approved":
        case "passed":
            return "approved-status";

        case "pending":
        case "processing":
            return "pending-status";

        case "rejected":
        case "failed":
            return "rejected-status";

        case "needs_review":
            return "review-status";

        default:
            return "unverified-status";
    }
}


function verificationStatusLabel($status)
{
    if (!$status) {
        return "Unverified";
    }

    return ucwords(
        str_replace(
            "_",
            " ",
            $status
        )
    );
}


/*
|--------------------------------------------------------------------------
| GET EMPLOYER
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare("

        SELECT

            id,
            user_id,
            company_name,
            company_description,
            company_logo,
            contact_person,
            contact_number,
            address,
            barangay,
            latitude,
            longitude,
            caloocan_area,
            verification_status,
            created_at,
            updated_at

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


$employerId =
    (int) $employer["id"];


/*
|--------------------------------------------------------------------------
| GET VERIFICATION
|--------------------------------------------------------------------------
*/

$stmt =
    $pdo->prepare("

        SELECT *

        FROM employer_verifications

        WHERE employer_id = ?

        LIMIT 1

    ");


$stmt->execute([
    $employerId
]);


$verification =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


/*
|--------------------------------------------------------------------------
| HANDLE FORM
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {

    try {

        /*
        |--------------------------------------------------------------------------
        | INPUTS
        |--------------------------------------------------------------------------
        */

        $companyName =
            trim(
                $_POST["company_name"]
                ?? ""
            );


        $companyDescription =
            trim(
                $_POST["company_description"]
                ?? ""
            );


        $contactPerson =
            trim(
                $_POST["contact_person"]
                ?? ""
            );


        $contactNumber =
            trim(
                $_POST["contact_number"]
                ?? ""
            );


        $address =
            trim(
                $_POST["address"]
                ?? ""
            );


        $barangay =
            trim(
                $_POST["barangay"]
                ?? ""
            );


        /*
        |--------------------------------------------------------------------------
        | HIDDEN COORDINATES
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
        | DETECT NORTH / SOUTH CALOOCAN
        |--------------------------------------------------------------------------
        */

        $caloocanArea = null;

        $barangayNumber = null;


        preg_match(
            '/\d+/',
            $barangay,
            $barangayMatch
        );


        if (
            !empty(
                $barangayMatch
            )
        ) {

            $barangayNumber =
                (int)
                $barangayMatch[0];


            /*
            |--------------------------------------------------------------------------
            | SOUTH CALOOCAN
            |--------------------------------------------------------------------------
            */

            if (
                $barangayNumber >= 1 &&
                $barangayNumber <= 164
            ) {

                $caloocanArea =
                    "south";


            /*
            |--------------------------------------------------------------------------
            | NORTH CALOOCAN
            |--------------------------------------------------------------------------
            */

            } elseif (
                $barangayNumber >= 165 &&
                $barangayNumber <= 188
            ) {

                $caloocanArea =
                    "north";
            }
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $companyName === ""
        ) {
            throw new Exception(
                "Company name is required."
            );
        }


        if (
            $contactPerson === ""
        ) {
            throw new Exception(
                "Contact person is required."
            );
        }


        if (
            $address === ""
        ) {
            throw new Exception(
                "Company address is required."
            );
        }


        if (
            $barangay === ""
        ) {
            throw new Exception(
                "Barangay is required."
            );
        }


        if (
            $caloocanArea === null
        ) {
            throw new Exception(
                "Please enter a valid Caloocan barangay number from 1 to 188."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOCATION VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $latitude === "" ||
            $longitude === ""
        ) {

            throw new Exception(
                "Please click Use My Current Location to set your business location."
            );
        }


        if (
            !is_numeric($latitude) ||
            !is_numeric($longitude)
        ) {

            throw new Exception(
                "Invalid business location."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE COORDINATE RANGE
        |--------------------------------------------------------------------------
        */

        $latitudeValue =
            (float) $latitude;


        $longitudeValue =
            (float) $longitude;


        if (
            $latitudeValue < -90 ||
            $latitudeValue > 90
        ) {
            throw new Exception(
                "Invalid latitude."
            );
        }


        if (
            $longitudeValue < -180 ||
            $longitudeValue > 180
        ) {
            throw new Exception(
                "Invalid longitude."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE EMPLOYER
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdo->prepare("

                UPDATE employers

                SET

                    company_name = ?,
                    company_description = ?,
                    contact_person = ?,
                    contact_number = ?,
                    address = ?,
                    barangay = ?,
                    latitude = ?,
                    longitude = ?,
                    caloocan_area = ?,
                    verification_status = 'pending',
                    updated_at = NOW()

                WHERE id = ?

            ");


        $stmt->execute([

            $companyName,

            $companyDescription !== ""
                ? $companyDescription
                : null,

            $contactPerson,

            $contactNumber !== ""
                ? $contactNumber
                : null,

            $address,

            $barangay,

            $latitudeValue,

            $longitudeValue,

            $caloocanArea,

            $employerId

        ]);


        /*
        |--------------------------------------------------------------------------
        | UPLOAD DOCUMENTS
        |--------------------------------------------------------------------------
        */

        $governmentId =
            uploadDocument(
                $_FILES["government_id"]
                ?? null,
                $uploadDir
            );


        $barangayCertificate =
            uploadDocument(
                $_FILES["barangay_certificate"]
                ?? null,
                $uploadDir
            );


        $businessPermit =
            uploadDocument(
                $_FILES["business_permit"]
                ?? null,
                $uploadDir
            );


        /*
        |--------------------------------------------------------------------------
        | KEEP OLD DOCUMENTS
        |--------------------------------------------------------------------------
        */

        if ($verification) {

            if (!$governmentId) {

                $governmentId =
                    $verification[
                        "government_id"
                    ]
                    ?? null;
            }


            if (!$barangayCertificate) {

                $barangayCertificate =
                    $verification[
                        "barangay_certificate"
                    ]
                    ?? null;
            }


            if (!$businessPermit) {

                $businessPermit =
                    $verification[
                        "business_permit"
                    ]
                    ?? null;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE VERIFICATION
        |--------------------------------------------------------------------------
        */

        if ($verification) {

            $stmt =
                $pdo->prepare("

                    UPDATE employer_verifications

                    SET

                        government_id = ?,
                        barangay_certificate = ?,
                        business_permit = ?,

                        detected_address = NULL,

                        document_status = 'pending',
                        location_status = 'pending',
                        admin_status = 'pending',

                        submitted_at = NOW()

                    WHERE employer_id = ?

                ");


            $stmt->execute([

                $governmentId,

                $barangayCertificate,

                $businessPermit,

                $employerId

            ]);

        } else {

            $stmt =
                $pdo->prepare("

                    INSERT INTO employer_verifications
                    (
                        employer_id,
                        government_id,
                        barangay_certificate,
                        business_permit,
                        detected_address,
                        document_status,
                        location_status,
                        admin_status,
                        submitted_at
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        NULL,
                        'pending',
                        'pending',
                        'pending',
                        NOW()
                    )

                ");


            $stmt->execute([

                $employerId,

                $governmentId,

                $barangayCertificate,

                $businessPermit

            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | SUCCESS
        |--------------------------------------------------------------------------
        */

        $success =
            "Profile saved successfully. "
            . "Your area was detected as "
            . strtoupper($caloocanArea)
            . " CALOOCAN.";


        /*
        |--------------------------------------------------------------------------
        | RELOAD EMPLOYER
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdo->prepare("

                SELECT *

                FROM employers

                WHERE id = ?

                LIMIT 1

            ");


        $stmt->execute([
            $employerId
        ]);


        $employer =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        /*
        |--------------------------------------------------------------------------
        | RELOAD VERIFICATION
        |--------------------------------------------------------------------------
        */

        $stmt =
            $pdo->prepare("

                SELECT *

                FROM employer_verifications

                WHERE employer_id = ?

                LIMIT 1

            ");


        $stmt->execute([
            $employerId
        ]);


        $verification =
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
            "Database error. Please try again.";


    } catch (
        Exception $e
    ) {

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
    Employer Profile - Caloocan Job Portal
</title>


<style>

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


.nav-links a:hover,
.nav-links .active {

    color:
        #2563eb;
}


.nav-links .active {

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
        1200px;

    margin:
        40px auto;

    padding:
        0 20px 50px;
}


.welcome {

    margin-bottom:
        30px;
}


.welcome h1 {

    margin-bottom:
        8px;
}


.welcome p {

    color:
        #666;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| ALERT
|--------------------------------------------------------------------------
*/

.alert {

    padding:
        15px 18px;

    border-radius:
        8px;

    margin-bottom:
        20px;

    line-height:
        1.5;
}


.alert-success {

    background:
        #dcfce7;

    color:
        #166534;

    border:
        1px solid #bbf7d0;
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
| GRID
|--------------------------------------------------------------------------
*/

.profile-grid {

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

    border:
        1px solid #e5e7eb;

    box-shadow:
        0 2px 10px
        rgba(
            0,
            0,
            0,
            0.05
        );

    margin-bottom:
        20px;
}


.card h2 {

    margin-bottom:
        8px;
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

    font-weight:
        bold;

    font-size:
        14px;

    margin-bottom:
        7px;
}


input[type="text"],
input[type="tel"],
textarea {

    width:
        100%;

    padding:
        11px 12px;

    border:
        1px solid #d1d5db;

    border-radius:
        6px;

    font-size:
        14px;

    font-family:
        Arial,
        sans-serif;

    background:
        white;
}


input:focus,
textarea:focus {

    outline:
        none;

    border-color:
        #2563eb;

    box-shadow:
        0 0 0 2px
        rgba(
            37,
            99,
            235,
            0.08
        );
}


textarea {

    resize:
        vertical;

    min-height:
        110px;
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
        11px 16px;

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
        14px;

    text-decoration:
        none;
}


.btn:hover {

    background:
        #1d4ed8;
}


.btn:disabled {

    opacity:
        0.65;

    cursor:
        not-allowed;
}


/*
|--------------------------------------------------------------------------
| LOCATION
|--------------------------------------------------------------------------
*/

.location-status {

    margin-top:
        15px;

    padding:
        12px 14px;

    background:
        #dbeafe;

    color:
        #1e40af;

    border-radius:
        6px;

    font-size:
        14px;

    line-height:
        1.5;
}


.area-south {

    background:
        #dcfce7 !important;

    color:
        #166534 !important;
}


.area-north {

    background:
        #dbeafe !important;

    color:
        #1e40af !important;
}


.area-invalid {

    background:
        #fee2e2 !important;

    color:
        #991b1b !important;
}


/*
|--------------------------------------------------------------------------
| DOCUMENTS
|--------------------------------------------------------------------------
*/

.document {

    padding:
        18px 0;

    border-bottom:
        1px solid #eee;
}


.document:first-of-type {

    padding-top:
        0;
}


.document:last-child {

    border-bottom:
        none;
}


.document h3 {

    margin-bottom:
        6px;

    font-size:
        16px;
}


.document p {

    color:
        #666;

    font-size:
        13px;

    line-height:
        1.5;

    margin-bottom:
        12px;
}


input[type="file"] {

    width:
        100%;

    padding:
        10px;

    border:
        1px solid #ddd;

    border-radius:
        6px;

    background:
        #fafafa;
}


.uploaded {

    margin-top:
        10px;

    font-size:
        13px;

    font-weight:
        bold;

    color:
        #166534;
}


.verification-note {

    background:
        #f8fafc;

    padding:
        15px;

    border-radius:
        8px;

    border:
        1px solid #e2e8f0;

    font-size:
        13px;

    line-height:
        1.6;

    color:
        #555;

    margin-top:
        20px;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status-item {

    padding:
        15px 0;

    border-bottom:
        1px solid #eee;
}


.status-item:last-child {

    border-bottom:
        none;
}


.status-item-label {

    color:
        #666;

    font-size:
        13px;

    margin-bottom:
        8px;
}


.status {

    display:
        inline-block;

    padding:
        5px 10px;

    border-radius:
        20px;

    font-size:
        12px;

    font-weight:
        bold;
}


.pending-status {

    background:
        #fef3c7;

    color:
        #92400e;
}


.approved-status {

    background:
        #dcfce7;

    color:
        #166534;
}


.rejected-status {

    background:
        #fee2e2;

    color:
        #991b1b;
}


.review-status {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.unverified-status {

    background:
        #e5e7eb;

    color:
        #4b5563;
}


.company-info p {

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 800px
) {

    .profile-grid {

        grid-template-columns:
            1fr;
    }


    .form-grid {

        grid-template-columns:
            1fr;
    }


    .form-group.full {

        grid-column:
            auto;
    }
}


@media (
    max-width: 650px
) {

    .navbar {

        padding:
            15px 20px;

        flex-direction:
            column;

        gap:
            15px;

        align-items:
            flex-start;
    }


    .nav-links {

        width:
            100%;

        gap:
            12px;

        flex-wrap:
            wrap;
    }


    .container {

        margin:
            25px auto;

        padding:
            0 15px 35px;
    }


    .card {

        padding:
            20px;
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


        <a
            href="profile.php"
            class="active"
        >
            Profile
        </a>


        <a href="jobs.php">
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


<!-- =========================================================
     MAIN
========================================================= -->

<div class="container">


    <div class="welcome">


        <h1>
            Employer Profile
        </h1>


        <p>

            Manage your company information,
            business location and verification
            documents.

        </p>


    </div>


    <!-- =====================================================
         SUCCESS
    ====================================================== -->

    <?php if ($success): ?>


        <div class="alert alert-success">

            <?= htmlspecialchars(
                $success
            ) ?>

        </div>


    <?php endif; ?>


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


    <form
        method="POST"
        enctype="multipart/form-data"
    >


        <div class="profile-grid">


            <!-- =================================================
                 LEFT COLUMN
            ================================================== -->

            <div>


                <!-- =================================================
                     COMPANY INFORMATION
                ================================================== -->

                <div class="card">


                    <h2>
                        Company Information
                    </h2>


                    <p class="card-description">

                        Update the information
                        displayed for your company.

                    </p>


                    <div class="form-grid">


                        <!-- COMPANY NAME -->

                        <div class="form-group">


                            <label>
                                Company Name *
                            </label>


                            <input
                                type="text"
                                name="company_name"
                                required

                                value="<?= htmlspecialchars(
                                    $employer[
                                        "company_name"
                                    ]
                                    ?? ""
                                ) ?>"
                            >


                        </div>


                        <!-- CONTACT PERSON -->

                        <div class="form-group">


                            <label>
                                Contact Person *
                            </label>


                            <input
                                type="text"
                                name="contact_person"
                                required

                                value="<?= htmlspecialchars(
                                    $employer[
                                        "contact_person"
                                    ]
                                    ?? ""
                                ) ?>"
                            >


                        </div>


                        <!-- CONTACT NUMBER -->

                        <div class="form-group">


                            <label>
                                Contact Number
                            </label>


                            <input
                                type="tel"
                                name="contact_number"

                                placeholder="09XXXXXXXXX"

                                value="<?= htmlspecialchars(
                                    $employer[
                                        "contact_number"
                                    ]
                                    ?? ""
                                ) ?>"
                            >


                        </div>


                        <!-- DESCRIPTION -->

                        <div class="form-group full">


                            <label>
                                Company Description
                            </label>


                            <textarea
                                name="company_description"
                                placeholder="Tell job seekers about your company..."
                            ><?= htmlspecialchars(
                                $employer[
                                    "company_description"
                                ]
                                ?? ""
                            ) ?></textarea>


                        </div>


                    </div>


                </div>


                <!-- =================================================
                     BUSINESS LOCATION
                ================================================== -->

                <div class="card">


                    <h2>
                        Business Location
                    </h2>


                    <p class="card-description">

                        Enter your business address
                        and barangay, then use your
                        current location to save the
                        exact coordinates of your business.

                    </p>


                    <!-- ADDRESS -->

                    <div class="form-group">


                        <label>
                            Complete Address *
                        </label>


                        <textarea
                            name="address"
                            required
                            placeholder="House / Building No., Street, Barangay, Caloocan City"
                        ><?= htmlspecialchars(
                            $employer[
                                "address"
                            ]
                            ?? ""
                        ) ?></textarea>


                    </div>


                    <!-- BARANGAY -->

                    <div class="form-group">


                        <label>
                            Barangay *
                        </label>


                        <input
                            type="text"
                            name="barangay"
                            id="barangay"
                            required

                            placeholder="Example: Barangay 176"

                            oninput="detectCaloocanArea()"

                            value="<?= htmlspecialchars(
                                $employer[
                                    "barangay"
                                ]
                                ?? ""
                            ) ?>"
                        >


                        <!-- NORTH/SOUTH RESULT -->

                        <div
                            id="areaResult"
                            class="location-status"

                            style="
                                display:none;
                                margin-top:10px;
                            "
                        ></div>


                    </div>


                    <!-- =================================================
                         CURRENT LOCATION BUTTON
                    ================================================== -->

                    <button
                        type="button"
                        class="btn"
                        id="locationButton"
                        onclick="getCurrentLocation()"
                    >

                        Use My Current Location

                    </button>


                    <!-- =================================================
                         LOCATION MESSAGE
                    ================================================== -->

                    <div
                        id="locationStatus"
                        class="location-status"
                    >

                        Click "Use My Current Location"
                        to save your exact business location.

                    </div>


                    <!-- =================================================
                         HIDDEN LATITUDE
                    ================================================== -->

                    <input
                        type="hidden"
                        name="latitude"
                        id="latitude"

                        value="<?= htmlspecialchars(
                            $employer[
                                "latitude"
                            ]
                            ?? ""
                        ) ?>"
                    >


                    <!-- =================================================
                         HIDDEN LONGITUDE
                    ================================================== -->

                    <input
                        type="hidden"
                        name="longitude"
                        id="longitude"

                        value="<?= htmlspecialchars(
                            $employer[
                                "longitude"
                            ]
                            ?? ""
                        ) ?>"
                    >


                </div>


                <!-- =================================================
                     VERIFICATION DOCUMENTS
                ================================================== -->

                <div class="card">


                    <h2>
                        Verification Documents
                    </h2>


                    <p class="card-description">

                        Submit proof that you or
                        your business is located
                        in Caloocan City.

                    </p>


                    <!-- =================================================
                         GOVERNMENT ID
                    ================================================== -->

                    <div class="document">


                        <h3>
                            Government ID
                        </h3>


                        <p>

                            Upload a valid
                            government-issued ID showing
                            your Caloocan address.

                        </p>


                        <input
                            type="file"
                            name="government_id"
                            accept=".jpg,.jpeg,.png,.pdf"
                        >


                        <?php if (
                            !empty(
                                $verification[
                                    "government_id"
                                ]
                                ?? ""
                            )
                        ): ?>


                            <div class="uploaded">

                                ✓ Government ID
                                already uploaded

                            </div>


                        <?php endif; ?>


                    </div>


                    <!-- =================================================
                         BARANGAY CERTIFICATE
                    ================================================== -->

                    <div class="document">


                        <h3>
                            Barangay Certificate
                        </h3>


                        <p>

                            Upload a Barangay Certificate
                            as proof of your
                            Caloocan address.

                        </p>


                        <input
                            type="file"
                            name="barangay_certificate"
                            accept=".jpg,.jpeg,.png,.pdf"
                        >


                        <?php if (
                            !empty(
                                $verification[
                                    "barangay_certificate"
                                ]
                                ?? ""
                            )
                        ): ?>


                            <div class="uploaded">

                                ✓ Barangay Certificate
                                already uploaded

                            </div>


                        <?php endif; ?>


                    </div>


                    <!-- =================================================
                         BUSINESS PERMIT
                    ================================================== -->

                    <div class="document">


                        <h3>
                            Business Permit
                        </h3>


                        <p>

                            Required for companies
                            or registered businesses.

                        </p>


                        <input
                            type="file"
                            name="business_permit"
                            accept=".jpg,.jpeg,.png,.pdf"
                        >


                        <?php if (
                            !empty(
                                $verification[
                                    "business_permit"
                                ]
                                ?? ""
                            )
                        ): ?>


                            <div class="uploaded">

                                ✓ Business Permit
                                already uploaded

                            </div>


                        <?php endif; ?>


                    </div>


                    <div class="verification-note">

                        Accepted formats:
                        JPG, PNG and PDF.

                        <br>

                        Maximum file size:
                        5MB per document.

                    </div>


                </div>


            </div>


            <!-- =================================================
                 RIGHT COLUMN
            ================================================== -->

            <div>


                <!-- =================================================
                     LOCATION SUMMARY
                ================================================== -->

                <div class="card">


                    <h2>
                        Location Summary
                    </h2>


                    <!-- BARANGAY -->

                    <div class="status-item">


                        <div class="status-item-label">
                            Barangay
                        </div>


                        <strong>

                            <?= htmlspecialchars(
                                $employer[
                                    "barangay"
                                ]
                                ?: "Not set"
                            ) ?>

                        </strong>


                    </div>


                    <!-- AREA -->

                    <div class="status-item">


                        <div class="status-item-label">
                            Caloocan Area
                        </div>


                        <?php if (
                            !empty(
                                $employer[
                                    "caloocan_area"
                                ]
                            )
                        ): ?>


                            <span
                                class="
                                    status
                                    approved-status
                                "
                            >

                                <?= strtoupper(
                                    htmlspecialchars(
                                        $employer[
                                            "caloocan_area"
                                        ]
                                    )
                                ) ?>

                                CALOOCAN

                            </span>


                        <?php else: ?>


                            <span
                                class="
                                    status
                                    unverified-status
                                "
                            >

                                Not Detected

                            </span>


                        <?php endif; ?>


                    </div>


                    <!-- COORDINATE STATUS -->

                    <div class="status-item">


                        <div class="status-item-label">
                            Exact Location
                        </div>


                        <?php if (
                            !empty(
                                $employer[
                                    "latitude"
                                ]
                            )
                            &&
                            !empty(
                                $employer[
                                    "longitude"
                                ]
                            )
                        ): ?>


                            <span
                                class="
                                    status
                                    approved-status
                                "
                            >

                                Location Saved

                            </span>


                        <?php else: ?>


                            <span
                                class="
                                    status
                                    unverified-status
                                "
                            >

                                Not Set

                            </span>


                        <?php endif; ?>


                    </div>


                </div>


                <!-- =================================================
                     VERIFICATION STATUS
                ================================================== -->

                <div class="card">


                    <h2>
                        Verification Status
                    </h2>


                    <?php

                    $documentStatus =
                        $verification[
                            "document_status"
                        ]
                        ?? "unverified";


                    $locationStatus =
                        $verification[
                            "location_status"
                        ]
                        ?? "unverified";


                    $adminStatus =
                        $verification[
                            "admin_status"
                        ]
                        ?? "unverified";


                    $accountStatus =
                        $employer[
                            "verification_status"
                        ]
                        ?? "unverified";

                    ?>


                    <!-- DOCUMENT STATUS -->

                    <div class="status-item">


                        <div class="status-item-label">
                            Documents
                        </div>


                        <span
                            class="
                                status
                                <?= verificationStatusClass(
                                    $documentStatus
                                ) ?>
                            "
                        >

                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $documentStatus
                                )
                            ) ?>

                        </span>


                    </div>


                    <!-- LOCATION STATUS -->

                    <div class="status-item">


                        <div class="status-item-label">
                            Location
                        </div>


                        <span
                            class="
                                status
                                <?= verificationStatusClass(
                                    $locationStatus
                                ) ?>
                            "
                        >

                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $locationStatus
                                )
                            ) ?>

                        </span>


                    </div>


                    <!-- ADMIN STATUS -->

                    <div class="status-item">


                        <div class="status-item-label">
                            Admin Approval
                        </div>


                        <span
                            class="
                                status
                                <?= verificationStatusClass(
                                    $adminStatus
                                ) ?>
                            "
                        >

                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $adminStatus
                                )
                            ) ?>

                        </span>


                    </div>


                    <!-- ACCOUNT -->

                    <div class="status-item">


                        <div class="status-item-label">
                            Employer Account
                        </div>


                        <span
                            class="
                                status
                                <?= verificationStatusClass(
                                    $accountStatus
                                ) ?>
                            "
                        >

                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $accountStatus
                                )
                            ) ?>

                        </span>


                    </div>


                </div>


                <!-- =================================================
                     VERIFICATION PROCESS
                ================================================== -->

                <div class="card">


                    <h2>
                        Verification Process
                    </h2>


                    <div class="company-info">


                        <p>

                            <strong>
                                1. Documents
                            </strong>

                        </p>


                        <p>

                            Government ID,
                            Barangay Certificate,
                            and Business Permit.

                        </p>


                        <br>


                        <p>

                            <strong>
                                2. Location
                            </strong>

                        </p>


                        <p>

                            Your business location
                            must be inside
                            Caloocan City.

                        </p>


                        <br>


                        <p>

                            <strong>
                                3. Admin Review
                            </strong>

                        </p>


                        <p>

                            An administrator will review
                            the submitted information
                            before your account is
                            fully verified.

                        </p>


                    </div>


                </div>


                <!-- =================================================
                     SAVE
                ================================================== -->

                <div class="card">


                    <h2>
                        Save Profile
                    </h2>


                    <p class="card-description">

                        Save your profile and
                        submit the information
                        for verification.

                    </p>


                    <button
                        type="submit"
                        class="btn"
                    >

                        Save & Submit

                    </button>


                </div>


            </div>


        </div>


    </form>


</div>


<script>


/*
|--------------------------------------------------------------------------
| LIVE NORTH / SOUTH CALOOCAN DETECTION
|--------------------------------------------------------------------------
*/

function detectCaloocanArea()
{

    const barangay =
        document.getElementById(
            "barangay"
        );


    const result =
        document.getElementById(
            "areaResult"
        );


    if (
        !barangay ||
        !result
    ) {

        return;
    }


    const value =
        barangay.value.trim();


    /*
    |--------------------------------------------------------------------------
    | GET BARANGAY NUMBER
    |--------------------------------------------------------------------------
    |
    | Examples accepted:
    |
    | Barangay 7
    | Brgy 176
    | Brgy. 176
    | 176
    |
    */

    const match =
        value.match(
            /\d+/
        );


    /*
    |--------------------------------------------------------------------------
    | NO NUMBER
    |--------------------------------------------------------------------------
    */

    if (!match) {

        result.style.display =
            "none";


        result.innerHTML =
            "";


        result.classList.remove(
            "area-south",
            "area-north",
            "area-invalid"
        );


        return;
    }


    const barangayNumber =
        parseInt(
            match[0],
            10
        );


    /*
    |--------------------------------------------------------------------------
    | SOUTH CALOOCAN
    |--------------------------------------------------------------------------
    */

    if (
        barangayNumber >= 1 &&
        barangayNumber <= 164
    ) {

        result.style.display =
            "block";


        result.classList.remove(
            "area-north",
            "area-invalid"
        );


        result.classList.add(
            "area-south"
        );


        result.innerHTML =
            "✓ Detected Area: "
            + "<strong>SOUTH CALOOCAN</strong>";


        return;
    }


    /*
    |--------------------------------------------------------------------------
    | NORTH CALOOCAN
    |--------------------------------------------------------------------------
    */

    if (
        barangayNumber >= 165 &&
        barangayNumber <= 188
    ) {

        result.style.display =
            "block";


        result.classList.remove(
            "area-south",
            "area-invalid"
        );


        result.classList.add(
            "area-north"
        );


        result.innerHTML =
            "✓ Detected Area: "
            + "<strong>NORTH CALOOCAN</strong>";


        return;
    }


    /*
    |--------------------------------------------------------------------------
    | INVALID
    |--------------------------------------------------------------------------
    */

    result.style.display =
        "block";


    result.classList.remove(
        "area-south",
        "area-north"
    );


    result.classList.add(
        "area-invalid"
    );


    result.innerHTML =
        "⚠ Please enter a valid Caloocan "
        + "barangay number from 1 to 188.";
}


/*
|--------------------------------------------------------------------------
| GET CURRENT LOCATION
|--------------------------------------------------------------------------
|
| No map is displayed.
|
| The GPS coordinates are saved inside hidden form fields.
|
*/

function getCurrentLocation()
{

    const status =
        document.getElementById(
            "locationStatus"
        );


    const button =
        document.getElementById(
            "locationButton"
        );


    const latitudeInput =
        document.getElementById(
            "latitude"
        );


    const longitudeInput =
        document.getElementById(
            "longitude"
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
            "⚠ Geolocation is not supported "
            + "by your browser.";


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
        "Detecting your exact business location...";


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

            const latitude =
                position.coords.latitude;


            const longitude =
                position.coords.longitude;


            /*
            |--------------------------------------------------------------------------
            | SAVE TO HIDDEN INPUTS
            |--------------------------------------------------------------------------
            */

            latitudeInput.value =
                latitude.toFixed(8);


            longitudeInput.value =
                longitude.toFixed(8);


            /*
            |--------------------------------------------------------------------------
            | MESSAGE
            |--------------------------------------------------------------------------
            */

            status.innerHTML =
                "✓ Business location detected successfully. "
                + "Click Save & Submit to save your location.";


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


            switch (
                error.code
            ) {


                case error.PERMISSION_DENIED:


                    status.innerHTML =
                        "⚠ Location permission was denied. "
                        + "Please allow location access "
                        + "in your browser.";


                    break;


                case error.POSITION_UNAVAILABLE:


                    status.innerHTML =
                        "⚠ Your current location "
                        + "is unavailable.";


                    break;


                case error.TIMEOUT:


                    status.innerHTML =
                        "⚠ Location request timed out. "
                        + "Please try again.";


                    break;


                default:


                    status.innerHTML =
                        "⚠ Unable to detect "
                        + "your current location.";

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
                0

        }

    );

}


/*
|--------------------------------------------------------------------------
| PAGE LOAD
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function()
    {

        /*
        |--------------------------------------------------------------------------
        | SHOW SAVED NORTH / SOUTH
        |--------------------------------------------------------------------------
        */

        detectCaloocanArea();


        /*
        |--------------------------------------------------------------------------
        | CHECK SAVED LOCATION
        |--------------------------------------------------------------------------
        */

        const savedLatitude =
            document.getElementById(
                "latitude"
            ).value;


        const savedLongitude =
            document.getElementById(
                "longitude"
            ).value;


        /*
        |--------------------------------------------------------------------------
        | LOCATION ALREADY SAVED
        |--------------------------------------------------------------------------
        */

        if (
            savedLatitude !== "" &&
            savedLongitude !== ""
        ) {

            document.getElementById(
                "locationStatus"
            ).innerHTML =
                "✓ Exact business location "
                + "has already been saved.";


            document.getElementById(
                "locationButton"
            ).innerHTML =
                "Update Current Location";

        }

    }
);


</script>


</body>

</html>