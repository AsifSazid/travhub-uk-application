<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

require 'db_connection.php'; // your PDO connection

try {

    // Insert applications table
    $stmt = $pdo->prepare("INSERT INTO applications (pnr, name_of_applicant, total_applicants, status, timestamp) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['pnr'],
        $data['nameOfApplicant'],
        $data['totalApplicants'],
        $data['status'],
        date('Y-m-d H:i:s', strtotime($data['timestamp']))
    ]);

    // Insert applicants
    foreach ($data['applicants'] as $applicant) {

        // Debug dump (optional)
        file_put_contents("debug-applicant.txt", print_r($applicant, true));

        // Convert arrays to JSON (safe handling)
        $passport_info         = json_encode($applicant['passportInfo'] ?? []);
        $nid_info              = json_encode($applicant['nidInfo'] ?? []);
        $contact_info          = json_encode($applicant['contactInfo'] ?? []);
        $family_info           = json_encode($applicant['familyInfo'] ?? []);
        $accommodation_details = json_encode($applicant['accommodationDetails'] ?? []);
        $employment_info       = json_encode($applicant['employmentInfo'] ?? []);
        $income_expenditure    = json_encode($applicant['incomeExpenditure'] ?? []);
        $travel_info           = json_encode($applicant['travelInfo'] ?? []);
        $travel_history        = json_encode($applicant['travelHistory'] ?? []);

        // Prepare insert
        $stmt = $pdo->prepare("
        INSERT INTO applicants (
            pnr,
            user_pnr,
            completed,
            passport_info,
            nid_info,
            contact_info,
            family_info,
            accommodation_details,
            employment_info,
            income_expenditure,
            travel_info,
            travel_history
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

        // Execute insert
        $stmt->execute([
            $applicant['pnr'] ?? null,
            $applicant['user_pnr'] ?? null,
            ($applicant['completed'] ?? false) ? 1 : 0,
            $passport_info,
            $nid_info,
            $contact_info,
            $family_info,
            $accommodation_details,
            $employment_info,
            $income_expenditure,
            $travel_info,
            $travel_history
        ]);
    }


    echo json_encode(["status" => "success", "message" => "Data saved successfully"]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
