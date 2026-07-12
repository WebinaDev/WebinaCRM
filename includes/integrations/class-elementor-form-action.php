<?php
/**
 * WebinoCRM Elementor Pro Form Action
 *
 * Registers "ارسال به CRM" as a Form Action in Elementor Pro forms.
 * Appears in "Actions After Submit" → Add Item → WebinoCRM.
 *
 * @package WebinoCRM
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WebinoCRM_Elementor_Form_Action extends \ElementorPro\Modules\Forms\Classes\Action_Base {

    /**
     * Field mapping: label/name patterns => CRM meta key.
     */
    private static $field_map = [
        '_first_name'    => [ 'نام', 'first_name', 'firstname', 'name' ],
        '_last_name'     => [ 'نام خانوادگی', 'last_name', 'lastname', 'نام_خانوادگی', 'family' ],
        '_mobile'        => [ 'موبایل', 'mobile', 'tel', 'شماره', 'تلفن', 'phone', 'cell' ],
        '_email'         => [ 'ایمیل', 'email' ],
        '_business_name' => [ 'نام کسب‌وکار', 'business', 'شرکت', 'company', 'business_name' ],
        '_gender'        => [ 'جنسیت', 'gender' ],
    ];

    public function get_name() {
        return 'webinocrm_lead';
    }

    public function get_label() {
        return __( 'ارسال به CRM (WebinoCRM)', 'webinocrm' );
    }

    public function register_settings_section( $widget ) {
        $widget->start_controls_section(
            'section_webino_crm',
            [
                'label' => $this->get_label(),
                'condition' => [
                    'submit_actions' => $this->get_name(),
                ],
            ]
        );
        $widget->add_control(
            'webino_crm_description',
            [
                'type' => \Elementor\Controls_Manager::RAW_HTML,
                'raw' => '<p>' . esc_html__( 'اطلاعات فرم به بخش سرنخ‌های WebinoCRM ارسال می‌شود.', 'webinocrm' ) . '</p>',
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
            ]
        );
        $widget->end_controls_section();
    }

    public function run( $record, $ajax_handler ) {
        $raw_fields = $record->get( 'fields' );
        if ( ! is_array( $raw_fields ) || empty( $raw_fields ) ) {
            return;
        }

        $settings    = $record->get( 'form_settings' );
        $form_name   = isset( $settings['form_name'] ) ? sanitize_text_field( $settings['form_name'] ) : __( 'فرم Elementor', 'webinocrm' );
        $form_data   = [];
        $mapped      = [
            '_first_name'    => '',
            '_last_name'     => '',
            '_mobile'        => '',
            '_email'         => '',
            '_business_name' => '',
            '_gender'        => '',
        ];
        $notes_parts = [];

        foreach ( $raw_fields as $id => $field ) {
            $value = isset( $field['value'] ) ? $field['value'] : '';
            $title = isset( $field['title'] ) ? $field['title'] : ( isset( $field['label'] ) ? $field['label'] : $id );

            if ( is_array( $value ) ) {
                $value = implode( ', ', array_map( 'wp_strip_all_tags', $value ) );
            } else {
                $value = wp_strip_all_tags( (string) $value );
            }

            $form_data[] = [
                'label' => sanitize_text_field( $title ),
                'value' => sanitize_text_field( $value ),
            ];

            $normalized_title = $this->normalize_label( $title );
            $normalized_id    = $this->normalize_label( $id );

            foreach ( self::$field_map as $meta_key => $patterns ) {
                if ( $mapped[ $meta_key ] !== '' ) {
                    continue;
                }
                foreach ( $patterns as $pattern ) {
                    if ( $normalized_title === $this->normalize_label( $pattern ) ||
                         $normalized_id === $this->normalize_label( $pattern ) ) {
                        if ( $meta_key === '_email' ) {
                            $mapped[ $meta_key ] = sanitize_email( $value );
                        } else {
                            $mapped[ $meta_key ] = sanitize_text_field( $value );
                        }
                        break 2;
                    }
                }
            }

            if ( in_array( $normalized_title, [ 'پیام', 'message', 'توضیحات', 'notes', 'یادداشت' ], true ) ||
                 in_array( $normalized_id, [ 'message', 'notes' ], true ) ) {
                $notes_parts[] = sanitize_textarea_field( $value );
            }
        }

        $first_name = $mapped['_first_name'];
        $last_name  = $mapped['_last_name'];
        $mobile     = $mapped['_mobile'];
        $email      = $mapped['_email'];
        $business   = $mapped['_business_name'];
        $gender     = $mapped['_gender'];
        $notes      = implode( "\n\n", array_filter( $notes_parts ) );

        if ( empty( $first_name ) && empty( $last_name ) ) {
            $first_name = __( 'نامشخص', 'webinocrm' );
            $last_name  = '';
        }
        if ( empty( $mobile ) && empty( $email ) ) {
            $mobile = __( 'ثبت نشده', 'webinocrm' );
        } elseif ( empty( $mobile ) ) {
            $mobile = '-';
        }

        $lead_id = wp_insert_post( [
            'post_type'    => 'lead',
            'post_title'   => trim( $first_name . ' ' . $last_name ) ?: $form_name,
            'post_content' => $notes,
            'post_status'  => 'publish',
        ] );

        if ( is_wp_error( $lead_id ) ) {
            return;
        }

        update_post_meta( $lead_id, '_first_name', $first_name );
        update_post_meta( $lead_id, '_last_name', $last_name );
        update_post_meta( $lead_id, '_mobile', $mobile );
        update_post_meta( $lead_id, '_email', $email );
        update_post_meta( $lead_id, '_business_name', $business );
        update_post_meta( $lead_id, '_gender', $gender );
        update_post_meta( $lead_id, '_elementor_form_data', wp_json_encode( $form_data ) );
        update_post_meta( $lead_id, '_elementor_form_name', $form_name );

        $source_slug = sanitize_title( $form_name );
        if ( ! empty( $source_slug ) ) {
            if ( ! term_exists( $source_slug, 'lead_source' ) ) {
                wp_insert_term( $form_name, 'lead_source', [ 'slug' => $source_slug ] );
            }
            wp_set_object_terms( $lead_id, $source_slug, 'lead_source' );
        }

        if ( class_exists( 'WebinoCRM_Settings_Handler' ) ) {
            $settings_handler = WebinoCRM_Settings_Handler::get_all_settings();
            $default_status   = ! empty( $settings_handler['lead_default_status'] ) ? $settings_handler['lead_default_status'] : null;
            if ( $default_status ) {
                wp_set_object_terms( $lead_id, $default_status, 'lead_status' );
            } else {
                $first_status = get_terms( [
                    'taxonomy'   => 'lead_status',
                    'hide_empty' => false,
                    'number'     => 1,
                    'orderby'    => 'term_id',
                    'order'      => 'ASC',
                ] );
                if ( ! empty( $first_status ) && ! is_wp_error( $first_status ) ) {
                    wp_set_object_terms( $lead_id, $first_status[0]->slug, 'lead_status' );
                }
            }
        }

        if ( class_exists( 'WebinoCRM_Logger' ) ) {
            WebinoCRM_Logger::add( 'سرنخ از فرم Elementor', [
                'content' => sprintf(
                    __( 'سرنخ جدید از فرم «%1$s»: %2$s %3$s', 'webinocrm' ),
                    $form_name,
                    $first_name,
                    $last_name
                ),
                'type'    => 'log',
                'details' => [
                    'lead_id'    => $lead_id,
                    'form_name'  => $form_name,
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'mobile'     => $mobile,
                ],
            ] );
        }
    }

    private function normalize_label( $label ) {
        $label = str_replace( [ ' ', '_', '-', '\'', '"' ], '', (string) $label );
        return strtolower( $label );
    }
}
