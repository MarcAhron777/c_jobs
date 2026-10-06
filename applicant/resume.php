<?php

session_start();

require_once "../config/database.php";
require_once "../services/ResumeParser.php";


/*
|--------------------------------------------------------------------------
| AUTH CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    $_SESSION["role"] !== "applicant"
) {

    header("Location: ../login.php");
    exit;
}


$userId = (int) $_SESSION["user_id"];

$success = "";
$error = "";


/*
|--------------------------------------------------------------------------
| GET APPLICANT
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT id

    FROM applicants

    WHERE user_id = ?

    LIMIT 1

");

$stmt->execute([
    $userId
]);

$applicant =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$applicant) {

    die(
        "Applicant profile not found."
    );
}


$applicantId =
    (int) $applicant["id"];


/*
|--------------------------------------------------------------------------
| RESUME TEMPLATE PATH
|--------------------------------------------------------------------------
|
| File location:
|
| c_jobs/assets/forms/Caloocan_City_Resume_Template.pdf
|
*/

$resumeTemplate =
    "../assets/forms/Caloocan_City_Resume_Template.pdf";


/*
|--------------------------------------------------------------------------
| UPLOAD RESUME
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {


    /*
    |--------------------------------------------------------------------------
    | CHECK FILE
    |--------------------------------------------------------------------------
    */

    if (
        !isset(
            $_FILES["resume"]
        )
    ) {

        $error =
            "Please select a resume.";

    } else {


        $file =
            $_FILES["resume"];


        /*
        |--------------------------------------------------------------------------
        | CHECK UPLOAD ERROR
        |--------------------------------------------------------------------------
        */

        if (
            $file["error"]
            !== UPLOAD_ERR_OK
        ) {

            $error =
                "There was an error uploading your resume.";

        } else {


            /*
            |--------------------------------------------------------------------------
            | MAXIMUM FILE SIZE
            |--------------------------------------------------------------------------
            */

            $maxSize =
                5 * 1024 * 1024;


            if (
                $file["size"] >
                $maxSize
            ) {

                $error =
                    "Resume must not exceed 5 MB.";

            } else {


                /*
                |--------------------------------------------------------------------------
                | FILE INFO
                |--------------------------------------------------------------------------
                */

                $originalName =
                    basename(
                        $file["name"]
                    );


                $extension =
                    strtolower(
                        pathinfo(
                            $originalName,
                            PATHINFO_EXTENSION
                        )
                    );


                /*
                |--------------------------------------------------------------------------
                | PDF ONLY
                |--------------------------------------------------------------------------
                */

                if (
                    $extension !== "pdf"
                ) {

                    $error =
                        "Only PDF resumes are currently supported for resume matching.";

                } else {


                    /*
                    |--------------------------------------------------------------------------
                    | MIME TYPE CHECK
                    |--------------------------------------------------------------------------
                    */

                    $finfo =
                        new finfo(
                            FILEINFO_MIME_TYPE
                        );


                    $mimeType =
                        $finfo->file(
                            $file["tmp_name"]
                        );


                    $allowedMimeTypes = [

                        "application/pdf",

                        "application/x-pdf"

                    ];


                    if (
                        !in_array(
                            $mimeType,
                            $allowedMimeTypes,
                            true
                        )
                    ) {

                        $error =
                            "The selected file is not a valid PDF.";

                    } else {


                        /*
                        |--------------------------------------------------------------------------
                        | UPLOAD DIRECTORY
                        |--------------------------------------------------------------------------
                        */

                        $uploadDirectory =
                            "../uploads/resumes/";


                        if (
                            !is_dir(
                                $uploadDirectory
                            )
                        ) {

                            if (
                                !mkdir(
                                    $uploadDirectory,
                                    0755,
                                    true
                                )
                            ) {

                                $error =
                                    "Failed to create resume upload directory.";
                            }
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | CONTINUE UPLOAD
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $error === ""
                        ) {


                            /*
                            |--------------------------------------------------------------------------
                            | UNIQUE FILE NAME
                            |--------------------------------------------------------------------------
                            */

                            $newFileName =

                                "resume_"

                                . $applicantId

                                . "_"

                                . time()

                                . "_"

                                . bin2hex(
                                    random_bytes(4)
                                )

                                . ".pdf";


                            $filePath =

                                $uploadDirectory

                                . $newFileName;


                            /*
                            |--------------------------------------------------------------------------
                            | MOVE FILE
                            |--------------------------------------------------------------------------
                            */

                            if (
                                move_uploaded_file(
                                    $file["tmp_name"],
                                    $filePath
                                )
                            ) {


                                try {


                                    /*
                                    |--------------------------------------------------------------------------
                                    | EXTRACT TEXT
                                    |--------------------------------------------------------------------------
                                    */

                                    $extractedText =
                                        ResumeParser::extractText(
                                            $filePath
                                        );


                                    /*
                                    |--------------------------------------------------------------------------
                                    | CHECK EXTRACTED TEXT
                                    |--------------------------------------------------------------------------
                                    */

                                    if (
                                        trim(
                                            $extractedText
                                        ) === ""
                                    ) {


                                        /*
                                        |--------------------------------------------------------------------------
                                        | DELETE UNREADABLE PDF
                                        |--------------------------------------------------------------------------
                                        */

                                        if (
                                            file_exists(
                                                $filePath
                                            )
                                        ) {

                                            unlink(
                                                $filePath
                                            );
                                        }


                                        $error =

                                            "The PDF was uploaded, but no readable text could be extracted. "

                                            . "Please make sure your resume contains selectable text "

                                            . "and is not only a scanned image.";


                                    } else {


                                        /*
                                        |--------------------------------------------------------------------------
                                        | RELATIVE FILE PATH
                                        |--------------------------------------------------------------------------
                                        */

                                        $relativePath =

                                            "uploads/resumes/"

                                            . $newFileName;


                                        /*
                                        |--------------------------------------------------------------------------
                                        | SAVE RESUME
                                        |--------------------------------------------------------------------------
                                        */

                                        $stmt =
                                            $pdo->prepare("

                                                INSERT INTO resumes
                                                (
                                                    applicant_id,
                                                    file_name,
                                                    file_path,
                                                    file_type,
                                                    extracted_text
                                                )

                                                VALUES
                                                (
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?,
                                                    ?
                                                )

                                            ");


                                        $stmt->execute([

                                            $applicantId,

                                            $originalName,

                                            $relativePath,

                                            "application/pdf",

                                            $extractedText

                                        ]);


                                        $success =

                                            "Resume uploaded successfully. "

                                            . "The resume text was also extracted "

                                            . "for job matching.";

                                    }


                                } catch (
                                    Exception $e
                                ) {


                                    /*
                                    |--------------------------------------------------------------------------
                                    | DELETE FILE IF PARSING FAILED
                                    |--------------------------------------------------------------------------
                                    */

                                    if (
                                        file_exists(
                                            $filePath
                                        )
                                    ) {

                                        unlink(
                                            $filePath
                                        );
                                    }


                                    $error =

                                        "Resume uploaded, but the system could not read the PDF. "

                                        . "Please make sure the PDF is valid and try again.";

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

}


/*
|--------------------------------------------------------------------------
| GET UPLOADED RESUMES
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("

    SELECT *

    FROM resumes

    WHERE applicant_id = ?

    ORDER BY uploaded_at DESC

");


$stmt->execute([
    $applicantId
]);


$resumes =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
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
    My Resume - Caloocan Job Portal
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
        white;

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
        900px;

    margin:
        40px auto;

    padding:
        0 20px 50px;
}


.back {

    display:
        inline-block;

    margin-bottom:
        20px;

    text-decoration:
        none;

    color:
        #2563eb;

    font-size:
        14px;
}


.back:hover {

    text-decoration:
        underline;
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
        30px;

    border-radius:
        12px;

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
        25px;
}


h1 {

    margin-bottom:
        10px;
}


.subtitle {

    color:
        #666;

    line-height:
        1.6;

    margin-bottom:
        25px;
}


/*
|--------------------------------------------------------------------------
| MESSAGE
|--------------------------------------------------------------------------
*/

.message {

    padding:
        14px 16px;

    border-radius:
        7px;

    margin-bottom:
        20px;

    line-height:
        1.5;

    font-size:
        14px;
}


.success {

    background:
        #dcfce7;

    color:
        #166534;

    border:
        1px solid #bbf7d0;
}


.error {

    background:
        #fee2e2;

    color:
        #991b1b;

    border:
        1px solid #fecaca;
}


/*
|--------------------------------------------------------------------------
| RESUME TEMPLATE DOWNLOAD
|--------------------------------------------------------------------------
*/

.template-box {

    background:
        #eff6ff;

    border:
        1px solid #bfdbfe;

    border-radius:
        10px;

    padding:
        24px;

    margin-bottom:
        25px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        25px;
}


.template-content {

    flex:
        1;
}


.template-title {

    font-size:
        18px;

    font-weight:
        bold;

    color:
        #1e3a8a;

    margin-bottom:
        8px;
}


.template-description {

    color:
        #475569;

    font-size:
        14px;

    line-height:
        1.6;

    margin-bottom:
        5px;
}


.template-info {

    color:
        #64748b;

    font-size:
        12px;

    margin-top:
        8px;
}


.download-btn {

    display:
        inline-block;

    flex-shrink:
        0;

    background:
        #16a34a;

    color:
        white;

    padding:
        12px 18px;

    border-radius:
        7px;

    text-decoration:
        none;

    font-size:
        14px;

    font-weight:
        bold;

    white-space:
        nowrap;
}


.download-btn:hover {

    background:
        #15803d;
}


/*
|--------------------------------------------------------------------------
| STEPS
|--------------------------------------------------------------------------
*/

.steps {

    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        12px;

    margin-bottom:
        25px;
}


.step {

    background:
        #f8fafc;

    border:
        1px solid #e2e8f0;

    border-radius:
        8px;

    padding:
        15px;

    text-align:
        center;
}


.step-number {

    width:
        30px;

    height:
        30px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    margin:
        0 auto 8px;

    border-radius:
        50%;

    background:
        #2563eb;

    color:
        white;

    font-weight:
        bold;
}


.step strong {

    display:
        block;

    margin-bottom:
        4px;

    font-size:
        13px;
}


.step p {

    color:
        #64748b;

    font-size:
        12px;

    line-height:
        1.4;
}


/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
*/

.upload-box {

    border:
        2px dashed #cbd5e1;

    padding:
        35px;

    text-align:
        center;

    border-radius:
        10px;

    background:
        #fafafa;
}


.upload-box h3 {

    margin-bottom:
        8px;
}


.upload-description {

    color:
        #666;

    line-height:
        1.6;

    font-size:
        14px;
}


.file-input {

    display:
        block;

    margin:
        20px auto;

    max-width:
        100%;
}


.upload-btn {

    padding:
        12px 22px;

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
}


.upload-btn:hover {

    background:
        #1d4ed8;
}


/*
|--------------------------------------------------------------------------
| UPLOADED RESUMES
|--------------------------------------------------------------------------
*/

.resume-list-title {

    margin-bottom:
        20px;
}


.resume {

    padding:
        18px;

    border:
        1px solid #ddd;

    border-radius:
        8px;

    margin-bottom:
        12px;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;
}


.resume:last-child {

    margin-bottom:
        0;
}


.resume-name {

    font-weight:
        bold;

    overflow-wrap:
        anywhere;
}


.resume-date {

    color:
        #777;

    font-size:
        13px;

    margin-top:
        5px;
}


.extracted {

    margin-top:
        8px;

    font-size:
        13px;

    color:
        #166534;
}


.view {

    color:
        #2563eb;

    text-decoration:
        none;

    white-space:
        nowrap;

    font-size:
        14px;

    font-weight:
        bold;
}


.view:hover {

    text-decoration:
        underline;
}


.empty-resume {

    color:
        #64748b;

    padding:
        15px 0;
}


/*
|--------------------------------------------------------------------------
| NOTE
|--------------------------------------------------------------------------
*/

.note {

    margin-top:
        18px;

    background:
        #fffbeb;

    border:
        1px solid #fde68a;

    color:
        #854d0e;

    padding:
        14px;

    border-radius:
        8px;

    font-size:
        13px;

    line-height:
        1.5;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (
    max-width: 700px
) {

    .navbar {

        padding:
            15px 20px;

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


    .template-box {

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .download-btn {

        width:
            100%;

        text-align:
            center;
    }


    .steps {

        grid-template-columns:
            1fr;
    }


    .resume {

        flex-direction:
            column;

        align-items:
            flex-start;
    }


    .view {

        width:
            100%;

        padding-top:
            8px;
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


        <a href="../jobs/index.php">

            Find Jobs

        </a>


        <a href="profile.php">

            Profile

        </a>


        <a
            href="resume.php"
            class="active"
        >

            Resume

        </a>


        <a href="applications.php">

            Applications

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


    <a
        href="dashboard.php"
        class="back"
    >

        ← Back to Dashboard

    </a>


    <!-- =====================================================
         MAIN RESUME CARD
    ====================================================== -->

    <div class="card">


        <h1>

            My Resume

        </h1>


        <p class="subtitle">

            Upload your PDF resume so employers
            can review your application and the
            system can match your skills with
            available jobs.

        </p>


        <!-- =================================================
             SUCCESS MESSAGE
        ================================================== -->

        <?php if ($success): ?>


            <div
                class="
                    message
                    success
                "
            >

                <?= htmlspecialchars(
                    $success
                ) ?>

            </div>


        <?php endif; ?>


        <!-- =================================================
             ERROR MESSAGE
        ================================================== -->

        <?php if ($error): ?>


            <div
                class="
                    message
                    error
                "
            >

                <?= htmlspecialchars(
                    $error
                ) ?>

            </div>


        <?php endif; ?>


        <!-- =================================================
             DOWNLOAD RESUME TEMPLATE
        ================================================== -->

        <div class="template-box">


            <div class="template-content">


                <div class="template-title">

                    Need a Resume?

                </div>


                <p class="template-description">

                    Download our Caloocan City
                    applicant resume template.

                    Fill in your information,
                    save the completed document
                    as PDF, then upload it below.

                </p>


                <div class="template-info">

                    Caloocan City Resume Format • PDF

                </div>


            </div>


            <a
                href="<?= htmlspecialchars(
                    $resumeTemplate
                ) ?>"

                download="Caloocan_City_Resume_Template.pdf"

                class="download-btn"
            >

                ↓ Download Resume Template

            </a>


        </div>


        <!-- =================================================
             STEPS
        ================================================== -->

        <div class="steps">


            <div class="step">

                <div class="step-number">
                    1
                </div>

                <strong>
                    Download
                </strong>

                <p>
                    Download the Caloocan
                    City resume template.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    2
                </div>

                <strong>
                    Fill Up
                </strong>

                <p>
                    Complete your personal,
                    education, experience
                    and skills information.
                </p>

            </div>


            <div class="step">

                <div class="step-number">
                    3
                </div>

                <strong>
                    Upload
                </strong>

                <p>
                    Save the completed resume
                    as PDF and upload it below.
                </p>

            </div>


        </div>


        <!-- =================================================
             UPLOAD FORM
        ================================================== -->

        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <div class="upload-box">


                <h3>

                    Upload Resume

                </h3>


                <p class="upload-description">

                    Select your completed
                    resume in PDF format.

                    <br>

                    Maximum file size:
                    <strong>5 MB</strong>

                </p>


                <input
                    type="file"
                    name="resume"

                    accept="
                        .pdf,
                        application/pdf
                    "

                    class="file-input"

                    required
                >


                <button
                    type="submit"
                    class="upload-btn"
                >

                    Upload Resume

                </button>


            </div>


        </form>


        <div class="note">

            <strong>
                Important:
            </strong>

            For better job matching,
            upload a PDF containing selectable
            text. Avoid uploading a scanned
            image-only resume.

        </div>


    </div>


    <!-- =====================================================
         UPLOADED RESUMES
    ====================================================== -->

    <div class="card">


        <h2 class="resume-list-title">

            Uploaded Resumes

        </h2>


        <?php if (
            count(
                $resumes
            ) > 0
        ): ?>


            <?php foreach (
                $resumes
                as
                $resume
            ): ?>


                <div class="resume">


                    <div>


                        <div class="resume-name">

                            <?= htmlspecialchars(
                                $resume[
                                    "file_name"
                                ]
                            ) ?>

                        </div>


                        <div class="resume-date">

                            Uploaded:

                            <?= date(
                                "F d, Y h:i A",
                                strtotime(
                                    $resume[
                                        "uploaded_at"
                                    ]
                                )
                            ) ?>

                        </div>


                        <?php if (
                            !empty(
                                $resume[
                                    "extracted_text"
                                ]
                            )
                        ): ?>


                            <div class="extracted">

                                ✓ Resume text extracted
                                successfully

                            </div>


                        <?php endif; ?>


                    </div>


                    <a
                        href="../<?= htmlspecialchars(
                            $resume[
                                "file_path"
                            ]
                        ) ?>"

                        target="_blank"

                        class="view"
                    >

                        View Resume

                    </a>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <p class="empty-resume">

                You haven't uploaded
                a resume yet.

            </p>


        <?php endif; ?>


    </div>


</div>


</body>

</html>