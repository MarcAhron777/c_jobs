<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "applicant") {
    header("Location: ../login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$stmt = $pdo->prepare("
    SELECT id, first_name, last_name
    FROM applicants
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$userId]);
$applicant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$applicant) {
    die("Applicant profile not found.");
}

$applicantId = (int) $applicant["id"];

$stmt = $pdo->prepare("
    SELECT
        ap.id,
        ap.status,
        ap.applied_at,
        j.job_title,
        j.location,
        j.employment_type,
        e.company_name
    FROM applications ap
    INNER JOIN jobs j ON ap.job_id = j.id
    INNER JOIN employers e ON j.employer_id = e.id
    WHERE ap.applicant_id = ?
    ORDER BY ap.applied_at DESC
");
$stmt->execute([$applicantId]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - Caloocan Job Portal</title>

    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f5f7fb; color: #333; }
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px 50px; }
        .page-heading { margin-bottom: 25px; }
        .page-heading h1 { margin-bottom: 8px; }
        .page-heading p { color: #666; }
        .application { background: white; padding: 25px; border-radius: 10px; margin-bottom: 15px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .application h2 { margin-bottom: 8px; }
        .company { color: #555; margin-bottom: 10px; }
        .details { color: #777; font-size: 14px; margin-bottom: 15px; line-height: 1.6; }
        .status { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .pending { background: #fef3c7; color: #92400e; }
        .reviewed { background: #dbeafe; color: #1e40af; }
        .shortlisted { background: #dcfce7; color: #166534; }
        .rejected { background: #fee2e2; color: #991b1b; }
        .hired { background: #d1fae5; color: #065f46; }
        .view { display: inline-flex; align-items: center; gap: 7px; margin-top: 15px; padding: 9px 14px; background: #2563eb; color: white; text-decoration: none; border-radius: 6px; }
        .view:hover { background: #1d4ed8; }
        .empty { background: white; padding: 40px; text-align: center; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
        @media (max-width: 768px) { .container { margin: 25px auto; } }
    </style>
</head>
<body>

<?php
require __DIR__ . "/includes/header.php";
?>

<div class="container">
    <div class="page-heading">
        <h1>My Applications</h1>
        <p>Track the status of your job applications.</p>
    </div>

    <?php if (count($applications) > 0): ?>
        <?php foreach ($applications as $application): ?>
            <div class="application">
                <h2><?= htmlspecialchars($application["job_title"]) ?></h2>

                <div class="company">
                    <?= htmlspecialchars($application["company_name"]) ?>
                </div>

                <div class="details">
                    <i class="fa-solid fa-location-dot"></i>
                    <?= htmlspecialchars($application["location"] ?? "Not specified") ?>
                    &nbsp; • &nbsp;
                    <i class="fa-solid fa-briefcase"></i>
                    <?= htmlspecialchars($application["employment_type"]) ?>
                    &nbsp; • &nbsp;
                    Applied: <?= date("M d, Y", strtotime($application["applied_at"])) ?>
                </div>

                <span class="status <?= htmlspecialchars($application["status"]) ?>">
                    <?= ucfirst(htmlspecialchars($application["status"])) ?>
                </span>

                <br>

                <a href="application.php?id=<?= (int) $application["id"] ?>" class="view">
                    <i class="fa-solid fa-eye"></i>
                    View Application
                </a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty">
            <h2>No Applications Yet</h2>
            <p style="margin:10px 0 20px;color:#666;">Start looking for your next opportunity.</p>
            <a href="../jobs/index.php" class="view">
                <i class="fa-solid fa-magnifying-glass"></i>
                Find Jobs
            </a>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
