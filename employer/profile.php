<?php

session_start();

require_once "../config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "employer"
) {
    header("Location: ../login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$success = "";
$error = "";

$uploadDir = "../uploads/verification/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}


/*
|--------------------------------------------------------------------------
| UPLOAD DOCUMENT
|--------------------------------------------------------------------------
*/

function uploadDocument($file, string $uploadDir): ?string
{
    if (
        !$file ||
        !isset($file["error"]) ||
        $file["error"] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($file["error"] !== UPLOAD_ERR_OK) {
        throw new Exception(
            "There was an error uploading the file."
        );
    }

    if (($file["size"] ?? 0) > 5 * 1024 * 1024) {
        throw new Exception(
            "File size must not exceed 5MB."
        );
    }

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

    $extension = $allowedTypes[$mimeType];

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
| STATUS HELPERS
|--------------------------------------------------------------------------
*/

function verificationStatusClass(?string $status): string
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

function verificationStatusLabel(?string $status): string
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

$stmt = $pdo->prepare("
    SELECT
        id,
        user_id,
        company_name,
        employer_type,
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

$employer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employer) {
    die("Employer profile not found.");
}

$employerId = (int) $employer["id"];

$employerType = $employer["employer_type"] ?? "private";

$employerTypeLabel = $employerType === "lgu"
    ? "Local Government Unit (LGU)"
    : "Private Employer";


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

$verification = $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| HANDLE FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $companyName = trim(
            $_POST["company_name"] ?? ""
        );

        $companyDescription = trim(
            $_POST["company_description"] ?? ""
        );

        $contactPerson = trim(
            $_POST["contact_person"] ?? ""
        );

        $contactNumber = trim(
            $_POST["contact_number"] ?? ""
        );

        $address = trim(
            $_POST["address"] ?? ""
        );

        $barangay = trim(
            $_POST["barangay"] ?? ""
        );

        $latitude = trim(
            $_POST["latitude"] ?? ""
        );

        $longitude = trim(
            $_POST["longitude"] ?? ""
        );


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($companyName === "") {
            throw new Exception(
                "Company / Organization Name is required."
            );
        }

        if ($contactPerson === "") {
            throw new Exception(
                "Contact person is required."
            );
        }

        if ($address === "") {
            throw new Exception(
                "Company / Organization address is required."
            );
        }

        if ($barangay === "") {
            throw new Exception(
                "Barangay is required."
            );
        }


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

            if (
                $barangayNumber >= 1 &&
                $barangayNumber <= 164
            ) {
                $caloocanArea = "south";
            } elseif (
                $barangayNumber >= 165 &&
                $barangayNumber <= 188
            ) {
                $caloocanArea = "north";
            }
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
                "Please set your business or organization location."
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

        $latitudeValue = (float) $latitude;
        $longitudeValue = (float) $longitude;

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
        |
        | employer_type is intentionally NOT updated here.
        | It comes from registration and is reviewed by the admin.
        |
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

        $governmentId = uploadDocument(
            $_FILES["government_id"] ?? null,
            $uploadDir
        );

        $barangayCertificate = uploadDocument(
            $_FILES["barangay_certificate"] ?? null,
            $uploadDir
        );

        $businessPermit = uploadDocument(
            $_FILES["business_permit"] ?? null,
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
                    $verification["government_id"]
                    ?? null;
            }

            if (!$barangayCertificate) {
                $barangayCertificate =
                    $verification["barangay_certificate"]
                    ?? null;
            }

            if (!$businessPermit) {
                $businessPermit =
                    $verification["business_permit"]
                    ?? null;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DOCUMENT REQUIREMENTS
        |--------------------------------------------------------------------------
        */

        if (!$governmentId) {
            throw new Exception(
                "Government ID is required for verification."
            );
        }

        if (!$barangayCertificate) {
            throw new Exception(
                "Barangay Certificate is required for verification."
            );
        }

        /*
         * Business Permit is required for Private Employers.
         * LGU employers do not need a Business Permit.
         */
        if (
            $employerType === "private" &&
            !$businessPermit
        ) {
            throw new Exception(
                "Business Permit is required for Private Employers."
            );
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
            "Profile saved successfully. Your information has been submitted for verification.";


        /*
        |--------------------------------------------------------------------------
        | RELOAD EMPLOYER
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                id,
                user_id,
                company_name,
                employer_type,
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
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->execute([$employerId]);

        $employer =
            $stmt->fetch(PDO::FETCH_ASSOC);

        $employerType =
            $employer["employer_type"]
            ?? "private";

        $employerTypeLabel =
            $employerType === "lgu"
                ? "Local Government Unit (LGU)"
                : "Private Employer";


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

        $verification =
            $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| STATUS VALUES
|--------------------------------------------------------------------------
*/

$documentStatus =
    $verification["document_status"]
    ?? "unverified";

$locationVerificationStatus =
    $verification["location_status"]
    ?? "unverified";

$adminStatus =
    $verification["admin_status"]
    ?? "unverified";

$accountStatus =
    $employer["verification_status"]
    ?? "unverified";

$adminNotes =
    trim(
        $verification["admin_notes"]
        ?? ""
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
        Employer Profile - Caloocan Job Portal
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

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px 50px;
        }

        .welcome {
            margin-bottom: 30px;
        }

        .welcome h1 {
            margin-bottom: 8px;
        }

        .welcome p {
            color: #64748b;
            line-height: 1.6;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            align-items: start;
        }

        .card {
            margin-bottom: 20px;
            padding: 25px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .card h2 {
            margin-bottom: 8px;
        }

        .card-description {
            margin-bottom: 22px;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert {
            margin-bottom: 20px;
            padding: 15px 18px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .alert-success {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="tel"],
        textarea {
            width: 100%;
            padding: 11px 12px;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.08);
        }

        input[readonly] {
            background: #f1f5f9;
            color: #475569;
            cursor: not-allowed;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        .help-text {
            display: block;
            margin-top: 6px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .employer-type-box {
            padding: 15px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
        }

        .employer-type-box strong {
            display: block;
            margin-bottom: 5px;
            color: #1e40af;
        }

        .employer-type-box p {
            color: #475569;
            font-size: 13px;
            line-height: 1.5;
        }

        .btn {
            display: inline-block;
            padding: 10px 15px;
            background: #2563eb;
            border: none;
            border-radius: 6px;
            color: #fff;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .location-status {
            margin-top: 15px;
            padding: 12px 14px;
            background: #dbeafe;
            border-radius: 6px;
            color: #1e40af;
            font-size: 14px;
            line-height: 1.5;
        }

        .area-south {
            background: #dcfce7 !important;
            color: #166534 !important;
        }

        .area-north {
            background: #dbeafe !important;
            color: #1e40af !important;
        }

        .area-invalid {
            background: #fee2e2 !important;
            color: #991b1b !important;
        }

        #map {
            width: 100%;
            height: 350px;
            margin-top: 18px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .document {
            padding: 18px 0;
            border-bottom: 1px solid #eee;
        }

        .document:first-of-type {
            padding-top: 0;
        }

        .document:last-of-type {
            border-bottom: none;
        }

        .document h3 {
            margin-bottom: 6px;
            font-size: 16px;
        }

        .document p {
            margin-bottom: 12px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.6;
        }

        input[type="file"] {
            width: 100%;
            padding: 10px;
            background: #fafafa;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .uploaded {
            margin-top: 10px;
            color: #166534;
            font-size: 13px;
            font-weight: bold;
        }

        .verification-note {
            margin-top: 20px;
            padding: 15px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
        }

        .status-item {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-item-label {
            margin-bottom: 8px;
            color: #64748b;
            font-size: 13px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending-status {
            background: #fef3c7;
            color: #92400e;
        }

        .approved-status {
            background: #dcfce7;
            color: #166534;
        }

        .rejected-status {
            background: #fee2e2;
            color: #991b1b;
        }

        .review-status {
            background: #dbeafe;
            color: #1e40af;
        }

        .unverified-status {
            background: #e5e7eb;
            color: #4b5563;
        }

        .admin-note {
            margin-top: 15px;
            padding: 14px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 7px;
            color: #9a3412;
            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 800px) {

            .profile-grid,
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

        }

        @media (max-width: 600px) {

            .container {
                margin: 25px auto;
                padding: 0 15px;
            }

            .card {
                padding: 20px;
            }

            #map {
                height: 280px;
            }

        }

    </style>

</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>


<div class="container">

    <div class="welcome">

        <h1>
            Employer Profile
        </h1>

        <p>
            Manage your company or organization information,
            business location, and verification documents.
        </p>

    </div>


    <?php if ($success): ?>

        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


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


            <!-- LEFT COLUMN -->

            <div>


                <!-- COMPANY / ORGANIZATION -->

                <div class="card">

                    <h2>
                        Company / Organization Information
                    </h2>

                    <p class="card-description">
                        Update the information shown for your
                        company or organization.
                    </p>


                    <div class="form-grid">


                        <!-- EMPLOYER TYPE -->

                        <div class="form-group full">

                            <label>
                                Employer Type
                            </label>

                            <div class="employer-type-box">

                                <strong>
                                    <?= htmlspecialchars(
                                        $employerTypeLabel
                                    ) ?>
                                </strong>

                                <p>
                                    Your employer type was selected
                                    during registration and cannot
                                    be changed from this profile.
                                </p>

                            </div>

                        </div>


                        <!-- COMPANY NAME -->

                        <div class="form-group">

                            <label for="company_name">
                                Company / Organization Name *
                            </label>

                            <input
                                type="text"
                                name="company_name"
                                id="company_name"
                                value="<?= htmlspecialchars(
                                    $employer["company_name"]
                                    ?? ""
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- CONTACT PERSON -->

                        <div class="form-group">

                            <label for="contact_person">
                                Contact Person *
                            </label>

                            <input
                                type="text"
                                name="contact_person"
                                id="contact_person"
                                value="<?= htmlspecialchars(
                                    $employer["contact_person"]
                                    ?? ""
                                ) ?>"
                                required
                            >

                        </div>


                        <!-- CONTACT NUMBER -->

                        <div class="form-group">

                            <label for="contact_number">
                                Contact Number
                            </label>

                            <input
                                type="tel"
                                name="contact_number"
                                id="contact_number"
                                placeholder="09XXXXXXXXX"
                                value="<?= htmlspecialchars(
                                    $employer["contact_number"]
                                    ?? ""
                                ) ?>"
                            >

                        </div>


                        <!-- DESCRIPTION -->

                        <div class="form-group full">

                            <label for="company_description">
                                Company / Organization Description
                            </label>

                            <textarea
                                name="company_description"
                                id="company_description"
                                placeholder="Tell job seekers about your company or organization..."
                            ><?= htmlspecialchars(
                                $employer["company_description"]
                                ?? ""
                            ) ?></textarea>

                        </div>

                    </div>

                </div>


                <!-- LOCATION -->

                <div class="card">

                    <h2>
                        Business / Organization Location
                    </h2>

                    <p class="card-description">
                        Set the exact location of your company
                        or organization in Caloocan City.
                    </p>


                    <div class="form-group">

                        <label for="address">
                            Complete Address *
                        </label>

                        <textarea
                            name="address"
                            id="address"
                            required
                            placeholder="Building No., Street, Barangay, Caloocan City"
                        ><?= htmlspecialchars(
                            $employer["address"]
                            ?? ""
                        ) ?></textarea>

                    </div>


                    <div class="form-group">

                        <label for="barangay">
                            Barangay *
                        </label>

                        <input
                            type="text"
                            name="barangay"
                            id="barangay"
                            placeholder="Example: Barangay 176"
                            value="<?= htmlspecialchars(
                                $employer["barangay"]
                                ?? ""
                            ) ?>"
                            oninput="detectCaloocanArea()"
                            required
                        >

                        <div
                            id="areaResult"
                            class="location-status"
                            style="display:none;"
                        ></div>

                    </div>


                    <button
                        type="button"
                        class="btn"
                        onclick="getCurrentLocation()"
                    >
                        Use My Current Location
                    </button>


                    <div
                        id="locationStatus"
                        class="location-status"
                    >
                        Click "Use My Current Location"
                        to detect your current location.
                    </div>


                    <input
                        type="hidden"
                        name="latitude"
                        id="latitude"
                        value="<?= htmlspecialchars(
                            $employer["latitude"]
                            ?? ""
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="longitude"
                        id="longitude"
                        value="<?= htmlspecialchars(
                            $employer["longitude"]
                            ?? ""
                        ) ?>"
                    >


                    <iframe
                        id="map"
                        loading="lazy"
                        allowfullscreen
                    ></iframe>

                </div>


                <!-- VERIFICATION DOCUMENTS -->

                <div class="card">

                    <h2>
                        Verification Documents
                    </h2>

                    <p class="card-description">

                        <?php if ($employerType === "lgu"): ?>

                            Submit documents that allow the administrator
                            to verify your LGU employer account and
                            Caloocan location.

                        <?php else: ?>

                            Submit proof that you or your business
                            is located in Caloocan City.

                        <?php endif; ?>

                    </p>


                    <!-- GOVERNMENT ID -->

                    <div class="document">

                        <h3>
                            Government ID *
                        </h3>

                        <p>
                            Upload a valid government-issued ID
                            for the authorized contact person.
                        </p>

                        <input
                            type="file"
                            name="government_id"
                            accept=".jpg,.jpeg,.png,.pdf"
                        >

                        <?php if (
                            !empty(
                                $verification["government_id"]
                                ?? ""
                            )
                        ): ?>

                            <div class="uploaded">
                                Government ID already uploaded
                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- BARANGAY CERTIFICATE -->

                    <div class="document">

                        <h3>
                            Barangay Certificate *
                        </h3>

                        <p>
                            Upload a Barangay Certificate or
                            supporting location document for
                            the Caloocan address.
                        </p>

                        <input
                            type="file"
                            name="barangay_certificate"
                            accept=".jpg,.jpeg,.png,.pdf"
                        >

                        <?php if (
                            !empty(
                                $verification["barangay_certificate"]
                                ?? ""
                            )
                        ): ?>

                            <div class="uploaded">
                                Barangay Certificate already uploaded
                            </div>

                        <?php endif; ?>

                    </div>


                    <!-- BUSINESS PERMIT -->

                    <div class="document">

                        <h3>

                            <?php if ($employerType === "lgu"): ?>

                                Business Permit

                            <?php else: ?>

                                Business Permit *

                            <?php endif; ?>

                        </h3>


                        <?php if ($employerType === "lgu"): ?>

                            <p>
                                Business Permit is not required
                                for an LGU employer account.
                            </p>

                        <?php else: ?>

                            <p>
                                Required for Private Employer
                                companies or registered businesses.
                            </p>

                        <?php endif; ?>


                        <input
                            type="file"
                            name="business_permit"
                            accept=".jpg,.jpeg,.png,.pdf"
                        >


                        <?php if (
                            !empty(
                                $verification["business_permit"]
                                ?? ""
                            )
                        ): ?>

                            <div class="uploaded">
                                Business Permit already uploaded
                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="verification-note">

                        Accepted formats:
                        JPG, PNG, and PDF.

                        <br>

                        Maximum file size:
                        5MB per document.

                        <?php if ($employerType === "lgu"): ?>

                            <br><br>

                            Your LGU classification will still
                            be reviewed by the administrator
                            before your employer account is approved.

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- RIGHT COLUMN -->

            <div>


                <!-- EMPLOYER ACCOUNT TYPE -->

                <div class="card">

                    <h2>
                        Employer Account
                    </h2>

                    <div class="status-item">

                        <div class="status-item-label">
                            Employer Type
                        </div>

                        <strong>
                            <?= htmlspecialchars(
                                $employerTypeLabel
                            ) ?>
                        </strong>

                    </div>


                    <div class="status-item">

                        <div class="status-item-label">
                            Job Source After Approval
                        </div>

                        <strong>

                            <?= $employerType === "lgu"
                                ? "LGU"
                                : "Private Employer"
                            ?>

                        </strong>

                    </div>

                </div>


                <!-- LOCATION SUMMARY -->

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
                                $employer["barangay"]
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
                                $employer["caloocan_area"]
                            )
                        ): ?>

                            <span class="status approved-status">

                                <?= strtoupper(
                                    htmlspecialchars(
                                        $employer["caloocan_area"]
                                    )
                                ) ?>
                                CALOOCAN

                            </span>

                        <?php else: ?>

                            <span class="status unverified-status">
                                Not Detected
                            </span>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- VERIFICATION STATUS -->

                <div class="card">

                    <h2>
                        Verification Status
                    </h2>


                    <div class="status-item">

                        <div class="status-item-label">
                            Documents
                        </div>

                        <span
                            class="status <?= verificationStatusClass(
                                $documentStatus
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $documentStatus
                                )
                            ) ?>
                        </span>

                    </div>


                    <div class="status-item">

                        <div class="status-item-label">
                            Location
                        </div>

                        <span
                            class="status <?= verificationStatusClass(
                                $locationVerificationStatus
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $locationVerificationStatus
                                )
                            ) ?>
                        </span>

                    </div>


                    <div class="status-item">

                        <div class="status-item-label">
                            Admin Approval
                        </div>

                        <span
                            class="status <?= verificationStatusClass(
                                $adminStatus
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $adminStatus
                                )
                            ) ?>
                        </span>

                    </div>


                    <div class="status-item">

                        <div class="status-item-label">
                            Employer Account
                        </div>

                        <span
                            class="status <?= verificationStatusClass(
                                $accountStatus
                            ) ?>"
                        >
                            <?= htmlspecialchars(
                                verificationStatusLabel(
                                    $accountStatus
                                )
                            ) ?>
                        </span>

                    </div>


                    <?php if ($adminNotes !== ""): ?>

                        <div class="admin-note">

                            <strong>
                                Admin Notes
                            </strong>

                            <br>

                            <?= nl2br(
                                htmlspecialchars(
                                    $adminNotes
                                )
                            ) ?>

                        </div>

                    <?php endif; ?>

                </div>


                <!-- VERIFICATION REQUIREMENTS -->

                <div class="card">

                    <h2>
                        Verification Process
                    </h2>

                    <div class="verification-note">

                        <strong>
                            1. Documents
                        </strong>

                        <br>

                        Government ID and
                        Barangay Certificate.

                        <?php if ($employerType === "private"): ?>

                            Business Permit is also required
                            for Private Employers.

                        <?php endif; ?>


                        <br><br>


                        <strong>
                            2. Location
                        </strong>

                        <br>

                        Your company or organization
                        location must be inside
                        Caloocan City.


                        <br><br>


                        <strong>
                            3. Employer Type
                        </strong>

                        <br>

                        The administrator will verify
                        whether the account is a
                        Private Employer or LGU employer.


                        <br><br>


                        <strong>
                            4. Admin Review
                        </strong>

                        <br>

                        Job posting access is enabled
                        only after the employer account
                        has been approved.

                    </div>

                </div>


                <!-- SAVE -->

                <div class="card">

                    <h2>
                        Save Profile
                    </h2>

                    <p class="card-description">
                        Saving your profile submits
                        the information for administrator
                        verification.
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
| NORTH / SOUTH CALOOCAN DETECTION
|--------------------------------------------------------------------------
*/

