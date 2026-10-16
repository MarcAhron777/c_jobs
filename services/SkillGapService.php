<?php

class SkillGapService
{
    public static function build(array $missingSkills, array $missingQualifications): string
    {
        $lines = [];

        if ($missingSkills) {
            $lines[] = 'Improve or demonstrate these job-related skills: ' . implode(', ', $missingSkills) . '.';
        }

        if ($missingQualifications) {
            $lines[] = 'Qualifications to complete or strengthen: ' . implode(', ', $missingQualifications) . '.';
        }

        if (!$lines) {
            return 'No major skill or qualification gap was detected from the information currently saved in your profile and resume.';
        }

        $lines[] = 'Update your profile, qualifications, and resume after gaining the missing skills or credentials so future matches can reflect the change.';

        return implode("\n", $lines);
    }
}
