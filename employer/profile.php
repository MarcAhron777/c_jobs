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

$employer =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employer) {
    die("Employer profile not found.");
}

$employerId =
    (int) $employer["id"];

$employerType =
    $employer["employer_type"]
    ?? "private";

$employerTypeLabel =
    $employerType === "lgu"
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

$verification =
    $stmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| HANDLE FORM
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
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
        | MAP LOCATION VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $latitude === "" ||
            $longitude === ""
        ) {
            throw new Exception(
                "Please locate the address on the map before saving."
            );
        }

        if (
            !is_numeric($latitude) ||
            !is_numeric($longitude)
        ) {
            throw new Exception(
                "Invalid map coordinates."
            );
        }

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
        | BASIC CALOOCAN BOUNDARY CHECK
        |--------------------------------------------------------------------------
        |
        | Approximate geographic check only.
        | Final location verification is still performed by the admin.
        |
        */

        if (
            $latitudeValue < 14.60 ||
            $latitudeValue > 14.83 ||
            $longitudeValue < 120.94 ||
            $longitudeValue > 121.11
        ) {
            throw new Exception(
                "The selected map location does not appear to be within Caloocan City."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DOCUMENT UPLOADS
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
        | KEEP EXISTING DOCUMENTS
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
        | SAVE EMPLOYER
        |--------------------------------------------------------------------------
        */

        $pdo->beginTransaction();

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
                    detected_address = ?,
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
                $address,
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
                    ?, ?, ?, ?, ?,
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
                $businessPermit,
                $address
            ]);
        }

        $pdo->commit();

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
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error =
            $e->getMessage();
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
    background: #ffffff;
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
    background: #ffffff;
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
    color: #ffffff;
    font-size: 14px;
    text-decoration: none;
    cursor: pointer;
}

.btn:hover {
    background: #1d4ed8;
}