function detectCaloocanArea() {
    const barangay =
        document.getElementById("barangay");

    const result =
        document.getElementById("areaResult");

    if (!barangay || !result) {
        return;
    }

    const value =
        barangay.value.trim();

    const match =
        value.match(/\d+/);

    if (!match) {
        result.style.display = "none";
        result.innerHTML = "";

        result.classList.remove(
            "area-south",
            "area-north",
            "area-invalid"
        );

        return;
    }

    const barangayNumber =
        parseInt(match[0], 10);

    if (
        barangayNumber >= 1 &&
        barangayNumber <= 164
    ) {
        result.style.display = "block";

        result.classList.remove(
            "area-north",
            "area-invalid"
        );

        result.classList.add(
            "area-south"
        );

        result.innerHTML =
            "Detected Area: <strong>SOUTH CALOOCAN</strong>";

        return;
    }

    if (
        barangayNumber >= 165 &&
        barangayNumber <= 188
    ) {
        result.style.display = "block";

        result.classList.remove(
            "area-south",
            "area-invalid"
        );

        result.classList.add(
            "area-north"
        );

        result.innerHTML =
            "Detected Area: <strong>NORTH CALOOCAN</strong>";

        return;
    }

    result.style.display = "block";

    result.classList.remove(
        "area-south",
        "area-north"
    );

    result.classList.add(
        "area-invalid"
    );

    result.innerHTML =
        "Please enter a valid Caloocan barangay number from 1 to 188.";
}


