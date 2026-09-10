<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "employer") {
    header("Location: ../login.php");
    exit;
}

$userId = $_SESSION["user_id"];

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| Get Employer
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT id, company_name
    FROM employers
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);

$employer = $stmt->fetch();

if (!$employer) {
    die("Employer profile not found.");
}

$employerId = $employer["id"];


/*
|--------------------------------------------------------------------------
| Create Job
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $jobTitle = trim($_POST["job_title"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $requirements = trim($_POST["requirements"] ?? "");

    /*
    |--------------------------------------------------------------------------
    | Location is fixed to Caloocan City
    |--------------------------------------------------------------------------
    */

    $location = "Caloocan City";

    $employmentType = $_POST["employment_type"] ?? "Full-time";

    $salaryMin = trim($_POST["salary_min"] ?? "");
    $salaryMax = trim($_POST["salary_max"] ?? "");

    $isRemote = isset($_POST["is_remote"]) ? 1 : 0;

    $deadline = $_POST["application_deadline"] ?? "";


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($jobTitle === "") {

        $error = "Job title is required.";

    } elseif ($description === "") {

        $error = "Job description is required.";

    } elseif (!in_array(
        $employmentType,
        [
            "Full-time",
            "Part-time",
            "Contract",
            "Internship",
            "Freelance"
        ],
        true
    )) {

        $error = "Invalid employment type.";

    } elseif (
        $salaryMin !== ""
        && !is_numeric($salaryMin)
    ) {

        $error = "Minimum salary must be a valid number.";

    } elseif (
        $salaryMax !== ""
        && !is_numeric($salaryMax)
    ) {

        $error = "Maximum salary must be a valid number.";

    } elseif (
        $salaryMin !== ""
        && $salaryMax !== ""
        && (float)$salaryMin > (float)$salaryMax
    ) {

        $error = "Minimum salary cannot be higher than maximum salary.";

    }


    /*
    |--------------------------------------------------------------------------
    | Save Job
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        try {

            $stmt = $pdo->prepare("
                INSERT INTO jobs
                (
                    employer_id,
                    job_title,
                    description,
                    requirements,
                    location,
                    employment_type,
                    salary_min,
                    salary_max,
                    is_remote,
                    status,
                    application_deadline
                )

                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
            ");


            $stmt->execute([

                $employerId,

                $jobTitle,

                $description,

                $requirements !== ""
                    ? $requirements
                    : null,

                /*
                |--------------------------------------------------------------------------
                | Always save Caloocan City
                |--------------------------------------------------------------------------
                */

                $location,

                $employmentType,

                $salaryMin !== ""
                    ? $salaryMin
                    : null,

                $salaryMax !== ""
                    ? $salaryMax
                    : null,

                $isRemote,

                $deadline !== ""
                    ? $deadline
                    : null

            ]);


            header("Location: jobs.php?created=1");
            exit;


        } catch (PDOException $e) {

            $error =
                "Failed to create job. Please try again.";

        }

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

<title>Create Job - Caloocan Job Portal</title>

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
    max-width: 850px;
    margin: 40px auto;
    padding: 0 20px;
}

.back {
    display: inline-block;
    margin-bottom: 20px;
    color: #2563eb;
    text-decoration: none;
}

.card {
    background: white;
    padding: 30px;
    border-radius: 10px;

    box-shadow:
        0 2px 10px rgba(0,0,0,0.05);
}