.btn:disabled {
    background: #94a3b8;
    cursor: not-allowed;
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

.location-success {
    background: #dcfce7 !important;
    color: #166534 !important;
}

.location-error {
    background: #fee2e2 !important;
    color: #991b1b !important;
}

.location-searching {
    background: #fef3c7 !important;
    color: #92400e !important;
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

.map-wrapper {
    margin-top: 18px;
}

#map {
    width: 100%;
    height: 350px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    background: #f8fafc;
}

.map-help {
    margin-top: 8px;
    color: #64748b;
    font-size: 12px;
}

.document {
    padding: 18px 0;
    border-bottom: 1px solid #eeeeee;
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
    border: 1px solid #dddddd;
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
    border-bottom: 1px solid #eeeeee;
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

.location-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
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
        id="employerProfileForm"
    >

        <div class="profile-grid">


            <!-- LEFT COLUMN -->

            <div>


                <!-- COMPANY INFORMATION -->

                <div class="card">

                    <h2>
                        Company / Organization Information
                    </h2>

                    <p class="card-description">
                        Update the information shown for your
                        company or organization.
                    </p>


                    <div class="form-grid">

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
                        Enter the business address and barangay.
                        The system will find the address on
                        OpenStreetMap and save its coordinates.
                    </p>


                    <div class="form-group">

                        <label for="address">
                            Complete Address *
                        </label>

                        <textarea
                            name="address"
                            id="address"
                            required
                            placeholder="Example: 123 Zabarte Road, Camarin, Caloocan City"
                        ><?= htmlspecialchars(
                            $employer["address"]
                            ?? ""
                        ) ?></textarea>

                        <small class="help-text">
                            Enter the building or house number,
                            street, subdivision or area name.
                        </small>

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
                            required
                        >

                        <div
                            id="areaResult"
                            class="location-status"
                            style="display:none;"
                        ></div>

                    </div>


                    <div class="location-buttons">

                        <button
                            type="button"
                            class="btn"
                            id="findAddressButton"
                        >
                            Locate Address on Map
                        </button>

                    </div>


                    <div
                        id="locationStatus"
                        class="location-status"
                    >
                        Enter your complete address and barangay,
                        then click "Locate Address on Map".
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


                    <div class="map-wrapper">

                        <iframe
                            id="map"
                            loading="lazy"
                            allowfullscreen
                        ></iframe>

                        <div class="map-help">
                            The marker should represent the business
                            or organization location. The administrator
                            will still review the submitted location.
                        </div>

                    </div>

                </div>


                <!-- VERIFICATION DOCUMENTS -->

                <div class="card">

                    <h2>
                        Verification Documents
                    </h2>

                    <p class="card-description">

                        <?php if ($employerType === "lgu"): ?>

                            Submit documents that allow the
                            administrator to verify your LGU
                            employer account and Caloocan location.

                        <?php else: ?>

                            Submit proof that you or your business
                            is located in Caloocan City.

                        <?php endif; ?>

                    </p>


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
                                $verification[
                                    "barangay_certificate"
                                ]
                                ?? ""
                            )
                        ): ?>

                            <div class="uploaded">
                                Barangay Certificate already uploaded
                            </div>

                        <?php endif; ?>

                    </div>


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


                <!-- EMPLOYER ACCOUNT -->

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
                                        $employer[
                                            "caloocan_area"
                                        ]
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


                <!-- VERIFICATION PROCESS -->

                <div class="card">

                    <h2>
                        Verification Process
                    </h2>

                    <div class="verification-note">

                        <strong>
                            1. Documents
                        </strong>

                        <br>

                        Government ID and Barangay Certificate.

                        <?php if ($employerType === "private"): ?>

                            Business Permit is also required
                            for Private Employers.

                        <?php endif; ?>


                        <br><br>


                        <strong>
                            2. Location
                        </strong>

                        <br>

                        Enter your business address and barangay,
                        then locate it on the map.


                        <br><br>


                        <strong>
                            3. Employer Type
                        </strong>

                        <br>

                        The administrator will verify whether
                        the account is a Private Employer or
                        LGU employer.


                        <br><br>


                        <strong>
                            4. Admin Review
                        </strong>

                        <br>

                        Job posting access is enabled only
                        after the employer account has been
                        approved.

                    </div>

                </div>


                <!-- SAVE -->

                <div class="card">

                    <h2>
                        Save Profile
                    </h2>

                    <p class="card-description">
                        Saving your profile submits the
                        information for administrator verification.
                    </p>

                    <button
                        type="submit"
                        class="btn"
                        id="saveProfileButton"
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
| NORTH / SOUTH CALOOCAN
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

    result.classList.remove(
        "area-south",
        "area-north",
        "area-invalid"
    );

    if (!match) {
        result.style.display = "none";
        result.innerHTML = "";
        return;
    }

    const barangayNumber =
        parseInt(
            match[0],
            10
        );

    if (
        barangayNumber >= 1 &&
        barangayNumber <= 164
    ) {
        result.style.display =
            "block";

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
        result.style.display =
            "block";

        result.classList.add(
            "area-north"
        );

        result.innerHTML =
            "Detected Area: <strong>NORTH CALOOCAN</strong>";

        return;
    }

    result.style.display =
        "block";

    result.classList.add(
        "area-invalid"
    );

    result.innerHTML =
        "Please enter a valid Caloocan barangay number from 1 to 188.";
}


/*
|--------------------------------------------------------------------------
| LOCATION STATUS
|--------------------------------------------------------------------------
*/

function setLocationStatus(
    message,
    type = "normal"
) {
    const status =
        document.getElementById(
            "locationStatus"
        );

    if (!status) {
        return;
    }

    status.classList.remove(
        "location-success",
        "location-error",
        "location-searching"
    );

    if (type === "success") {
        status.classList.add(
            "location-success"
        );
    }

    if (type === "error") {
        status.classList.add(
            "location-error"
        );
    }

    if (type === "searching") {
        status.classList.add(
            "location-searching"
        );
    }

    status.innerHTML =
        message;
}


