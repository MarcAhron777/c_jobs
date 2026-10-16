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

$jobId =
    isset($_GET["id"])
        ? (int) $_GET["id"]
        : 0;

$error = "";

if ($jobId <= 0) {
    header("Location: jobs.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| EMPLOYER
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
| JOB
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM jobs
    WHERE id = ?
    AND employer_id = ?
    LIMIT 1
");

$stmt->execute([
    $jobId,
    $employerId
]);

$job =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    die("Job not found.");
}


/*
|--------------------------------------------------------------------------
| INITIAL VALUES
|--------------------------------------------------------------------------
*/

$jobTitle =
    $job["job_title"]
    ?? "";

$jobDescription =
    $job["job_description"]
    ?: ($job["description"] ?? "");

$requirements =
    $job["requirements"]
    ?? "";

$requiredSkills =
    $job["required_skills"]
    ?? "";

$employmentType =
    $job["employment_type"]
    ?? "Full-time";

$salaryMin =
    $job["salary_min"]
    ?? "";

$salaryMax =
    $job["salary_max"]
    ?? "";

$applicationDeadline =
    $job["application_deadline"]
    ?? "";

$isRemote =
    (int) (
        $job["is_remote"]
        ?? 0
    );

$postingType =
    ($job["job_source"] ?? "")
    === "job_fair"
        ? "job_fair"
        : "regular";

$sourceName =
    $job["source_name"]
    ?? "";

$sourceUrl =
    $job["source_url"]
    ?? "";

$lguJobCategory =
    $job["lgu_job_category"]
    ?? "";

$educationalRequirement =
    $job["educational_requirement"]
    ?? "";

$requiredDegree =
    $job["required_degree"]
    ?? "";

$degreeClassification =
    $job["degree_classification"]
    ?? "";

$tesdaQualification =
    $job["tesda_qualification"]
    ?? "";

$requiredNcLevel =
    $job["required_nc_level"]
    ?? "";

$requiresEducation =
    (
        trim($educationalRequirement) !== ""
        ||
        trim($requiredDegree) !== ""
        ||
        trim($degreeClassification) !== ""
    )
        ? "yes"
        : "no";

$requiresTesda =
    (
        trim($tesdaQualification) !== ""
        ||
        trim($requiredNcLevel) !== ""
    )
        ? "yes"
        : "no";


/*
|--------------------------------------------------------------------------
| UPDATE
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
                "Invalid source URL."
            );
        }


        if ($requiresEducation === "no") {
            $educationalRequirement = "";
            $requiredDegree = "";
            $degreeClassification = "";
        } elseif (
            $educationalRequirement === ""
        ) {
            throw new Exception(
                "Please select the educational requirement."
            );
        }


        if ($requiresTesda === "no") {
            $tesdaQualification = "";
            $requiredNcLevel = "";
        } else {
            if ($tesdaQualification === "") {
                throw new Exception(
                    "TESDA qualification is required."
                );
            }

            if ($requiredNcLevel === "") {
                throw new Exception(
                    "NC level is required."
                );
            }
        }


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
                "Minimum salary cannot exceed maximum salary."
            );
        }


        if ($applicationDeadline === "") {
            throw new Exception(
                "Application deadline is required."
            );
        }


        $location =
            "Brgy. "
            . $employer["barangay"]
            . ", "
            . ucfirst(
                $employer["caloocan_area"]
            )
            . " Caloocan";


        /*
        |--------------------------------------------------------------------------
        | EDITED JOB RETURNS TO PENDING
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            UPDATE jobs
            SET
                job_title = ?,
                job_description = ?,
                description = ?,
                requirements = ?,
                educational_requirement = ?,
                required_degree = ?,
                required_skills = ?,
                location = ?,
                address = ?,
                barangay = ?,
                caloocan_area = ?,
                latitude = ?,
                longtitude = ?,
                employment_type = ?,
                salary_min = ?,
                salary_max = ?,
                is_remote = ?,
                status = 'pending',
                application_deadline = ?,
                tesda_qualification = ?,
                required_nc_level = ?,
                job_source = ?,
                source_name = ?,
                source_url = ?,
                degree_classification = ?,
                lgu_job_category = ?,
                updated_at = NOW()
            WHERE id = ?
            AND employer_id = ?
        ");

        $stmt->execute([
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
            $employer["address"],
            $employer["barangay"],
            $employer["caloocan_area"],
            $employer["latitude"],
            $employer["longitude"],
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
                : null,

            $jobId,
            $employerId
        ]);

        header(
            "Location: jobs.php?updated=1"
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
    Edit Job - Employer
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

.card {
    margin-bottom: 20px;
    padding: 25px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 9px;
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
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    font-family: inherit;
}

textarea {
    min-height: 110px;
    resize: vertical;
}

.optional {
    padding: 18px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 7px;
}

.notice {
    margin-bottom: 20px;
    padding: 14px;
    background: #fef3c7;
    color: #92400e;
    border-radius: 7px;
}

.error {
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

.btn {
    display: inline-block;
    padding: 11px 16px;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    cursor: pointer;
    font-weight: bold;
}

.primary {
    background: #2563eb;
    color: #ffffff;
}

.secondary {
    background: #e2e8f0;
    color: #334155;
}

.actions {
    display: flex;
    gap: 10px;
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

    <h1>
        Edit Job
    </h1>

    <div class="notice">
        Editing an approved job will return it to
        Pending status for administrator review.
    </div>

    <?php if ($error): ?>

        <div class="error">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>


    <form method="POST">


        <div class="card">

            <h2>
                Posting Source
            </h2>

            <div class="form-group">

                <label>
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

                    <label>
                        Job Fair / Source Name
                    </label>

                    <input
                        type="text"
                        name="source_name"
                        id="source_name"
                        value="<?= htmlspecialchars(
                            $sourceName
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Official / Reference URL
                    </label>

                    <input
                        type="url"
                        name="source_url"
                        value="<?= htmlspecialchars(
                            $sourceUrl
                        ) ?>"
                    >

                </div>

            </div>

        </div>


        <div class="card">

            <h2>
                Job Information
            </h2>

            <div class="form-grid">

                <div class="form-group full">

                    <label>
                        Job Title
                    </label>

                    <input
                        type="text"
                        name="job_title"
                        value="<?= htmlspecialchars(
                            $jobTitle
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group full">

                    <label>
                        Job Description
                    </label>

                    <textarea
                        name="job_description"
                        required
                    ><?= htmlspecialchars(
                        $jobDescription
                    ) ?></textarea>

                </div>

                <div class="form-group full">

                    <label>
                        Other Requirements
                    </label>

                    <textarea
                        name="requirements"
                    ><?= htmlspecialchars(
                        $requirements
                    ) ?></textarea>

                </div>

                <div class="form-group full">

                    <label>
                        Required Skills
                    </label>

                    <textarea
                        name="required_skills"
                        required
                    ><?= htmlspecialchars(
                        $requiredSkills
                    ) ?></textarea>

                </div>

                <div class="form-group">

                    <label>
                        Employment Type
                    </label>

                    <select name="employment_type">

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

                    <label>
                        Application Deadline
                    </label>

                    <input
                        type="date"
                        name="application_deadline"
                        value="<?= htmlspecialchars(
                            $applicationDeadline
                        ) ?>"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Minimum Salary
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        name="salary_min"
                        value="<?= htmlspecialchars(
                            $salaryMin
                        ) ?>"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Maximum Salary
                    </label>

                    <input
                        type="number"
                        step="0.01"
                        name="salary_max"
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

                    <label>
                        Job Category
                    </label>

                    <input
                        type="text"
                        name="lgu_job_category"
                        value="<?= htmlspecialchars(
                            $lguJobCategory
                        ) ?>"
                    >

                </div>

            </div>

        </div>


        <div class="card">

            <h2>
                Education Requirement
            </h2>

            <div class="form-group">

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
                        No Education Requirement
                    </option>

                    <option
                        value="yes"
                        <?= $requiresEducation === "yes"
                            ? "selected"
                            : ""
                        ?>
                    >
                        Education Required
                    </option>

                </select>

            </div>


            <div
                id="educationFields"
                class="optional"
            >

                <div class="form-grid">

                    <div class="form-group">

                        <label>
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

                        <label>
                            Required Degree / Program
                        </label>

                        <input
                            type="text"
                            name="required_degree"
                            value="<?= htmlspecialchars(
                                $requiredDegree
                            ) ?>"
                        >

                    </div>

                    <div class="form-group full">

                        <label>
                            Degree Classification
                        </label>

                        <input
                            type="text"
                            name="degree_classification"
                            value="<?= htmlspecialchars(
                                $degreeClassification
                            ) ?>"
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
                        No TESDA / NC Requirement
                    </option>

                    <option
                        value="yes"
                        <?= $requiresTesda === "yes"
                            ? "selected"
                            : ""
                        ?>
                    >
                        TESDA / NC Required
                    </option>

                </select>

            </div>


            <div
                id="tesdaFields"
                class="optional"
            >

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            TESDA Qualification
                        </label>

                        <input
                            type="text"
                            name="tesda_qualification"
                            id="tesda_qualification"
                            value="<?= htmlspecialchars(
                                $tesdaQualification
                            ) ?>"
                        >

                    </div>

                    <div class="form-group">

                        <label>
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


        <div class="actions">

            <button
                type="submit"
                class="btn primary"
            >
                Update Job
            </button>

            <a
                href="jobs.php"
                class="btn secondary"
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

    const source =
        document.getElementById(
            "source_name"
        );

    if (type === "job_fair") {
        fields.style.display = "block";
        source.required = true;
    } else {
        fields.style.display = "none";
        source.required = false;
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

    const education =
        document.getElementById(
            "educational_requirement"
        );

    if (value === "yes") {
        fields.style.display = "block";
        education.required = true;
    } else {
        fields.style.display = "none";
        education.required = false;
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