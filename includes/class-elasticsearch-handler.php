<?php
/**
 * Elasticsearch Handler for Advanced Search
 * 
 * Provides advanced search capabilities using Elasticsearch
 *
 * @package    WebinoCRM
 * @subpackage WebinoCRM/includes
 */

class WebinoCRM_Elasticsearch_Handler {

    private $es_host;
    private $es_port;
    private $index_name;

    /**
     * Initialize Elasticsearch handler
     */
    public function __construct() {
        $this->es_host = get_option('webinocrm_es_host', 'localhost');
        $this->es_port = get_option('webinocrm_es_port', '9200');
        $this->index_name = get_option('webinocrm_es_index', 'webinocrm');

        add_action('save_post', [$this, 'index_post'], 10, 3);
        add_action('delete_post', [$this, 'delete_from_index']);
        add_action('wp_ajax_webinocrm_advanced_search', [$this, 'ajax_search']);
    }

    /**
     * Get Elasticsearch base URL
     */
    private function get_es_url() {
        return "http://{$this->es_host}:{$this->es_port}";
    }

    /**
     * Check if Elasticsearch is available
     */
    public function is_available() {
        $response = wp_remote_get($this->get_es_url(), ['timeout' => 5]);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }

    /**
     * Create index with mapping
     */
    public function create_index() {
        $mapping = [
            'mappings' => [
                'properties' => [
                    'post_id' => ['type' => 'integer'],
                    'post_type' => ['type' => 'keyword'],
                    'post_title' => [
                        'type' => 'text',
                        'analyzer' => 'standard',
                        'fields' => [
                            'keyword' => ['type' => 'keyword']
                        ]
                    ],
                    'post_content' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ],
                    'post_excerpt' => ['type' => 'text'],
                    'post_status' => ['type' => 'keyword'],
                    'post_author' => ['type' => 'integer'],
                    'post_date' => ['type' => 'date'],
                    'post_modified' => ['type' => 'date'],
                    'meta' => ['type' => 'object', 'enabled' => true],
                    'terms' => ['type' => 'keyword'],
                    'author_name' => ['type' => 'text'],
                    'suggest' => [
                        'type' => 'completion',
                        'analyzer' => 'simple'
                    ]
                ]
            ],
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'analysis' => [
                    'analyzer' => [
                        'persian_analyzer' => [
                            'type' => 'custom',
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'persian_normalization']
                        ]
                    ]
                ]
            ]
        ];

        $response = wp_remote_request(
            $this->get_es_url() . "/{$this->index_name}",
            [
                'method' => 'PUT',
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($mapping)
            ]
        );

        return !is_wp_error($response);
    }

    /**
     * Index a post
     */
    public function index_post($post_id, $post, $update) {
        // Only index WebinoCRM post types
        $allowed_types = ['lead', 'project', 'contract', 'task', 'ticket'];
        
        if (!in_array($post->post_type, $allowed_types)) {
            return;
        }

        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Prepare document
        $document = $this->prepare_document($post);

        // Index document
        $response = wp_remote_request(
            $this->get_es_url() . "/{$this->index_name}/_doc/{$post_id}",
            [
                'method' => 'PUT',
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($document)
            ]
        );

        return !is_wp_error($response);
    }

    /**
     * Prepare document for indexing
     */
    private function prepare_document($post) {
        $author = get_userdata($post->post_author);
        
        // Get all meta data
        $meta = get_post_meta($post->ID);
        $prepared_meta = [];
        foreach ($meta as $key => $value) {
            if (!str_starts_with($key, '_')) {
                $prepared_meta[$key] = maybe_unserialize($value[0]);
            }
        }

        // Get terms
        $taxonomies = get_object_taxonomies($post->post_type);
        $terms = [];
        foreach ($taxonomies as $taxonomy) {
            $post_terms = WebinoCRM_Term_Helper::get_term_field_list( $post->ID, $taxonomy, 'names' );
            if ( ! empty( $post_terms ) ) {
                $terms = array_merge( $terms, $post_terms );
            }
        }

        return [
            'post_id' => $post->ID,
            'post_type' => $post->post_type,
            'post_title' => $post->post_title,
            'post_content' => wp_strip_all_tags($post->post_content),
            'post_excerpt' => $post->post_excerpt,
            'post_status' => $post->post_status,
            'post_author' => $post->post_author,
            'author_name' => $author ? $author->display_name : '',
            'post_date' => date('c', strtotime($post->post_date)),
            'post_modified' => date('c', strtotime($post->post_modified)),
            'meta' => $prepared_meta,
            'terms' => $terms,
            'suggest' => [
                'input' => [$post->post_title],
                'weight' => 10
            ]
        ];
    }

    /**
     * Delete from index
     */
    public function delete_from_index($post_id) {
        wp_remote_request(
            $this->get_es_url() . "/{$this->index_name}/_doc/{$post_id}",
            ['method' => 'DELETE']
        );
    }

    /**
     * Perform search
     */
    public function search($query, $args = []) {
        $defaults = [
            'post_types' => ['lead', 'project', 'contract', 'task', 'ticket'],
            'per_page' => 20,
            'page' => 1,
            'fields' => ['post_title', 'post_content', 'meta.*'],
            'fuzzy' => true,
            'filters' => []
        ];

        $args = wp_parse_args($args, $defaults);

        // Build Elasticsearch query
        $es_query = [
            'from' => ($args['page'] - 1) * $args['per_page'],
            'size' => $args['per_page'],
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'multi_match' => [
                                'query' => $query,
                                'fields' => $args['fields'],
                                'fuzziness' => $args['fuzzy'] ? 'AUTO' : 0,
                                'operator' => 'or'
                            ]
                        ]
                    ],
                    'filter' => [
                        ['terms' => ['post_type' => $args['post_types']]]
                    ]
                ]
            ],
            'highlight' => [
                'fields' => [
                    'post_title' => (object)[],
                    'post_content' => (object)[]
                ]
            ],
            'sort' => [
                '_score' => ['order' => 'desc'],
                'post_date' => ['order' => 'desc']
            ]
        ];

        // Add additional filters
        if (!empty($args['filters'])) {
            foreach ($args['filters'] as $filter) {
                $es_query['query']['bool']['filter'][] = $filter;
            }
        }

        // Execute search
        $response = wp_remote_post(
            $this->get_es_url() . "/{$this->index_name}/_search",
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($es_query)
            ]
        );

        if (is_wp_error($response)) {
            return ['hits' => [], 'total' => 0, 'error' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        return [
            'hits' => $body['hits']['hits'] ?? [],
            'total' => $body['hits']['total']['value'] ?? 0,
            'max_score' => $body['hits']['max_score'] ?? 0
        ];
    }

    /**
     * Get autocomplete suggestions
     */
    public function suggest($prefix) {
        $query = [
            'suggest' => [
                'post-suggest' => [
                    'prefix' => $prefix,
                    'completion' => [
                        'field' => 'suggest',
                        'size' => 10,
                        'skip_duplicates' => true
                    ]
                ]
            ]
        ];

        $response = wp_remote_post(
            $this->get_es_url() . "/{$this->index_name}/_search",
            [
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode($query)
            ]
        );

        if (is_wp_error($response)) {
            return [];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $suggestions = $body['suggest']['post-suggest'][0]['options'] ?? [];

        return array_map(function($item) {
            return [
                'text' => $item['text'],
                'score' => $item['_score'],
                'source' => $item['_source']
            ];
        }, $suggestions);
    }

    /**
     * AJAX search handler
     */
    public function ajax_search() {
        check_ajax_referer('webinocrm-ajax-nonce', 'nonce');

        $query = sanitize_text_field($_POST['query'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $post_types = $_POST['post_types'] ?? [];

        if (empty($query)) {
            wp_send_json_error(['message' => 'جستجو نمی‌تواند خالی باشد']);
        }

        $args = [
            'page' => $page,
            'per_page' => 20
        ];

        if (!empty($post_types)) {
            $args['post_types'] = array_map('sanitize_key', $post_types);
        }

        $results = $this->search($query, $args);

        wp_send_json_success($results);
    }

    /**
     * Reindex all posts
     */
    public function reindex_all() {
        $post_types = ['lead', 'project', 'contract', 'task', 'ticket'];
        
        foreach ($post_types as $post_type) {
            $posts = get_posts([
                'post_type' => $post_type,
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);

            foreach ($posts as $post) {
                $this->index_post($post->ID, $post, false);
            }
        }

        return true;
    }
}

