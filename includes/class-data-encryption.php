<?php
/**
 * Data Encryption Handler
 * 
 * Encrypts sensitive data for security
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Data_Encryption {

    private $cipher_method;
    private $encryption_key;

    /**
     * Initialize Encryption Handler
     */
    public function __construct() {
        $this->cipher_method = 'AES-256-CBC';
        $this->encryption_key = $this->get_encryption_key();
        
        add_filter('webinocrm_encrypt_field', [$this, 'encrypt_field'], 10, 1);
        add_filter('webinocrm_decrypt_field', [$this, 'decrypt_field'], 10, 1);
    }

    /**
     * Get or generate encryption key
     */
    private function get_encryption_key() {
        $key = get_option('webinocrm_encryption_key');
        
        if (!$key) {
            // Generate new key
            $key = base64_encode(openssl_random_pseudo_bytes(32));
            update_option('webinocrm_encryption_key', $key, false);
        }
        
        return base64_decode($key);
    }

    /**
     * Encrypt data
     */
    public function encrypt($data) {
        if (empty($data)) {
            return $data;
        }

        if (!function_exists('openssl_encrypt')) {
            error_log('WebinoCRM: OpenSSL extension not available for encryption');
            return $data;
        }

        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher_method));
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher_method,
            $this->encryption_key,
            0,
            $iv
        );

        // Combine IV and encrypted data
        return base64_encode($iv . '::' . $encrypted);
    }

    /**
     * Decrypt data
     */
    public function decrypt($data) {
        if (empty($data)) {
            return $data;
        }

        if (!function_exists('openssl_decrypt')) {
            error_log('WebinoCRM: OpenSSL extension not available for decryption');
            return $data;
        }

        $decoded = base64_decode($data);
        
        if (strpos($decoded, '::') === false) {
            // Not encrypted or wrong format
            return $data;
        }

        list($iv, $encrypted) = explode('::', $decoded, 2);

        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher_method,
            $this->encryption_key,
            0,
            $iv
        );

        return $decrypted !== false ? $decrypted : $data;
    }

    /**
     * Encrypt field filter
     */
    public function encrypt_field($data) {
        return $this->encrypt($data);
    }

    /**
     * Decrypt field filter
     */
    public function decrypt_field($data) {
        return $this->decrypt($data);
    }

    /**
     * Hash password
     */
    public static function hash_password($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     */
    public static function verify_password($password, $hash) {
        return password_verify($password, $hash);
    }

    /**
     * Generate secure token
     */
    public static function generate_token($length = 32) {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    }

    /**
     * Encrypt sensitive meta data
     */
    public static function encrypt_meta($meta_key, $meta_value) {
        $sensitive_fields = apply_filters('webinocrm_sensitive_fields', [
            '_contract_value',
            '_payment_details',
            '_bank_account',
            '_credit_card',
            '_ssn',
            '_tax_id',
            '_api_key',
            '_api_secret',
            '_password',
            '_phone',
            '_email',
            '_national_id'
        ]);

        if (in_array($meta_key, $sensitive_fields)) {
            $instance = new self();
            return $instance->encrypt($meta_value);
        }

        return $meta_value;
    }

    /**
     * Decrypt sensitive meta data
     */
    public static function decrypt_meta($meta_key, $meta_value) {
        $sensitive_fields = apply_filters('webinocrm_sensitive_fields', [
            '_contract_value',
            '_payment_details',
            '_bank_account',
            '_credit_card',
            '_ssn',
            '_tax_id',
            '_api_key',
            '_api_secret',
            '_password',
            '_phone',
            '_email',
            '_national_id'
        ]);

        if (in_array($meta_key, $sensitive_fields)) {
            $instance = new self();
            return $instance->decrypt($meta_value);
        }

        return $meta_value;
    }

    /**
     * Sanitize and encrypt credit card
     */
    public static function encrypt_credit_card($card_number) {
        // Remove spaces and dashes
        $card_number = preg_replace('/[^0-9]/', '', $card_number);
        
        if (strlen($card_number) < 13 || strlen($card_number) > 19) {
            return new WP_Error('invalid_card', 'شماره کارت نامعتبر است');
        }

        $instance = new self();
        return $instance->encrypt($card_number);
    }

    /**
     * Mask credit card for display
     */
    public static function mask_credit_card($card_number) {
        if (strlen($card_number) < 13) {
            return '****';
        }

        return str_repeat('*', strlen($card_number) - 4) . substr($card_number, -4);
    }

    /**
     * Encrypt email for GDPR compliance
     */
    public static function encrypt_email($email) {
        if (!is_email($email)) {
            return $email;
        }

        $instance = new self();
        return $instance->encrypt($email);
    }

    /**
     * Mask email for display
     */
    public static function mask_email($email) {
        if (!is_email($email)) {
            return $email;
        }

        list($username, $domain) = explode('@', $email);
        
        $masked_username = substr($username, 0, 2) . str_repeat('*', strlen($username) - 2);
        
        return $masked_username . '@' . $domain;
    }

    /**
     * Encrypt phone number
     */
    public static function encrypt_phone($phone) {
        // Remove non-numeric characters
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        $instance = new self();
        return $instance->encrypt($phone);
    }

    /**
     * Mask phone for display
     */
    public static function mask_phone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) < 6) {
            return '****';
        }

        return substr($phone, 0, 3) . str_repeat('*', strlen($phone) - 6) . substr($phone, -3);
    }

    /**
     * Rotate encryption key (careful operation!)
     */
    public static function rotate_encryption_key() {
        global $wpdb;

        $old_instance = new self();
        
        // Generate new key
        $new_key = base64_encode(openssl_random_pseudo_bytes(32));
        
        // Create new instance with new key
        $new_instance = new self();
        $new_instance->encryption_key = base64_decode($new_key);

        // Get all encrypted meta data
        $sensitive_fields = apply_filters('webinocrm_sensitive_fields', []);
        
        foreach ($sensitive_fields as $meta_key) {
            $meta_data = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s",
                $meta_key
            ));

            foreach ($meta_data as $meta) {
                // Decrypt with old key
                $decrypted = $old_instance->decrypt($meta->meta_value);
                
                // Encrypt with new key
                $encrypted = $new_instance->encrypt($decrypted);
                
                // Update
                $wpdb->update(
                    $wpdb->postmeta,
                    ['meta_value' => $encrypted],
                    ['meta_id' => $meta->meta_id],
                    ['%s'],
                    ['%d']
                );
            }
        }

        // Update key
        update_option('webinocrm_encryption_key', $new_key, false);

        return true;
    }
}

// Hook into meta operations to automatically encrypt/decrypt
add_filter('add_post_metadata', function($check, $object_id, $meta_key, $meta_value, $unique) {
    $encrypted = WebinoCRM_Data_Encryption::encrypt_meta($meta_key, $meta_value);
    
    if ($encrypted !== $meta_value) {
        // Manually insert encrypted value
        global $wpdb;
        $wpdb->insert(
            $wpdb->postmeta,
            [
                'post_id' => $object_id,
                'meta_key' => $meta_key,
                'meta_value' => $encrypted
            ]
        );
        
        return true; // Prevent default insertion
    }
    
    return $check;
}, 10, 5);

add_filter('get_post_metadata', function($value, $object_id, $meta_key, $single, $meta_type) {
    if ($meta_key && $value) {
        if (is_array($value) && isset($value[0])) {
            $value[0] = WebinoCRM_Data_Encryption::decrypt_meta($meta_key, $value[0]);
        } elseif (!is_array($value)) {
            $value = WebinoCRM_Data_Encryption::decrypt_meta($meta_key, $value);
        }
    }
    
    return $value;
}, 10, 5);

