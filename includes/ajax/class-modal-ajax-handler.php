<?php
/**
 * Modal AJAX Handler - Customer 360 details
 *
 * @deprecated 2.x Dashboard SPA uses GET /webinocrm/v1/customers/{id}/360.
 * @package WebinoCRM
 */

if (!defined('ABSPATH')) exit;

class WebinoCRM_Modal_Ajax_Handler {

    public function __construct() {
        add_action('wp_ajax_webino_get_customer_360', [$this, 'get_customer_360']);
    }


    /**
     * Get Customer 360 View
     *
     * @deprecated Use REST customer_360 instead.
     */
    public function get_customer_360() {
        check_ajax_referer('webinocrm-ajax-nonce', 'security');
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(['message' => 'شناسه مشتری نامعتبر است.']);
        }

        $customer = get_user_by('id', $customer_id);
        if (!$customer) {
            wp_send_json_error(['message' => 'مشتری یافت نشد.']);
        }

        // Get customer data
        $projects = get_posts(['post_type' => 'project', 'posts_per_page' => -1, 'meta_query' => [['key' => '_customer_id', 'value' => $customer_id]]]);
        $contracts = get_posts(['post_type' => 'contract', 'posts_per_page' => -1, 'meta_query' => [['key' => '_customer_id', 'value' => $customer_id]]]);
        $tickets = get_posts(['post_type' => 'ticket', 'posts_per_page' => -1, 'author' => $customer_id]);

        $total_revenue = 0;
        foreach ($contracts as $contract) {
            $total_revenue += (float) get_post_meta($contract->ID, '_total_amount', true);
        }

        ob_start();
        ?>
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title">
                <i class="ri-user-line me-2"></i><?php echo esc_html($customer->display_name); ?>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-md-8">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#customer-overview">نمای کلی</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#customer-projects">پروژه‌ها (<?php echo count($projects); ?>)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#customer-tickets">تیکت‌ها (<?php echo count($tickets); ?>)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#customer-contracts">قراردادها (<?php echo count($contracts); ?>)</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Overview -->
                        <div class="tab-pane fade show active" id="customer-overview">
                            <div class="card custom-card">
                                <div class="card-body">
                                    <h6 class="mb-3">اطلاعات تماس</h6>
                                    <div class="mb-2"><i class="ri-mail-line me-2"></i><?php echo esc_html($customer->user_email); ?></div>
                                    <div class="mb-2"><i class="ri-phone-line me-2"></i><?php echo esc_html(get_user_meta($customer_id, 'mobile_phone', true) ?: '---'); ?></div>
                                    <div class="mb-2"><i class="ri-calendar-line me-2"></i>عضو از: <?php echo date_i18n('Y/m/d', strtotime($customer->user_registered)); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Projects -->
                        <div class="tab-pane fade" id="customer-projects">
                            <?php if (!empty($projects)): ?>
                                <div class="list-group">
                                    <?php foreach ($projects as $project): ?>
                                    <div class="list-group-item">
                                        <div class="fw-semibold"><?php echo esc_html($project->post_title); ?></div>
                                        <small class="text-muted"><?php echo get_the_date('Y/m/d', $project); ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">هیچ پروژه‌ای وجود ندارد</p>
                            <?php endif; ?>
                        </div>

                        <!-- Tickets -->
                        <div class="tab-pane fade" id="customer-tickets">
                            <?php if (!empty($tickets)): ?>
                                <div class="list-group">
                                    <?php foreach (array_slice($tickets, 0, 10) as $ticket): ?>
                                    <div class="list-group-item">
                                        <div class="fw-semibold"><?php echo esc_html($ticket->post_title); ?></div>
                                        <small class="text-muted"><?php echo get_the_date('Y/m/d', $ticket); ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">هیچ تیکتی وجود ندارد</p>
                            <?php endif; ?>
                        </div>

                        <!-- Contracts -->
                        <div class="tab-pane fade" id="customer-contracts">
                            <?php if (!empty($contracts)): ?>
                                <div class="list-group">
                                    <?php foreach ($contracts as $contract): 
                                        $amount = get_post_meta($contract->ID, '_total_amount', true);
                                    ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <div class="fw-semibold"><?php echo esc_html($contract->post_title); ?></div>
                                                <small class="text-muted"><?php echo get_the_date('Y/m/d', $contract); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold text-success"><?php echo number_format($amount); ?></div>
                                                <small class="text-muted">تومان</small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">هیچ قراردادی وجود ندارد</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Customer Card -->
                    <div class="card custom-card text-center">
                        <div class="card-body">
                            <?php echo get_avatar($customer_id, 100, '', '', ['class' => 'rounded-circle mb-3']); ?>
                            <h5><?php echo esc_html($customer->display_name); ?></h5>
                            <p class="text-muted"><?php echo esc_html($customer->user_email); ?></p>
                            
                            <hr>
                            
                            <div class="d-grid gap-2">
                                <a href="?view=customers&action=edit&user_id=<?php echo $customer_id; ?>" class="btn btn-primary">
                                    <i class="ri-edit-line me-1"></i>ویرایش مشتری
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="card custom-card mt-3">
                        <div class="card-body">
                            <div class="mb-3 pb-3 border-bottom">
                                <small class="text-muted d-block">پروژه‌ها</small>
                                <h4 class="mb-0"><?php echo count($projects); ?></h4>
                            </div>
                            <div class="mb-3 pb-3 border-bottom">
                                <small class="text-muted d-block">قراردادها</small>
                                <h4 class="mb-0"><?php echo count($contracts); ?></h4>
                            </div>
                            <div class="mb-3 pb-3 border-bottom">
                                <small class="text-muted d-block">تیکت‌ها</small>
                                <h4 class="mb-0"><?php echo count($tickets); ?></h4>
                            </div>
                            <div>
                                <small class="text-muted d-block">کل درآمد</small>
                                <h4 class="mb-0 text-success"><?php echo number_format($total_revenue / 1000000, 1); ?>M</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        
        $html = ob_get_clean();
        wp_send_json_success(['html' => $html]);
    }

}

