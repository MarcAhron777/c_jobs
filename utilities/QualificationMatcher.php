<?php

class QualificationMatcher
{
    private static array $educationRank = [
        'No Formal Requirement' => 0,
        'Elementary' => 1,
        'Junior High School' => 2,
        'Senior High School' => 3,
        'Vocational' => 4,
        'College Undergraduate' => 5,
        'College Graduate' => 6,
        'Postgraduate' => 7,
    ];

    public static function calculate(array $job, ?array $education, array $tesdaRows): array
    {
        $matched = [];
        $missing = [];
        $explanations = [];

        $educationScore = self::educationScore($job, $education, $matched, $missing, $explanations);
        $tesdaScore = self::tesdaScore($job, $tesdaRows, $matched, $missing, $explanations);

        return [
            'education_score' => round($educationScore, 2),
            'tesda_score' => round($tesdaScore, 2),
            'matched_qualifications' => array_values(array_unique($matched)),
            'missing_qualifications' => array_values(array_unique($missing)),
            'explanation' => implode("\n", $explanations),
        ];
    }

    private static function educationScore(array $job, ?array $education, array &$matched, array &$missing, array &$explanations): float
    {
        $requiredLevel = trim((string)($job['educational_requirement'] ?? ''));
        $requiredDegree = trim((string)($job['required_degree'] ?? ''));

        if ($requiredLevel === '' && $requiredDegree === '') {
            $explanations[] = 'Education: no specific education requirement was set by the employer.';
            return 100.0;
        }

        if (!$education) {
            if ($requiredLevel !== '') $missing[] = 'Education: ' . $requiredLevel;
            if ($requiredDegree !== '') $missing[] = 'Degree/Program: ' . $requiredDegree;
            $explanations[] = 'Education: applicant has no saved education record.';
            return 0.0;
        }

        $parts = [];

        if ($requiredLevel !== '' && $requiredLevel !== 'No Formal Requirement') {
            $applicantLevel = trim((string)($education['educational_attainment'] ?? ''));
            $requiredRank = self::$educationRank[$requiredLevel] ?? null;
            $applicantRank = self::$educationRank[$applicantLevel] ?? null;

            $levelMatched = $requiredRank !== null && $applicantRank !== null && $applicantRank >= $requiredRank;
            $parts[] = $levelMatched ? 100.0 : 0.0;

            if ($levelMatched) {
                $matched[] = 'Education level: ' . $applicantLevel;
                $explanations[] = 'Education level matched: applicant has ' . $applicantLevel . ', which meets the required ' . $requiredLevel . '.';
            } else {
                $missing[] = 'Education level: ' . $requiredLevel;
                $explanations[] = 'Education level not matched: job requires ' . $requiredLevel . ', while applicant saved ' . ($applicantLevel !== '' ? $applicantLevel : 'no level') . '.';
            }
        }

        if ($requiredDegree !== '') {
            $applicantDegree = trim((string)($education['degree_program'] ?? ''));
            $degreeMatched = self::similar($requiredDegree, $applicantDegree);
            $parts[] = $degreeMatched ? 100.0 : 0.0;

            if ($degreeMatched) {
                $matched[] = 'Degree/Program: ' . $applicantDegree;
                $explanations[] = 'Degree/program matched the employer requirement.';
            } else {
                $missing[] = 'Degree/Program: ' . $requiredDegree;
                $explanations[] = 'Degree/program requirement is not matched. Required: ' . $requiredDegree . '; applicant saved: ' . ($applicantDegree !== '' ? $applicantDegree : 'none') . '.';
            }
        }

        return $parts ? array_sum($parts) / count($parts) : 100.0;
    }

    private static function tesdaScore(array $job, array $tesdaRows, array &$matched, array &$missing, array &$explanations): float
    {
        $requiredTesda = trim((string)($job['tesda_qualification'] ?? ''));
        $requiredNc = trim((string)($job['required_nc_level'] ?? ''));

        if ($requiredTesda === '' && ($requiredNc === '' || $requiredNc === 'No NC Required')) {
            $explanations[] = 'TESDA: no specific TESDA qualification or NC level was required.';
            return 100.0;
        }

        $qualificationMatched = ($requiredTesda === '');
        $ncMatched = ($requiredNc === '' || $requiredNc === 'No NC Required');
        $matchedRow = null;

        foreach ($tesdaRows as $row) {
            $name = trim((string)($row['qualification_name'] ?? ''));
            $nc = trim((string)($row['nc_level'] ?? 'No NC'));

            if ($requiredTesda === '' || self::similar($requiredTesda, $name)) {
                $qualificationMatched = true;
                $matchedRow = $row;

                if ($requiredNc === '' || $requiredNc === 'No NC Required') {
                    $ncMatched = true;
                } else {
                    $ncMatched = self::ncRank($nc) >= self::ncRank($requiredNc);
                }

                if ($ncMatched) break;
            }
        }

        $parts = [];

        if ($requiredTesda !== '') {
            $parts[] = $qualificationMatched ? 100.0 : 0.0;
            if ($qualificationMatched) {
                $matched[] = 'TESDA: ' . trim((string)($matchedRow['qualification_name'] ?? $requiredTesda));
                $explanations[] = 'TESDA qualification matched: ' . trim((string)($matchedRow['qualification_name'] ?? $requiredTesda)) . '.';
            } else {
                $missing[] = 'TESDA qualification: ' . $requiredTesda;
                $explanations[] = 'TESDA qualification not matched. Required: ' . $requiredTesda . '.';
            }
        }

        if ($requiredNc !== '' && $requiredNc !== 'No NC Required') {
            $parts[] = $ncMatched ? 100.0 : 0.0;
            if ($ncMatched) {
                $matched[] = 'NC Level: ' . trim((string)($matchedRow['nc_level'] ?? $requiredNc));
                $explanations[] = 'NC level meets the employer requirement of ' . $requiredNc . '.';
            } else {
                $missing[] = 'NC Level: ' . $requiredNc;
                $explanations[] = 'Required NC level is ' . $requiredNc . ' and the applicant does not have a matching or higher saved level.';
            }
        }

        return $parts ? array_sum($parts) / count($parts) : 100.0;
    }

    private static function normalize(string $value): string
    {
        $value = strtolower($value);
        $value = str_replace(['bachelor of science in', 'bachelor of science', 'b.s.', 'bs '], ' ', $value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private static function similar(string $a, string $b): bool
    {
        $a = self::normalize($a);
        $b = self::normalize($b);
        if ($a === '' || $b === '') return false;
        if ($a === $b || str_contains($a, $b) || str_contains($b, $a)) return true;

        $aWords = array_values(array_filter(explode(' ', $a), fn($w) => strlen($w) > 2));
        $bWords = array_values(array_filter(explode(' ', $b), fn($w) => strlen($w) > 2));
        if (!$aWords || !$bWords) return false;

        $common = array_intersect($aWords, $bWords);
        $ratio = count($common) / max(1, min(count($aWords), count($bWords)));
        return $ratio >= 0.6;
    }

    private static function ncRank(string $nc): int
    {
        return match (strtoupper(trim($nc))) {
            'NC I' => 1,
            'NC II' => 2,
            'NC III' => 3,
            'NC IV' => 4,
            default => 0,
        };
    }
}