h1 {
    margin-bottom: 8px;
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

.error {
    background: #fee2e2;
    color: #991b1b;
}

.form-group {
    margin-bottom: 20px;
}

label {
    display: block;
    font-weight: bold;
    margin-bottom: 7px;
}

input,
select,
textarea {
    width: 100%;
    padding: 12px;

    border: 1px solid #ccc;
    border-radius: 6px;

    font-size: 15px;
}

textarea {
    min-height: 150px;
    resize: vertical;
}

.row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.checkbox {
    display: flex;
    align-items: center;
    gap: 8px;
}

.checkbox input {
    width: auto;
}

button {
    width: 100%;
    padding: 13px;

    border: none;
    border-radius: 6px;

    background: #2563eb;
    color: white;

    font-size: 16px;
    cursor: pointer;
}

button:hover {
    background: #1d4ed8;
}

.note {
    background: #eff6ff;
    color: #1e40af;

    padding: 12px;

    border-radius: 6px;

    margin-bottom: 20px;
}

.location-box {
    background: #f8fafc;

    border: 1px solid #cbd5e1;

    padding: 12px;

    border-radius: 6px;

    color: #334155;

    font-weight: bold;
}

.location-description {
    margin-top: 7px;

    font-size: 13px;

    color: #64748b;

    font-weight: normal;
}

@media (max-width: 650px) {

    .row {
        grid-template-columns: 1fr;
    }

    .navbar {
        padding: 15px 20px;
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

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="jobs.php">
            My Jobs
        </a>

        <a href="applicants.php">
            Applicants
        </a>

        <a href="../logout.php">
            Logout
        </a>

    </div>

</nav>


<div class="container">

    <a href="jobs.php" class="back">
        ← Back to My Jobs
    </a>


    <div class="card">

        <h1>
            Post a New Job
        </h1>

        <p class="subtitle">
            Create a job posting for
            <?= htmlspecialchars($employer["company_name"]) ?>.
        </p>


        <div class="note">

            Your job will be submitted for admin approval
            before it becomes visible to applicants.

            <br><br>

            <strong>
                Job postings are currently limited to
                Caloocan City.
            </strong>

        </div>


        <?php if ($error): ?>

            <div class="message error">

                <?= htmlspecialchars($error) ?>

            </div>

        <?php endif; ?>


        <form method="POST">


            <!-- Job Title -->

            <div class="form-group">

                <label>
                    Job Title *
                </label>

                <input
                    type="text"
                    name="job_title"
                    value="<?= htmlspecialchars(
                        $_POST["job_title"] ?? ""
                    ) ?>"
                    placeholder="e.g. Senior PHP Developer"
                    required
                >

            </div>


            <!-- Job Description -->

            <div class="form-group">

                <label>
                    Job Description *
                </label>

                <textarea
                    name="description"
                    placeholder="Describe the role and responsibilities..."
                    required
                ><?= htmlspecialchars(
                    $_POST["description"] ?? ""
                ) ?></textarea>

            </div>


            <!-- Requirements -->

            <div class="form-group">

                <label>
                    Requirements
                </label>

                <textarea
                    name="requirements"
                    placeholder="Example: C#, React.js, PHP, Laravel, MySQL"
                ><?= htmlspecialchars(
                    $_POST["requirements"] ?? ""
                ) ?></textarea>

            </div>


            <!-- Location + Employment Type -->

            <div class="row">


                <div class="form-group">

                    <label>
                        Location
                    </label>

                    <div class="location-box">

                        📍 Caloocan City

                        <div class="location-description">

                            All job postings on this portal
                            are currently limited to Caloocan City.

                        </div>

                    </div>

                </div>


                <div class="form-group">

                    <label>
                        Employment Type
                    </label>

                    <select name="employment_type">

                        <?php

                        $types = [
                            "Full-time",
                            "Part-time",
                            "Contract",
                            "Internship",
                            "Freelance"
                        ];

                        foreach ($types as $type):

                        ?>

                            <option
                                value="<?= htmlspecialchars($type) ?>"
                                <?= (
                                    ($_POST["employment_type"]
                                        ?? "Full-time")
                                    === $type
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                <?= htmlspecialchars($type) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

            </div>


            <!-- Salary -->

            <div class="row">


                <div class="form-group">

                    <label>
                        Minimum Salary
                    </label>

                    <input
                        type="number"
                        name="salary_min"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $_POST["salary_min"] ?? ""
                        ) ?>"
                        placeholder="50000"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Maximum Salary
                    </label>

                    <input
                        type="number"
                        name="salary_max"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $_POST["salary_max"] ?? ""
                        ) ?>"
                        placeholder="70000"
                    >

                </div>

            </div>


            <!-- Deadline -->

            <div class="form-group">

                <label>
                    Application Deadline
                </label>

                <input
                    type="date"
                    name="application_deadline"
                    value="<?= htmlspecialchars(
                        $_POST["application_deadline"] ?? ""
                    ) ?>"
                >

            </div>


            <!-- Remote -->

            <div class="form-group checkbox">

                <input
                    type="checkbox"
                    name="is_remote"
                    id="is_remote"
                    <?= isset($_POST["is_remote"])
                        ? "checked"
                        : ""
                    ?>
                >

                <label
                    for="is_remote"
                    style="margin:0;"
                >
                    This is a remote position
                </label>

            </div>


            <!-- Submit -->

            <button type="submit">
                Submit Job for Approval
            </button>

        </form>

    </div>

</div>

</body>

</html>