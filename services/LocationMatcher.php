<?php

class LocationMatcher
{

    /*
    |--------------------------------------------------------------------------
    | CHECK IF TEXT CONTAINS CALOOCAN
    |--------------------------------------------------------------------------
    |
    | Existing function mo.
    | Pwede pa rin gamitin sa resume matching.
    |
    */

    public static function isCaloocan(
        string $resumeText
    ): bool {

        $resumeText =
            strtolower(
                trim($resumeText)
            );


        if ($resumeText === "") {
            return false;
        }


        $locations = [
            "caloocan city",
            "caloocan"
        ];


        foreach ($locations as $location) {

            if (
                strpos(
                    $resumeText,
                    $location
                ) !== false
            ) {
                return true;
            }
        }


        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | DISTANCE IN KILOMETERS
    |--------------------------------------------------------------------------
    |
    | Uses Haversine Formula.
    |
    | Applicant:
    | latitude1 / longitude1
    |
    | Job:
    | latitude2 / longitude2
    |
    */

    public static function distanceKm(
        $latitude1,
        $longitude1,
        $latitude2,
        $longitude2
    ): ?float {


        /*
        |--------------------------------------------------------------------------
        | VALIDATE COORDINATES
        |--------------------------------------------------------------------------
        */

        if (
            !is_numeric($latitude1) ||
            !is_numeric($longitude1) ||
            !is_numeric($latitude2) ||
            !is_numeric($longitude2)
        ) {
            return null;
        }


        $latitude1 =
            (float) $latitude1;

        $longitude1 =
            (float) $longitude1;

        $latitude2 =
            (float) $latitude2;

        $longitude2 =
            (float) $longitude2;


        /*
        |--------------------------------------------------------------------------
        | VALID RANGE
        |--------------------------------------------------------------------------
        */

        if (
            $latitude1 < -90 ||
            $latitude1 > 90 ||
            $latitude2 < -90 ||
            $latitude2 > 90 ||
            $longitude1 < -180 ||
            $longitude1 > 180 ||
            $longitude2 < -180 ||
            $longitude2 > 180
        ) {
            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | EARTH RADIUS
        |--------------------------------------------------------------------------
        */

        $earthRadiusKm = 6371;


        /*
        |--------------------------------------------------------------------------
        | CONVERT DEGREES TO RADIANS
        |--------------------------------------------------------------------------
        */

        $lat1 =
            deg2rad($latitude1);

        $lon1 =
            deg2rad($longitude1);

        $lat2 =
            deg2rad($latitude2);

        $lon2 =
            deg2rad($longitude2);


        /*
        |--------------------------------------------------------------------------
        | DIFFERENCE
        |--------------------------------------------------------------------------
        */

        $latitudeDifference =
            $lat2 - $lat1;

        $longitudeDifference =
            $lon2 - $lon1;


        /*
        |--------------------------------------------------------------------------
        | HAVERSINE FORMULA
        |--------------------------------------------------------------------------
        */

        $a =
            sin(
                $latitudeDifference / 2
            ) ** 2
            +
            cos($lat1)
            *
            cos($lat2)
            *
            sin(
                $longitudeDifference / 2
            ) ** 2;


        $c =
            2
            *
            atan2(
                sqrt($a),
                sqrt(1 - $a)
            );


        $distance =
            $earthRadiusKm
            *
            $c;


        return round(
            $distance,
            2
        );
    }

}