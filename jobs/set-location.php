<?php 
session_start();

header("Content-Type: application/json");

$latitude = $_POST['latitude'] ?? null;
$longtitude = $_POST['longtitude'] ?? null;

if (!is_numeric($latitude) || !is_numeric($longtitude)) {
    echo json_encode(["success" => false, "message" => "Invalid Location."]);

    exit;
}

$latitude = (float) $latitude;
$longtitude = (float) $longtitude;

if ($latitude < -90 || $latitude > 90 || $longtitude < -180 || $longtitude > 180) {
    echo json_encode(["success" => false, "message" => "Invalid coordinates"]);

    exit;
}

$_SESSION["applicant_latitude"] = $latitude;

$_SESSION["applicant_longtitute"] = $longtitude;

echo json_encode(["success" => true]);

exit;
?>