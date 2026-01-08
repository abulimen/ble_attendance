<?php
// Define the expected CSV headers for student import
$headers = ['application_id', 'matric_number', 'first_name', 'last_name', 'middle_name', 'email', 'level'];

// Set the filename for download
$filename = "student_import_sample.csv";

// Set headers to force download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Output the headers
fputcsv($output, $headers);

// Optional: Output one row of sample data (or leave blank)
// fputcsv($output, ['APP123', 'MAT456', 'John', 'Doe', 'M', 'john.doe@example.com', '100']);

// Close the file pointer
fclose($output);
exit;
?>
