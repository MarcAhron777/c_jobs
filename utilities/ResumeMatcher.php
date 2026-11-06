<?php

class ResumeMatcher
{
    public static function calculateSkillsMatch(string $requirements, string $resumeText): array
    {
        $requiredSkills = self::parseRequiredSkills($requirements);
        $resumeSkillsText = self::extractSkillsSection($resumeText);

        if (!$requiredSkills) {
            return [
                'score' => 100.0,
                'matched_skills' => [],
                'missing_skills' => [],
            ];
        }

        if ($resumeSkillsText === '') {
            return [
                'score' => 0.0,
                'matched_skills' => [],
                'missing_skills' => $requiredSkills,
            ];
        }

        $matched = [];
        $missing = [];

        foreach ($requiredSkills as $skill) {
            if (self::skillExists($skill, $resumeSkillsText)) {
                $matched[] = $skill;
            } else {
                $missing[] = $skill;
            }
        }

        $score = (count($matched) / count($requiredSkills)) * 100;

        return [
            'score' => round($score, 2),
            'matched_skills' => $matched,
            'missing_skills' => $missing,
        ];
    }

    public static function extractSkillsSection(string $resumeText): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $resumeText);

        $patterns = [
            '/SKILLS\s+AND\s+COMPETENCIES\s*(.*?)(?=\n\s*(?:TRAININGS?|SEMINARS?|CERTIFICATIONS?|WORK EXPERIENCE|EDUCATIONAL BACKGROUND|CHARACTER REFERENCES|DECLARATION)\b|$)/is',
            '/\bSKILLS\b\s*(.*?)(?=\n\s*(?:TRAININGS?|SEMINARS?|CERTIFICATIONS?|WORK EXPERIENCE|EDUCATIONAL BACKGROUND|CHARACTER REFERENCES|DECLARATION)\b|$)/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match)) {
                return trim((string)($match[1] ?? ''));
            }
        }

        return '';
    }

    private static function parseRequiredSkills(string $requirements): array
    {
        $requirements = str_replace(["\r\n", "\r", '•', '·', '|'], ["\n", "\n", "\n", "\n", "\n"], $requirements);
        $parts = preg_split('/[,;\n]+/', $requirements) ?: [];
        $skills = [];

        foreach ($parts as $part) {
            $part = trim(preg_replace('/^[-*\d\.\)\s]+/', '', $part));
            if ($part === '') continue;
            if (strlen($part) > 120) {
                $sub = preg_split('/\band\b/i', $part) ?: [$part];
                foreach ($sub as $s) {
                    $s = trim($s);
                    if ($s !== '') $skills[] = $s;
                }
            } else {
                $skills[] = $part;
            }
        }

        return array_values(array_unique($skills));
    }

    private static function skillExists(string $requiredSkill, string $resumeSkillsText): bool
    {
        $required = self::canonical($requiredSkill);
        $resume = self::canonical($resumeSkillsText);

        if ($required === '' || $resume === '') return false;
        if (str_contains($resume, $required)) return true;

        $aliases = [
            'communication skills' => ['communication', 'verbal communication', 'written communication', 'verbal and written communication'],
            'customer service' => ['customer service', 'customer support', 'client service'],
            'sales' => ['sales', 'selling', 'product assistance'],
            'pos operation' => ['pos operation', 'point of sale', 'point of sale operation'],
            'basic computer skills' => ['computer skills', 'microsoft word', 'microsoft excel', 'microsoft office'],
            'cash handling' => ['cash handling', 'cashier'],
            'teamwork' => ['teamwork', 'team player', 'collaboration'],
            'time management' => ['time management'],
        ];

        foreach ($aliases as $canonical => $variants) {
            $reqHit = ($required === $canonical) || in_array($required, $variants, true);
            if (!$reqHit) continue;
            foreach ($variants as $variant) {
                if (str_contains($resume, self::canonical($variant))) return true;
            }
        }

        $reqWords = array_values(array_filter(explode(' ', $required), fn($w) => strlen($w) > 2));
        if (!$reqWords) return false;

        $hits = 0;
        foreach ($reqWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/', $resume)) $hits++;
        }

        return ($hits / count($reqWords)) >= 0.75;
    }

    private static function canonical(string $value): string
    {
        $value = strtolower($value);
        $value = str_replace(['&', '/', '-', '(', ')'], [' and ', ' ', ' ', ' ', ' '], $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
