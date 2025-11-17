<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Get raw JSON
$input = file_get_contents("php://input");

// Convert to associative array
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

// Example: save JSON to a file
file_put_contents("applications.json", json_encode($data, JSON_PRETTY_PRINT));

// Return response
echo json_encode([
    "status" => "success",
    "message" => "Application received",
    "pnr" => $data["pnr"]
]);
