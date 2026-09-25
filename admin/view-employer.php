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
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| GET EMPLOYER ID
|--------------------------------------------------------------------------
*/

$employerId =
    isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


if ($employerId <= 0) {
    header("Location: employers.php");
    exit;
}


$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| HANDLE ADMIN ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action =
        $_POST["action"] ?? "";

    $adminNotes =
        trim(
            $_POST["admin_notes"]
            ?? ""
        );


    try {

        /*
        |--------------------------------------------------------------------------
        | SAVE ADMIN NOTES
        |--------------------------------------------------------------------------
        */

        if ($action === "save_notes") {

            $stmt = $pdo->prepare("
                UPDATE employer_verifications
                SET admin_notes = ?
                WHERE employer_id = ?
            ");

            $stmt->execute([
                $adminNotes,
                $employerId
            ]);

            $success =
                "Admin notes saved successfully.";
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVE DOCUMENTS
        |--------------------------------------------------------------------------
        */

        elseif ($action === "approve_documents") {

            $stmt = $pdo->prepare("
                UPDATE employer_verifications
                SET
                    document_status = 'passed',
                    admin_notes = ?
                WHERE employer_id = ?
            ");

            $stmt->execute([
                $adminNotes,
                $employerId
            ]);

            $success =
                "Verification documents approved.";
        }


        /*
        |--------------------------------------------------------------------------
        | REJECT DOCUMENTS
        |--------------------------------------------------------------------------
        */

        elseif ($action === "reject_documents") {

            if ($adminNotes === "") {
                throw new Exception(
                    "Please add an admin note explaining why the documents were rejected."
                );
            }


            $stmt = $pdo->prepare("
                UPDATE employer_verifications
                SET
                    document_status = 'failed',
                    admin_notes = ?
                WHERE employer_id = ?
            ");

            $stmt->execute([
                $adminNotes,
                $employerId
            ]);

            $success =
                "Verification documents rejected.";
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVE LOCATION
        |--------------------------------------------------------------------------
        */

        elseif ($action === "approve_location") {

            $stmt = $pdo->prepare("
                UPDATE employer_verifications
                SET
                    location_status = 'passed',
                    admin_notes = ?
                WHERE employer_id = ?
            ");

            $stmt->execute([
                $adminNotes,
                $employerId
            ]);

            $success =
                "Employer location approved.";
        }


        /*
        |--------------------------------------------------------------------------
        | REJECT LOCATION
        |--------------------------------------------------------------------------
        */

        elseif ($action === "reject_location") {

            if ($adminNotes === "") {
                throw new Exception(
                    "Please add an admin note explaining why the location was rejected."
                );
            }


            $stmt = $pdo->prepare("
                UPDATE employer_verifications
                SET
                    location_status = 'failed',
                    admin_notes = ?
                WHERE employer_id = ?
            ");

            $stmt->execute([
                $adminNotes,
                $employerId
            ]);

            $success =
                "Employer location rejected.";
        }


        /*
        |--------------------------------------------------------------------------
        | APPROVE EMPLOYER
        |--------------------------------------------------------------------------
        */

        elseif ($action === "approve_employer") {


            /*
            |--------------------------------------------------------------------------
            | CHECK VERIFICATION
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT
                    document_status,
                    location_status
                FROM employer_verifications
                WHERE employer_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $employerId
            ]);

            $verificationCheck =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$verificationCheck) {
                throw new Exception(
                    "Verification record not found."
                );
            }


            if (
                $verificationCheck["document_status"]
                !== "passed"
            ) {
                throw new Exception(
                    "Approve the employer documents first."
                );
            }


            if (
                $verificationCheck["location_status"]
                !== "passed"
            ) {
                throw new Exception(
                    "Approve the employer location first."
                );
            }


            /*
            |--------------------------------------------------------------------------
            | APPROVE ADMIN STATUS
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE employer_verifications
                SET
                    admin_status = 'approved',
                    admin_notes = ?
                WHERE employer_id = ?
            ");

            $stmt->execute([
                $adminNotes,
                $employerId
            ]);


            /*
            |--------------------------------------------------------------------------
            | APPROVE EMPLOYER
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE employers
                SET verification_status = 'approved'
                WHERE id = ?
            ");

            $stmt->execute([
                $employerId
            ]);


            $success =
                "Employer successfully approved.";
        }


        /*
        |--------------------------------------------------------------------------
        | REJECT EMPLOYER
        |--------------------------------------------------------------------------
        */

        elseif ($action === "reject_employer") {

            if ($adminNotes === "") {
                throw new Exception(
                    "Please add an admin note explaining why the employer was rejected."
                );
            }


            $stmt = $pdo->prepare("
                UPDATE employer_verifications
                SET
                    admin_status = 'rejected',
                    admin_notes = ?
                WHERE employer_id = ?
            ");

            $stmt->execute([
                $adminNotes,
                $employerId
            ]);


            $stmt = $pdo->prepare("
                UPDATE employers
                SET verification_status = 'rejected'
                WHERE id = ?
            ");

            $stmt->execute([
                $employerId
            ]);


            $success =
                "Employer verification rejected.";
        }


    } catch (Exception $e) {

        $error =
            $e->getMessage();
    }
}


/*
|--------------------------------------------------------------------------
| GET EMPLOYER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT

        e.*,

        u.email,

        u.is_active

    FROM employers e

    INNER JOIN users u
        ON e.user_id = u.id

    WHERE e.id = ?

    LIMIT 1
");


$stmt->execute([
    $employerId
]);


$employer =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$employer) {
    die("Employer not found.");
}


/*
|--------------------------------------------------------------------------
| GET VERIFICATION DATA
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
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
| STATUS HELPERS
|--------------------------------------------------------------------------
*/

function statusClass($status)
{

    switch ($status) {

        case "approved":
        case "passed":
            return "approved";


        case "pending":
        case "processing":
            return "pending";


        case "rejected":
        case "failed":
            return "rejected";


        case "needs_review":
            return "review";


        default:
            return "unverified";
    }
}


function statusLabel($status)
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
| DOCUMENT PREVIEW
|--------------------------------------------------------------------------
*/

function documentPreview(
    $filename,
    $label
) {

    if (!$filename) {

        echo '
            <div class="document-missing">
                No file uploaded
            </div>
        ';

        return;
    }


    $fileUrl =
        "../uploads/verification/"
        . rawurlencode($filename);


    $extension =
        strtolower(
            pathinfo(
                $filename,
                PATHINFO_EXTENSION
            )
        );


    /*
    |--------------------------------------------------------------------------
    | IMAGE
    |--------------------------------------------------------------------------
    */

    if (
        in_array(
            $extension,
            [
                "jpg",
                "jpeg",
                "png"
            ],
            true
        )
    ) {

        echo '

            <div class="document-preview">

                <img
                    src="' .
                    htmlspecialchars($fileUrl) .
                    '"
                    alt="' .
                    htmlspecialchars($label) .
                    '"
                >

            </div>


            <a
                href="' .
                htmlspecialchars($fileUrl) .
                '"
                target="_blank"
                class="document-link"
            >
                Open Full Image
            </a>

        ';

    }


    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    elseif ($extension === "pdf") {

        echo '

            <div class="pdf-preview">

                <iframe
                    src="' .
                    htmlspecialchars($fileUrl) .
                    '"
                ></iframe>

            </div>


            <a
                href="' .
                htmlspecialchars($fileUrl) .
                '"
                target="_blank"
                class="document-link"
            >
                Open PDF
            </a>

        ';

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
    View Employer - Admin
</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
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
        #111827;

    padding:
        18px 50px;
}


.navbar-container {

    max-width:
        1200px;

    margin:
        auto;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;
}


.logo {

    color:
        white;

    text-decoration:
        none;

    font-size:
        22px;

    font-weight:
        bold;
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

    color:
        white;

    text-decoration:
        none;

    font-size:
        14px;
}


.logout {

    background:
        #dc2626;

    padding:
        8px 14px;

    border-radius:
        5px;
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


.page-header {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;

    margin-bottom:
        25px;
}


.page-header p {

    color:
        #666;

    margin-top:
        7px;
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
}


.alert-success {

    background:
        #dcfce7;

    color:
        #166534;
}


.alert-error {

    background:
        #fee2e2;

    color:
        #991b1b;
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
        20px;
}


/*
|--------------------------------------------------------------------------
| INFO
|--------------------------------------------------------------------------
*/

.info-row {

    padding:
        13px 0;

    border-bottom:
        1px solid #eee;
}


.info-row:last-child {

    border-bottom:
        none;
}


.info-label {

    color:
        #666;

    font-size:
        13px;

    margin-bottom:
        5px;
}


.info-value {

    font-size:
        14px;

    line-height:
        1.5;

    word-break:
        break-word;
}


/*
|--------------------------------------------------------------------------
| STATUS
|--------------------------------------------------------------------------
*/

.status {

    display:
        inline-block;

    padding:
        5px 10px;

    border-radius:
        15px;

    font-size:
        12px;

    font-weight:
        bold;
}


.approved {

    background:
        #dcfce7;

    color:
        #166534;
}


.pending {

    background:
        #fef3c7;

    color:
        #92400e;
}


.rejected {

    background:
        #fee2e2;

    color:
        #991b1b;
}


.review {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.unverified {

    background:
        #e5e7eb;

    color:
        #374151;
}


/*
|--------------------------------------------------------------------------
| AREA
|--------------------------------------------------------------------------
*/

.area {

    display:
        inline-block;

    padding:
        5px 10px;

    border-radius:
        15px;

    font-size:
        12px;

    font-weight:
        bold;
}


.north {

    background:
        #dbeafe;

    color:
        #1e40af;
}


.south {

    background:
        #dcfce7;

    color:
        #166534;
}


/*
|--------------------------------------------------------------------------
| DOCUMENT
|--------------------------------------------------------------------------
*/

.document {

    border:
        1px solid #e5e7eb;

    border-radius:
        8px;

    padding:
        18px;

    margin-bottom:
        18px;
}


.document h3 {

    margin-bottom:
        15px;
}


.document-preview {

    width:
        100%;

    max-height:
        450px;

    overflow:
        auto;

    background:
        #f9fafb;

    border-radius:
        8px;

    margin-bottom:
        12px;
}


.document-preview img {

    display:
        block;

    width:
        100%;

    height:
        auto;

    object-fit:
        contain;
}


.pdf-preview {

    height:
        450px;

    margin-bottom:
        12px;
}


.pdf-preview iframe {

    width:
        100%;

    height:
        100%;

    border:
        1px solid #ddd;

    border-radius:
        8px;
}


.document-link {

    display:
        inline-block;

    margin-top:
        5px;

    color:
        #2563eb;

    text-decoration:
        none;

    font-size:
        14px;
}


.document-missing {

    color:
        #991b1b;

    background:
        #fee2e2;

    padding:
        12px;

    border-radius:
        6px;
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
        15px;
}


/*
|--------------------------------------------------------------------------
| ADMIN NOTES
|--------------------------------------------------------------------------
*/

.admin-notes {

    width:
        100%;

    min-height:
        140px;

    padding:
        12px;

    border:
        1px solid #d1d5db;

    border-radius:
        6px;

    resize:
        vertical;

    font-family:
        Arial,
        sans-serif;

    font-size:
        14px;

    line-height:
        1.5;
}


.admin-notes:focus {

    outline:
        none;

    border-color:
        #2563eb;
}


.note-help {

    color:
        #666;

    font-size:
        13px;

    line-height:
        1.5;

    margin-bottom:
        12px;
}


/*
|--------------------------------------------------------------------------
| BUTTONS
|--------------------------------------------------------------------------
*/

.btn {

    display:
        inline-block;

    border:
        none;

    padding:
        10px 15px;

    border-radius:
        6px;

    cursor:
        pointer;

    color:
        white;

    text-decoration:
        none;

    font-size:
        14px;
}


.btn-blue {

    background:
        #2563eb;
}


.btn-green {

    background:
        #16a34a;
}


.btn-red {

    background:
        #dc2626;
}


.btn-gray {

    background:
        #64748b;
}


.action-buttons {

    display:
        flex;

    gap:
        10px;

    flex-wrap:
        wrap;

    margin-top:
        20px;
}


/*
|--------------------------------------------------------------------------
| FINAL APPROVAL
|--------------------------------------------------------------------------
*/

.final-approval {

    border:
        2px solid #dbeafe;

    background:
        #eff6ff;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 800px) {

    .grid {

        grid-template-columns:
            1fr;
    }


    .page-header {

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .navbar {

        padding:
            15px 20px;
    }


    .navbar-container {

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


    .pdf-preview {

        height:
            300px;
    }

}

</style>

</head>


<body>


<!-- =========================================================
     NAVBAR
========================================================= -->

<nav class="navbar">

    <div class="navbar-container">


        <a
            href="dashboard.php"
            class="logo"
        >
            Job Portal Admin
        </a>


        <div class="nav-links">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="jobs.php">
                Manage Jobs
            </a>

            <a href="employers.php">
                Manage Employers
            </a>

            <a href="applicants.php">
                Manage Applicants
            </a>

            <a
                href="../logout.php"
                class="logout"
            >
                Logout
            </a>

        </div>

    </div>

</nav>



<div class="container">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="page-header">

        <div>

            <h1>

                <?= htmlspecialchars(
                    $employer[
                        "company_name"
                    ]
                ) ?>

            </h1>


            <p>
                Employer profile and verification review.
            </p>

        </div>


        <a
            href="employers.php"
            class="btn btn-gray"
        >
            ← Back to Employers
        </a>

    </div>



    <!-- SUCCESS -->

    <?php if ($success): ?>

        <div class="alert alert-success">

            <?= htmlspecialchars(
                $success
            ) ?>

        </div>

    <?php endif; ?>



    <!-- ERROR -->

    <?php if ($error): ?>

        <div class="alert alert-error">

            <?= htmlspecialchars(
                $error
            ) ?>

        </div>

    <?php endif; ?>



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


    $employerStatus =
        $employer[
            "verification_status"
        ]
        ?? "unverified";


    $adminNotes =
        $verification[
            "admin_notes"
        ]
        ?? "";

    ?>



    <div class="grid">


        <!-- =================================================
             LEFT COLUMN
        ================================================== -->

        <div>


            <!-- COMPANY INFO -->

            <div class="card">

                <h2>
                    Company Information
                </h2>


                <div class="info-row">

                    <div class="info-label">
                        Company Name
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $employer[
                                "company_name"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Email
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $employer[
                                "email"
                            ]
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Company Description
                    </div>

                    <div class="info-value">

                        <?= nl2br(
                            htmlspecialchars(
                                $employer[
                                    "company_description"
                                ]
                                ?: "N/A"
                            )
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Contact Person
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $employer[
                                "contact_person"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Contact Number
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $employer[
                                "contact_number"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>


            </div>



            <!-- BUSINESS LOCATION -->

            <div class="card">

                <h2>
                    Business Location
                </h2>


                <div class="info-row">

                    <div class="info-label">
                        Address
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $employer[
                                "address"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Barangay
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $employer[
                                "barangay"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Caloocan Area
                    </div>


                    <div class="info-value">


                        <?php if (
                            !empty(
                                $employer[
                                    "caloocan_area"
                                ]
                            )
                        ): ?>


                            <span
                                class="
                                    area
                                    <?= htmlspecialchars(
                                        $employer[
                                            "caloocan_area"
                                        ]
                                    ) ?>
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

                            N/A

                        <?php endif; ?>


                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Latitude
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $employer[
                                "latitude"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Longitude
                    </div>

                    <div class="info-value">

                        <?= htmlspecialchars(
                            $employer[
                                "longitude"
                            ]
                            ?: "N/A"
                        ) ?>

                    </div>

                </div>



                <?php if (
                    !empty(
                        $employer[
                            "latitude"
                        ]
                    ) &&
                    !empty(
                        $employer[
                            "longitude"
                        ]
                    )
                ): ?>


                    <iframe
                        id="map"

                        src="https://www.google.com/maps?q=<?= urlencode(
                            $employer[
                                "latitude"
                            ]
                        ) ?>,<?= urlencode(
                            $employer[
                                "longitude"
                            ]
                        ) ?>&z=17&output=embed"

                        loading="lazy"

                        allowfullscreen
                    ></iframe>


                <?php endif; ?>



                <!-- LOCATION ACTION -->

                <div class="action-buttons">


                    <form method="POST">

                        <textarea
                            name="admin_notes"
                            style="display:none;"
                        ><?= htmlspecialchars(
                            $adminNotes
                        ) ?></textarea>


                        <input
                            type="hidden"
                            name="action"
                            value="approve_location"
                        >


                        <button
                            type="submit"
                            class="btn btn-green"
                        >
                            ✓ Approve Location
                        </button>

                    </form>



                    <form method="POST">

                        <textarea
                            name="admin_notes"
                            style="display:none;"
                        ><?= htmlspecialchars(
                            $adminNotes
                        ) ?></textarea>


                        <input
                            type="hidden"
                            name="action"
                            value="reject_location"
                        >


                        <button
                            type="submit"
                            class="btn btn-red"
                        >
                            Reject Location
                        </button>

                    </form>


                </div>


            </div>



            <!-- =================================================
                 DOCUMENTS
            ================================================== -->

            <div class="card">

                <h2>
                    Verification Documents
                </h2>



                <!-- GOVERNMENT ID -->

                <div class="document">

                    <h3>
                        Government ID
                    </h3>


                    <?php

                    documentPreview(
                        $verification[
                            "government_id"
                        ]
                        ?? null,
                        "Government ID"
                    );

                    ?>

                </div>



                <!-- BARANGAY CERTIFICATE -->

                <div class="document">

                    <h3>
                        Barangay Certificate
                    </h3>


                    <?php

                    documentPreview(
                        $verification[
                            "barangay_certificate"
                        ]
                        ?? null,
                        "Barangay Certificate"
                    );

                    ?>

                </div>



                <!-- BUSINESS PERMIT -->

                <div class="document">

                    <h3>
                        Business Permit
                    </h3>


                    <?php

                    documentPreview(
                        $verification[
                            "business_permit"
                        ]
                        ?? null,
                        "Business Permit"
                    );

                    ?>

                </div>



                <!-- DOCUMENT ACTION -->

                <div class="action-buttons">


                    <form method="POST">

                        <textarea
                            name="admin_notes"
                            style="display:none;"
                        ><?= htmlspecialchars(
                            $adminNotes
                        ) ?></textarea>


                        <input
                            type="hidden"
                            name="action"
                            value="approve_documents"
                        >


                        <button
                            type="submit"
                            class="btn btn-green"
                        >
                            ✓ Approve Documents
                        </button>

                    </form>



                    <form method="POST">

                        <textarea
                            name="admin_notes"
                            style="display:none;"
                        ><?= htmlspecialchars(
                            $adminNotes
                        ) ?></textarea>


                        <input
                            type="hidden"
                            name="action"
                            value="reject_documents"
                        >


                        <button
                            type="submit"
                            class="btn btn-red"
                        >
                            Reject Documents
                        </button>

                    </form>


                </div>


            </div>


        </div>



        <!-- =================================================
             RIGHT COLUMN
        ================================================== -->

        <div>


            <!-- VERIFICATION STATUS -->

            <div class="card">

                <h2>
                    Verification Status
                </h2>



                <div class="info-row">

                    <div class="info-label">
                        Documents
                    </div>


                    <span
                        class="
                            status
                            <?= statusClass(
                                $documentStatus
                            ) ?>
                        "
                    >

                        <?= htmlspecialchars(
                            statusLabel(
                                $documentStatus
                            )
                        ) ?>

                    </span>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Location
                    </div>


                    <span
                        class="
                            status
                            <?= statusClass(
                                $locationStatus
                            ) ?>
                        "
                    >

                        <?= htmlspecialchars(
                            statusLabel(
                                $locationStatus
                            )
                        ) ?>

                    </span>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Admin Approval
                    </div>


                    <span
                        class="
                            status
                            <?= statusClass(
                                $adminStatus
                            ) ?>
                        "
                    >

                        <?= htmlspecialchars(
                            statusLabel(
                                $adminStatus
                            )
                        ) ?>

                    </span>

                </div>



                <div class="info-row">

                    <div class="info-label">
                        Employer Verification
                    </div>


                    <span
                        class="
                            status
                            <?= statusClass(
                                $employerStatus
                            ) ?>
                        "
                    >

                        <?= htmlspecialchars(
                            statusLabel(
                                $employerStatus
                            )
                        ) ?>

                    </span>

                </div>


            </div>



            <!-- =================================================
                 ADMIN NOTES
            ================================================== -->

            <div class="card">

                <h2>
                    Admin Notes
                </h2>


                <p class="note-help">

                    Add notes about the employer,
                    documents, location, or reason
                    for rejection.

                </p>


                <form method="POST">


                    <textarea
                        name="admin_notes"
                        class="admin-notes"
                        placeholder="Example: Business permit is valid. Barangay certificate matches the company address."
                    ><?= htmlspecialchars(
                        $adminNotes
                    ) ?></textarea>


                    <input
                        type="hidden"
                        name="action"
                        value="save_notes"
                    >


                    <div class="action-buttons">

                        <button
                            type="submit"
                            class="btn btn-blue"
                        >
                            Save Admin Notes
                        </button>

                    </div>


                </form>


            </div>



            <!-- =================================================
                 FINAL EMPLOYER APPROVAL
            ================================================== -->

            <div
                class="
                    card
                    final-approval
                "
            >

                <h2>
                    Employer Approval
                </h2>


                <p
                    style="
                        color:#666;
                        line-height:1.5;
                        margin-bottom:20px;
                    "
                >

                    Documents and location must
                    both be approved before the
                    employer can be approved.

                </p>



                <!-- APPROVE EMPLOYER -->

                <form
                    method="POST"
                    style="margin-bottom:10px;"
                >


                    <textarea
                        name="admin_notes"
                        style="display:none;"
                    ><?= htmlspecialchars(
                        $adminNotes
                    ) ?></textarea>


                    <input
                        type="hidden"
                        name="action"
                        value="approve_employer"
                    >


                    <button
                        type="submit"
                        class="btn btn-green"
                    >

                        ✓ APPROVE EMPLOYER

                    </button>


                </form>



                <!-- REJECT EMPLOYER -->

                <form method="POST">


                    <textarea
                        name="admin_notes"
                        style="display:none;"
                    ><?= htmlspecialchars(
                        $adminNotes
                    ) ?></textarea>


                    <input
                        type="hidden"
                        name="action"
                        value="reject_employer"
                    >


                    <button
                        type="submit"
                        class="btn btn-red"
                    >

                        REJECT EMPLOYER

                    </button>


                </form>


            </div>



            <!-- ACCOUNT -->

            <div class="card">

                <h2>
                    Account Information
                </h2>


                <div class="info-row">

                    <div class="info-label">
                        Account Status
                    </div>


                    <?php if (
                        $employer[
                            "is_active"
                        ]
                    ): ?>


                        <span
                            class="
                                status
                                approved
                            "
                        >
                            Active
                        </span>


                    <?php else: ?>


                        <span
                            class="
                                status
                                rejected
                            "
                        >
                            Inactive
                        </span>


                    <?php endif; ?>


                </div>



                <div class="info-row">

                    <div class="info-label">
                        Registered
                    </div>


                    <div class="info-value">

                        <?= date(
                            "F d, Y",
                            strtotime(
                                $employer[
                                    "created_at"
                                ]
                            )
                        ) ?>

                    </div>

                </div>


            </div>


        </div>


    </div>


</div>


</body>

</html>