/*
|--------------------------------------------------------------------------
| CURRENT LOCATION
|--------------------------------------------------------------------------
*/

function getCurrentLocation() {
    const status =
        document.getElementById(
            "locationStatus"
        );

    if (!navigator.geolocation) {
        status.innerHTML =
            "Geolocation is not supported by your browser.";

        return;
    }

    status.innerHTML =
        "Getting your current location...";

    navigator.geolocation.getCurrentPosition(
        function (position) {
            const latitude =
                position.coords.latitude;

            const longitude =
                position.coords.longitude;

            document.getElementById(
                "latitude"
            ).value = latitude;

            document.getElementById(
                "longitude"
            ).value = longitude;

            status.innerHTML =
                "Current location detected successfully.";

            showMap(
                latitude,
                longitude
            );
        },

        function (error) {
            switch (error.code) {
                case error.PERMISSION_DENIED:
                    status.innerHTML =
                        "Location permission was denied. Please allow location access.";
                    break;

                case error.POSITION_UNAVAILABLE:
                    status.innerHTML =
                        "Your current location is unavailable.";
                    break;

                case error.TIMEOUT:
                    status.innerHTML =
                        "Location request timed out. Please try again.";
                    break;

                default:
                    status.innerHTML =
                        "Unable to get your current location.";
            }
        },

        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}


/*
|--------------------------------------------------------------------------
| MAP
|--------------------------------------------------------------------------
*/

function showMap(latitude, longitude) {
    const map =
        document.getElementById("map");

    if (!map) {
        return;
    }

    const delta = 0.008;

    const minLongitude =
        parseFloat(longitude) - delta;

    const minLatitude =
        parseFloat(latitude) - delta;

    const maxLongitude =
        parseFloat(longitude) + delta;

    const maxLatitude =
        parseFloat(latitude) + delta;

    const bbox = [
        minLongitude,
        minLatitude,
        maxLongitude,
        maxLatitude
    ].join(",");

    map.src =
        "https://www.openstreetmap.org/export/embed.html"
        + "?bbox="
        + encodeURIComponent(bbox)
        + "&layer=mapnik"
        + "&marker="
        + encodeURIComponent(latitude)
        + "%2C"
        + encodeURIComponent(longitude);
}


/*
|--------------------------------------------------------------------------
| PAGE LOAD
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        detectCaloocanArea();

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
                "Saved business / organization location loaded.";
        }
    }
);

</script>

</body>
</html>