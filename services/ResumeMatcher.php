<?php

class ResumeMatcher
{
    /*
    |--------------------------------------------------------------------------
    | MAIN MATCH FUNCTION
    |--------------------------------------------------------------------------
    */
    public static function calculateSkillsMatch(
        string $requirements,
        string $resumeText
    ): array {

        // Get ONLY the Skills and Competencies section
        $applicantSkills =
            self::extractSkillsSection($resumeText);

        // Get employer requirements
        $jobRequirements =
            self::parseRequirements($requirements);


        if (count($jobRequirements) === 0) {
            return [
                "score" => 0,
                "matched_skills" => [],
                "missing_skills" => [],
                "resume_skills" => $applicantSkills
            ];
        }


        if (count($applicantSkills) === 0) {
            return [
                "score" => 0,
                "matched_skills" => [],
                "missing_skills" => $jobRequirements,
                "resume_skills" => []
            ];
        }


        $matchedSkills = [];
        $missingSkills = [];

        $totalScore = 0;


        foreach ($jobRequirements as $requirement) {

            $bestScore = 0;


            foreach ($applicantSkills as $resumeSkill) {

                $score =
                    self::calculateSimilarity(
                        $requirement,
                        $resumeSkill
                    );


                if ($score > $bestScore) {
                    $bestScore = $score;
                }
            }


            $totalScore += $bestScore;


            // 60% similarity = matched
            if ($bestScore >= 60) {

                $matchedSkills[] =
                    $requirement;

            } else {

                $missingSkills[] =
                    $requirement;
            }
        }


        $finalScore =
            $totalScore /
            count($jobRequirements);


        if ($finalScore < 0) {
            $finalScore = 0;
        }

        if ($finalScore > 100) {
            $finalScore = 100;
        }


        return [
            "score" => round($finalScore, 2),

            "matched_skills" =>
                array_values(
                    array_unique($matchedSkills)
                ),

            "missing_skills" =>
                array_values(
                    array_unique($missingSkills)
                ),

            "resume_skills" =>
                $applicantSkills
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | EXTRACT ONLY SKILLS AND COMPETENCIES
    |--------------------------------------------------------------------------
    */
    public static function extractSkillsSection(
        string $resumeText
    ): array {

        $resumeText =
            str_replace(
                ["\r\n", "\r"],
                "\n",
                $resumeText
            );


        /*
        |--------------------------------------------------------------------------
        | FIND SKILLS SECTION
        |--------------------------------------------------------------------------
        */

        $pattern =
            '/SKILLS\s+AND\s+COMPETENCIES(.*?)(?=' .
            'TRAININGS|' .
            'SEMINARS|' .
            'CERTIFICATIONS|' .
            'CHARACTER\s+REFERENCES|' .
            'REFERENCES|' .
            'DECLARATION|' .
            'WORK\s+EXPERIENCE|' .
            'EDUCATIONAL\s+BACKGROUND|' .
            '$)/is';


        if (
            !preg_match(
                $pattern,
                $resumeText,
                $matches
            )
        ) {

            return [];
        }


        $skillsText =
            trim($matches[1]);


        if ($skillsText === "") {
            return [];
        }


        /*
        |--------------------------------------------------------------------------
        | NORMALIZE SPACES
        |--------------------------------------------------------------------------
        */

        $skillsText =
            preg_replace(
                '/[ \t]+/',
                ' ',
                $skillsText
            );


        $skills = [];


        /*
        |--------------------------------------------------------------------------
        | TRY NUMBERED ITEMS
        |--------------------------------------------------------------------------
        |
        | Supports:
        |
        | 1. Customer Service
        | 2. Communication
        |
        | OR
        |
        | 1.
        | Customer Service
        |
        */

        preg_match_all(
            '/(?:^|\s)(\d{1,2})\s*[\.\)]\s*(.*?)(?=(?:\s+\d{1,2}\s*[\.\)])|$)/s',
            $skillsText,
            $numberedMatches
        );


        if (
            !empty(
                $numberedMatches[2]
            )
        ) {

            foreach (
                $numberedMatches[2]
                as
                $skill
            ) {

                $skill =
                    preg_replace(
                        '/\s+/',
                        ' ',
                        $skill
                    );

                $skill =
                    trim($skill);


                if ($skill === "") {
                    continue;
                }


                if (
                    mb_strlen($skill) > 120
                ) {
                    continue;
                }


                $skills[] =
                    $skill;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | FALLBACK: LINE BY LINE
        |--------------------------------------------------------------------------
        */

        if (count($skills) === 0) {

            $lines =
                preg_split(
                    '/\R+/',
                    $skillsText
                );


            foreach ($lines as $line) {

                $line =
                    trim($line);


                if ($line === "") {
                    continue;
                }


                // Remove 1. / 2. / - / bullet
                $line =
                    preg_replace(
                        '/^\s*(?:\d+\s*[\.\)]|[-*•–—])\s*/u',
                        '',
                        $line
                    );


                $line =
                    preg_replace(
                        '/\s+/',
                        ' ',
                        $line
                    );


                $line =
                    trim($line);


                if ($line === "") {
                    continue;
                }


                // Ignore standalone number
                if (
                    preg_match(
                        '/^\d+$/',
                        $line
                    )
                ) {
                    continue;
                }


                if (
                    mb_strlen($line) > 120
                ) {
                    continue;
                }


                $skills[] =
                    $line;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | REMOVE DUPLICATES
        |--------------------------------------------------------------------------
        */

        $unique = [];


        foreach ($skills as $skill) {

            $key =
                strtolower(
                    trim($skill)
                );


            if ($key === "") {
                continue;
            }


            if (
                !isset(
                    $unique[$key]
                )
            ) {

                $unique[$key] =
                    $skill;
            }
        }


        return array_values(
            $unique
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PARSE EMPLOYER REQUIREMENTS
    |--------------------------------------------------------------------------
    */
    private static function parseRequirements(
        string $requirements
    ): array {

        $requirements =
            str_replace(
                [
                    "\r\n",
                    "\r",
                    "\n",
                    "•",
                    "–",
                    "—",
                    ";",
                    "|"
                ],
                ",",
                $requirements
            );


        $parts =
            explode(
                ",",
                $requirements
            );


        $cleanRequirements = [];


        foreach ($parts as $requirement) {

            $requirement =
                trim($requirement);


            $requirement =
                preg_replace(
                    '/^(?:requirements?|skills?|required skills?|qualifications?)\s*:\s*/i',
                    '',
                    $requirement
                );


            $requirement =
                preg_replace(
                    '/^\s*(?:\d+\s*[\.\)]|[-*•–—])\s*/u',
                    '',
                    $requirement
                );


            $requirement =
                preg_replace(
                    '/\s+/',
                    ' ',
                    $requirement
                );


            $requirement =
                trim($requirement);


            if ($requirement === "") {
                continue;
            }


            $cleanRequirements[] =
                $requirement;
        }


        return array_values(
            array_unique(
                $cleanRequirements
            )
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CALCULATE SIMILARITY
    |--------------------------------------------------------------------------
    */
    private static function calculateSimilarity(
        string $jobSkill,
        string $resumeSkill
    ): float {

        $job =
            self::normalizeSkill(
                $jobSkill
            );

        $resume =
            self::normalizeSkill(
                $resumeSkill
            );


        if (
            $job === "" ||
            $resume === ""
        ) {

            return 0;
        }


        /*
        |--------------------------------------------------------------------------
        | EXACT MATCH
        |--------------------------------------------------------------------------
        */

        if ($job === $resume) {
            return 100;
        }


        /*
        |--------------------------------------------------------------------------
        | CANONICAL MATCH
        |--------------------------------------------------------------------------
        */

        $jobCanonical =
            self::canonicalSkill($job);

        $resumeCanonical =
            self::canonicalSkill($resume);


        if (
            $jobCanonical !== "" &&
            $jobCanonical === $resumeCanonical
        ) {

            return 100;
        }


        /*
        |--------------------------------------------------------------------------
        | PARTIAL CONTAINMENT
        |--------------------------------------------------------------------------
        */

        if (
            str_contains(
                $resume,
                $job
            ) ||
            str_contains(
                $job,
                $resume
            )
        ) {

            return 95;
        }


        /*
        |--------------------------------------------------------------------------
        | STRING SIMILARITY
        |--------------------------------------------------------------------------
        */

        similar_text(
            $jobCanonical,
            $resumeCanonical,
            $percent
        );


        /*
        |--------------------------------------------------------------------------
        | WORD OVERLAP
        |--------------------------------------------------------------------------
        */

        $jobWords =
            self::importantWords(
                $jobCanonical
            );

        $resumeWords =
            self::importantWords(
                $resumeCanonical
            );


        if (
            count($jobWords) === 0 ||
            count($resumeWords) === 0
        ) {

            return 0;
        }


        $common =
            array_intersect(
                $jobWords,
                $resumeWords
            );


        $commonCount =
            count(
                array_unique(
                    $common
                )
            );


        if ($commonCount === 0) {
            return 0;
        }


        $coverage =
            $commonCount /
            max(
                count($jobWords),
                1
            );


        $tokenScore =
            $coverage * 100;


        $final =
            max(
                $percent,
                $tokenScore
            );


        if ($final > 100) {
            $final = 100;
        }


        return round(
            $final,
            2
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CANONICAL SKILL NAMES
    |--------------------------------------------------------------------------
    */
    private static function canonicalSkill(
        string $skill
    ): string {

        $skill =
            self::normalizeSkill(
                $skill
            );


        /*
        | Customer Service
        */

        if (
            str_contains(
                $skill,
                "customer service"
            )
            ||
            str_contains(
                $skill,
                "customer support"
            )
        ) {

            return "customer service";
        }


        /*
        | Communication
        */

        if (
            str_contains(
                $skill,
                "communication"
            )
        ) {

            return "communication";
        }


        /*
        | Sales
        */

        if (
            str_contains(
                $skill,
                "sales"
            )
            ||
            str_contains(
                $skill,
                "selling"
            )
            ||
            str_contains(
                $skill,
                "product assistance"
            )
        ) {

            return "sales";
        }


        /*
        | Cash Handling
        */

        if (
            str_contains(
                $skill,
                "cash handling"
            )
            ||
            str_contains(
                $skill,
                "cashier"
            )
        ) {

            return "cash handling";
        }


        /*
        | POS
        */

        if (
            preg_match(
                '/\bpos\b/',
                $skill
            )
            ||
            str_contains(
                $skill,
                "point of sale"
            )
        ) {

            return "pos operation";
        }


        /*
        | Basic Computer Skills
        */

        if (
            str_contains(
                $skill,
                "computer"
            )
            ||
            str_contains(
                $skill,
                "microsoft word"
            )
            ||
            str_contains(
                $skill,
                "microsoft excel"
            )
            ||
            str_contains(
                $skill,
                "microsoft office"
            )
        ) {

            return "computer skills";
        }


        /*
        | Teamwork
        */

        if (
            str_contains(
                $skill,
                "teamwork"
            )
            ||
            str_contains(
                $skill,
                "team work"
            )
            ||
            str_contains(
                $skill,
                "team player"
            )
        ) {

            return "teamwork";
        }


        /*
        | Time Management
        */

        if (
            str_contains(
                $skill,
                "time management"
            )
        ) {

            return "time management";
        }


        /*
        | Inventory
        */

        if (
            str_contains(
                $skill,
                "inventory"
            )
        ) {

            return "inventory management";
        }


        /*
        | Problem Solving
        */

        if (
            str_contains(
                $skill,
                "problem solving"
            )
            ||
            str_contains(
                $skill,
                "problem-solving"
            )
        ) {

            return "problem solving";
        }


        return $skill;
    }


    /*
    |--------------------------------------------------------------------------
    | NORMALIZE
    |--------------------------------------------------------------------------
    */
    private static function normalizeSkill(
        string $text
    ): string {

        $text =
            mb_strtolower(
                $text
            );


        $text =
            str_replace(
                [
                    "point-of-sale",
                    "point of sale",
                    "(pos)"
                ],
                [
                    "pos",
                    "pos",
                    "pos"
                ],
                $text
            );


        $text =
            preg_replace(
                '/[^a-z0-9+#\.\s]/u',
                ' ',
                $text
            );


        $text =
            preg_replace(
                '/\s+/',
                ' ',
                $text
            );


        return trim($text);
    }


    /*
    |--------------------------------------------------------------------------
    | IMPORTANT WORDS
    |--------------------------------------------------------------------------
    */
    private static function importantWords(
        string $text
    ): array {

        $text =
            self::normalizeSkill(
                $text
            );


        $words =
            preg_split(
                '/\s+/',
                $text
            );


        $stopWords = [

            "and",
            "or",
            "the",
            "a",
            "an",
            "of",
            "to",
            "in",
            "for",
            "with",

            "good",
            "basic",
            "strong",
            "excellent",

            "skill",
            "skills",

            "experience",
            "experienced",

            "knowledge",
            "ability"

        ];


        $result = [];


        foreach ($words as $word) {

            $word =
                trim($word);


            if ($word === "") {
                continue;
            }


            if (
                in_array(
                    $word,
                    $stopWords,
                    true
                )
            ) {
                continue;
            }


            $result[] =
                $word;
        }


        return array_values(
            array_unique(
                $result
            )
        );
    }
}