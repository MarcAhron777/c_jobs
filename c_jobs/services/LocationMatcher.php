<?php 

class LocationMatcher {
    public static function isCaloocan(string $resumeText): bool {

        $resumeText = strtolower(trim($resumeText));

        if ($resumeText === "") {
            return false;
        }

        $locations = [
            "caloocan city",
            "caloocan"
        ];

        foreach ($locations as $location) {
            if (strpos($resumeText, $location) !== false) {
                return true;
            }
        }

        return false;
    }
}