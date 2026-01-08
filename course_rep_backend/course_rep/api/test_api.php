<?php
// API Test Script
// This file is for testing the API endpoints

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to make API requests
function makeRequest($endpoint, $method = 'GET', $data = null, $token = null) {
    $url = "http://localhost/course_rep/api/" . $endpoint;
    
    $ch = curl_init($url);
    
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "Error: " . curl_error($ch) . "\n";
    }
    
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => $response ? json_decode($response, true) : null
    ];
}

// Test login endpoint
echo "Testing login endpoint...\n";
$loginData = [
    'username' => 'course_rep_username', // Replace with actual test credentials
    'password' => 'password123'
];
$loginResult = makeRequest('login.php', 'POST', $loginData);
echo "Status Code: " . $loginResult['code'] . "\n";
echo "Response: " . print_r($loginResult['response'], true) . "\n\n";

// If login successful, test other endpoints
if ($loginResult['code'] === 200 && isset($loginResult['response']['data']['token'])) {
    $token = $loginResult['response']['data']['token'];
    
    // Test verify token endpoint
    echo "Testing verify token endpoint...\n";
    $verifyData = ['token' => $token];
    $verifyResult = makeRequest('verify_token.php', 'POST', $verifyData);
    echo "Status Code: " . $verifyResult['code'] . "\n";
    echo "Response: " . print_r($verifyResult['response'], true) . "\n\n";
    
    // Test get groups endpoint
    echo "Testing get groups endpoint...\n";
    $groupsResult = makeRequest('get_groups.php', 'GET', null, $token);
    echo "Status Code: " . $groupsResult['code'] . "\n";
    echo "Response: " . print_r($groupsResult['response'], true) . "\n\n";
    
    // If groups are returned, test get courses endpoint
    if ($groupsResult['code'] === 200 && !empty($groupsResult['response']['data']['groups'])) {
        $groupId = $groupsResult['response']['data']['groups'][0]['group_id'];
        
        echo "Testing get courses endpoint...\n";
        $coursesResult = makeRequest('get_courses.php?group_id=' . $groupId, 'GET', null, $token);
        echo "Status Code: " . $coursesResult['code'] . "\n";
        echo "Response: " . print_r($coursesResult['response'], true) . "\n\n";
        
        // If courses are returned, test start attendance session endpoint
        if ($coursesResult['code'] === 200 && !empty($coursesResult['response']['data']['courses'])) {
            $courseId = $coursesResult['response']['data']['courses'][0]['course_id'];
            
            echo "Testing start attendance session endpoint...\n";
            $startSessionData = [
                'group_id' => $groupId,
                'course_id' => $courseId,
                'location' => 'Test Location',
                'ble_id' => 'test_ble_device_123'
            ];
            $startSessionResult = makeRequest('start_attendance_session.php', 'POST', $startSessionData, $token);
            echo "Status Code: " . $startSessionResult['code'] . "\n";
            echo "Response: " . print_r($startSessionResult['response'], true) . "\n\n";
            
            // If session started successfully, test get active session endpoint
            if ($startSessionResult['code'] === 200 && isset($startSessionResult['response']['data']['session_id'])) {
                $sessionId = $startSessionResult['response']['data']['session_id'];
                
                echo "Testing get active session endpoint...\n";
                $activeSessionResult = makeRequest('get_active_session.php?group_id=' . $groupId . '&course_id=' . $courseId, 'GET', null, $token);
                echo "Status Code: " . $activeSessionResult['code'] . "\n";
                echo "Response: " . print_r($activeSessionResult['response'], true) . "\n\n";
                
                // Test end attendance session endpoint
                echo "Testing end attendance session endpoint...\n";
                $endSessionData = [
                    'session_id' => $sessionId,
                    'group_id' => $groupId,
                    'course_id' => $courseId
                ];
                $endSessionResult = makeRequest('end_attendance_session.php', 'POST', $endSessionData, $token);
                echo "Status Code: " . $endSessionResult['code'] . "\n";
                echo "Response: " . print_r($endSessionResult['response'], true) . "\n\n";
            }
        }
    }
}

echo "API testing completed.\n";
?>
