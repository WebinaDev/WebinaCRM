/**
 * Drag & Drop File Upload for WebinoCRM
 * Modern file upload with drag and drop support
 */

(function($) {
    'use strict';

    class DragDropUpload {
        constructor(element, options) {
            this.element = $(element);
            this.options = $.extend({}, this.defaults, options);
            this.files = [];
            this.init();
        }

        get defaults() {
            return {
                url: webinocrm_ajax_obj.ajax_url,
                action: 'webinocrm_upload_document',
                maxFiles: 10,
                maxFileSize: 10 * 1024 * 1024, // 10MB
                allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                multiple: true,
                autoUpload: false,
                thumbnails: true,
                onFileAdded: null,
                onFileRemoved: null,
                onUploadProgress: null,
                onUploadComplete: null,
                onError: null
            };
        }

        init() {
            this.createUploadArea();
            this.attachEvents();
        }

        createUploadArea() {
            const html = `
                <div class="webino-upload-container">
                    <div class="webino-upload-dropzone">
                        <input type="file" 
                               class="webino-upload-input" 
                               ${this.options.multiple ? 'multiple' : ''}
                               accept="${this.options.allowedTypes.join(',')}"
                               style="display: none;">
                        
                        <div class="webino-upload-prompt">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <div class="upload-text">
                                <h4>فایل‌ها را اینجا بکشید و رها کنید</h4>
                                <p>یا <button type="button" class="webino-upload-browse">انتخاب فایل</button></p>
                            </div>
                            <div class="upload-info">
                                <small>حداکثر ${this.options.maxFiles} فایل، هر فایل حداکثر ${this.formatFileSize(this.options.maxFileSize)}</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="webino-upload-preview" style="display: none;">
                        <div class="preview-header">
                            <h5>فایل‌های انتخاب شده (${this.files.length})</h5>
                            <button type="button" class="btn btn-sm btn-danger webino-clear-all">
                                <i class="fas fa-trash"></i> حذف همه
                            </button>
                        </div>
                        <div class="preview-list"></div>
                        ${!this.options.autoUpload ? '<button type="button" class="btn btn-primary webino-start-upload">آپلود فایل‌ها</button>' : ''}
                    </div>
                </div>
            `;

            this.element.html(html);
            this.dropzone = this.element.find('.webino-upload-dropzone');
            this.input = this.element.find('.webino-upload-input');
            this.previewArea = this.element.find('.webino-upload-preview');
            this.previewList = this.element.find('.preview-list');
        }

        attachEvents() {
            const self = this;

            // Browse button
            this.element.find('.webino-upload-browse').on('click', function(e) {
                e.preventDefault();
                self.input.trigger('click');
            });

            // File input change
            this.input.on('change', function(e) {
                self.handleFiles(e.target.files);
            });

            // Drag and drop
            this.dropzone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            this.dropzone.on('dragleave dragend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            this.dropzone.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
                
                const files = e.originalEvent.dataTransfer.files;
                self.handleFiles(files);
            });

            // Clear all button
            this.element.find('.webino-clear-all').on('click', function(e) {
                e.preventDefault();
                self.clearAll();
            });

            // Upload button
            this.element.find('.webino-start-upload').on('click', function(e) {
                e.preventDefault();
                self.uploadAll();
            });
        }

        handleFiles(fileList) {
            const files = Array.from(fileList);

            // Check max files
            if (this.files.length + files.length > this.options.maxFiles) {
                this.showError(`حداکثر ${this.options.maxFiles} فایل می‌توانید انتخاب کنید`);
                return;
            }

            files.forEach(file => {
                // Validate file
                if (!this.validateFile(file)) {
                    return;
                }

                // Add to files array
                const fileObj = {
                    file: file,
                    id: this.generateId(),
                    status: 'pending',
                    progress: 0,
                    xhr: null
                };

                this.files.push(fileObj);

                // Create preview
                this.createFilePreview(fileObj);

                // Callback
                if (typeof this.options.onFileAdded === 'function') {
                    this.options.onFileAdded(fileObj);
                }

                // Auto upload if enabled
                if (this.options.autoUpload) {
                    this.uploadFile(fileObj);
                }
            });

            // Show preview area
            if (this.files.length > 0) {
                this.previewArea.show();
                this.dropzone.hide();
            }

            // Update counter
            this.updateCounter();
        }

        validateFile(file) {
            // Check file type
            if (this.options.allowedTypes.length > 0 && !this.options.allowedTypes.includes(file.type)) {
                this.showError(`نوع فایل "${file.name}" مجاز نیست`);
                return false;
            }

            // Check file size
            if (file.size > this.options.maxFileSize) {
                this.showError(`حجم فایل "${file.name}" بیش از حد مجاز است`);
                return false;
            }

            return true;
        }

        createFilePreview(fileObj) {
            const self = this;
            const preview = $(`
                <div class="preview-item" data-file-id="${fileObj.id}">
                    <div class="preview-thumbnail">
                        ${this.options.thumbnails && fileObj.file.type.startsWith('image/') 
                            ? '<img src="" class="thumb-img">' 
                            : '<i class="fas fa-file fa-3x"></i>'}
                    </div>
                    <div class="preview-info">
                        <div class="file-name">${fileObj.file.name}</div>
                        <div class="file-size">${this.formatFileSize(fileObj.file.size)}</div>
                        <div class="progress" style="display: none;">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <div class="file-status"></div>
                    </div>
                    <div class="preview-actions">
                        <button type="button" class="btn btn-sm btn-danger remove-file">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `);

            // Load thumbnail if image
            if (this.options.thumbnails && fileObj.file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.find('.thumb-img').attr('src', e.target.result);
                };
                reader.readAsDataURL(fileObj.file);
            }

            // Remove button
            preview.find('.remove-file').on('click', function(e) {
                e.preventDefault();
                self.removeFile(fileObj.id);
            });

            this.previewList.append(preview);
        }

        uploadFile(fileObj) {
            const self = this;
            const formData = new FormData();
            
            formData.append('action', this.options.action);
            formData.append('nonce', webinocrm_ajax_obj.nonce);
            formData.append('file', fileObj.file);

            // Add additional data
            Object.keys(this.options.data || {}).forEach(key => {
                formData.append(key, this.options.data[key]);
            });

            const xhr = new XMLHttpRequest();
            fileObj.xhr = xhr;
            fileObj.status = 'uploading';

            // Progress
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const progress = (e.loaded / e.total) * 100;
                    self.updateProgress(fileObj.id, progress);

                    if (typeof self.options.onUploadProgress === 'function') {
                        self.options.onUploadProgress(fileObj, progress);
                    }
                }
            });

            // Complete
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            fileObj.status = 'completed';
                            fileObj.response = response.data;
                            self.updateStatus(fileObj.id, 'completed', 'آپلود شد');

                            if (typeof self.options.onUploadComplete === 'function') {
                                self.options.onUploadComplete(fileObj, response.data);
                            }
                        } else {
                            fileObj.status = 'error';
                            self.updateStatus(fileObj.id, 'error', response.data.message || 'خطا در آپلود');
                            
                            if (typeof self.options.onError === 'function') {
                                self.options.onError(fileObj, response.data.message);
                            }
                        }
                    } catch (e) {
                        fileObj.status = 'error';
                        self.updateStatus(fileObj.id, 'error', 'خطای سرور');
                    }
                } else {
                    fileObj.status = 'error';
                    self.updateStatus(fileObj.id, 'error', 'خطای ارتباط با سرور');
                }
            });

            // Error
            xhr.addEventListener('error', function() {
                fileObj.status = 'error';
                self.updateStatus(fileObj.id, 'error', 'خطا در آپلود');
                
                if (typeof self.options.onError === 'function') {
                    self.options.onError(fileObj, 'خطا در آپلود');
                }
            });

            xhr.open('POST', this.options.url);
            xhr.send(formData);
        }

        uploadAll() {
            this.files.forEach(fileObj => {
                if (fileObj.status === 'pending') {
                    this.uploadFile(fileObj);
                }
            });
        }

        removeFile(id) {
            const index = this.files.findIndex(f => f.id === id);
            
            if (index !== -1) {
                const fileObj = this.files[index];
                
                // Cancel upload if in progress
                if (fileObj.xhr) {
                    fileObj.xhr.abort();
                }

                // Remove from array
                this.files.splice(index, 1);

                // Remove preview
                this.previewList.find(`[data-file-id="${id}"]`).remove();

                // Callback
                if (typeof this.options.onFileRemoved === 'function') {
                    this.options.onFileRemoved(fileObj);
                }

                // Update counter
                this.updateCounter();

                // Hide preview if no files
                if (this.files.length === 0) {
                    this.previewArea.hide();
                    this.dropzone.show();
                }
            }
        }

        clearAll() {
            // Cancel all uploads
            this.files.forEach(fileObj => {
                if (fileObj.xhr) {
                    fileObj.xhr.abort();
                }
            });

            // Clear arrays and UI
            this.files = [];
            this.previewList.empty();
            this.previewArea.hide();
            this.dropzone.show();
        }

        updateProgress(id, progress) {
            const item = this.previewList.find(`[data-file-id="${id}"]`);
            item.find('.progress').show();
            item.find('.progress-bar').css('width', progress + '%');
        }

        updateStatus(id, status, message) {
            const item = this.previewList.find(`[data-file-id="${id}"]`);
            const statusEl = item.find('.file-status');
            
            statusEl.removeClass('status-pending status-uploading status-completed status-error');
            statusEl.addClass('status-' + status);
            statusEl.html(message);

            if (status === 'completed') {
                item.find('.progress').hide();
                item.find('.remove-file').hide();
            }
        }

        updateCounter() {
            this.previewArea.find('.preview-header h5').text(`فایل‌های انتخاب شده (${this.files.length})`);
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }

        generateId() {
            return Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }

        showError(message) {
            if (typeof this.options.onError === 'function') {
                this.options.onError(null, message);
            } else {
                alert(message);
            }
        }

        // Public methods
        getFiles() {
            return this.files;
        }

        reset() {
            this.clearAll();
        }
    }

    // jQuery plugin
    $.fn.webinoUpload = function(options) {
        return this.each(function() {
            if (!$.data(this, 'webinoUpload')) {
                $.data(this, 'webinoUpload', new DragDropUpload(this, options));
            }
        });
    };

})(jQuery);

