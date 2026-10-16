<?php

session_start();

require_once "../config/database.php";
require_once "../utilities/NotificationService.php";
require_once "../utilities/JobMatchNotificationService.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$jobId =
    isset($_GET["id"])
        ? (int) $_GET["id"]
        : 0;

if ($jobId <= 0) {
    header("Location: jobs.php");
    exit;
}

$error = "";
$success = "";


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

    if (
        !in_array(
            $action,
            [
                "approve",
                "reject",
                "close",
                "pending"
            ],
            true
        )
    ) {
        $error =
            "Invalid job action.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    j.*,

                    e.user_id
                        AS employer_user_id,

                    e.employer_type,

                    e.verification_status
                        AS employer_verification_status

                FROM jobs j

                INNER JOIN employers e
                    ON j.employer_id = e.id

                WHERE j.id = ?

                LIMIT 1
            ");

            $stmt->execute([$jobId]);

            $jobCheck =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$jobCheck) {
                throw new Exception(
                    "Job not found."
                );
            }

            $oldStatus =
                $jobCheck["status"];


            /*
            |--------------------------------------------------------------------------
            | APPROVE
            |--------------------------------------------------------------------------
            */

            if ($action === "approve") {
                if (
                    $jobCheck[
                        "employer_verification_status"
                    ] !== "approved"
                ) {
                    throw new Exception(
                        "Employer must be verified first."
                    );
                }

                if (
                    empty($jobCheck["address"]) ||
                    empty($jobCheck["barangay"]) ||
                    $jobCheck["latitude"] === null ||
                    $jobCheck["longtitude"] === null
                ) {
                    throw new Exception(
                        "Job location is incomplete."
                    );
                }

                if (
                    !in_array(
                        $jobCheck["caloocan_area"],
                        [
                            "north",
                            "south"
                        ],
                        true
                    )
                ) {
                    throw new Exception(
                        "Invalid Caloocan area."
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | SOURCE VALIDATION
                |--------------------------------------------------------------------------
                */

                $source =
                    $jobCheck["job_source"]
                    ?? "private_employer";

                $employerType =
                    $jobCheck["employer_type"]
                    ?? "private";

                if (
                    $source === "lgu" &&
                    $employerType !== "lgu"
                ) {
                    throw new Exception(
                        "LGU job source can only be used by a verified LGU employer."
                    );
                }

                if (
                    $source === "private_employer" &&
                    $employerType !== "private"
                ) {
                    throw new Exception(
                        "This employer is registered as LGU. The job source must be corrected before approval."
                    );
                }

                if (
                    $source === "job_fair" &&
                    trim(
                        (string) (
                            $jobCheck[
                                "source_name"
                            ]
                            ?? ""
                        )
                    ) === ""
                ) {
                    throw new Exception(
                        "Job Fair postings must have a source name."
                    );
                }

                if (
                    !empty(
                        $jobCheck["source_url"]
                    ) &&
                    !filter_var(
                        $jobCheck["source_url"],
                        FILTER_VALIDATE_URL
                    )
                ) {
                    throw new Exception(
                        "The job source URL is invalid."
                    );
                }

                $newStatus =
                    "approved";
            }

            elseif ($action === "reject") {
                $newStatus =
                    "rejected";
            }

            elseif ($action === "close") {
                $newStatus =
                    "closed";
            }

            else {
                $newStatus =
                    "pending";
            }


            /*
            |--------------------------------------------------------------------------
            | UPDATE
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE jobs
                SET
                    status = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");

            $stmt->execute([
                $newStatus,
                $jobId
            ]);


            /*
            |--------------------------------------------------------------------------
            | EMPLOYER NOTIFICATION
            |--------------------------------------------------------------------------
            */

            if ($oldStatus !== $newStatus) {
                try {
                    if (
                        $newStatus === "approved"
                    ) {
                        $title =
                            "Job Posting Approved";

                        $message =
                            'Your job posting "'
                            . $jobCheck["job_title"]
                            . '" has been approved and is now visible to applicants.';
                    }

                    elseif (
                        $newStatus === "rejected"
                    ) {
                        $title =
                            "Job Posting Rejected";

                        $message =
                            'Your job posting "'
                            . $jobCheck["job_title"]
                            . '" was rejected by the administrator.';
                    }

                    elseif (
                        $newStatus === "closed"
                    ) {
                        $title =
                            "Job Posting Closed";

                        $message =
                            'Your job posting "'
                            . $jobCheck["job_title"]
                            . '" has been closed.';
                    }

                    else {
                        $title =
                            "Job Posting Under Review";

                        $message =
                            'Your job posting "'
                            . $jobCheck["job_title"]
                            . '" was moved back to pending review.';
                    }

                    NotificationService::create(
                        $pdo,
                        (int)
                            $jobCheck[
                                "employer_user_id"
                            ],
                        $title,
                        $message,
                        "job_status",
                        $jobId
                    );

                } catch (Throwable $notificationError) {
                    error_log(
                        "Employer job notification error: "
                        . $notificationError->getMessage()
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | MATCHING JOB NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            $matchedApplicantsNotified = 0;

            if (
                $newStatus === "approved" &&
                $oldStatus !== "approved"
            ) {
                try {
                    $matchedApplicantsNotified =
                        JobMatchNotificationService::notifyForApprovedJob(
                            $pdo,
                            $jobId,
                            60
                        );

                } catch (Throwable $matchError) {
                    error_log(
                        "Job match notification error: "
                        . $matchError->getMessage()
                    );
                }
            }


            $success =
                "Job status updated to "
                . ucfirst($newStatus)
                . ".";

            if (
                $newStatus === "approved" &&
                $matchedApplicantsNotified > 0
            ) {
                $success .=
                    " "
                    . $matchedApplicantsNotified
                    . " matching applicant(s) were notified.";
            }

        } catch (Throwable $e) {
            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| JOB DETAILS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        j.*,

        e.id AS employer_id,
        e.user_id AS employer_user_id,
        e.company_name,
        e.employer_type,
        e.company_description,
        e.contact_person,
        e.contact_number,
        e.address AS employer_address,
        e.barangay AS employer_barangay,
        e.caloocan_area AS employer_area,

        e.verification_status
            AS employer_verification_status,

        u.email
            AS employer_email,

        (
            SELECT COUNT(*)
            FROM applications a
            WHERE a.job_id = j.id
        ) AS applicant_count

    FROM jobs j

    INNER JOIN employers e
        ON j.employer_id = e.id

    INNER JOIN users u
        ON e.user_id = u.id

    WHERE j.id = ?

    LIMIT 1
");

$stmt->execute([$jobId]);

$job =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("Job not found.");
}


function jobStatusClass(
    string $status
): string {
    return match ($status) {
        "approved" =>
            "approved",

        "rejected" =>
            "rejected",

        "closed" =>
            "closed",

        default =>
            "pending"
    };
}


function jobSourceLabel(
    ?string $source
): string {
    return match ($source) {
        "lgu" =>
            "LGU Opportunity",

        "job_fair" =>
            "Job Fair",

        default =>
            "Private Employer"
    };
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>
    View Job - Admin
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
    margin-top: 5px;
    line-height: 1.6;
    white-space: pre-line;
}

.status,
.badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: bold;
}

.pending {
    background: #fef3c7;
    color: #92400e;
}

.approved {
    background: #dcfce7;
    color: #166534;
}

.rejected {
    background: #fee2e2;
    color: #991b1b;
}

.closed {
    background: #e5e7eb;
    color: #374151;
}

.source {
    background: #dbeafe;
    color: #1e40af;
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

.actions {
    display: flex;
    gap: 7px;
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

.green {
    background: #16a34a;
}

.red {
    background: #dc2626;
}

.gray {
    background: #64748b;
}

.orange {
    background: #d97706;
}

.blue {
    background: #2563eb;
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
            $job["job_title"]
        ) ?>
    </h1>

    <p>
        Review job posting.
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
                    Job Information
                </h2>

                <div class="info">

                    <div class="label">
                        Job Source
                    </div>

                    <div class="value">

                        <span class="badge source">
                            <?= htmlspecialchars(
                                jobSourceLabel(
                                    $job["job_source"]
                                    ?? null
                                )
                            ) ?>
                        </span>

                    </div>

                </div>


                <?php if (
                    !empty(
                        $job["source_name"]
                    )
                ): ?>

                    <div class="info">

                        <div class="label">
                            Source Name
                        </div>

                        <div class="value">
                            <?= htmlspecialchars(
                                $job["source_name"]
                            ) ?>
                        </div>

                    </div>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $job["source_url"]
                    )
                ): ?>

                    <div class="info">

                        <div class="label">
                            Source URL
                        </div>

                        <div class="value">

                            <a
                                href="<?= htmlspecialchars(
                                    $job["source_url"]
                                ) ?>"
                                target="_blank"
                            >
                                <?= htmlspecialchars(
                                    $job["source_url"]
                                ) ?>
                            </a>

                        </div>

                    </div>

                <?php endif; ?>


                <div class="info">

                    <div class="label">
                        Description
                    </div>

                    <div class="value">
                        <?= htmlspecialchars(
                            $job["job_description"]
                            ?: $job["description"]
                        ) ?>
                    </div>

                </div>


                <div class="info">

                    <div class="label">
                        Required Skills
                    </div>

                    <div class="value">
                        <?= htmlspecialchars(
                            $job["required_skills"]
                            ?: "None"
                        ) ?>
                    </div>

                </div>


                <div class="info">

                    <div class="label">
                        Education Requirement
                    </div>

                    <div class="value">

                        <?= htmlspecialchars(
                            $job[
                                "educational_requirement"
                            ]
                            ?: "None"
                        ) ?>

                        <?php if (
                            !empty(
                                $job["required_degree"]
                            )
                        ): ?>

                            <br>
                            Required Degree:
                            <?= htmlspecialchars(
                                $job["required_degree"]
                            ) ?>

                        <?php endif; ?>

                        <?php if (
                            !empty(
                                $job[
                                    "degree_classification"
                                ]
                            )
                        ): ?>

                            <br>
                            Classification:
                            <?= htmlspecialchars(
                                $job[
                                    "degree_classification"
                                ]
                            ) ?>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="info">

                    <div class="label">
                        TESDA / NC
                    </div>

                    <div class="value">

                        <?php if (
                            !empty(
                                $job[
                                    "tesda_qualification"
                                ]
                            )
                        ): ?>

                            <?= htmlspecialchars(
                                $job[
                                    "tesda_qualification"
                                ]
                            ) ?>

                            <?php if (
                                !empty(
                                    $job[
                                        "required_nc_level"
                                    ]
                                )
                            ): ?>

                                -
                                <?= htmlspecialchars(
                                    $job[
                                        "required_nc_level"
                                    ]
                                ) ?>

                            <?php endif; ?>

                        <?php else: ?>

                            None

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <div class="card">

                <h2>
                    Location
                </h2>

                <p>
                    <strong>
                        Address:
                    </strong>

                    <?= htmlspecialchars(
                        $job["address"]
                        ?: "N/A"
                    ) ?>
                </p>

                <p>
                    <strong>
                        Barangay:
                    </strong>

                    <?= htmlspecialchars(
                        $job["barangay"]
                        ?: "N/A"
                    ) ?>
                </p>

                <p>
                    <strong>
                        Area:
                    </strong>

                    <?= $job["caloocan_area"]
                        ? ucfirst(
                            htmlspecialchars(
                                $job[
                                    "caloocan_area"
                                ]
                            )
                        )
                        . " Caloocan"
                        : "N/A"
                    ?>
                </p>

            </div>

        </div>


        <div>

            <div class="card">

                <h2>
                    Status
                </h2>

                <span
                    class="status <?= jobStatusClass(
                        $job["status"]
                    ) ?>"
                >
                    <?= ucfirst(
                        htmlspecialchars(
                            $job["status"]
                        )
                    ) ?>
                </span>

                <br><br>

                Applicants:
                <strong>
                    <?= (int)
                        $job["applicant_count"]
                    ?>
                </strong>

            </div>


            <div class="card">

                <h2>
                    Employer
                </h2>

                <p>
                    <strong>
                        Organization:
                    </strong>

                    <?= htmlspecialchars(
                        $job["company_name"]
                    ) ?>
                </p>

                <p>
                    <strong>
                        Employer Type:
                    </strong>

                    <?= (
                        $job["employer_type"]
                        ?? "private"
                    ) === "lgu"
                        ? "LGU"
                        : "Private Employer"
                    ?>
                </p>

                <p>
                    <strong>
                        Verification:
                    </strong>

                    <?= ucfirst(
                        htmlspecialchars(
                            $job[
                                "employer_verification_status"
                            ]
                        )
                    ) ?>
                </p>

            </div>


            <div class="card">

                <h2>
                    Admin Actions
                </h2>

                <form method="POST">

                    <div class="actions">

                        <?php if (
                            $job["status"]
                            !== "approved"
                        ): ?>

                            <button
                                name="action"
                                value="approve"
                                class="btn green"
                            >
                                Approve
                            </button>

                        <?php endif; ?>


                        <?php if (
                            $job["status"]
                            !== "rejected"
                        ): ?>

                            <button
                                name="action"
                                value="reject"
                                class="btn red"
                            >
                                Reject
                            </button>

                        <?php endif; ?>


                        <?php if (
                            $job["status"]
                            !== "closed"
                        ): ?>

                            <button
                                name="action"
                                value="close"
                                class="btn gray"
                            >
                                Close
                            </button>

                        <?php endif; ?>


                        <?php if (
                            $job["status"]
                            !== "pending"
                        ): ?>

                            <button
                                name="action"
                                value="pending"
                                class="btn orange"
                            >
                                Set Pending
                            </button>

                        <?php endif; ?>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

</body>
</html>