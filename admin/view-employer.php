<?php

session_start();

require_once "../config/database.php";
require_once "../utilities/NotificationService.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

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

$stmt->execute([$employerId]);

$employer =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employer) {
    die("Employer not found.");
}

$employerUserId =
    (int) $employer["user_id"];

$employerType =
    $employer["employer_type"]
    ?? "private";


/*
|--------------------------------------------------------------------------
| VERIFICATION
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
| ACTION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {
    $action =
        $_POST["action"]
        ?? "";

    $adminNotes =
        trim(
            $_POST["admin_notes"]
            ?? ""
        );

    try {
        if (!$verification) {
            throw new Exception(
                "Verification record not found."
            );
        }


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
                "Admin notes saved.";
        }


        elseif (
            $action === "approve_documents"
        ) {
            if (
                empty(
                    $verification["government_id"]
                )
            ) {
                throw new Exception(
                    "Government ID is missing."
                );
            }

            if (
                empty(
                    $verification[
                        "barangay_certificate"
                    ]
                )
            ) {
                throw new Exception(
                    "Barangay Certificate is missing."
                );
            }

            if (
                $employerType === "private" &&
                empty(
                    $verification["business_permit"]
                )
            ) {
                throw new Exception(
                    "Business Permit is required for Private Employers."
                );
            }

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

            NotificationService::create(
                $pdo,
                $employerUserId,
                "Verification Documents Approved",
                "Your verification documents have been approved.",
                "verification_documents",
                $employerId
            );

            $success =
                "Verification documents approved.";
        }


        elseif (
            $action === "reject_documents"
        ) {
            if ($adminNotes === "") {
                throw new Exception(
                    "Please provide a rejection reason."
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

            NotificationService::create(
                $pdo,
                $employerUserId,
                "Verification Documents Rejected",
                "Your verification documents were rejected. Reason: "
                    . $adminNotes,
                "verification_documents",
                $employerId
            );

            $success =
                "Verification documents rejected.";
        }


        elseif (
            $action === "approve_location"
        ) {
            if (
                empty($employer["address"]) ||
                empty($employer["barangay"]) ||
                empty($employer["caloocan_area"])
            ) {
                throw new Exception(
                    "Employer location is incomplete."
                );
            }

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

            NotificationService::create(
                $pdo,
                $employerUserId,
                "Employer Location Approved",
                "Your employer location has been approved.",
                "verification_location",
                $employerId
            );

            $success =
                "Employer location approved.";
        }


        elseif (
            $action === "reject_location"
        ) {
            if ($adminNotes === "") {
                throw new Exception(
                    "Please provide a rejection reason."
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

            NotificationService::create(
                $pdo,
                $employerUserId,
                "Employer Location Rejected",
                "Your employer location was rejected. Reason: "
                    . $adminNotes,
                "verification_location",
                $employerId
            );

            $success =
                "Employer location rejected.";
        }


        elseif (
            $action === "approve_employer"
        ) {
            if (
                $verification["document_status"]
                !== "passed"
            ) {
                throw new Exception(
                    "Approve the documents first."
                );
            }

            if (
                $verification["location_status"]
                !== "passed"
            ) {
                throw new Exception(
                    "Approve the location first."
                );
            }

            $pdo->beginTransaction();

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

            $stmt = $pdo->prepare("
                UPDATE employers
                SET verification_status = 'approved'
                WHERE id = ?
            ");

            $stmt->execute([$employerId]);

            $pdo->commit();

            try {
                NotificationService::create(
                    $pdo,
                    $employerUserId,
                    "Employer Account Approved",
                    "Your employer account has been verified and approved. You can now post jobs.",
                    "employer_verification",
                    $employerId
                );
            } catch (Throwable $e) {
                error_log(
                    "Employer notification error: "
                    . $e->getMessage()
                );
            }

            $success =
                "Employer successfully approved.";
        }


        elseif (
            $action === "reject_employer"
        ) {
            if ($adminNotes === "") {
                throw new Exception(
                    "Please provide a rejection reason."
                );
            }

            $pdo->beginTransaction();

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

            $stmt->execute([$employerId]);

            $pdo->commit();

            try {
                NotificationService::create(
                    $pdo,
                    $employerUserId,
                    "Employer Account Rejected",
                    "Your employer verification was rejected. Reason: "
                        . $adminNotes,
                    "employer_verification",
                    $employerId
                );
            } catch (Throwable $e) {
                error_log(
                    "Employer notification error: "
                    . $e->getMessage()
                );
            }

            $success =
                "Employer verification rejected.";
        }

        else {
            throw new Exception(
                "Invalid action."
            );
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $error =
            $e->getMessage();
    }


    /*
    |--------------------------------------------------------------------------
    | RELOAD
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

    $stmt->execute([$employerId]);

    $employer =
        $stmt->fetch(PDO::FETCH_ASSOC);

    $employerType =
        $employer["employer_type"]
        ?? "private";

    $stmt = $pdo->prepare("
        SELECT *
        FROM employer_verifications
        WHERE employer_id = ?
        LIMIT 1
    ");

    $stmt->execute([$employerId]);

    $verification =
        $stmt->fetch(PDO::FETCH_ASSOC);
}


function statusClass(?string $status): string
{
    return match ($status) {
        "approved",
        "passed" => "approved",

        "pending",
        "processing" => "pending",

        "rejected",
        "failed" => "rejected",

        default => "unverified"
    };
}


function statusLabel(?string $status): string
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


function documentPreview(
    ?string $file,
    string $label
): void {
    if (!$file) {
        echo '<div class="missing">No file uploaded.</div>';
        return;
    }

    $url =
        "../uploads/verification/"
        . rawurlencode($file);

    $extension =
        strtolower(
            pathinfo(
                $file,
                PATHINFO_EXTENSION
            )
        );

    if (
        in_array(
            $extension,
            ["jpg", "jpeg", "png"],
            true
        )
    ) {
        echo '<img class="document-image" src="'
            . htmlspecialchars($url)
            . '" alt="'
            . htmlspecialchars($label)
            . '">';
    } elseif ($extension === "pdf") {
        echo '<iframe class="document-pdf" src="'
            . htmlspecialchars($url)
            . '"></iframe>';
    }

    echo '<br><a class="document-link" target="_blank" href="'
        . htmlspecialchars($url)
        . '">Open Document</a>';
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    View Employer - Admin
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f5f7fb;
    color: #222;
}

.container {
    max-width: 1200px;
    margin: 35px auto;
    padding: 0 20px 50px;
}

.grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
}

.card {
    padding: 23px;
    margin-bottom: 20px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
}

.info {
    padding: 11px 0;
    border-bottom: 1px solid #eee;
}

.label {
    color: #64748b;
    font-size: 12px;
}

.value {
    margin-top: 4px;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.approved {
    background: #dcfce7;
    color: #166534;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.unverified {
    background: #e5e7eb;
    color: #374151;
}

.document-image,
.document-pdf {
    width: 100%;
    max-height: 400px;
    border: 1px solid #ddd;
}

.document-pdf {
    height: 350px;
}

.document-link {
    color: #2563eb;
}

.document {
    margin-bottom: 25px;
}

textarea {
    width: 100%;
    min-height: 120px;
    padding: 11px;
}

.actions {
    display: flex;
    gap: 7px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.btn {
    padding: 9px 12px;
    border: none;
    border-radius: 5px;
    color: #ffffff;
    cursor: pointer;
    text-decoration: none;
}

.blue {
    background: #2563eb;
}

.green {
    background: #16a34a;
}

.red {
    background: #dc2626;
}

.gray {
    background: #64748b;
}

.alert {
    padding: 14px;
    margin-bottom: 15px;
    border-radius: 7px;
}

.success {
    background: #dcfce7;
    color: #166534;
}

.error {
    background: #fee2e2;
    color: #991b1b;
}

@media (max-width: 800px) {
    .grid {
        grid-template-columns: 1fr;
    }
}

</style>

</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>


<div class="container">

    <h1>
        <?= htmlspecialchars(
            $employer["company_name"]
        ) ?>
    </h1>

    <p>
        Employer verification review.
    </p>

    <br>


    <?php if ($success): ?>

        <div class="alert success">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>


    <?php if ($error): ?>

        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <div class="grid">

        <div>

            <div class="card">

                <h2>
                    Employer Information
                </h2>

                <div class="info">
                    <div class="label">
                        Employer Type
                    </div>
                    <div class="value">
                        <?= $employerType === "lgu"
                            ? "Local Government Unit (LGU)"
                            : "Private Employer"
                        ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">
                        Email
                    </div>
                    <div class="value">
                        <?= htmlspecialchars(
                            $employer["email"]
                        ) ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">
                        Contact Person
                    </div>
                    <div class="value">
                        <?= htmlspecialchars(
                            $employer["contact_person"]
                            ?: "N/A"
                        ) ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">
                        Contact Number
                    </div>
                    <div class="value">
                        <?= htmlspecialchars(
                            $employer["contact_number"]
                            ?: "N/A"
                        ) ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">
                        Address
                    </div>
                    <div class="value">
                        <?= htmlspecialchars(
                            $employer["address"]
                            ?: "N/A"
                        ) ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">
                        Barangay / Area
                    </div>
                    <div class="value">
                        <?= htmlspecialchars(
                            $employer["barangay"]
                            ?: "N/A"
                        ) ?>

                        <?php if (
                            $employer["caloocan_area"]
                        ): ?>

                            -
                            <?= ucfirst(
                                htmlspecialchars(
                                    $employer[
                                        "caloocan_area"
                                    ]
                                )
                            ) ?>
                            Caloocan

                        <?php endif; ?>
                    </div>
                </div>

            </div>


            <div class="card">

                <h2>
                    Verification Documents
                </h2>

                <div class="document">

                    <h3>
                        Government ID
                    </h3>

                    <?php
                    documentPreview(
                        $verification["government_id"]
                        ?? null,
                        "Government ID"
                    );
                    ?>

                </div>


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


                <div class="document">

                    <h3>
                        Business Permit
                    </h3>

                    <?php if (
                        $employerType === "lgu" &&
                        empty(
                            $verification[
                                "business_permit"
                            ]
                            ?? null
                        )
                    ): ?>

                        <div class="missing">
                            Not required for LGU employer.
                        </div>

                    <?php else: ?>

                        <?php
                        documentPreview(
                            $verification[
                                "business_permit"
                            ]
                            ?? null,
                            "Business Permit"
                        );
                        ?>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <div>

            <div class="card">

                <h2>
                    Status
                </h2>

                <?php
                $documentStatus =
                    $verification[
                        "document_status"
                    ]
                    ?? null;

                $locationStatus =
                    $verification[
                        "location_status"
                    ]
                    ?? null;

                $adminStatus =
                    $verification[
                        "admin_status"
                    ]
                    ?? null;
                ?>

                <p>
                    Documents:
                    <span
                        class="status <?= statusClass(
                            $documentStatus
                        ) ?>"
                    >
                        <?= htmlspecialchars(
                            statusLabel(
                                $documentStatus
                            )
                        ) ?>
                    </span>
                </p>

                <br>

                <p>
                    Location:
                    <span
                        class="status <?= statusClass(
                            $locationStatus
                        ) ?>"
                    >
                        <?= htmlspecialchars(
                            statusLabel(
                                $locationStatus
                            )
                        ) ?>
                    </span>
                </p>

                <br>

                <p>
                    Admin:
                    <span
                        class="status <?= statusClass(
                            $adminStatus
                        ) ?>"
                    >
                        <?= htmlspecialchars(
                            statusLabel(
                                $adminStatus
                            )
                        ) ?>
                    </span>
                </p>

            </div>


            <div class="card">

                <h2>
                    Admin Review
                </h2>

                <form method="POST">

                    <textarea
                        name="admin_notes"
                        placeholder="Admin notes / rejection reason"
                    ><?= htmlspecialchars(
                        $verification["admin_notes"]
                        ?? ""
                    ) ?></textarea>


                    <div class="actions">

                        <button
                            name="action"
                            value="save_notes"
                            class="btn blue"
                        >
                            Save Notes
                        </button>

                    </div>


                    <div class="actions">

                        <button
                            name="action"
                            value="approve_documents"
                            class="btn green"
                        >
                            Approve Documents
                        </button>

                        <button
                            name="action"
                            value="reject_documents"
                            class="btn red"
                        >
                            Reject Documents
                        </button>

                    </div>


                    <div class="actions">

                        <button
                            name="action"
                            value="approve_location"
                            class="btn green"
                        >
                            Approve Location
                        </button>

                        <button
                            name="action"
                            value="reject_location"
                            class="btn red"
                        >
                            Reject Location
                        </button>

                    </div>


                    <div class="actions">

                        <button
                            name="action"
                            value="approve_employer"
                            class="btn green"
                        >
                            Approve Employer
                        </button>

                        <button
                            name="action"
                            value="reject_employer"
                            class="btn red"
                        >
                            Reject Employer
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

</body>
</html>