/*
|--------------------------------------------------------------------------
| ESCAPE HTML
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {
    const element =
        document.createElement("div");

    element.textContent =
        value;

    return element.innerHTML;
}


/*
|--------------------------------------------------------------------------
| CLEAR MAP LOCATION
|--------------------------------------------------------------------------
*/

function clearMapLocation() {
    const latitude =
        document.getElementById(
            "latitude"
        );

    const longitude =
        document.getElementById(
            "longitude"
        );

    const map =
        document.getElementById(
            "map"
        );

    if (latitude) {
        latitude.value = "";
    }

    if (longitude) {
        longitude.value = "";
    }

    if (map) {
        map.removeAttribute(
            "src"
        );
    }
}


/*
|--------------------------------------------------------------------------
| SHOW OPENSTREETMAP
|--------------------------------------------------------------------------
*/

function showMap(
    latitude,
    longitude
) {
    const map =
        document.getElementById(
            "map"
        );

    if (!map) {
        return;
    }

    const lat =
        parseFloat(latitude);

    const lng =
        parseFloat(longitude);

    if (
        !Number.isFinite(lat) ||
        !Number.isFinite(lng)
    ) {
        return;
    }

    const delta =
        0.006;

    const minLongitude =
        lng - delta;

    const minLatitude =
        lat - delta;

    const maxLongitude =
        lng + delta;

    const maxLatitude =
        lat + delta;

    const bbox = [
        minLongitude,
        minLatitude,
        maxLongitude,
        maxLatitude
    ].join(",");

    map.src =
        "https://www.openstreetmap.org/export/embed.html"
        + "?bbox="
        + encodeURIComponent(
            bbox
        )
        + "&layer=mapnik"
        + "&marker="
        + encodeURIComponent(
            lat
        )
        + "%2C"
        + encodeURIComponent(
            lng
        );
}


/*
|--------------------------------------------------------------------------
| BUILD ADDRESS SEARCH
|--------------------------------------------------------------------------
*/

function buildAddressQueries(
    address,
    barangay
) {
    /*
    | We try a more specific query first,
    | then slightly simpler versions if needed.
    */

    return [
        address
            + ", "
            + barangay
            + ", Caloocan City, Metro Manila, Philippines",

        address
            + ", "
            + barangay
            + ", Caloocan, Philippines",

        barangay
            + ", Caloocan City, Metro Manila, Philippines"
    ];
}


/*
|--------------------------------------------------------------------------
| SEARCH ONE ADDRESS
|--------------------------------------------------------------------------
*/

async function searchAddress(
    query
) {
    const url =
        "https://nominatim.openstreetmap.org/search"
        + "?format=jsonv2"
        + "&addressdetails=1"
        + "&limit=5"
        + "&countrycodes=ph"
        + "&q="
        + encodeURIComponent(
            query
        );

    const response =
        await fetch(
            url,
            {
                headers: {
                    "Accept":
                        "application/json"
                }
            }
        );

    if (!response.ok) {
        throw new Error(
            "Unable to connect to the map service."
        );
    }

    const data =
        await response.json();

    if (!Array.isArray(data)) {
        return [];
    }

    return data;
}


/*
|--------------------------------------------------------------------------
| CHECK APPROXIMATE CALOOCAN AREA
|--------------------------------------------------------------------------
*/

function isAroundCaloocan(
    latitude,
    longitude
) {
    return (
        latitude >= 14.60 &&
        latitude <= 14.83 &&
        longitude >= 120.94 &&
        longitude <= 121.11
    );
}


/*
|--------------------------------------------------------------------------
| LOCATE ADDRESS
|--------------------------------------------------------------------------
*/

