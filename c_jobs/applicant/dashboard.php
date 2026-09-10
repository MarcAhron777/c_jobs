<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "applicant") {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION["user_id"];

/*
|--------------------------------------------------------------------------
| Get applicant information
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT 
        a.id,
        a.first_name,
        a.last_name,
        a.phone,
        a.address,
        a.bio
    FROM applicants a
    WHERE a.user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$applicant = $stmt->fetch();

if (!$applicant) {
    die("Applicant profile not found.");
}

$applicantId = $applicant["id"];

/*
|--------------------------------------------------------------------------
| Count applications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM applications
    WHERE applicant_id = ?
");

$stmt->execute([$applicantId]);

$totalApplications = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Count active applications
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM applications
    WHERE applicant_id = ?
    AND status IN ('pending', 'reviewed', 'shortlisted')
");

$stmt->execute([$applicantId]);

$activeApplications = $stmt->fetchColumn();

/*
|--------------------------------------------------------------------------
| Get latest resume
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM resumes
    WHERE applicant_id = ?
    ORDER BY uploaded_at DESC
    LIMIT 1
");

$stmt->execute([$applicantId]);

$resume = $stmt->fetch();

/*
|--------------------------------------------------------------------------
| Get recent applications
|--------------------------------------------------------------------------
*/

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
    INNER JOIN jobs j 
        ON ap.job_id = j.id
    INNER JOIN employers e 
        ON j.employer_id = e.id
    WHERE ap.applicant_id = ?
    ORDER BY ap.applied_at DESC
    LIMIT 5
");

$stmt->execute([$applicantId]);

$recentApplications = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Applicant Dashboard - Caloocan Job Portal</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #333;
        }

        .navbar {
            background: #ffffff;
            border-bottom: 1px solid #ddd;
            padding: 16px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
        }

        .logout {
            color: #dc2626 !important;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome {
            margin-bottom: 30px;
        }

        .welcome h1 {
            margin-bottom: 8px;
        }

        .welcome p {
            color: #666;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            color: #2563eb;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .card h2 {
            margin-bottom: 20px;
        }

        .application {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .application:last-child {
            border-bottom: none;
        }

        .application h3 {
            margin-bottom: 5px;
        }

        .company {
            color: #666;
            margin-bottom: 5px;
        }

        .details {
            color: #777;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending {
            background: #fef3c7;
            color: #92400e;
        }

        .reviewed {
            background: #dbeafe;
            color: #1e40af;
        }

        .shortlisted {
            background: #dcfce7;
            color: #166534;
        }

        .rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .hired {
            background: #d1fae5;
            color: #065f46;
        }

        .profile-info p {
            margin-bottom: 10px;
        }

        .profile-info strong {
            display: inline-block;
            width: 100px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 10px;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .resume-status {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            margin-bottom: 10px;
        }

        @media (max-width: 768px) {

            .stats {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .navbar {
                padding: 15px 20px;
            }

            .nav-links {
                gap: 10px;
            }

        }

    </style>

</head>

<body>

<nav class="navbar">

    <div class="logo">
        JobPortal
    </div>

    <div class="nav-links">

        <a href="../index.php">Home</a>

        <a href="../jobs/index.php">Find Jobs</a>

        <a href="profile.php">Profile</a>

        <a href="resume.php">Resume</a>

        <a href="applications.php">Applications</a>

        <a href="../logout.php" class="logout">Logout</a>

    </div>

</nav>


<div class="container">

    <div class="welcome">

        <h1>
            Welcome, <?= htmlspecialchars($applicant["first_name"]) ?>!
        </h1>

        <p>
            Manage your profile, resume, and job applications.
        </p>

    </div>


    <!-- Statistics -->

    <div class="stats">

        <div class="stat-card">

            <h3>Total Applications</h3>

            <div class="stat-number">
                <?= $totalApplications ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>Active Applications</h3>

            <div class="stat-number">
                <?= $activeApplications ?>
            </div>

        </div>


        <div class="stat-card">

            <h3>Resume</h3>

            <div class="stat-number">

                <?= $resume ? "✓" : "!" ?>

            </div>

        </div>

    </div>


    <div class="content-grid">

        <!-- Recent Applications -->

        <div class="card">

            <h2>Recent Applications</h2>

            <?php if (count($recentApplications) > 0): ?>

                <?php foreach ($recentApplications as $application): ?>

                    <div class="application">

                        <h3>
                            <?= htmlspecialchars($application["job_title"]) ?>
                        </h3>

                        <div class="company">
                            <?= htmlspecialchars($application["company_name"]) ?>
                        </div>

                        <div class="details">

                            <?= htmlspecialchars($application["location"] ?? "Not specified") ?>

                            • 

                            <?= htmlspecialchars($application["employment_type"]) ?>

                        </div>

                        <span class="status <?= htmlspecialchars($application["status"]) ?>">

                            <?= ucfirst(htmlspecialchars($application["status"])) ?>

                        </span>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <p>
                    You haven't applied for any jobs yet.
                </p>

                <a href="../jobs/index.php" class="btn">
                    Find Jobs
                </a>

            <?php endif; ?>

        </div>


        <!-- Profile -->

        <div>

            <div class="card">

                <h2>My Profile</h2>

                <div class="profile-info">

                    <p>
                        <strong>Name:</strong>

                        <?= htmlspecialchars(
                            $applicant["first_name"] . " " . $applicant["last_name"]
                        ) ?>
                    </p>

                    <p>
                        <strong>Phone:</strong>

                        <?= htmlspecialchars($applicant["phone"] ?? "Not provided") ?>
                    </p>

                    <p>
                        <strong>Address:</strong>

                        <?= htmlspecialchars($applicant["address"] ?? "Not provided") ?>
                    </p>

                </div>

                <a href="profile.php" class="btn">
                    Edit Profile
                </a>

            </div>


            <div class="card">

                <h2>My Resume</h2>

                <?php if ($resume): ?>

                    <div class="resume-status">

                        <strong>
                            <?= htmlspecialchars($resume["file_name"]) ?>
                        </strong>

                        <br>

                        Uploaded:
                        <?= date("M d, Y", strtotime($resume["uploaded_at"])) ?>

                    </div>

                <?php else: ?>

                    <div class="resume-status">
                        No resume uploaded yet.
                    </div>

                <?php endif; ?>

                <a href="resume.php" class="btn">
                    <?= $resume ? "Manage Resume" : "Upload Resume" ?>
                </a>

            </div>

        </div>

    </div>

</div>

</body>

</html>