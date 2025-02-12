<?php
// Strict error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');  // Enable cross-origin requests if needed

try {
    // Receive JSON data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate data
    if (!$data) {
        throw new Exception('Invalid data', 400);
    }

    // Required data keys
    $required_keys = ['action', 'license_key', 'domain', 'version'];
    foreach ($required_keys as $key) {
        if (!isset($data[$key])) {
            throw new Exception("Missing required key: {$key}", 400);
        }
    }

    // Prepare plugin details
    $plugin_details = [
        'name' => 'Sample Plugin Update Checker - Update Server',
        'slug' => 'sample-update-checker-server-plugin',
        "author" => "<a href='https://yoursite.com'>Cao Nguyễn</a>",
        'version' => '1.1.0',
        'last_updated' => date('Y-m-d H:i:s'),
        'new_version' => '1.1.0',
        'url' => 'https://yoursite.com/plugin-page',
        'package' => 'https://yoursite.com/downloads/your-plugin-1.1.0.zip',
        'tested' => '6.3',
        'requires' => '6.0',
        'sections' => [
            'description' => 'Plugin description',
            'changelog' => 'Version 1.1.0 changes...'
        ],
        'banners' => [
    		'low' => 'https://yoursite.com/wp-content/uploads/updater/banner-772x250.jpg',
    		'high' => 'https://yoursite.com/wp-content/uploads/updater/banner-1544x500.jpg'
    	]
    ];

    // Handle different actions
    switch ($data['action']) {
        case 'version':
            $response = [
                'new_version' => $plugin_details['new_version'],
                'tested' => $plugin_details['tested'],
                'package' => $plugin_details['package']
            ];
            break;

        case 'info':
            $response = $plugin_details;
            break;

        default:
            throw new Exception('Invalid action', 400);
    }

    // Send successful response
    http_response_code(200);
    echo json_encode($response);
    exit;

} catch (Exception $e) {
    // Log the error
    error_log('Plugin Update Error: ' . $e->getMessage());

    // Send error response
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    exit;
}