async function locateAddressOnMap() {
    const addressInput =
        document.getElementById(
            "address"
        );

    const barangayInput =
        document.getElementById(
            "barangay"
        );

    const latitudeInput =
        document.getElementById(
            "latitude"
        );

    const longitudeInput =
        document.getElementById(
            "longitude"
        );

    const button =
        document.getElementById(
            "findAddressButton"
        );

    if (
        !addressInput ||
        !barangayInput ||
        !latitudeInput ||
        !longitudeInput
    ) {
        return false;
    }

    const address =
        addressInput.value.trim();

    const barangay =
        barangayInput.value.trim();

    if (address === "") {
        setLocationStatus(
            "Please enter the complete address first.",
            "error"
        );

        addressInput.focus();

        return false;
    }

    if (barangay === "") {
        setLocationStatus(
            "Please enter the barangay first.",
            "error"
        );

        barangayInput.focus();

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK BARANGAY NUMBER
    |--------------------------------------------------------------------------
    */

    const barangayMatch =
        barangay.match(/\d+/);

    if (!barangayMatch) {
        setLocationStatus(
            "Please enter a valid Caloocan barangay number.",
            "error"
        );

        return false;
    }

    const barangayNumber =
        parseInt(
            barangayMatch[0],
            10
        );

    if (
        barangayNumber < 1 ||
        barangayNumber > 188
    ) {
        setLocationStatus(
            "Barangay must be between 1 and 188.",
            "error"
        );

        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | START SEARCH
    |--------------------------------------------------------------------------
    */

    button.disabled =
        true;

    button.textContent =
        "Locating...";

    setLocationStatus(
        "Searching the address on OpenStreetMap...",
        "searching"
    );

    try {
        const queries =
            buildAddressQueries(
                address,
                barangay
            );

        let chosenResult =
            null;

        for (
            const query
            of
            queries
        ) {
            const results =
                await searchAddress(
                    query
                );

            if (!results.length) {
                continue;
            }

            /*
            | Prefer results that are geographically
            | around Caloocan City.
            */

            chosenResult =
                results.find(
                    function (result) {
                        const lat =
                            parseFloat(
                                result.lat
                            );

                        const lng =
                            parseFloat(
                                result.lon
                            );

                        return (
                            Number.isFinite(lat) &&
                            Number.isFinite(lng) &&
                            isAroundCaloocan(
                                lat,
                                lng
                            )
                        );
                    }
                )
                ?? null;

            if (chosenResult) {
                break;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | NOT FOUND
        |--------------------------------------------------------------------------
        */

        if (!chosenResult) {
            clearMapLocation();

            setLocationStatus(
                "Address not found in Caloocan City. Please make the address more specific and try again.",
                "error"
            );

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | COORDINATES
        |--------------------------------------------------------------------------
        */

        const latitude =
            parseFloat(
                chosenResult.lat
            );

        const longitude =
            parseFloat(
                chosenResult.lon
            );

        if (
            !Number.isFinite(
                latitude
            ) ||
            !Number.isFinite(
                longitude
            )
        ) {
            throw new Error(
                "The map service returned invalid coordinates."
            );
        }

        if (
            !isAroundCaloocan(
                latitude,
                longitude
            )
        ) {
            clearMapLocation();

            setLocationStatus(
                "The detected location is outside Caloocan City.",
                "error"
            );

            return false;
        }


        /*
        |--------------------------------------------------------------------------
        | SAVE HIDDEN VALUES
        |--------------------------------------------------------------------------
        */

        latitudeInput.value =
            latitude.toFixed(8);

        longitudeInput.value =
            longitude.toFixed(8);


        /*
        |--------------------------------------------------------------------------
        | MAP
        |--------------------------------------------------------------------------
        */

        showMap(
            latitude,
            longitude
        );


        /*
        |--------------------------------------------------------------------------
        | DISPLAY RESULT
        |--------------------------------------------------------------------------
        */

        const displayName =
            chosenResult.display_name
            || (
                address
                + ", "
                + barangay
                + ", Caloocan City"
            );

        setLocationStatus(
            "<strong>Location found.</strong><br>"
            + escapeHtml(
                displayName
            ),
            "success"
        );

        return true;

    } catch (error) {
        console.error(
            error
        );

        clearMapLocation();

        setLocationStatus(
            "Unable to locate the address right now. Please check your internet connection and try again.",
            "error"
        );

        return false;

    } finally {
        button.disabled =
            false;

        button.textContent =
            "Locate Address on Map";
    }
}


/*
|--------------------------------------------------------------------------
| ADDRESS / BARANGAY CHANGED
|--------------------------------------------------------------------------
*/

function invalidateSavedLocation() {
    const latitude =
        document.getElementById(
            "latitude"
        );

    const longitude =
        document.getElementById(
            "longitude"
        );

    const map =
        document.getElementById(
            "map"
        );

    if (
        !latitude ||
        !longitude
    ) {
        return;
    }

    /*
    | Only clear when a saved map coordinate
    | currently exists.
    */

    if (
        latitude.value !== "" ||
        longitude.value !== ""
    ) {
        latitude.value = "";
        longitude.value = "";

        if (map) {
            map.removeAttribute(
                "src"
            );
        }

        setLocationStatus(
            "Address changed. Click \"Locate Address on Map\" to update the location.",
            "normal"
        );
    }
}


/*
|--------------------------------------------------------------------------
| PAGE LOAD
|--------------------------------------------------------------------------
*/

document.addEventListener(
    "DOMContentLoaded",
    function () {
        const address =
            document.getElementById(
                "address"
            );

        const barangay =
            document.getElementById(
                "barangay"
            );

        const button =
            document.getElementById(
                "findAddressButton"
            );

        const latitude =
            document.getElementById(
                "latitude"
            );

        const longitude =
            document.getElementById(
                "longitude"
            );

        const form =
            document.getElementById(
                "employerProfileForm"
            );


        /*
        |--------------------------------------------------------------------------
        | INITIAL BARANGAY AREA
        |--------------------------------------------------------------------------
        */

        detectCaloocanArea();


        /*
        |--------------------------------------------------------------------------
        | LOAD SAVED MAP
        |--------------------------------------------------------------------------
        */

        if (
            latitude &&
            longitude &&
            latitude.value !== "" &&
            longitude.value !== ""
        ) {
            showMap(
                latitude.value,
                longitude.value
            );

            setLocationStatus(
                "Saved business / organization location loaded.",
                "success"
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOCATE BUTTON
        |--------------------------------------------------------------------------
        */

        if (button) {
            button.addEventListener(
                "click",
                locateAddressOnMap
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ADDRESS CHANGE
        |--------------------------------------------------------------------------
        */

        if (address) {
            address.addEventListener(
                "input",
                invalidateSavedLocation
            );
        }


        /*
        |--------------------------------------------------------------------------
        | BARANGAY CHANGE
        |--------------------------------------------------------------------------
        */

        if (barangay) {
            barangay.addEventListener(
                "input",
                function () {
                    detectCaloocanArea();
                    invalidateSavedLocation();
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | ENTER ADDRESS -> AUTO LOCATE AFTER LEAVING FIELD
        |--------------------------------------------------------------------------
        */

        if (address) {
            address.addEventListener(
                "change",
                function () {
                    if (
                        address.value.trim() !== "" &&
                        barangay &&
                        barangay.value.trim() !== ""
                    ) {
                        locateAddressOnMap();
                    }
                }
            );
        }


        if (barangay) {
            barangay.addEventListener(
                "change",
                function () {
                    if (
                        barangay.value.trim() !== "" &&
                        address &&
                        address.value.trim() !== ""
                    ) {
                        locateAddressOnMap();
                    }
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT SUBMIT WITHOUT MAP LOCATION
        |--------------------------------------------------------------------------
        */

        if (form) {
            form.addEventListener(
                "submit",
                function (event) {
                    if (
                        !latitude ||
                        !longitude ||
                        latitude.value === "" ||
                        longitude.value === ""
                    ) {
                        event.preventDefault();

                        setLocationStatus(
                            "Please locate the business address on the map before saving.",
                            "error"
                        );

                        if (button) {
                            button.scrollIntoView({
                                behavior: "smooth",
                                block: "center"
                            });
                        }
                    }
                }
            );
        }
    }
);

</script>

</body>
</html>