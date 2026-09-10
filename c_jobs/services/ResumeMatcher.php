<?php

class ResumeMatcher {
    public static function calculateSkillsMatch(
        string $requirements,
        string $resumeText
    ): array {

        $requirements = strtolower($requirements);
        $resumeText = strtolower($resumeText);

        $requirements = str_replace(
            ["\r\n", "\r", "\n", "•", "–", "—", ";", "|"],
            ",",
            $requirements
        );

        $skills = array_filter(
            array_map(
                "trim",
                explode(",", $requirements)
            )
        );

        $cleanSkills = [];

        foreach ($skills as $skill) {

            $skill = trim($skill);

            $skill = preg_replace(
                '/^(requirements?|skills?|technical skills?)\s*:\s*/i',
                '',
                $skill
            );

            $skill = preg_replace(
                '/^[-*]+\s*/',
                '',
                $skill
            );

            $skill = trim($skill);

            if ($skill !== "") {
                $cleanSkills[] = $skill;
            }
        }

        $cleanSkills = array_values(
            array_unique($cleanSkills)
        );

        $matchedSkills = [];
        $missingSkills = [];

        foreach ($cleanSkills as $skill) {

            $skillLower = strtolower(trim($skill));

            if ($skillLower === "") {
                continue;
            }

            $pattern = '/(?<![a-z0-9])'
                . preg_quote($skillLower, '/')
                . '(?![a-z0-9])/i';

            if (preg_match($pattern, $resumeText)) {

                $matchedSkills[] = $skill;

            } else {

                $missingSkills[] = $skill;

            }
        }

        $totalSkills = count($cleanSkills);

        $matchedCount = count($matchedSkills);

        $score = 0;

        if ($totalSkills > 0) {

            $score =
                ($matchedCount / $totalSkills) * 100;
        }

        return [

            "score" => round($score, 2),

            "matched_skills" => $matchedSkills,

            "missing_skills" => $missingSkills

        ];
    }
}