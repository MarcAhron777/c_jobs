<?php

session_start();

require_once "../config/database.php";
require_once "../services/ResumeParser.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "applicant") {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION["user_id"];

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| Get Applicant
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id
    FROM applicants
    WHERE user_id = ?
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
| Upload Resume
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!isset($_FILES["resume"])) {

        $error = "Please select a resume.";

    } else {

        $file = $_FILES["resume"];


        /*
        |--------------------------------------------------------------------------
        | Check Upload Error
        |--------------------------------------------------------------------------
        */

        if ($file["error"] !== UPLOAD_ERR_OK) {

            $error = "There was an error uploading your resume.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | Maximum File Size
            |--------------------------------------------------------------------------
            */

            $maxSize = 5 * 1024 * 1024; // 5MB

            if ($file["size"] > $maxSize) {

                $error = "Resume must not exceed 5 MB.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Get File Information
                |--------------------------------------------------------------------------
                */

                $originalName = $file["name"];

                $extension = strtolower(
                    pathinfo($originalName, PATHINFO_EXTENSION)
                );


                /*
                |--------------------------------------------------------------------------
                | PDF Only
                |--------------------------------------------------------------------------
                */

                if ($extension !== "pdf") {

                    $error = "Only PDF resumes are currently supported for resume matching.";

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Upload Directory
                    |--------------------------------------------------------------------------
                    */

                    $uploadDirectory = "../uploads/resumes/";


                    if (!is_dir($uploadDirectory)) {

                        if (!mkdir($uploadDirectory, 0755, true)) {

                            $error = "Failed to create resume upload directory.";

                        }

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Continue Upload
                    |--------------------------------------------------------------------------
                    */

                    if ($error === "") {

                        /*
                        |--------------------------------------------------------------------------
                        | Generate Unique Filename
                        |--------------------------------------------------------------------------
                        */

                        $newFileName =
                            "resume_" .
                            $applicantId .
                            "_" .
                            time() .
                            "_" .
                            bin2hex(random_bytes(4)) .
                            "." .
                            $extension;


                        $filePath =
                            $uploadDirectory .
                            $newFileName;


                        /*
                        |--------------------------------------------------------------------------
                        | Move Uploaded File
                        |--------------------------------------------------------------------------
                        */

                        if (move_uploaded_file(
                            $file["tmp_name"],
                            $filePath
                        )) {

                            try {

                                /*
                                |--------------------------------------------------------------------------
                                | Extract Resume Text
                                |--------------------------------------------------------------------------
                                */

                                $extractedText =
                                    ResumeParser::extractText($filePath);


                                /*
                                |--------------------------------------------------------------------------
                                | Check Extracted Text
                                |--------------------------------------------------------------------------
                                */

                                if ($extractedText === "") {

                                    /*
                                    |--------------------------------------------------------------------------
                                    | Delete File If No Text Was Extracted
                                    |--------------------------------------------------------------------------
                                    */

                                    if (file_exists($filePath)) {
                                        unlink($filePath);
                                    }

                                    $error =
                                        "The PDF was uploaded, but no readable text could be extracted from it. Please make sure your resume contains selectable text and is not just a scanned image.";

                                } else {

                                    /*
                                    |--------------------------------------------------------------------------
                                    | Relative File Path
                                    |--------------------------------------------------------------------------
                                    */

                                    $relativePath =
                                        "uploads/resumes/" .
                                        $newFileName;


                                    /*
                                    |--------------------------------------------------------------------------
                                    | Save Resume + Extracted Text
                                    |--------------------------------------------------------------------------
                                    */

                                    $stmt = $pdo->prepare("
                                        INSERT INTO resumes
                                        (
                                            applicant_id,
                                            file_name,
                                            file_path,
                                            file_type,
                                            extracted_text
                                        )

                                        VALUES (?, ?, ?, ?, ?)
                                    ");


                                    $stmt->execute([
                                        $applicantId,
                                        $originalName,
                                        $relativePath,
                                        $file["type"],
                                        $extractedText
                                    ]);


                                    $success =
                                        "Resume uploaded successfully and resume text was extracted for job matching.";

                                }

                            } catch (Exception $e) {

                                /*
                                |--------------------------------------------------------------------------
                                | Delete Uploaded File If Parsing Failed
                                |--------------------------------------------------------------------------
                                */

                                if (file_exists($filePath)) {
                                    unlink($filePath);
                                }

                                $error =
                                    "Resume uploaded, but the system could not read the PDF. Please make sure the PDF is valid and try again.";

                            }

                        } else {

                            $error =
                                "Failed to save the uploaded file.";

                        }

                    }

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| Get Resumes
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM resumes
    WHERE applicant_id = ?
    ORDER BY uploaded_at DESC
");

$stmt->execute([$applicantId]);

$resumes = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        My Resume - Caloocan Job Portal
    </title>

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
            background: white;
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
        }

        .nav-links a {
            text-decoration: none;
            color: #333;
        }

        .container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .back {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            color: #2563eb;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        h1 {
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }

        .message {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .upload-box {
            border: 2px dashed #cbd5e1;
            padding: 35px;
            text-align: center;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        input[type="file"] {
            margin: 20px 0;
        }

        button {
            padding: 12px 20px;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .resume {
            padding: 18px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .resume-name {
            font-weight: bold;
        }

        .resume-date {
            color: #777;
            font-size: 13px;
            margin-top: 5px;
        }

        .view {
            color: #2563eb;
            text-decoration: none;
        }

        .extracted {
            margin-top: 8px;
            font-size: 13px;
            color: #166534;
        }

        @media (max-width: 700px) {

            .navbar {
                padding: 15px 20px;
            }

            .resume {
                flex-direction: column;
                align-items: flex-start;
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

        <a href="dashboard.php">Dashboard</a>
        
        <a href="../jobs/index.php">Find Jobs</a>

        <a href="profile.php">Profile</a>

        <a href="resume.php">Resume</a>

        <a href="applications.php">Applications</a>

        <a href="../logout.php" class="logout">Logout</a>

    </div>

</nav>


<div class="container">

    <a href="dashboard.php" class="back">
        ← Back to Dashboard
    </a>


    <div class="card">

        <h1>
            My Resume
        </h1>

        <p class="subtitle">
            Upload your PDF resume so employers can review your application
            and the system can match your skills with job requirements.
        </p>


        <?php if ($success): ?>

            <div class="message success">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="message error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            enctype="multipart/form-data"
        >

            <div class="upload-box">

                <h3>
                    Upload Resume
                </h3>

                <p style="margin-top:10px;color:#666;">

                    PDF only

                    <br>

                    Maximum size: 5 MB

                </p>


                <input
                    type="file"
                    name="resume"
                    accept=".pdf,application/pdf"
                    required
                >


                <br>


                <button type="submit">
                    Upload Resume
                </button>

            </div>

        </form>

    </div>


    <div class="card">

        <h2 style="margin-bottom:20px;">
            Uploaded Resumes
        </h2>


        <?php if (count($resumes) > 0): ?>

            <?php foreach ($resumes as $resume): ?>

                <div class="resume">

                    <div>

                        <div class="resume-name">

                            <?= htmlspecialchars(
                                $resume["file_name"]
                            ) ?>

                        </div>

                        <div class="resume-date">

                            Uploaded:

                            <?= date(
                                "F d, Y h:i A",
                                strtotime($resume["uploaded_at"])
                            ) ?>

                        </div>

                        <?php if (!empty($resume["extracted_text"])): ?>

                            <div class="extracted">

                                ✓ Resume text extracted successfully

                            </div>

                        <?php endif; ?>

                    </div>


                    <a
                        href="../<?= htmlspecialchars(
                            $resume["file_path"]
                        ) ?>"
                        target="_blank"
                        class="view"
                    >
                        View Resume
                    </a>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <p>
                You haven't uploaded a resume yet.
            </p>

        <?php endif; ?>

    </div>

</div>

</body>

</html>