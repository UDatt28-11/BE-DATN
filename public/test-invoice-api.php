<?php
// Test API
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "http://127.0.0.1:8000/api/invoices/create-from-booking",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => json_encode([
        'booking_order_id' => 1,
        'due_date' => '2025-12-18',
        'notes' => 'Test invoice'
    ]),
    CURLOPT_HTTPHEADER => array(
        "Content-Type: application/json"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err;
} else {
    echo "Response:\n";
    echo json_encode(json_decode($response), JSON_PRETTY_PRINT);
}
?>
