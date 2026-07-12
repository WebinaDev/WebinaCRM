/**
 * Bulk Actions Handler
 * @package WebinoCRM
 */

jQuery(document).ready(function($) {
    
    let selectedItems = [];
    
    /**
     * Toggle All Checkboxes
     */
    $(document).on('change', '.bulk-select-all', function() {
        const isChecked = $(this).prop('checked');
        const targetClass = $(this).data('target') || '.bulk-select-item';
        
        $(targetClass).prop('checked', isChecked);
        updateBulkActionsUI();
    });
    
    /**
     * Individual Checkbox
     */
    $(document).on('change', '.bulk-select-item', function() {
        updateBulkActionsUI();
    });
    
    /**
     * Update Bulk Actions UI
     */
    function updateBulkActionsUI() {
        selectedItems = [];
        $('.bulk-select-item:checked').each(function() {
            selectedItems.push($(this).val());
        });
        
        const count = selectedItems.length;
        const $bulkBar = $('#bulk-actions-bar');
        
        if (count > 0) {
            if (!$bulkBar.length) {
                $('body').append(`
                    <div id="bulk-actions-bar" class="position-fixed bottom-0 start-50 translate-middle-x bg-primary text-white p-3 rounded-top shadow-lg" style="z-index: 9999; min-width: 400px;">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="fw-bold"><i class="ri-checkbox-multiple-line me-2"></i><span id="selected-count">${count}</span> مورد انتخاب شده</span>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-danger bulk-delete">
                                    <i class="ri-delete-bin-line me-1"></i>حذف
                                </button>
                                <button class="btn btn-warning bulk-change-status">
                                    <i class="ri-refresh-line me-1"></i>تغییر وضعیت
                                </button>
                                <button class="btn btn-light bulk-cancel">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `);
            } else {
                $('#selected-count').text(count);
            }
        } else {
            $bulkBar.remove();
        }
    }
    
    /**
     * Bulk Delete
     */
    $(document).on('click', '.bulk-delete', function() {
        const type = $('.bulk-select-item:first').data('type') || 'item';
        const action = $('.bulk-select-item:first').data('action') || 'webino_bulk_delete';
        
        Swal.fire({
            title: 'حذف ' + selectedItems.length + ' مورد؟',
            text: 'این عملیات غیرقابل بازگشت است!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'بله، حذف کن',
            cancelButtonText: 'انصراف'
        }).then((result) => {
            if (result.isConfirmed) {
                bulkDelete(action, selectedItems);
            }
        });
    });
    
    /**
     * Bulk Change Status
     */
    $(document).on('click', '.bulk-change-status', function() {
        const type = $('.bulk-select-item:first').data('type') || 'item';
        const statuses = $('.bulk-select-item:first').data('statuses') || '';
        
        let statusOptions = {};
        if (statuses) {
            const statusArr = statuses.split(',');
            statusArr.forEach(status => {
                const parts = status.split(':');
                statusOptions[parts[0]] = parts[1] || parts[0];
            });
        } else {
            statusOptions = {
                'active': 'فعال',
                'inactive': 'غیرفعال',
                'pending': 'در انتظار',
                'completed': 'تکمیل شده'
            };
        }
        
        Swal.fire({
            title: 'تغییر وضعیت ' + selectedItems.length + ' مورد',
            input: 'select',
            inputOptions: statusOptions,
            inputPlaceholder: 'انتخاب وضعیت',
            showCancelButton: true,
            confirmButtonText: 'تایید',
            cancelButtonText: 'انصراف'
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                bulkChangeStatus(selectedItems, result.value);
            }
        });
    });
    
    /**
     * Cancel Bulk Selection
     */
    $(document).on('click', '.bulk-cancel', function() {
        $('.bulk-select-item').prop('checked', false);
        $('.bulk-select-all').prop('checked', false);
        updateBulkActionsUI();
    });
    
    /**
     * Bulk Delete AJAX
     */
    function bulkDelete(action, items) {
        Swal.fire({
            title: 'در حال حذف...',
            html: '<div class="spinner-border text-danger"></div>',
            showConfirmButton: false,
            allowOutsideClick: false
        });
        
        $.ajax({
            url: webinocrm_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: action,
                security: webinocrm_ajax_obj.nonce,
                items: items
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'موفق!',
                        text: response.data.message || items.length + ' مورد حذف شد',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: response.data.message
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'خطا',
                    text: 'خطا در ارتباط با سرور'
                });
            }
        });
    }
    
    /**
     * Bulk Change Status AJAX
     */
    function bulkChangeStatus(items, newStatus) {
        const action = $('.bulk-select-item:first').data('status-action') || 'webino_bulk_change_status';
        
        Swal.fire({
            title: 'در حال به‌روزرسانی...',
            html: '<div class="spinner-border text-primary"></div>',
            showConfirmButton: false,
            allowOutsideClick: false
        });
        
        $.ajax({
            url: webinocrm_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: action,
                security: webinocrm_ajax_obj.nonce,
                items: items,
                new_status: newStatus
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'موفق!',
                        text: items.length + ' مورد به‌روزرسانی شد',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطا',
                        text: response.data.message
                    });
                }
            }
        });
    }
});

