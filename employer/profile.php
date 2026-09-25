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
    | Maximum file size: 5MB
    |--------------------------------------------------------------------------
    */

    if ($file["size"] > 5 * 1024 * 1024) {
        throw new Exception(
            "File size must not exceed 5MB."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Check MIME Type
    |--------------------------------------------------------------------------
    */

    $allowedTypes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "application/pdf" => "pdf"
    ];


    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file(
        $file["tmp_name"]
    );


    if (!isset($allowedTypes[$mimeType])) {
        throw new Exception(
            "Only JPG, PNG, and PDF files are allowed."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Generate Random Filename
    |--------------------------------------------------------------------------
    */

    $extension =
        $allowedTypes[$mimeType];


    $filename =
        bin2hex(random_bytes(16))
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
| GET EMPLOYER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
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


$stmt->execute([$userId]);

$employer = $stmt->fetch();


if (!$employer) {
    die("Employer profile not found.");
}


$employerId = $employer["id"];


/*
|--------------------------------------------------------------------------
| GET VERIFICATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM employer_verifications
    WHERE employer_id = ?
    LIMIT 1
");


$stmt->execute([$employerId]);

$verification = $stmt->fetch();


/*
|--------------------------------------------------------------------------
| HANDLE FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        /*
        |--------------------------------------------------------------------------
        | INPUT
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


        if (!empty($barangayMatch)) {

            $barangayNumber =
                (int) $barangayMatch[0];


            /*
            |--------------------------------------------------------------------------
            | SOUTH CALOOCAN
            |--------------------------------------------------------------------------
            */

            if (
                $barangayNumber >= 1 &&
                $barangayNumber <= 164
            ) {

                $caloocanArea = "south";

            }


            /*
            |--------------------------------------------------------------------------
            | NORTH CALOOCAN
            |--------------------------------------------------------------------------
            */

            elseif (
                $barangayNumber >= 165 &&
                $barangayNumber <= 188
            ) {

                $caloocanArea = "north";

            }

        }


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
        | BASIC VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($companyName === "") {

            throw new Exception(
                "Company name is required."
            );
        }


        if ($contactPerson === "") {

            throw new Exception(
                "Contact person is required."
            );
        }


        if ($address === "") {

            throw new Exception(
                "Company address is required."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BARANGAY VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($barangay === "") {

            throw new Exception(
                "Barangay is required."
            );
        }


        if ($caloocanArea === null) {

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
                "Please set your business location."
            );
        }


        if (
            !is_numeric($latitude) ||
            !is_numeric($longitude)
        ) {

            throw new Exception(
                "Invalid latitude or longitude."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE EMPLOYER PROFILE
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
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
            $companyDescription,
            $contactPerson,
            $contactNumber,
            $address,
            $barangay,
            $latitude,
            $longitude,
            $caloocanArea,
            $employerId
        ]);


        /*
        |--------------------------------------------------------------------------
        | UPLOAD VERIFICATION DOCUMENTS
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
        | KEEP OLD FILES IF USER DID NOT UPLOAD NEW FILE
        |--------------------------------------------------------------------------
        */

        if ($verification) {

            if (!$governmentId) {

                $governmentId =
                    $verification[
                        "government_id"
                    ];
            }


            if (!$barangayCertificate) {

                $barangayCertificate =
                    $verification[
                        "barangay_certificate"
                    ];
            }


            if (!$businessPermit) {

                $businessPermit =
                    $verification[
                        "business_permit"
                    ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE VERIFICATION
        |--------------------------------------------------------------------------
        */

        if ($verification) {

            $stmt = $pdo->prepare("
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

            $stmt = $pdo->prepare("
                INSERT INTO employer_verifications (
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
                VALUES (
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
            "Profile saved successfully. Your area was detected as "
            . strtoupper($caloocanArea)
            . " CALOOCAN.";


        /*
        |--------------------------------------------------------------------------
        | RELOAD EMPLOYER DATA
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT *
            FROM employers
            WHERE id = ?
            LIMIT 1
        ");


        $stmt->execute([$employerId]);

        $employer = $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | RELOAD VERIFICATION
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT *
            FROM employer_verifications
            WHERE employer_id = ?
            LIMIT 1
        ");


        $stmt->execute([$employerId]);

        $verification = $stmt->fetch();


    } catch (Exception $e) {

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| STATUS HELPER
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


/*
|--------------------------------------------------------------------------
| STATUS LABEL
|--------------------------------------------------------------------------
*/

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
        1200px;

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

    box-shadow:
        0 2px 10px
        rgba(0,0,0,0.05);

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
        rgba(37,99,235,0.08);
}


textarea {

    resize:
        vertical;

    min-height:
        110px;
}


/*
|--------------------------------------------------------------------------
| BUTTONS
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

    border:
        none;

    border-radius:
        6px;

    cursor:
        pointer;

    font-size:
        14px;

    font-weight:
        normal;
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
| LOCATION STATUS
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


/*
|--------------------------------------------------------------------------
| AREA RESULT
|--------------------------------------------------------------------------
*/

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
| COORDINATES
|--------------------------------------------------------------------------
*/

.coordinates {

    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        15px;

    margin-top:
        20px;
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
        5px;
}


/*
|--------------------------------------------------------------------------
| DOCUMENT BOX
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


/*
|--------------------------------------------------------------------------
| VERIFICATION INFO
|--------------------------------------------------------------------------
*/

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
| COMPANY INFO
|--------------------------------------------------------------------------
*/

.company-info p {

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

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


    .coordinates {

        grid-template-columns:
            1fr;
    }

}


@media (max-width: 650px) {

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
            0 15px;
    }


    .card {

        padding:
            20px;
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
     MAIN CONTENT
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
         SUCCESS MESSAGE
    ====================================================== -->

    <?php if ($success): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars($success) ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         ERROR MESSAGE
    ====================================================== -->

    <?php if ($error): ?>

        <div class="alert alert-error">

            <?= htmlspecialchars($error) ?>

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

                                value="<?=
                                    htmlspecialchars(
                                        $employer[
                                            "company_name"
                                        ]
                                        ?? ""
                                    )
                                ?>"
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

                                value="<?=
                                    htmlspecialchars(
                                        $employer[
                                            "contact_person"
                                        ]
                                        ?? ""
                                    )
                                ?>"
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

                                value="<?=
                                    htmlspecialchars(
                                        $employer[
                                            "contact_number"
                                        ]
                                        ?? ""
                                    )
                                ?>"
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

                        Set the exact location of
                        your company or business
                        in Caloocan.

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

                            placeholder="Example: Barangay 7"

                            oninput="detectCaloocanArea()"

                            value="<?=
                                htmlspecialchars(
                                    $employer[
                                        "barangay"
                                    ]
                                    ?? ""
                                )
                            ?>"
                        >


                        <!-- LIVE AREA RESULT -->

                        <div
                            id="areaResult"
                            class="location-status"
                            style="
                                display:none;
                                margin-top:10px;
                            "
                        ></div>

                    </div>



                    <!-- CURRENT LOCATION BUTTON -->

                    <button
                        type="button"
                        class="btn"
                        onclick="getCurrentLocation()"
                    >

                        Use My Current Location

                    </button>



                    <!-- LOCATION MESSAGE -->

                    <div
                        id="locationStatus"
                        class="location-status"
                    >

                        Click "Use My Current Location"
                        to detect your current
                        location.

                    </div>



                    <!-- LATITUDE / LONGITUDE -->

                    <div class="coordinates">


                        <div class="form-group">

                            <label>
                                Latitude
                            </label>


                            <input
                                type="text"
                                name="latitude"
                                id="latitude"
                                

                                value="<?=
                                    htmlspecialchars(
                                        $employer[
                                            "latitude"
                                        ]
                                        ?? ""
                                    )
                                ?>"
                            >

                        </div>



                        <div class="form-group">

                            <label>
                                Longitude
                            </label>


                            <input
                                type="text"
                                name="longitude"
                                id="longitude"
                                

                                value="<?=
                                    htmlspecialchars(
                                        $employer[
                                            "longitude"
                                        ]
                                        ?? ""
                                    )
                                ?>"
                            >

                        </div>


                    </div>



                    <!-- GOOGLE MAP -->

                    <iframe
                        id="map"
                        loading="lazy"
                        allowfullscreen
                    ></iframe>


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



                    <!-- GOVERNMENT ID -->

                    <div class="document">

                        <h3>
                            Government ID
                        </h3>


                        <p>

                            Upload a valid government-issued
                            ID showing your Caloocan address.

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



                    <!-- BARANGAY CERTIFICATE -->

                    <div class="document">

                        <h3>
                            Barangay Certificate
                        </h3>


                        <p>

                            Upload a Barangay
                            Certificate as proof
                            of your Caloocan address.

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



                    <!-- BUSINESS PERMIT -->

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
                        Maximum file size is
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


                            <span class="
                                status
                                approved-status
                            ">

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


                            <span class="
                                status
                                unverified-status
                            ">

                                Not Detected

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


                        <span class="
                            status
                            <?= verificationStatusClass(
                                $documentStatus
                            ) ?>
                        ">

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


                        <span class="
                            status
                            <?= verificationStatusClass(
                                $locationStatus
                            ) ?>
                        ">

                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $locationStatus
                                )
                            ) ?>

                        </span>

                    </div>



                    <!-- ADMIN -->

                    <div class="status-item">

                        <div class="status-item-label">
                            Admin Approval
                        </div>


                        <span class="
                            status
                            <?= verificationStatusClass(
                                $adminStatus
                            ) ?>
                        ">

                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $adminStatus
                                )
                            ) ?>

                        </span>

                    </div>



                    <!-- EMPLOYER ACCOUNT -->

                    <div class="status-item">

                        <div class="status-item-label">
                            Employer Account
                        </div>


                        <span class="
                            status
                            <?= verificationStatusClass(
                                $accountStatus
                            ) ?>
                        ">

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

                            Your location must be
                            verified as being inside
                            Caloocan City.

                        </p>


                        <br>


                        <p>

                            <strong>
                                3. Admin Review
                            </strong>

                        </p>


                        <p>

                            An administrator will
                            review the submitted
                            information before your
                            account is fully verified.

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

                        Save your profile and submit
                        the information for verification.

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


    if (!barangay || !result) {

        return;
    }


    const value =
        barangay.value.trim();


    /*
    |--------------------------------------------------------------------------
    | Extract first number
    |--------------------------------------------------------------------------
    |
    | Examples:
    |
    | Barangay 7
    | Brgy. 176
    | 188
    |
    */

    const match =
        value.match(/\d+/);


    /*
    |--------------------------------------------------------------------------
    | No number found
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
| SHOW SAVED AREA ON PAGE LOAD
|--------------------------------------------------------------------------
*/

detectCaloocanArea();



/*
|--------------------------------------------------------------------------
| SHOW SAVED LOCATION
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


if (
    savedLatitude !== "" &&
    savedLongitude !== ""
) {

    showMap(
        savedLatitude,
        savedLongitude
    );


    document.getElementById(
        "locationStatus"
    ).innerHTML =
        "✓ Saved business location.";

}



/*
|--------------------------------------------------------------------------
| GET CURRENT LOCATION
|--------------------------------------------------------------------------
*/

function getCurrentLocation()
{

    const status =
        document.getElementById(
            "locationStatus"
        );


    if (!navigator.geolocation) {

        status.innerHTML =
            "Geolocation is not supported "
            + "by your browser.";


        return;
    }


    status.innerHTML =
        "Getting your current location...";


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


            document.getElementById(
                "latitude"
            ).value =
                latitude;


            document.getElementById(
                "longitude"
            ).value =
                longitude;


            status.innerHTML =
                "✓ Current location "
                + "detected successfully.";


            showMap(
                latitude,
                longitude
            );

        },


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        function(error)
        {

            switch (error.code) {


                case error.PERMISSION_DENIED:

                    status.innerHTML =
                        "Location permission was denied. "
                        + "Please allow location access.";

                    break;


                case error.POSITION_UNAVAILABLE:

                    status.innerHTML =
                        "Your current location "
                        + "is unavailable.";

                    break;


                case error.TIMEOUT:

                    status.innerHTML =
                        "Location request timed out. "
                        + "Please try again.";

                    break;


                default:

                    status.innerHTML =
                        "Unable to get your "
                        + "current location.";

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
| SHOW GOOGLE MAP
|--------------------------------------------------------------------------
*/

function showMap(
    latitude,
    longitude
)
{

    const map =
        document.getElementById(
            "map"
        );


    map.src =
        "https://www.google.com/maps?q="
        + encodeURIComponent(latitude)
        + ","
        + encodeURIComponent(longitude)
        + "&z=17&output=embed";

}


</script>


</body>

</html>