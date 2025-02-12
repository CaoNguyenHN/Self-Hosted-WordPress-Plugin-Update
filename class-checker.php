<?php
defined( 'ABSPATH' ) or die( 'Keep Silent' );
	
class Plugin_Updater_Checker {
    /**
     * @var string
     */
    private string $update_path;
    
    /**
     * @var string
     */
    private string $plugin_slug;
    
    /**
     * @var string 
     */
    private string $version;
    
    /**
     * @var string
     */
    private string $license_key;
    
    /**
     * @var array
     */
    private array $plugin_data;
    
    /**
     * @var string 
     */
    private string $cache_key;
    
    /**
     * @var bool
     */
    private bool $cache_enabled = true;
    
    /**
     * @var int Cache duration in seconds
     */
    private const CACHE_DURATION = 12 * HOUR_IN_SECONDS;
    
    /**
     * @param string $plugin_file
     * @param string $license_key
     * @param string $update_path
     * @param array $args
     */
    public function __construct(
        string $plugin_file,
        string $license_key,
        string $update_path,
        array $args = []
    ) {
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->license_key = $this->sanitizeLicenseKey($license_key);
        $this->update_path = esc_url($update_path);
        $this->plugin_data = get_plugin_data($plugin_file);
        $this->version = $this->plugin_data['Version'];
        $this->cache_key = 'plugin_updater_' . md5($this->plugin_slug);
        
        // Add hooks
        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkUpdate']);
        add_filter('plugins_api', [$this, 'getPluginInfo'], 10, 3);
        add_action('upgrader_process_complete', [$this, 'purgeCache'], 10, 2);
    }
    
    /**
     * Sanitize license key
     * @param string $key
     * @return string
     */
    private function sanitizeLicenseKey(string $key): string 
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $key);
    }
    
    /**
     * Make API request with error handling
     * @param string $action
     * @return object|null
     */
    private function makeRequest(string $action): ?object
    {
        // Check cache first
        if ($this->cache_enabled) {
            $cached = get_transient($this->cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        try {
            $response = wp_remote_post($this->update_path, [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ],
                'body' => wp_json_encode([
                    'action' => $action,
                    'license_key' => $this->license_key,
                    'domain' => wp_parse_url(home_url(), PHP_URL_HOST),
                    'version' => $this->version
                ])
            ]);
            
            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }
            
            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                throw new Exception("Invalid response code: {$code}");
            }
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                throw new Exception('Empty response body');
            }
            
            $data = json_decode($body);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response');
            }
            
            // Cache successful response
            if ($this->cache_enabled) {
                set_transient($this->cache_key, $data, self::CACHE_DURATION);
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Plugin updater error: {$e->getMessage()}");
            return null;
        }
    }
    
    /**
     * Check for updates
     * @param object $transient
     * @return object
     */
    public function checkUpdate(object $transient): object
    {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_data = $this->makeRequest('version');
        if (!$remote_data) {
            return $transient;
        }
        
        if (version_compare($this->version, $remote_data->new_version, '<')) {
            $response = new stdClass();
            $response->slug = dirname($this->plugin_slug);
            $response->plugin = $this->plugin_slug;
            $response->new_version = $remote_data->new_version;
            $response->tested = $remote_data->tested ?? '';
            $response->package = $remote_data->package ?? '';
            $response->url = $remote_data->url ?? '';
            
            $transient->response[$this->plugin_slug] = $response;
        }
        
        return $transient;
    }
    
    /**
     * Get plugin information
     * @param mixed $result
     * @param string $action
     * @param object $args
     * @return mixed
     */
    public function getPluginInfo($result, string $action, object $args)
	{
		// Verify this is for our specific plugin
		if ($action !== 'plugin_information' || 
			!isset($args->slug) || 
			$args->slug !== dirname($this->plugin_slug)) {
			return $result;
		}
		
		$remote_data = $this->makeRequest('info');
		
		// Convert stdClass to array if necessary
		if (is_object($remote_data)) {
			$remote_data = json_decode(json_encode($remote_data), true);
		}
		
		// Ensure minimum required keys exist
		if (!is_array($remote_data)) {
			return $result;
		}
		
		return (object)$remote_data;
	}
    
    /**
     * Clear cache after update
     * @param WP_Upgrader $upgrader
     * @param array $options
     * @return void
     */
    public function purgeCache(WP_Upgrader $upgrader, array $options): void
    {
        if ($this->cache_enabled 
            && isset($options['action'], $options['type']) 
            && $options['action'] === 'update' 
            && $options['type'] === 'plugin') {
            delete_transient($this->cache_key);
        }
    }
}
