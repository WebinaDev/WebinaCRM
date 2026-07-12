<?php
/**
 * Document Management System
 * 
 * Complete document storage, versioning, and management system
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Document_Management {

    private $upload_dir;
    private $max_file_size;
    private $allowed_types;

    /**
     * Initialize Document Management
     */
    public function __construct() {
        $this->upload_dir = wp_upload_dir()['basedir'] . '/webinocrm-documents/';
        $this->max_file_size = 10 * 1024 * 1024; // 10MB
        $this->allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip'];

        add_action('init', [$this, 'create_upload_directory']);
        // REST + SPA via WebinoCRM_Documents_Service; legacy wp_ajax hooks removed.
    }

    /**
     * Create upload directory
     */
    public function create_upload_directory() {
        if (!file_exists($this->upload_dir)) {
            wp_mkdir_p($this->upload_dir);
            
            // Create .htaccess for security
            $htaccess = $this->upload_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "deny from all\n");
            }
            
            // Create index.php
            $index = $this->upload_dir . 'index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php\n// Silence is golden.\n");
            }
        }
    }

    /**
     * Upload document
     */
    public static function upload_document($args) {
        global $wpdb;

        $defaults = [
            'file' => null,
            'entity_type' => '',
            'entity_id' => 0,
            'folder_id' => 0,
            'title' => '',
            'description' => '',
            'uploaded_by' => get_current_user_id(),
            'is_private' => 0,
            'tags' => []
        ];

        $data = wp_parse_args($args, $defaults);

        if (!$data['file'] || $data['file']['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_failed', 'خطا در آپلود فایل');
        }

        // Validate file
        $file_name = sanitize_file_name($data['file']['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $file_size = $data['file']['size'];

        $instance = new self();
        
        if (!in_array($file_ext, $instance->allowed_types)) {
            return new WP_Error('invalid_type', 'نوع فایل مجاز نیست');
        }

        if ($file_size > $instance->max_file_size) {
            return new WP_Error('file_too_large', 'حجم فایل بیش از حد مجاز است');
        }

        // Generate unique filename
        $unique_name = time() . '-' . wp_generate_password(10, false) . '.' . $file_ext;
        $upload_path = $instance->upload_dir . $unique_name;

        // Move uploaded file
        if (!move_uploaded_file($data['file']['tmp_name'], $upload_path)) {
            return new WP_Error('move_failed', 'خطا در ذخیره فایل');
        }

        // Generate file hash for duplicate detection
        $file_hash = hash_file('sha256', $upload_path);

        // Insert to database
        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_documents',
            [
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'folder_id' => $data['folder_id'],
                'title' => $data['title'] ?: $file_name,
                'description' => $data['description'],
                'file_name' => $file_name,
                'file_path' => $unique_name,
                'file_size' => $file_size,
                'file_type' => $file_ext,
                'file_hash' => $file_hash,
                'mime_type' => $data['file']['type'],
                'uploaded_by' => $data['uploaded_by'],
                'is_private' => $data['is_private'],
                'version' => 1,
                'uploaded_at' => current_time('mysql')
            ],
            ['%s', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s']
        );

        $document_id = $wpdb->insert_id;

        // Add tags
        if (!empty($data['tags'])) {
            self::add_document_tags($document_id, $data['tags']);
        }

        // Log activity
        WebinoCRM_Activity_Timeline::log([
            'user_id' => $data['uploaded_by'],
            'action_type' => 'document_uploaded',
            'entity_type' => $data['entity_type'],
            'entity_id' => $data['entity_id'],
            'description' => 'آپلود فایل: ' . $file_name,
            'metadata' => [
                'document_id' => $document_id,
                'file_name' => $file_name,
                'file_size' => $file_size
            ]
        ]);

        return $document_id;
    }

    /**
     * Upload new version of document
     */
    public static function upload_version($document_id, $file) {
        global $wpdb;

        $document = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_documents WHERE id = %d",
            $document_id
        ));

        if (!$document) {
            return new WP_Error('not_found', 'سند پیدا نشد');
        }

        // Archive current version
        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_document_versions',
            [
                'document_id' => $document_id,
                'version' => $document->version,
                'file_path' => $document->file_path,
                'file_size' => $document->file_size,
                'file_hash' => $document->file_hash,
                'uploaded_by' => $document->uploaded_by,
                'uploaded_at' => $document->uploaded_at
            ],
            ['%d', '%d', '%s', '%d', '%s', '%d', '%s']
        );

        // Upload new version
        $result = self::upload_document([
            'file' => $file,
            'entity_type' => $document->entity_type,
            'entity_id' => $document->entity_id,
            'folder_id' => $document->folder_id,
            'title' => $document->title,
            'description' => $document->description
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        // Update version number
        $wpdb->update(
            $wpdb->prefix . 'webinocrm_documents',
            ['version' => $document->version + 1],
            ['id' => $document_id],
            ['%d'],
            ['%d']
        );

        return $result;
    }

    /**
     * Get documents
     */
    public static function get_documents($args = []) {
        global $wpdb;

        $defaults = [
            'entity_type' => null,
            'entity_id' => null,
            'folder_id' => null,
            'uploaded_by' => null,
            'is_private' => null,
            'search' => null,
            'tags' => [],
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'uploaded_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where = ['d.is_deleted = 0'];
        $where_values = [];

        if ($args['entity_type']) {
            $where[] = 'd.entity_type = %s';
            $where_values[] = $args['entity_type'];
        }

        if ($args['entity_id']) {
            $where[] = 'd.entity_id = %d';
            $where_values[] = $args['entity_id'];
        }

        if ($args['folder_id'] !== null) {
            $where[] = 'd.folder_id = %d';
            $where_values[] = $args['folder_id'];
        }

        if ($args['uploaded_by']) {
            $where[] = 'd.uploaded_by = %d';
            $where_values[] = $args['uploaded_by'];
        }

        if ($args['is_private'] !== null) {
            $where[] = 'd.is_private = %d';
            $where_values[] = $args['is_private'];
        }

        if ($args['search']) {
            $where[] = '(d.title LIKE %s OR d.description LIKE %s OR d.file_name LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }

        $where_clause = implode(' AND ', $where);
        
        if (!empty($where_values)) {
            $where_clause = $wpdb->prepare($where_clause, $where_values);
        }

        $query = "SELECT d.*, u.display_name as uploader_name
                  FROM {$wpdb->prefix}webinocrm_documents d
                  LEFT JOIN {$wpdb->users} u ON d.uploaded_by = u.ID
                  WHERE {$where_clause}
                  ORDER BY d.{$args['orderby']} {$args['order']}
                  LIMIT %d OFFSET %d";

        $documents = $wpdb->get_results(
            $wpdb->prepare($query, $args['limit'], $args['offset'])
        );

        // Add tags to each document
        foreach ($documents as $document) {
            $document->tags = self::get_document_tags($document->id);
        }

        return $documents;
    }

    /**
     * Delete document
     */
    public static function delete_document($document_id, $permanent = false) {
        global $wpdb;

        if ($permanent) {
            $document = $wpdb->get_row($wpdb->prepare(
                "SELECT file_path FROM {$wpdb->prefix}webinocrm_documents WHERE id = %d",
                $document_id
            ));

            if ($document) {
                $instance = new self();
                $file_path = $instance->upload_dir . $document->file_path;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            return $wpdb->delete(
                $wpdb->prefix . 'webinocrm_documents',
                ['id' => $document_id],
                ['%d']
            );
        } else {
            // Soft delete
            return $wpdb->update(
                $wpdb->prefix . 'webinocrm_documents',
                ['is_deleted' => 1, 'deleted_at' => current_time('mysql')],
                ['id' => $document_id],
                ['%d', '%s'],
                ['%d']
            );
        }
    }

    /**
     * Download document
     */
    public static function download_document($document_id) {
        global $wpdb;

        $document = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_documents WHERE id = %d",
            $document_id
        ));

        if (!$document) {
            return new WP_Error('not_found', 'سند پیدا نشد');
        }

        // Check permissions
        if ($document->is_private && $document->uploaded_by != get_current_user_id()) {
            $has_access = self::check_document_access($document_id, get_current_user_id());
            if (!$has_access) {
                return new WP_Error('access_denied', 'دسترسی به این سند ندارید');
            }
        }

        $instance = new self();
        $file_path = $instance->upload_dir . $document->file_path;

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'فایل پیدا نشد');
        }

        // Increment download count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}webinocrm_documents 
             SET downloads = downloads + 1, last_downloaded_at = %s 
             WHERE id = %d",
            current_time('mysql'),
            $document_id
        ));

        // Log activity
        WebinoCRM_Activity_Timeline::log([
            'action_type' => 'document_downloaded',
            'entity_type' => $document->entity_type,
            'entity_id' => $document->entity_id,
            'description' => 'دانلود فایل: ' . $document->file_name
        ]);

        // Serve file
        header('Content-Type: ' . $document->mime_type);
        header('Content-Disposition: attachment; filename="' . $document->file_name . '"');
        header('Content-Length: ' . filesize($file_path));
        readfile($file_path);
        exit;
    }

    /**
     * Create folder
     */
    public static function create_folder($name, $parent_id = 0) {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'webinocrm_document_folders',
            [
                'name' => sanitize_text_field($name),
                'parent_id' => $parent_id,
                'created_by' => get_current_user_id(),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%d', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Rename document
     */
    public static function rename_document($document_id, $new_title) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'webinocrm_documents',
            ['title' => sanitize_text_field($new_title)],
            ['id' => $document_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Move document to folder
     */
    public static function move_document($document_id, $folder_id) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'webinocrm_documents',
            ['folder_id' => $folder_id],
            ['id' => $document_id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Share document with users
     */
    public static function share_document($document_id, $user_ids, $permissions = 'view') {
        global $wpdb;

        foreach ((array)$user_ids as $user_id) {
            $wpdb->replace(
                $wpdb->prefix . 'webinocrm_document_shares',
                [
                    'document_id' => $document_id,
                    'user_id' => $user_id,
                    'permissions' => $permissions,
                    'shared_by' => get_current_user_id(),
                    'shared_at' => current_time('mysql')
                ],
                ['%d', '%d', '%s', '%d', '%s']
            );
        }

        return true;
    }

    /**
     * Check document access
     */
    public static function check_document_access($document_id, $user_id) {
        global $wpdb;

        $share = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}webinocrm_document_shares 
             WHERE document_id = %d AND user_id = %d",
            $document_id,
            $user_id
        ));

        return (bool) $share;
    }

    /**
     * Add tags to document
     */
    private static function add_document_tags($document_id, $tags) {
        global $wpdb;

        foreach ((array)$tags as $tag) {
            $wpdb->insert(
                $wpdb->prefix . 'webinocrm_document_tags',
                [
                    'document_id' => $document_id,
                    'tag' => sanitize_text_field($tag)
                ],
                ['%d', '%s']
            );
        }
    }

    /**
     * Get document tags
     */
    private static function get_document_tags($document_id) {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT tag FROM {$wpdb->prefix}webinocrm_document_tags WHERE document_id = %d",
            $document_id
        ));
    }

    /**
     * Get document versions
     */
    public static function get_document_versions($document_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT v.*, u.display_name as uploader_name
             FROM {$wpdb->prefix}webinocrm_document_versions v
             LEFT JOIN {$wpdb->users} u ON v.uploaded_by = u.ID
             WHERE v.document_id = %d
             ORDER BY v.version DESC",
            $document_id
        ));
    }

    /**
     * AJAX Handlers
     */
    public function ajax_upload_document() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'فایل انتخاب نشده']);
        }

        $result = self::upload_document([
            'file' => $_FILES['file'],
            'entity_type' => sanitize_key($_POST['entity_type'] ?? ''),
            'entity_id' => intval($_POST['entity_id'] ?? 0),
            'folder_id' => intval($_POST['folder_id'] ?? 0),
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'is_private' => isset($_POST['is_private']) ? 1 : 0,
            'tags' => $_POST['tags'] ?? []
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'فایل با موفقیت آپلود شد',
            'document_id' => $result
        ]);
    }

    public function ajax_get_documents() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $args = [
            'entity_type' => isset($_POST['entity_type']) ? sanitize_key($_POST['entity_type']) : null,
            'entity_id' => isset($_POST['entity_id']) ? intval($_POST['entity_id']) : null,
            'folder_id' => isset($_POST['folder_id']) ? intval($_POST['folder_id']) : null,
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : null,
            'limit' => intval($_POST['limit'] ?? 50)
        ];

        $documents = self::get_documents($args);

        wp_send_json_success(['documents' => $documents]);
    }

    public function ajax_delete_document() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $document_id = intval($_POST['document_id'] ?? 0);
        $permanent = isset($_POST['permanent']) && $_POST['permanent'] === 'true';

        $result = self::delete_document($document_id, $permanent);

        if ($result) {
            wp_send_json_success(['message' => 'سند حذف شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در حذف سند']);
        }
    }

    public function ajax_download_document() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $document_id = intval($_GET['document_id'] ?? 0);

        $result = self::download_document($document_id);

        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
    }

    public function ajax_create_folder() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $name = sanitize_text_field($_POST['name'] ?? '');
        $parent_id = intval($_POST['parent_id'] ?? 0);

        $folder_id = self::create_folder($name, $parent_id);

        wp_send_json_success([
            'message' => 'پوشه ایجاد شد',
            'folder_id' => $folder_id
        ]);
    }

    public function ajax_rename_document() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $document_id = intval($_POST['document_id'] ?? 0);
        $new_title = sanitize_text_field($_POST['title'] ?? '');

        $result = self::rename_document($document_id, $new_title);

        if ($result) {
            wp_send_json_success(['message' => 'نام تغییر کرد']);
        } else {
            wp_send_json_error(['message' => 'خطا در تغییر نام']);
        }
    }

    public function ajax_move_document() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $document_id = intval($_POST['document_id'] ?? 0);
        $folder_id = intval($_POST['folder_id'] ?? 0);

        $result = self::move_document($document_id, $folder_id);

        if ($result) {
            wp_send_json_success(['message' => 'سند جابجا شد']);
        } else {
            wp_send_json_error(['message' => 'خطا در جابجایی']);
        }
    }

    public function ajax_share_document() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $document_id = intval($_POST['document_id'] ?? 0);
        $user_ids = $_POST['user_ids'] ?? [];
        $permissions = sanitize_key($_POST['permissions'] ?? 'view');

        $result = self::share_document($document_id, $user_ids, $permissions);

        wp_send_json_success(['message' => 'سند به اشتراک گذاشته شد']);
    }

    public function ajax_get_versions() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $document_id = intval($_POST['document_id'] ?? 0);

        $versions = self::get_document_versions($document_id);

        wp_send_json_success(['versions' => $versions]);
    }
}

