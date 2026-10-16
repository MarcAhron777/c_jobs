<?php

require_once __DIR__ . "/NotificationService.php";
require_once __DIR__ . "/ResumeMatcher.php";
require_once __DIR__ . "/QualificationMatcher.php";


class JobMatchNotificationService
{
    public static function notifyForApprovedJob(
        PDO $pdo,
        int $jobId,
        float $minimumScore = 60
    ): int {
        $stmt = $pdo->prepare("
            SELECT
                j.*,
                e.company_name
            FROM jobs j
            INNER JOIN employers e
                ON j.employer_id = e.id
            WHERE j.id = ?
            AND j.status = 'approved'
            LIMIT 1
        ");

        $stmt->execute([$jobId]);

        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            return 0;
        }

        $skillRequirements =
            trim(
                (string) (
                    $job["required_skills"]
                    ?? ""
                )
            );

        if ($skillRequirements === "") {
            $skillRequirements =
                trim(
                    (string) (
                        $job["requirements"]
                        ?? ""
                    )
                );
        }

        $stmt = $pdo->query("
            SELECT
                a.id,
                a.user_id,
                a.first_name,
                a.last_name,

                (
                    SELECT r.extracted_text
                    FROM resumes r
                    WHERE r.applicant_id = a.id
                    AND r.extracted_text IS NOT NULL
                    AND TRIM(r.extracted_text) != ''
                    ORDER BY r.uploaded_at DESC, r.id DESC
                    LIMIT 1
                ) AS resume_text

            FROM applicants a

            INNER JOIN users u
                ON a.user_id = u.id

            WHERE u.is_active = 1
        ");

        $applicants =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notificationCount = 0;

        foreach ($applicants as $applicant) {
            $resumeText =
                trim(
                    (string) (
                        $applicant["resume_text"]
                        ?? ""
                    )
                );

            if ($resumeText === "") {
                continue;
            }

            $applicantId =
                (int) $applicant["id"];

            $userId =
                (int) $applicant["user_id"];


            /*
            |--------------------------------------------------------------------------
            | EDUCATION
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM applicant_education
                WHERE applicant_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");

            $stmt->execute([$applicantId]);

            $education =
                $stmt->fetch(PDO::FETCH_ASSOC)
                ?: null;


            /*
            |--------------------------------------------------------------------------
            | TESDA
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT *
                FROM applicant_tesda_qualifications
                WHERE applicant_id = ?
                ORDER BY id DESC
            ");

            $stmt->execute([$applicantId]);

            $tesdaRows =
                $stmt->fetchAll(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | SKILLS MATCH
            |--------------------------------------------------------------------------
            */

            if ($skillRequirements !== "") {
                $skillsResult =
                    ResumeMatcher::calculateSkillsMatch(
                        $skillRequirements,
                        $resumeText
                    );
            } else {
                $skillsResult = [
                    "score" => 100,
                    "matched_skills" => [],
                    "missing_skills" => []
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | QUALIFICATION MATCH
            |--------------------------------------------------------------------------
            */

            $qualificationResult =
                QualificationMatcher::calculate(
                    $job,
                    $education,
                    $tesdaRows
                );


            /*
            |--------------------------------------------------------------------------
            | REQUIREMENT FLAGS
            |--------------------------------------------------------------------------
            */

            $hasSkills =
                $skillRequirements !== "";

            $hasEducation =
                trim(
                    (string) (
                        $job["educational_requirement"]
                        ?? ""
                    )
                ) !== ""
                ||
                trim(
                    (string) (
                        $job["required_degree"]
                        ?? ""
                    )
                ) !== ""
                ||
                trim(
                    (string) (
                        $job["degree_classification"]
                        ?? ""
                    )
                ) !== "";

            $requiredNc =
                trim(
                    (string) (
                        $job["required_nc_level"]
                        ?? ""
                    )
                );

            $hasTesda =
                trim(
                    (string) (
                        $job["tesda_qualification"]
                        ?? ""
                    )
                ) !== ""
                ||
                !in_array(
                    $requiredNc,
                    [
                        "",
                        "No NC",
                        "No NC Required"
                    ],
                    true
                );


            /*
            |--------------------------------------------------------------------------
            | SAME WEIGHTING USED BY APPLICATION MATCHING
            |--------------------------------------------------------------------------
            */

            $weightedTotal = 0;
            $weightTotal = 0;

            if ($hasSkills) {
                $weightedTotal +=
                    (float) (
                        $skillsResult["score"]
                        ?? 0
                    ) * 50;

                $weightTotal += 50;
            }

            if ($hasEducation) {
                $weightedTotal +=
                    (float) (
                        $qualificationResult[
                            "education_score"
                        ]
                        ?? 0
                    ) * 25;

                $weightTotal += 25;
            }

            if ($hasTesda) {
                $weightedTotal +=
                    (float) (
                        $qualificationResult[
                            "tesda_score"
                        ]
                        ?? 0
                    ) * 25;

                $weightTotal += 25;
            }

            if ($weightTotal <= 0) {
                continue;
            }

            $matchScore =
                round(
                    $weightedTotal /
                    $weightTotal,
                    2
                );

            if ($matchScore < $minimumScore) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | PREVENT DUPLICATE MATCH NOTIFICATION
            |--------------------------------------------------------------------------
            */

            if (
                NotificationService::exists(
                    $pdo,
                    $userId,
                    "job_match",
                    $jobId
                )
            ) {
                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | CREATE NOTIFICATION
            |--------------------------------------------------------------------------
            */

            $message =
                'A new job, "'
                . $job["job_title"]
                . '" from '
                . $job["company_name"]
                . " matches your qualifications at "
                . number_format(
                    $matchScore,
                    0
                )
                . "%.";

            NotificationService::create(
                $pdo,
                $userId,
                "New Matching Job",
                $message,
                "job_match",
                $jobId
            );

            $notificationCount++;
        }

        return $notificationCount;
    }
}