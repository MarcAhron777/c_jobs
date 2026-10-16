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

$userId =
    (int) $_SESSION["user_id"];

$error = "";


/*
|--------------------------------------------------------------------------
| GET EMPLOYER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        company_name,
        employer_type,
        address,
        barangay,
        caloocan_area,
        latitude,
        longitude,
        verification_status
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


/*
|--------------------------------------------------------------------------
| VERIFICATION
|--------------------------------------------------------------------------
*/

if (
    ($employer["verification_status"] ?? "")
    !== "approved"
) {
    header(
        "Location: jobs.php?verification_required=1"
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFIED LOCATION
|--------------------------------------------------------------------------
*/

$jobAddress =
    trim(
        $employer["address"]
        ?? ""
    );

$jobBarangay =
    trim(
        $employer["barangay"]
        ?? ""
    );

$jobArea =
    trim(
        $employer["caloocan_area"]
        ?? ""
    );

$jobLatitude =
    $employer["latitude"]
    ?? null;

$jobLongitude =
    $employer["longitude"]
    ?? null;

if (
    $jobAddress === "" ||
    $jobBarangay === "" ||
    $jobArea === "" ||
    !is_numeric($jobLatitude) ||
    !is_numeric($jobLongitude)
) {
    header("Location: profile.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$jobTitle = "";
$jobDescription = "";
$requirements = "";
$requiredSkills = "";

$employmentType =
    "Full-time";

$salaryMin = "";
$salaryMax = "";
$applicationDeadline = "";

$isRemote = 0;

$postingType =
    "regular";

$sourceName = "";
$sourceUrl = "";
$lguJobCategory = "";

$requiresEducation =
    "no";

$educationalRequirement = "";
$requiredDegree = "";
$degreeClassification = "";

$requiresTesda =
    "no";

$tesdaQualification = "";
$requiredNcLevel = "";


/*
|--------------------------------------------------------------------------
| SUBMIT
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {
    try {
        $jobTitle =
            trim(
                $_POST["job_title"]
                ?? ""
            );

        $jobDescription =
            trim(
                $_POST["job_description"]
                ?? ""
            );

        $requirements =
            trim(
                $_POST["requirements"]
                ?? ""
            );

        $requiredSkills =
            trim(
                $_POST["required_skills"]
                ?? ""
            );

        $employmentType =
            trim(
                $_POST["employment_type"]
                ?? ""
            );

        $salaryMin =
            trim(
                $_POST["salary_min"]
                ?? ""
            );

        $salaryMax =
            trim(
                $_POST["salary_max"]
                ?? ""
            );

        $applicationDeadline =
            trim(
                $_POST["application_deadline"]
                ?? ""
            );

        $isRemote =
            isset($_POST["is_remote"])
                ? 1
                : 0;

        $postingType =
            trim(
                $_POST["posting_type"]
                ?? "regular"
            );

        $sourceName =
            trim(
                $_POST["source_name"]
                ?? ""
            );

        $sourceUrl =
            trim(
                $_POST["source_url"]
                ?? ""
            );

        $lguJobCategory =
            trim(
                $_POST["lgu_job_category"]
                ?? ""
            );

        $requiresEducation =
            trim(
                $_POST["requires_education"]
                ?? "no"
            );

        $educationalRequirement =
            trim(
                $_POST["educational_requirement"]
                ?? ""
            );

        $requiredDegree =
            trim(
                $_POST["required_degree"]
                ?? ""
            );

        $degreeClassification =
            trim(
                $_POST["degree_classification"]
                ?? ""
            );

        $requiresTesda =
            trim(
                $_POST["requires_tesda"]
                ?? "no"
            );

        $tesdaQualification =
            trim(
                $_POST["tesda_qualification"]
                ?? ""
            );

        $requiredNcLevel =
            trim(
                $_POST["required_nc_level"]
                ?? ""
            );


        /*
        |--------------------------------------------------------------------------
        | VALIDATION
        |--------------------------------------------------------------------------
        */

        if ($jobTitle === "") {
            throw new Exception(
                "Job title is required."
            );
        }

        if ($jobDescription === "") {
            throw new Exception(
                "Job description is required."
            );
        }

        if ($requiredSkills === "") {
            throw new Exception(
                "Required skills are required."
            );
        }


        $allowedEmploymentTypes = [
            "Full-time",
            "Part-time",
            "Contract",
            "Internship",
            "Freelance"
        ];

        if (
            !in_array(
                $employmentType,
                $allowedEmploymentTypes,
                true
            )
        ) {
            throw new Exception(
                "Invalid employment type."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | POSTING TYPE
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $postingType,
                [
                    "regular",
                    "job_fair"
                ],
                true
            )
        ) {
            throw new Exception(
                "Invalid posting type."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | JOB SOURCE
        |--------------------------------------------------------------------------
        */

        if ($postingType === "job_fair") {
            $jobSource =
                "job_fair";

            if ($sourceName === "") {
                throw new Exception(
                    "Job Fair Name / Source Name is required."
                );
            }

        } else {
            if ($employerType === "lgu") {
                $jobSource =
                    "lgu";

                $sourceName =
                    $employer["company_name"];
            } else {
                $jobSource =
                    "private_employer";

                $sourceName = "";
            }

            $sourceUrl = "";
        }

        if (
            $sourceUrl !== "" &&
            !filter_var(
                $sourceUrl,
                FILTER_VALIDATE_URL
            )
        ) {
            throw new Exception(
                "Please enter a valid source URL."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EDUCATION
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $requiresEducation,
                ["yes", "no"],
                true
            )
        ) {
            $requiresEducation = "no";
        }

        if ($requiresEducation === "yes") {
            if (
                $educationalRequirement === ""
            ) {
                throw new Exception(
                    "Please select an educational requirement."
                );
            }
        } else {
            $educationalRequirement = "";
            $requiredDegree = "";
            $degreeClassification = "";
        }


        /*
        |--------------------------------------------------------------------------
        | TESDA
        |--------------------------------------------------------------------------
        */

        if (
            !in_array(
                $requiresTesda,
                ["yes", "no"],
                true
            )
        ) {
            $requiresTesda = "no";
        }

        if ($requiresTesda === "yes") {
            if ($tesdaQualification === "") {
                throw new Exception(
                    "Please enter the TESDA qualification."
                );
            }

            if ($requiredNcLevel === "") {
                throw new Exception(
                    "Please select the required NC level."
                );
            }
        } else {
            $tesdaQualification = "";
            $requiredNcLevel = "";
        }


        /*
        |--------------------------------------------------------------------------
        | SALARY
        |--------------------------------------------------------------------------
        */

        if (
            $salaryMin !== "" &&
            !is_numeric($salaryMin)
        ) {
            throw new Exception(
                "Invalid minimum salary."
            );
        }

        if (
            $salaryMax !== "" &&
            !is_numeric($salaryMax)
        ) {
            throw new Exception(
                "Invalid maximum salary."
            );
        }

        if (
            $salaryMin !== "" &&
            $salaryMax !== "" &&
            (float) $salaryMin >
            (float) $salaryMax
        ) {
            throw new Exception(
                "Minimum salary cannot be greater than maximum salary."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | DEADLINE
        |--------------------------------------------------------------------------
        */

        if ($applicationDeadline === "") {
            throw new Exception(
                "Application deadline is required."
            );
        }

        if (
            strtotime(
                $applicationDeadline
            ) <
            strtotime(
                date("Y-m-d")
            )
        ) {
            throw new Exception(
                "Application deadline cannot be in the past."
            );
        }


        /*
        |--------------------------------------------------------------------------
        | LOCATION
        |--------------------------------------------------------------------------
        */

        $location =
            "Brgy. "
            . $jobBarangay
            . ", "
            . ucfirst($jobArea)
            . " Caloocan";


        /*
        |--------------------------------------------------------------------------
        | INSERT
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            INSERT INTO jobs (
                employer_id,
                job_title,
                job_description,
                description,
                requirements,
                educational_requirement,
                required_degree,
                required_skills,
                location,
                address,
                barangay,
                caloocan_area,
                latitude,
                longtitude,
                employment_type,
                salary_min,
                salary_max,
                is_remote,
                status,
                application_deadline,
                tesda_qualification,
                required_nc_level,
                job_source,
                source_name,
                source_url,
                degree_classification,
                lgu_job_category,
                created_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?, ?, 'pending', ?,
                ?, ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        $stmt->execute([
            $employerId,
            $jobTitle,
            $jobDescription,
            $jobDescription,

            $requirements !== ""
                ? $requirements
                : null,

            $educationalRequirement !== ""
                ? $educationalRequirement
                : null,

            $requiredDegree !== ""
                ? $requiredDegree
                : null,

            $requiredSkills,

            $location,
            $jobAddress,
            $jobBarangay,
            $jobArea,
            $jobLatitude,
            $jobLongitude,
            $employmentType,

            $salaryMin !== ""
                ? (float) $salaryMin
                : null,

            $salaryMax !== ""
                ? (float) $salaryMax
                : null,

            $isRemote,
            $applicationDeadline,

            $tesdaQualification !== ""
                ? $tesdaQualification
                : null,

            $requiredNcLevel !== ""
                ? $requiredNcLevel
                : null,

            $jobSource,

            $sourceName !== ""
                ? $sourceName
                : null,

            $sourceUrl !== ""
                ? $sourceUrl
                : null,

            $degreeClassification !== ""
                ? $degreeClassification
                : null,

            $lguJobCategory !== ""
                ? $lguJobCategory
                : null
        ]);

        header(
            "Location: jobs.php?created=1"
        );
        exit;

    } catch (Throwable $e) {
        $error =
            $e->getMessage();
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
    Post Job - Employer
</title>

<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f7fb;
    color: #222;
}

.container {
    max-width: 950px;
    margin: 35px auto;
    padding: 0 20px 50px;
}

.page-header {
    margin-bottom: 25px;
}

.page-header p {
    color: #64748b;
}

.card {
    margin-bottom: 20px;
    padding: 25px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
}

.card h2 {
    margin-top: 0;
    margin-bottom: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.form-group {
    margin-bottom: 17px;
}

.full {
    grid-column: 1 / -1;
}

label {
    display: block;
    margin-bottom: 7px;
    font-weight: bold;
    font-size: 14px;
}

input,
select,
textarea {
    width: 100%;
    padding: 11px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

.help {
    display: block;
    margin-top: 6px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.account-box {
    padding: 15px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 7px;
    color: #1e40af;
    line-height: 1.6;
}

.optional {
    padding: 18px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
}

.alert {
    margin-bottom: 20px;
    padding: 14px;
    background: #fee2e2;
    color: #991b1b;
    border-radius: 7px;
}

.checkbox {
    display: flex;
    gap: 8px;
    align-items: center;
}

.checkbox input {
    width: auto;
}

.actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 11px 16px;
    border: none;
    border-radius: 6px;
    font-weight: bold;
    text-decoration: none;
    cursor: pointer;
}

.btn-primary {
    background: #2563eb;
    color: #ffffff;
}

.btn-secondary {
    background: #e2e8f0;
    color: #334155;
}

@media (max-width: 700px) {
    .form-grid {
        grid-template-columns: 1fr;
    }

    .full {
        grid-column: auto;
    }
}

</style>

</head>

<body>

<?php require __DIR__ . "/includes/header.php"; ?>


<div class="container">

    <div class="page-header">

        <h1>
            Post a Job
        </h1>

        <p>
            The job will be submitted for administrator approval.
        </p>

    </div>


    <?php if ($error): ?>

        <div class="alert">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST">


        <div class="card">

            <h2>
                Employer & Posting Source
            </h2>

            <div class="account-box">

                <strong>
                    Employer Type:
                </strong>

                <?= $employerType === "lgu"
                    ? "Local Government Unit (LGU)"
                    : "Private Employer"
                ?>

                <br>

                Regular jobs will automatically use your
                verified employer type as their source.

            </div>

            <br>

            <div class="form-group">

                <label for="posting_type">
                    Posting Type
                </label>

                <select
                    name="posting_type"
                    id="posting_type"
                    onchange="toggleSourceFields()"
                >

                    <option
                        value="regular"
                        <?= $postingType === "regular"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Regular Job Posting
                    </option>

                    <option
                        value="job_fair"
                        <?= $postingType === "job_fair"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Job Fair Posting
                    </option>

                </select>

            </div>


            <div
                id="sourceFields"
                class="optional"
            >

                <div class="form-group">

                    <label for="source_name">
                        Job Fair / Source Name
                    </label>

                    <input
                        type="text"
                        name="source_name"
                        id="source_name"
                        value="<?= htmlspecialchars(
                            $sourceName
                        ) ?>"
                        placeholder="Example: Caloocan Mega Job Fair 2026"
                    >

                </div>


                <div class="form-group">

                    <label for="source_url">
                        Official / Reference URL
                    </label>

                    <input
                        type="url"
                        name="source_url"
                        id="source_url"
                        value="<?= htmlspecialchars(
                            $sourceUrl
                        ) ?>"
                        placeholder="https://..."
                    >

                    <small class="help">
                        Optional. Add an official or trusted source
                        link when available.
                    </small>

                </div>

            </div>

        </div>


        <div class="card">

            <h2>
                Job Information
            </h2>

            <div class="form-grid">

                <div class="form-group full">

                    <label for="job_title">
                        Job Title
                    </label>

                    <input
                        type="text"
                        name="job_title"
                        id="job_title"
                        value="<?= htmlspecialchars(
                            $jobTitle
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group full">

                    <label for="job_description">
                        Job Description
                    </label>

                    <textarea
                        name="job_description"
                        id="job_description"
                        required
                    ><?= htmlspecialchars(
                        $jobDescription
                    ) ?></textarea>

                </div>


                <div class="form-group full">

                    <label for="requirements">
                        Other Requirements / Notes
                    </label>

                    <textarea
                        name="requirements"
                        id="requirements"
                    ><?= htmlspecialchars(
                        $requirements
                    ) ?></textarea>

                </div>


                <div class="form-group full">

                    <label for="required_skills">
                        Required Skills
                    </label>

                    <textarea
                        name="required_skills"
                        id="required_skills"
                        placeholder="Example: Microsoft Excel, Data Encoding, Customer Service"
                        required
                    ><?= htmlspecialchars(
                        $requiredSkills
                    ) ?></textarea>

                    <small class="help">
                        Separate skills using commas.
                    </small>

                </div>


                <div class="form-group">

                    <label for="employment_type">
                        Employment Type
                    </label>

                    <select
                        name="employment_type"
                        id="employment_type"
                    >

                        <?php foreach (
                            [
                                "Full-time",
                                "Part-time",
                                "Contract",
                                "Internship",
                                "Freelance"
                            ]
                            as
                            $type
                        ): ?>

                            <option
                                value="<?= $type ?>"
                                <?= $employmentType === $type
                                    ? "selected"
                                    : ""
                                ?>
                            >
                                <?= $type ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label for="application_deadline">
                        Application Deadline
                    </label>

                    <input
                        type="date"
                        name="application_deadline"
                        id="application_deadline"
                        value="<?= htmlspecialchars(
                            $applicationDeadline
                        ) ?>"
                        required
                    >

                </div>


                <div class="form-group">

                    <label for="salary_min">
                        Minimum Salary
                    </label>

                    <input
                        type="number"
                        name="salary_min"
                        id="salary_min"
                        step="0.01"
                        min="0"
                        value="<?= htmlspecialchars(
                            $salaryMin
                        ) ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="salary_max">
                        Maximum Salary
                    </label>

                    <input
                        type="number"
                        name="salary_max"
                        id="salary_max"
                        step="0.01"
                        min="0"
                        value="<?= htmlspecialchars(
                            $salaryMax
                        ) ?>"
                    >

                </div>


                <div class="form-group full">

                    <label class="checkbox">

                        <input
                            type="checkbox"
                            name="is_remote"
                            value="1"
                            <?= $isRemote
                                ? "checked"
                                : ""
                            ?>
                        >

                        Remote work available

                    </label>

                </div>


                <div class="form-group full">

                    <label for="lgu_job_category">
                        Job Category
                    </label>

                    <input
                        type="text"
                        name="lgu_job_category"
                        id="lgu_job_category"
                        value="<?= htmlspecialchars(
                            $lguJobCategory
                        ) ?>"
                        placeholder="Example: Administrative, IT, Healthcare, Skilled Work"
                    >

                </div>

            </div>

        </div>


        <div class="card">

            <h2>
                Education Requirement
            </h2>

            <div class="form-group">

                <label for="requires_education">
                    Does this job require formal education?
                </label>

                <select
                    name="requires_education"
                    id="requires_education"
                    onchange="toggleEducation()"
                >

                    <option
                        value="no"
                        <?= $requiresEducation === "no"
                            ? "selected"
                            : ""
                        ?>
                    >
                        No
                    </option>

                    <option
                        value="yes"
                        <?= $requiresEducation === "yes"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Yes
                    </option>

                </select>

            </div>


            <div
                id="educationFields"
                class="optional"
            >

                <div class="form-grid">

                    <div class="form-group">

                        <label for="educational_requirement">
                            Educational Attainment
                        </label>

                        <select
                            name="educational_requirement"
                            id="educational_requirement"
                        >

                            <option value="">
                                Select
                            </option>

                            <?php foreach (
                                [
                                    "Elementary",
                                    "Junior High School",
                                    "Senior High School",
                                    "Vocational",
                                    "College Undergraduate",
                                    "College Graduate",
                                    "Postgraduate"
                                ]
                                as
                                $education
                            ): ?>

                                <option
                                    value="<?= $education ?>"
                                    <?= $educationalRequirement === $education
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    <?= $education ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="required_degree">
                            Required Degree / Program
                        </label>

                        <input
                            type="text"
                            name="required_degree"
                            id="required_degree"
                            value="<?= htmlspecialchars(
                                $requiredDegree
                            ) ?>"
                            placeholder="Example: BS Information Technology"
                        >

                    </div>


                    <div class="form-group full">

                        <label for="degree_classification">
                            Degree Classification / Field
                        </label>

                        <input
                            type="text"
                            name="degree_classification"
                            id="degree_classification"
                            value="<?= htmlspecialchars(
                                $degreeClassification
                            ) ?>"
                            placeholder="Example: Information Technology"
                        >

                    </div>

                </div>

            </div>

        </div>


        <div class="card">

            <h2>
                TESDA / NC Requirement
            </h2>

            <div class="form-group">

                <label for="requires_tesda">
                    Does this job require TESDA / NC?
                </label>

                <select
                    name="requires_tesda"
                    id="requires_tesda"
                    onchange="toggleTesda()"
                >

                    <option
                        value="no"
                        <?= $requiresTesda === "no"
                            ? "selected"
                            : ""
                        ?>
                    >
                        No
                    </option>

                    <option
                        value="yes"
                        <?= $requiresTesda === "yes"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Yes
                    </option>

                </select>

            </div>


            <div
                id="tesdaFields"
                class="optional"
            >

                <div class="form-grid">

                    <div class="form-group">

                        <label for="tesda_qualification">
                            TESDA Qualification
                        </label>

                        <input
                            type="text"
                            name="tesda_qualification"
                            id="tesda_qualification"
                            value="<?= htmlspecialchars(
                                $tesdaQualification
                            ) ?>"
                            placeholder="Example: Computer Systems Servicing"
                        >

                    </div>


                    <div class="form-group">

                        <label for="required_nc_level">
                            Required NC Level
                        </label>

                        <select
                            name="required_nc_level"
                            id="required_nc_level"
                        >

                            <option value="">
                                Select
                            </option>

                            <?php foreach (
                                [
                                    "NC I",
                                    "NC II",
                                    "NC III",
                                    "NC IV"
                                ]
                                as
                                $level
                            ): ?>

                                <option
                                    value="<?= $level ?>"
                                    <?= $requiredNcLevel === $level
                                        ? "selected"
                                        : ""
                                    ?>
                                >
                                    <?= $level ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                </div>

            </div>

        </div>


        <div class="card">

            <h2>
                Work Location
            </h2>

            <p>
                <strong>Address:</strong>
                <?= htmlspecialchars(
                    $jobAddress
                ) ?>
            </p>

            <p>
                <strong>Barangay:</strong>
                <?= htmlspecialchars(
                    $jobBarangay
                ) ?>
            </p>

            <p>
                <strong>Area:</strong>
                <?= ucfirst(
                    htmlspecialchars(
                        $jobArea
                    )
                ) ?>
                Caloocan
            </p>

            <small class="help">
                Job location is automatically taken
                from your verified employer profile.
            </small>

        </div>


        <div class="actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Submit for Approval
            </button>

            <a
                href="jobs.php"
                class="btn btn-secondary"
            >
                Cancel
            </a>

        </div>

    </form>

</div>


<script>

function toggleSourceFields() {
    const type =
        document.getElementById(
            "posting_type"
        ).value;

    const fields =
        document.getElementById(
            "sourceFields"
        );

    const sourceName =
        document.getElementById(
            "source_name"
        );

    if (type === "job_fair") {
        fields.style.display = "block";
        sourceName.required = true;
    } else {
        fields.style.display = "none";
        sourceName.required = false;
    }
}


function toggleEducation() {
    const value =
        document.getElementById(
            "requires_education"
        ).value;

    const fields =
        document.getElementById(
            "educationFields"
        );

    const attainment =
        document.getElementById(
            "educational_requirement"
        );

    if (value === "yes") {
        fields.style.display = "block";
        attainment.required = true;
    } else {
        fields.style.display = "none";
        attainment.required = false;
    }
}


function toggleTesda() {
    const value =
        document.getElementById(
            "requires_tesda"
        ).value;

    const fields =
        document.getElementById(
            "tesdaFields"
        );

    const qualification =
        document.getElementById(
            "tesda_qualification"
        );

    const nc =
        document.getElementById(
            "required_nc_level"
        );

    if (value === "yes") {
        fields.style.display = "block";
        qualification.required = true;
        nc.required = true;
    } else {
        fields.style.display = "none";
        qualification.required = false;
        nc.required = false;
    }
}


document.addEventListener(
    "DOMContentLoaded",
    function () {
        toggleSourceFields();
        toggleEducation();
        toggleTesda();
    }
);

</script>

</body>
</html>