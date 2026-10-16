<?php



session_start();



require_once "../config/database.php";





/*

|--------------------------------------------------------------------------

| APPLICANT ONLY

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



$error = "";

$success = "";





/*

|--------------------------------------------------------------------------

| GET APPLICANT

|--------------------------------------------------------------------------

*/



$stmt = $pdo->prepare("

    SELECT

        id,

        first_name,

        last_name



    FROM applicants



    WHERE user_id = ?



    LIMIT 1

");



$stmt->execute([

    $userId

]);



$applicant = $stmt->fetch(

    PDO::FETCH_ASSOC

);





if (!$applicant) {

    die("Applicant profile not found.");

}





$applicantId =

    (int) $applicant["id"];





/*

|--------------------------------------------------------------------------

| HANDLE FORM ACTIONS

|--------------------------------------------------------------------------

*/



if (

    $_SERVER["REQUEST_METHOD"] === "POST"

) {



    $action =

        trim(

            $_POST["action"]

            ?? ""

        );





    /*

    |--------------------------------------------------------------------------

    | SAVE EDUCATION

    |--------------------------------------------------------------------------

    */



    if ($action === "save_education") {



        try {



            $educationalAttainment =

                trim(

                    $_POST["educational_attainment"]

                    ?? ""

                );





            $degreeProgram =

                trim(

                    $_POST["degree_program"]

                    ?? ""

                );





            $schoolName =

                trim(

                    $_POST["school_name"]

                    ?? ""

                );





            $graduationYear =

                trim(

                    $_POST["graduation_year"]

                    ?? ""

                );





            $currentYearLevel =

                trim(

                    $_POST["current_year_level"]

                    ?? ""

                );





            $allowedAttainment = [

                "Elementary",

                "Junior High School",

                "Senior High School",

                "Vocational",

                "College Undergraduate",

                "College Graduate",

                "Postgraduate"

            ];





            if (

                !in_array(

                    $educationalAttainment,

                    $allowedAttainment,

                    true

                )

            ) {

                throw new Exception(

                    "Please select a valid educational attainment."

                );

            }





            if (

                $graduationYear !== "" &&

                (

                    !ctype_digit($graduationYear) ||

                    (int) $graduationYear < 1900 ||

                    (int) $graduationYear > ((int) date("Y") + 10)

                )

            ) {

                throw new Exception(

                    "Please enter a valid graduation year."

                );

            }





            /*

            |--------------------------------------------------------------------------

            | CHECK EXISTING EDUCATION

            |--------------------------------------------------------------------------

            */



            $stmt = $pdo->prepare("

                SELECT id



                FROM applicant_education



                WHERE applicant_id = ?



                ORDER BY id ASC



                LIMIT 1

            ");



            $stmt->execute([

                $applicantId

            ]);



            $existingEducation =

                $stmt->fetch(

                    PDO::FETCH_ASSOC

                );





            if ($existingEducation) {



                /*

                |--------------------------------------------------------------------------

                | UPDATE

                |--------------------------------------------------------------------------

                */



                $stmt = $pdo->prepare("

                    UPDATE applicant_education



                    SET

                        educational_attainment = ?,

                        degree_program = ?,

                        school_name = ?,

                        graduation_year = ?,

                        current_year_level = ?



                    WHERE

                        id = ?

                        AND applicant_id = ?

                ");



                $stmt->execute([

                    $educationalAttainment,



                    $degreeProgram !== ""

                        ? $degreeProgram

                        : null,



                    $schoolName !== ""

                        ? $schoolName

                        : null,



                    $graduationYear !== ""

                        ? $graduationYear

                        : null,



                    $currentYearLevel !== ""

                        ? $currentYearLevel

                        : null,



                    (int) $existingEducation["id"],



                    $applicantId

                ]);



            } else {



                /*

                |--------------------------------------------------------------------------

                | INSERT

                |--------------------------------------------------------------------------

                */



                $stmt = $pdo->prepare("

                    INSERT INTO applicant_education

                    (

                        applicant_id,

                        educational_attainment,

                        degree_program,

                        school_name,

                        graduation_year,

                        current_year_level

                    )



                    VALUES

                    (

                        ?,

                        ?,

                        ?,

                        ?,

                        ?,

                        ?

                    )

                ");



                $stmt->execute([

                    $applicantId,



                    $educationalAttainment,



                    $degreeProgram !== ""

                        ? $degreeProgram

                        : null,



                    $schoolName !== ""

                        ? $schoolName

                        : null,



                    $graduationYear !== ""

                        ? $graduationYear

                        : null,



                    $currentYearLevel !== ""

                        ? $currentYearLevel

                        : null

                ]);

            }





            $success =

                "Education information saved successfully.";



        } catch (Exception $e) {



            $error =

                $e->getMessage();

        }

    }





    /*

    |--------------------------------------------------------------------------

    | ADD TESDA QUALIFICATION

    |--------------------------------------------------------------------------

    */



    if ($action === "add_tesda") {



        try {



            $qualificationName =

                trim(

                    $_POST["qualification_name"]

                    ?? ""

                );





            $ncLevel =

                trim(

                    $_POST["nc_level"]

                    ?? "No NC"

                );





            $skillsAcquired =

                trim(

                    $_POST["skills_acquired"]

                    ?? ""

                );





            $trainingCenter =

                trim(

                    $_POST["training_center"]

                    ?? ""

                );





            $yearCompleted =

                trim(

                    $_POST["year_completed"]

                    ?? ""

                );





            $allowedNcLevels = [

                "NC I",

                "NC II",

                "NC III",

                "NC IV",

                "No NC"

            ];





            if ($qualificationName === "") {



                throw new Exception(

                    "TESDA qualification or training name is required."

                );

            }





            if (

                !in_array(

                    $ncLevel,

                    $allowedNcLevels,

                    true

                )

            ) {



                throw new Exception(

                    "Please select a valid NC level."

                );

            }





            if (

                $yearCompleted !== "" &&

                (

                    !ctype_digit($yearCompleted) ||

                    (int) $yearCompleted < 1900 ||

                    (int) $yearCompleted > ((int) date("Y") + 10)

                )

            ) {



                throw new Exception(

                    "Please enter a valid year completed."

                );

            }





            $stmt = $pdo->prepare("

                INSERT INTO applicant_tesda_qualifications

                (

                    applicant_id,

                    qualification_name,

                    nc_level,

                    skills_acquired,

                    training_center,

                    year_completed

                )



                VALUES

                (

                    ?,

                    ?,

                    ?,

                    ?,

                    ?,

                    ?

                )

            ");





            $stmt->execute([

                $applicantId,

                $qualificationName,

                $ncLevel,



                $skillsAcquired !== ""

                    ? $skillsAcquired

                    : null,



                $trainingCenter !== ""

                    ? $trainingCenter

                    : null,



                $yearCompleted !== ""

                    ? $yearCompleted

                    : null

            ]);





            $success =

                "TESDA qualification added successfully.";



        } catch (Exception $e) {



            $error =

                $e->getMessage();

        }

    }





    /*

    |--------------------------------------------------------------------------

    | UPDATE TESDA QUALIFICATION

    |--------------------------------------------------------------------------

    */



    if ($action === "update_tesda") {



        try {



            $tesdaId =

                (int) (

                    $_POST["tesda_id"]

                    ?? 0

                );





            $qualificationName =

                trim(

                    $_POST["qualification_name"]

                    ?? ""

                );





            $ncLevel =

                trim(

                    $_POST["nc_level"]

                    ?? "No NC"

                );





            $skillsAcquired =

                trim(

                    $_POST["skills_acquired"]

                    ?? ""

                );





            $trainingCenter =

                trim(

                    $_POST["training_center"]

                    ?? ""

                );





            $yearCompleted =

                trim(

                    $_POST["year_completed"]

                    ?? ""

                );





            $allowedNcLevels = [

                "NC I",

                "NC II",

                "NC III",

                "NC IV",

                "No NC"

            ];





            if ($tesdaId <= 0) {



                throw new Exception(

                    "Invalid TESDA qualification."

                );

            }





            if ($qualificationName === "") {



                throw new Exception(

                    "TESDA qualification or training name is required."

                );

            }





            if (

                !in_array(

                    $ncLevel,

                    $allowedNcLevels,

                    true

                )

            ) {



                throw new Exception(

                    "Invalid NC level."

                );

            }





            if (

                $yearCompleted !== "" &&

                (

                    !ctype_digit($yearCompleted) ||

                    (int) $yearCompleted < 1900 ||

                    (int) $yearCompleted > ((int) date("Y") + 10)

                )

            ) {



                throw new Exception(

                    "Please enter a valid year completed."

                );

            }





            $stmt = $pdo->prepare("

                UPDATE applicant_tesda_qualifications



                SET

                    qualification_name = ?,

                    nc_level = ?,

                    skills_acquired = ?,

                    training_center = ?,

                    year_completed = ?



                WHERE

                    id = ?

                    AND applicant_id = ?

            ");





            $stmt->execute([

                $qualificationName,

                $ncLevel,



                $skillsAcquired !== ""

                    ? $skillsAcquired

                    : null,



                $trainingCenter !== ""

                    ? $trainingCenter

                    : null,



                $yearCompleted !== ""

                    ? $yearCompleted

                    : null,



                $tesdaId,

                $applicantId

            ]);





            $success =

                "TESDA qualification updated successfully.";



        } catch (Exception $e) {



            $error =

                $e->getMessage();

        }

    }





    /*

    |--------------------------------------------------------------------------

    | DELETE TESDA QUALIFICATION

    |--------------------------------------------------------------------------

    */



    if ($action === "delete_tesda") {



        try {



            $tesdaId =

                (int) (

                    $_POST["tesda_id"]

                    ?? 0

                );





            if ($tesdaId <= 0) {



                throw new Exception(

                    "Invalid TESDA qualification."

                );

            }





            $stmt = $pdo->prepare("

                DELETE FROM applicant_tesda_qualifications



                WHERE

                    id = ?

                    AND applicant_id = ?

            ");





            $stmt->execute([

                $tesdaId,

                $applicantId

            ]);





            $success =

                "TESDA qualification deleted successfully.";



        } catch (Exception $e) {



            $error =

                $e->getMessage();

        }

    }

}





/*

|--------------------------------------------------------------------------

| GET EDUCATION

|--------------------------------------------------------------------------

*/



$stmt = $pdo->prepare("

    SELECT

        id,

        educational_attainment,

        degree_program,

        school_name,

        graduation_year,

        current_year_level



    FROM applicant_education



    WHERE applicant_id = ?



    ORDER BY id ASC



    LIMIT 1

");



$stmt->execute([

    $applicantId

]);



$education =

    $stmt->fetch(

        PDO::FETCH_ASSOC

    );





if (!$education) {



    $education = [

        "id" => null,

        "educational_attainment" => "",

        "degree_program" => "",

        "school_name" => "",

        "graduation_year" => "",

        "current_year_level" => ""

    ];

}





/*

|--------------------------------------------------------------------------

| GET TESDA QUALIFICATIONS

|--------------------------------------------------------------------------

*/



$stmt = $pdo->prepare("

    SELECT

        id,

        qualification_name,

        nc_level,

        skills_acquired,

        training_center,

        year_completed,

        created_at



    FROM applicant_tesda_qualifications



    WHERE applicant_id = ?



    ORDER BY created_at DESC, id DESC

");



$stmt->execute([

    $applicantId

]);



$tesdaQualifications =

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

    My Qualifications - Caloocan Job Portal

</title>





<link

    rel="stylesheet"

    href="https\://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"

>





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





/*

|--------------------------------------------------------------------------

| NAVBAR

|--------------------------------------------------------------------------

*/



.navbar {

    background: #ffffff;

    border-bottom: 1px solid #ddd;

    padding: 16px 40px;



    display: flex;

    justify-content: space-between;

    align-items: center;

}





.logo {

    color: #2563eb;

    font-size: 24px;

    font-weight: bold;

    text-decoration: none;

}





.nav-links {

    display: flex;

    align-items: center;

    gap: 20px;

}





.nav-links a {

    text-decoration: none;

    color: #333;

}





.nav-links a:hover,

.nav-links .active {

    color: #2563eb;

}





.nav-links .active {

    font-weight: bold;

}





.logout {

    color: #dc2626 !important;

}





/*

|--------------------------------------------------------------------------

| MAIN

|--------------------------------------------------------------------------

*/



.container {

    max-width: 1100px;

    margin: 40px auto;

    padding: 0 20px 60px;

}





.page-header {

    margin-bottom: 28px;

}





.page-header h1 {

    margin-bottom: 7px;

    font-size: 30px;

}





.page-header p {

    color: #666;

    line-height: 1.5;

}





/*

|--------------------------------------------------------------------------

| MESSAGES

|--------------------------------------------------------------------------

*/



.message {

    border-radius: 7px;

    padding: 14px 16px;

    margin-bottom: 20px;

}





.message-success {

    background: #dcfce7;

    border: 1px solid #bbf7d0;

    color: #166534;

}





.message-error {

    background: #fee2e2;

    border: 1px solid #fecaca;

    color: #991b1b;

}





/*

|--------------------------------------------------------------------------

| CARD

|--------------------------------------------------------------------------

*/



.card {

    background: white;

    border-radius: 10px;

    padding: 25px;



    box-shadow:

        0 2px 10px rgba(0,0,0,0.05);



    margin-bottom: 25px;

}





.card-header {

    display: flex;

    align-items: center;

    gap: 12px;



    margin-bottom: 22px;

}





.card-icon {

    width: 42px;

    height: 42px;



    background: #eff6ff;

    color: #2563eb;



    border-radius: 8px;



    display: flex;

    align-items: center;

    justify-content: center;



    font-size: 18px;

}





.card-header h2 {

    font-size: 21px;

}





.card-header p {

    color: #777;

    margin-top: 3px;

    font-size: 13px;

}





/*

|--------------------------------------------------------------------------

| FORM

|--------------------------------------------------------------------------

*/



.form-grid {

    display: grid;

    grid-template-columns: 1fr 1fr;

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

    font-weight: 600;

    margin-bottom: 7px;

    font-size: 14px;

}





input,

select,

textarea {

    width: 100%;



    border:

        1px solid #cbd5e1;



    border-radius:

        6px;



    padding:

        11px 12px;



    font-size:

        14px;



    font-family:

        inherit;



    background:

        #ffffff;

}





textarea {

    min-height: 110px;

    resize: vertical;

}





input:focus,

select:focus,

textarea:focus {

    outline: none;

    border-color: #2563eb;

}





/*

|--------------------------------------------------------------------------

| BUTTONS

|--------------------------------------------------------------------------

*/



.btn {

    border: none;

    border-radius: 6px;



    padding:

        11px 16px;



    cursor: pointer;



    font-size:

        13px;



    font-weight:

        600;



    display:

        inline-flex;



    align-items:

        center;



    justify-content:

        center;



    gap:

        7px;



    text-decoration:

        none;

}





.btn-primary {

    background: #2563eb;

    color: white;

}





.btn-primary:hover {

    background: #1d4ed8;

}





.btn-warning {

    background: #f59e0b;

    color: white;

}





.btn-danger {

    background: #dc2626;

    color: white;

}





.btn-secondary {

    background: #64748b;

    color: white;

}





/*

|--------------------------------------------------------------------------

| TESDA LIST

|--------------------------------------------------------------------------

*/



.tesda-list {

    margin-top: 25px;

}





.tesda-item {

    border:

        1px solid #e5e7eb;



    border-radius:

        8px;



    padding:

        18px;



    margin-bottom:

        15px;



    background:

        #fafafa;

}





.tesda-top {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    gap: 15px;

}





.tesda-name {

    font-weight: bold;

    font-size: 16px;

    margin-bottom: 5px;

}





.nc-badge {

    display: inline-block;



    padding:

        4px 8px;



    border-radius:

        20px;



    background:

        #dbeafe;



    color:

        #1d4ed8;



    font-size:

        12px;



    font-weight:

        bold;

}





.tesda-details {

    margin-top: 12px;



    color:

        #555;



    line-height:

        1.6;



    font-size:

        14px;

}





.tesda-details strong {

    color: #333;

}





.tesda-actions {

    display: flex;

    gap: 7px;

    flex-wrap: wrap;

    margin-top: 15px;

}





/*

|--------------------------------------------------------------------------

| EDIT FORM

|--------------------------------------------------------------------------

*/



.edit-box {

    margin-top: 15px;

    padding-top: 15px;



    border-top:

        1px solid #e5e7eb;



    display: none;

}





.edit-box.show {

    display: block;

}





/*

|--------------------------------------------------------------------------

| EMPTY

|--------------------------------------------------------------------------

*/



.empty {

    text-align: center;

    padding: 35px 20px;

    color: #777;



    background:

        #f8fafc;



    border-radius:

        8px;



    border:

        1px dashed #cbd5e1;

}





.empty i {

    display: block;

    font-size: 28px;

    margin-bottom: 10px;

    color: #94a3b8;

}





/*

|--------------------------------------------------------------------------

| RESPONSIVE

|--------------------------------------------------------------------------

*/



@media (

    max-width: 760px

) {



    .navbar {

        padding: 15px 20px;

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }





    .nav-links {

        flex-wrap: wrap;

        gap: 12px;

    }





    .container {

        margin: 25px auto;

        padding: 0 15px 40px;

    }





    .form-grid {

        grid-template-columns: 1fr;

    }





    .form-group.full {

        grid-column: auto;

    }





    .tesda-top {

        flex-direction: column;

    }

}



</style>



</head>





<body>

<?php
require __DIR__ . "/includes/header.php";
?>

<div class="container">





    <div class="page-header">



        <h1>

            My Qualifications

        </h1>



        <p>

            Add your educational background

            and TESDA or technical qualifications.

            These details will be used in job matching.

        </p>



    </div>





    <?php if ($success !== ""): ?>



        <div class="

            message

            message-success

        ">



            <?= htmlspecialchars(

                $success

            ) ?>



        </div>



    <?php endif; ?>





    <?php if ($error !== ""): ?>



        <div class="

            message

            message-error

        ">



            <?= htmlspecialchars(

                $error

            ) ?>



        </div>



    <?php endif; ?>





    <!-- =====================================================

         EDUCATION

    ====================================================== -->



    <div class="card">





        <div class="card-header">





            <div class="card-icon">



                <i class="

                    fa-solid

                    fa-graduation-cap

                "></i>



            </div>





            <div>



                <h2>

                    Educational Background

                </h2>



                <p>

                    Add your highest educational

                    attainment and degree information.

                </p>



            </div>





        </div>





        <form method="POST">





            <input

                type="hidden"

                name="action"

                value="save_education"

            >





            <div class="form-grid">





                <div class="form-group">



                    <label>

                        Educational Attainment *

                    </label>



                    <select

                        name="educational_attainment"

                        required

                    >



                        <option value="">

                            Select attainment

                        </option>





                        <?php



                        $attainmentOptions = [

                            "Elementary",

                            "Junior High School",

                            "Senior High School",

                            "Vocational",

                            "College Undergraduate",

                            "College Graduate",

                            "Postgraduate"

                        ];



                        ?>





                        <?php foreach (

                            $attainmentOptions

                            as

                            $option

                        ): ?>



                            <option

                                value="<?= htmlspecialchars(

                                    $option

                                ) ?>"



                                <?= $education[

                                    "educational_attainment"

                                ] === $option

                                    ? "selected"

                                    : ""

                                ?>

                            >



                                <?= htmlspecialchars(

                                    $option

                                ) ?>



                            </option>



                        <?php endforeach; ?>





                    </select>



                </div>





                <div class="form-group">



                    <label>

                        Degree / Program

                    </label>



                    <input

                        type="text"

                        name="degree_program"

                        placeholder="e.g. BS Information Technology"

                        value="<?= htmlspecialchars(

                            $education[

                                "degree_program"

                            ]

                            ?? ""

                        ) ?>"

                    >



                </div>





                <div class="form-group">



                    <label>

                        School Name

                    </label>



                    <input

                        type="text"

                        name="school_name"

                        placeholder="Enter school name"

                        value="<?= htmlspecialchars(

                            $education[

                                "school_name"

                            ]

                            ?? ""

                        ) ?>"

                    >



                </div>





                <div class="form-group">



                    <label>

                        Graduation Year

                    </label>



                    <input

                        type="number"

                        name="graduation_year"



                        min="1900"



                        max="<?= (int) date("Y") + 10 ?>"



                        placeholder="e.g. 2026"



                        value="<?= htmlspecialchars(

                            $education[

                                "graduation_year"

                            ]

                            ?? ""

                        ) ?>"

                    >



                </div>





                <div class="

                    form-group

                    full

                ">



                    <label>

                        Current Year Level

                    </label>



                    <input

                        type="text"

                        name="current_year_level"

                        placeholder="e.g. 4th Year, Grade 12, 2nd Year"



                        value="<?= htmlspecialchars(

                            $education[

                                "current_year_level"

                            ]

                            ?? ""

                        ) ?>"

                    >



                </div>





            </div>





            <button

                type="submit"

                class="

                    btn

                    btn-primary

                "

            >



                <i class="

                    fa-solid

                    fa-floppy-disk

                "></i>



                Save Education



            </button>





        </form>





    </div>





    <!-- =====================================================

         TESDA

    ====================================================== -->



    <div class="card">





        <div class="card-header">





            <div class="card-icon">



                <i class="

                    fa-solid

                    fa-certificate

                "></i>



            </div>





            <div>



                <h2>

                    TESDA / Technical Qualifications

                </h2>



                <p>

                    Add TESDA qualifications,

                    national certificates,

                    or technical training.

                </p>



            </div>





        </div>





        <form method="POST">





            <input

                type="hidden"

                name="action"

                value="add_tesda"

            >





            <div class="form-grid">





                <div class="form-group">



                    <label>

                        Qualification / Training *

                    </label>



                    <input

                        type="text"

                        name="qualification_name"

                        required

                        placeholder="e.g. Computer Systems Servicing"

                    >



                </div>





                <div class="form-group">



                    <label>

                        NC Level

                    </label>



                    <select name="nc_level">



                        <option value="No NC">

                            No NC

                        </option>



                        <option value="NC I">

                            NC I

                        </option>



                        <option value="NC II">

                            NC II

                        </option>



                        <option value="NC III">

                            NC III

                        </option>



                        <option value="NC IV">

                            NC IV

                        </option>



                    </select>



                </div>





                <div class="

                    form-group

                    full

                ">



                    <label>

                        Skills Acquired

                    </label>



                    <textarea

                        name="skills_acquired"

                        placeholder="Example:

Computer Hardware Servicing

Network Configuration

Operating System Installation

Troubleshooting"

                    ></textarea>



                </div>





                <div class="form-group">



                    <label>

                        Training Center

                    </label>



                    <input

                        type="text"

                        name="training_center"

                        placeholder="Training center or institution"

                    >



                </div>





                <div class="form-group">



                    <label>

                        Year Completed

                    </label>



                    <input

                        type="number"

                        name="year_completed"



                        min="1900"



                        max="<?= (int) date("Y") + 10 ?>"



                        placeholder="e.g. 2025"

                    >



                </div>





            </div>





            <button

                type="submit"

                class="

                    btn

                    btn-primary

                "

            >



                <i class="

                    fa-solid

                    fa-plus

                "></i>



                Add Qualification



            </button>





        </form>





        <!-- =================================================

             TESDA LIST

        ================================================== -->



        <div class="tesda-list">





            <?php if (

                count(

                    $tesdaQualifications

                ) > 0

            ): ?>





                <?php foreach (

                    $tesdaQualifications

                    as

                    $tesda

                ): ?>





                    <div class="tesda-item">





                        <div class="tesda-top">





                            <div>



                                <div class="tesda-name">



                                    <?= htmlspecialchars(

                                        $tesda[

                                            "qualification_name"

                                        ]

                                    ) ?>



                                </div>





                                <span class="nc-badge">



                                    <?= htmlspecialchars(

                                        $tesda[

                                            "nc_level"

                                        ]

                                    ) ?>



                                </span>



                            </div>





                        </div>





                        <div class="tesda-details">





                            <?php if (

                                !empty(

                                    $tesda[

                                        "training_center"

                                    ]

                                )

                            ): ?>



                                <div>



                                    <strong>

                                        Training Center:

                                    </strong>



                                    <?= htmlspecialchars(

                                        $tesda[

                                            "training_center"

                                        ]

                                    ) ?>



                                </div>



                            <?php endif; ?>





                            <?php if (

                                !empty(

                                    $tesda[

                                        "year_completed"

                                    ]

                                )

                            ): ?>



                                <div>



                                    <strong>

                                        Year Completed:

                                    </strong>



                                    <?= htmlspecialchars(

                                        $tesda[

                                            "year_completed"

                                        ]

                                    ) ?>



                                </div>



                            <?php endif; ?>





                            <?php if (

                                !empty(

                                    $tesda[

                                        "skills_acquired"

                                    ]

                                )

                            ): ?>



                                <div style="margin-top:8px;">



                                    <strong>

                                        Skills Acquired:

                                    </strong>



                                    <br>



                                    <?= nl2br(

                                        htmlspecialchars(

                                            $tesda[

                                                "skills_acquired"

                                            ]

                                        )

                                    ) ?>



                                </div>



                            <?php endif; ?>





                        </div>





                        <div class="tesda-actions">





                            <button

                                type="button"

                                class="

                                    btn

                                    btn-warning

                                "

                                onclick="

                                    toggleEdit(

                                        <?= (int)

                                            $tesda["id"]

                                        ?>

                                    )

                                "

                            >



                                <i class="

                                    fa-solid

                                    fa-pen

                                "></i>



                                Edit



                            </button>





                            <form

                                method="POST"

                                onsubmit="

                                    return confirm(

                                        'Delete this qualification?'

                                    );

                                "

                            >



                                <input

                                    type="hidden"

                                    name="action"

                                    value="delete_tesda"

                                >



                                <input

                                    type="hidden"

                                    name="tesda_id"

                                    value="<?= (int)

                                        $tesda["id"]

                                    ?>"

                                >



                                <button

                                    type="submit"

                                    class="

                                        btn

                                        btn-danger

                                    "

                                >



                                    <i class="

                                        fa-solid

                                        fa-trash

                                    "></i>



                                    Delete



                                </button>



                            </form>





                        </div>





                        <!-- EDIT FORM -->



                        <div

                            class="edit-box"

                            id="edit-box-<?= (int)

                                $tesda["id"]

                            ?>"

                        >





                            <form method="POST">





                                <input

                                    type="hidden"

                                    name="action"

                                    value="update_tesda"

                                >





                                <input

                                    type="hidden"

                                    name="tesda_id"

                                    value="<?= (int)

                                        $tesda["id"]

                                    ?>"

                                >





                                <div class="form-grid">





                                    <div class="form-group">



                                        <label>

                                            Qualification / Training

                                        </label>



                                        <input

                                            type="text"

                                            name="qualification_name"

                                            required



                                            value="<?= htmlspecialchars(

                                                $tesda[

                                                    "qualification_name"

                                                ]

                                            ) ?>"

                                        >



                                    </div>





                                    <div class="form-group">



                                        <label>

                                            NC Level

                                        </label>



                                        <select

                                            name="nc_level"

                                        >



                                            <?php



                                            $ncOptions = [

                                                "No NC",

                                                "NC I",

                                                "NC II",

                                                "NC III",

                                                "NC IV"

                                            ];



                                            ?>





                                            <?php foreach (

                                                $ncOptions

                                                as

                                                $ncOption

                                            ): ?>



                                                <option

                                                    value="<?= htmlspecialchars(

                                                        $ncOption

                                                    ) ?>"



                                                    <?= $tesda[

                                                        "nc_level"

                                                    ] === $ncOption

                                                        ? "selected"

                                                        : ""

                                                    ?>

                                                >



                                                    <?= htmlspecialchars(

                                                        $ncOption

                                                    ) ?>



                                                </option>



                                            <?php endforeach; ?>





                                        </select>



                                    </div>





                                    <div class="

                                        form-group

                                        full

                                    ">



                                        <label>

                                            Skills Acquired

                                        </label>



                                        <textarea

                                            name="skills_acquired"

                                        ><?= htmlspecialchars(

                                            $tesda[

                                                "skills_acquired"

                                            ]

                                            ?? ""

                                        ) ?></textarea>



                                    </div>





                                    <div class="form-group">



                                        <label>

                                            Training Center

                                        </label>



                                        <input

                                            type="text"

                                            name="training_center"



                                            value="<?= htmlspecialchars(

                                                $tesda[

                                                    "training_center"

                                                ]

                                                ?? ""

                                            ) ?>"

                                        >



                                    </div>





                                    <div class="form-group">



                                        <label>

                                            Year Completed

                                        </label>



                                        <input

                                            type="number"

                                            name="year_completed"



                                            min="1900"



                                            max="<?= (int)

                                                date("Y") + 10

                                            ?>"



                                            value="<?= htmlspecialchars(

                                                $tesda[

                                                    "year_completed"

                                                ]

                                                ?? ""

                                            ) ?>"

                                        >



                                    </div>





                                </div>





                                <div

                                    style="

                                        display:flex;

                                        gap:8px;

                                        flex-wrap:wrap;

                                    "

                                >



                                    <button

                                        type="submit"

                                        class="

                                            btn

                                            btn-primary

                                        "

                                    >



                                        <i class="

                                            fa-solid

                                            fa-floppy-disk

                                        "></i>



                                        Save Changes



                                    </button>





                                    <button

                                        type="button"

                                        class="

                                            btn

                                            btn-secondary

                                        "

                                        onclick="

                                            toggleEdit(

                                                <?= (int)

                                                    $tesda["id"]

                                                ?>

                                            )

                                        "

                                    >



                                        Cancel



                                    </button>





                                </div>





                            </form>





                        </div>





                    </div>





                <?php endforeach; ?>





            <?php else: ?>





                <div class="empty">



                    <i class="

                        fa-solid

                        fa-certificate

                    "></i>



                    No TESDA or technical

                    qualifications added yet.



                </div>





            <?php endif; ?>





        </div>





    </div>





</div>





<script>



function toggleEdit(id) {



    const box =

        document.getElementById(

            "edit-box-" + id

        );





    if (!box) {

        return;

    }





    box.classList.toggle(

        "show"

    );

}



</script>





</body>



</html>