# Self-Hosted-WordPress-Plugin-Update

Create your own self-hosted WordPress Plugin repository for pushing automatic updates.

## Quick Start

1) Place the `class-checker.php` file somewhere in your plugin directory and require it.
```php
require_once( 'class-checker.php' );
```
2) Hook the [init](https://codex.wordpress.org/Plugin_API/Action_Reference/init) function to initiatilize the update function when your plugin loads. Best put in your main `plugin.php` file:
```php
	// Load the auto-update class
	add_action( 'init', 'activate_plugin_update' );
	function activate_plugin_update() {
		require_once( 'update-checker/class-checker.php' );
		
		$license_key = get_option('sample_license_key');
		$updater = new Plugin_Updater_Checker(
			__FILE__,
			$license_key,
			'https://yoursite.com/update-server/class-checker.php'
		);
	}
```

The `license_key` fields are optional. You can use these to implement an auto-update functionility for specified customers only. It's left to the developer to implement this if needed.

Note that it's possible to store certain settings as a Wordpress [option](https://codex.wordpress.org/Options_API) like the `update_path` version.
If you do so, you can use `get_option()` to get fields like `update_path`, `license_key` directly from your plugin. This increases maintainability.

3) Create your server back-end to handle the update requests. You are fee to implement this any way you want, with any framework you want. 
The idea is that when Wordpress loads your plugin, it will check the given remote path to see if an update is availabe through the returned transient. For a basic implementation see the example below. 

Note however this example does not provide any protection or security, it serves as a demonstration purpose only.

```php
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
```

4) Make sure the `download_link` points to a `*.zip` file that holds the new version of your plugin. This `*.zip` file must have the same name as your WordPress plugin does. Also the `*.zip` file must NOT contain the plugin files directly, but must have a subfolder with the same name as your plugin to make WordPress play nicely with it.
e.g.:
```php
my-plugin.zip
     │
     └ my-plugin
           │
           ├ my-plugin.php
           ├ README.txt
           ├ uninstall.php
           ├ index.php
           ├ ..
           └ etc.
```
