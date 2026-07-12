console.log('WebinoCRM Custom Datepicker: Script file loaded!');
console.log('WebinoCRM Custom Datepicker: jQuery available?', typeof jQuery !== 'undefined');
console.log('WebinoCRM Custom Datepicker: $ available?', typeof $ !== 'undefined');

jQuery(document).ready(function($) {
    console.log('WebinoCRM Custom Datepicker: jQuery ready callback executed!');
    /**
     * WebinoCRM Custom Jalali Date Picker - V2
     * with Month/Year selection and new theme.
     */
    function WebinoJalaliDatePicker() {
        // Helper functions (toJalali, toGregorian) remain the same
        function toJalali(gy, gm, gd) {
            var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
            var jy = (gy <= 1600) ? 0 : 979;
            gy -= (gy <= 1600) ? 621 : 1600;
            var gy2 = (gm > 2) ? (gy + 1) : gy;
            var days = 365 * gy + parseInt((gy2 + 3) / 4) - parseInt((gy2 + 99) / 100) + parseInt((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
            jy += 33 * parseInt(days / 12053);
            days %= 12053;
            jy += 4 * parseInt(days / 1461);
            days %= 1461;
            jy += parseInt((days - 1) / 365);
            if (days > 365) days = (days - 1) % 365;
            var jm = (days < 186) ? 1 + parseInt(days / 31) : 7 + parseInt((days - 186) / 30);
            var jd = 1 + ((days < 186) ? (days % 31) : ((days - 186) % 30));
            return [jy, jm, jd];
        }

        function toGregorian(jy, jm, jd) {
            var gy = (jy <= 979) ? 621 : 1600;
            jy -= (jy <= 979) ? 0 : 979;
            var days = 365 * jy + parseInt(jy / 33) * 8 + parseInt(((jy % 33) + 3) / 4) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
            gy += 400 * parseInt(days / 146097);
            days %= 146097;
            if (days > 36524) {
                gy += 100 * parseInt(--days / 36524);
                days %= 36524;
                if (days >= 365) days++;
            }
            gy += 4 * parseInt(days / 1461);
            days %= 1461;
            gy += parseInt((days - 1) / 365);
            if (days > 365) days = (days - 1) % 365;
            var gd = days + 1;
            var sal_a = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            for (var gm = 0; gm < 13 && gd > sal_a[gm]; gm++) gd -= sal_a[gm];
            return [gy, gm, gd];
        }

        const monthNames = ["فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور", "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند"];
        const dayNames = ["ش", "ی", "د", "س", "چ", "پ", "ج"];

        let today = new Date();
        let todayJalali = toJalali(today.getFullYear(), today.getMonth() + 1, today.getDate());

        function createPicker(input) {
            if (input.data('webino-datepicker-active')) {
                console.log('WebinoCRM Custom Datepicker: Picker already active for', input.attr('id') || input.attr('name'));
                return;
            }
            console.log('WebinoCRM Custom Datepicker: Creating picker for', input.attr('id') || input.attr('name'));
            input.data('webino-datepicker-active', true);
            input.attr('autocomplete', 'off');
            // Don't set readonly as it might interfere with form submission

            let currentYear = todayJalali[0];
            let currentMonth = todayJalali[1];
            let currentView = 'days'; // Can be 'days', 'months', 'years'

            const pickerContainer = $('<div class="webino-datepicker-container"></div>');
            const pickerHeader = $('<div class="webino-datepicker-header"></div>').appendTo(pickerContainer);
            const prevBtn = $('<button type="button" class="webino-datepicker-prev">&lt;</button>').appendTo(pickerHeader);
            const monthYearDisplay = $('<span class="webino-datepicker-month-year"></span>').appendTo(pickerHeader);
            const nextBtn = $('<button type="button" class="webino-datepicker-next">&gt;</button>').appendTo(pickerHeader);
            
            const calendarGrid = $('<div class="webino-datepicker-grid"></div>').appendTo(pickerContainer);
            const monthGrid = $('<div class="webino-datepicker-view" id="webino-month-view"></div>').appendTo(pickerContainer);
            const yearGrid = $('<div class="webino-datepicker-view" id="webino-year-view"></div>').appendTo(pickerContainer);
            
            const todayBtn = $('<button type="button" class="webino-datepicker-today">امروز</button>').appendTo(pickerContainer);

            // Append to body and ensure it's visible when shown
            $('body').append(pickerContainer);
            pickerContainer.hide(); // Initially hidden
            
            // Store reference to picker container in input data
            input.data('webino-picker-container', pickerContainer);

            function switchView(view) {
                currentView = view;
                calendarGrid.hide();
                monthGrid.hide().removeClass('active');
                yearGrid.hide().removeClass('active');
                todayBtn.toggle(view === 'days');

                if (view === 'days') {
                    calendarGrid.show();
                    monthYearDisplay.text(monthNames[currentMonth - 1] + ' ' + currentYear);
                } else if (view === 'months') {
                    monthGrid.addClass('active');
                    monthYearDisplay.text(currentYear);
                } else if (view === 'years') {
                    yearGrid.addClass('active');
                    let startYear = Math.floor(currentYear / 10) * 10;
                    monthYearDisplay.text(startYear + ' - ' + (startYear + 9));
                }
            }

            function renderDays(year, month) {
                calendarGrid.empty();
                dayNames.forEach(day => calendarGrid.append(`<div class="webino-datepicker-day-name">${day}</div>`));
                
                let gregorianStartDate = toGregorian(year, month, 1);
                let firstDayOfWeek = new Date(gregorianStartDate[0], gregorianStartDate[1] - 1, gregorianStartDate[2]).getDay();
                firstDayOfWeek = (firstDayOfWeek + 1) % 7;

                let daysInMonth = (month <= 6) ? 31 : (month <= 11) ? 30 : (toGregorian(year + 1, 1, 1)[0] - toGregorian(year, 1, 1)[0] > 365) ? 30 : 29;

                for (let i = 0; i < firstDayOfWeek; i++) calendarGrid.append('<div class="webino-datepicker-day empty"></div>');

                for (let day = 1; day <= daysInMonth; day++) {
                    let dayCell = $(`<div class="webino-datepicker-day">${day}</div>`);
                    if (year === todayJalali[0] && month === todayJalali[1] && day === todayJalali[2]) {
                        dayCell.addClass('today');
                    }
                    if (input.val() === `${year}/${String(month).padStart(2, '0')}/${String(day).padStart(2, '0')}`) {
                        dayCell.addClass('selected');
                    }
                    dayCell.on('click', function() {
                        let selectedDate = `${year}/${String(month).padStart(2, '0')}/${String(day).padStart(2, '0')}`;
                        input.val(selectedDate);
                        pickerContainer.hide();
                    });
                    calendarGrid.append(dayCell);
                }
            }

            function renderMonths() {
                monthGrid.empty();
                for (let i = 0; i < 12; i++) {
                    let monthCell = $(`<div class="webino-datepicker-month">${monthNames[i]}</div>`);
                    if (i + 1 === currentMonth) monthCell.addClass('selected');
                    monthCell.on('click', function() {
                        currentMonth = i + 1;
                        switchView('days');
                        renderDays(currentYear, currentMonth);
                    });
                    monthGrid.append(monthCell);
                }
            }
            
            function renderYears(startYear) {
                yearGrid.empty();
                 for (let i = -1; i < 11; i++) { // Show 12 years
                    const year = startYear + i;
                    let yearCell = $(`<div class="webino-datepicker-year">${year}</div>`);
                    if(i === -1 || i === 10) yearCell.css('color', '#999');
                    if (year === currentYear) yearCell.addClass('selected');
                    yearCell.on('click', function() {
                        currentYear = year;
                        switchView('months');
                        renderMonths();
                    });
                    yearGrid.append(yearCell);
                }
            }

            monthYearDisplay.on('click', function() {
                if (currentView === 'days') switchView('months');
                else if (currentView === 'months') switchView('years');
            });

            prevBtn.on('click', function() {
                if (currentView === 'days') {
                    currentMonth--;
                    if (currentMonth < 1) { currentMonth = 12; currentYear--; }
                    renderDays(currentYear, currentMonth);
                    monthYearDisplay.text(monthNames[currentMonth - 1] + ' ' + currentYear);
                } else if (currentView === 'months') {
                    currentYear--;
                    monthYearDisplay.text(currentYear);
                } else if (currentView === 'years') {
                    currentYear -= 10;
                    renderYears(Math.floor(currentYear / 10) * 10);
                    let startYear = Math.floor(currentYear / 10) * 10;
                    monthYearDisplay.text(startYear + ' - ' + (startYear + 9));
                }
            });

            nextBtn.on('click', function() {
                if (currentView === 'days') {
                    currentMonth++;
                    if (currentMonth > 12) { currentMonth = 1; currentYear++; }
                    renderDays(currentYear, currentMonth);
                     monthYearDisplay.text(monthNames[currentMonth - 1] + ' ' + currentYear);
                } else if (currentView === 'months') {
                    currentYear++;
                    monthYearDisplay.text(currentYear);
                } else if (currentView === 'years') {
                    currentYear += 10;
                    renderYears(Math.floor(currentYear / 10) * 10);
                    let startYear = Math.floor(currentYear / 10) * 10;
                    monthYearDisplay.text(startYear + ' - ' + (startYear + 9));
                }
            });
            
            todayBtn.on('click', function() {
                let todayStr = `${todayJalali[0]}/${String(todayJalali[1]).padStart(2, '0')}/${String(todayJalali[2]).padStart(2, '0')}`;
                input.val(todayStr);
                pickerContainer.hide();
            });

            // Use both focus and click events to ensure picker opens
            function showPicker(e) {
                if (e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                console.log('WebinoCRM Custom Datepicker: Showing picker for', input.attr('id') || input.attr('name'));
                let dateValue = input.val();
                if (dateValue && /^\d{4}\/\d{1,2}\/\d{1,2}$/.test(dateValue)) {
                    let parts = dateValue.split('/');
                    currentYear = parseInt(parts[0]);
                    currentMonth = parseInt(parts[1]);
                } else {
                    currentYear = todayJalali[0];
                    currentMonth = todayJalali[1];
                }
                switchView('days');
                renderDays(currentYear, currentMonth);
                renderMonths();
                renderYears(Math.floor(currentYear / 10) * 10);

                let offset = input.offset();
                if (!offset) {
                    console.error('WebinoCRM Custom Datepicker: Cannot get offset for input');
                    return;
                }
                
                // Hide all other pickers
                $('.webino-datepicker-container').not(pickerContainer).hide();
                
                // Show this picker - use show() method and force display
                pickerContainer.css({ 
                    top: (offset.top + input.outerHeight() + 5) + 'px', 
                    left: offset.left + 'px', 
                    right: 'auto',
                    position: 'absolute',
                    zIndex: 10000,
                    display: 'block',
                    visibility: 'visible',
                    opacity: 1
                });
                
                // Force show using jQuery
                pickerContainer.show();
                
                // Double check it's visible
                if (!pickerContainer.is(':visible')) {
                    console.warn('WebinoCRM Custom Datepicker: Picker container is not visible after show(), forcing display');
                    pickerContainer.css('display', 'block !important');
                }
                
                // Adjust position if it goes off screen
                setTimeout(function() {
                    var containerWidth = pickerContainer.outerWidth();
                    var windowWidth = $(window).width();
                    if(offset.left + containerWidth > windowWidth) {
                        pickerContainer.css({
                            left: 'auto', 
                            right: (windowWidth - (offset.left + input.outerWidth())) + 'px'
                        });
                    }
                }, 10);
                
                console.log('WebinoCRM Custom Datepicker: Picker shown at', offset.top, offset.left);
            }
            
            // Handle focus event - use namespaced events to prevent conflicts
            input.off('focus.webino-datepicker click.webino-datepicker mousedown.webino-datepicker')
                 .on('focus.webino-datepicker', function(e) {
                     console.log('WebinoCRM Custom Datepicker: Focus event triggered for', input.attr('id') || input.attr('name'));
                     showPicker(e);
                 })
                 .on('click.webino-datepicker', function(e) {
                     console.log('WebinoCRM Custom Datepicker: Click event triggered for', input.attr('id') || input.attr('name'));
                     showPicker(e);
                 })
                 .on('mousedown.webino-datepicker', function(e) {
                     console.log('WebinoCRM Custom Datepicker: Mousedown event triggered for', input.attr('id') || input.attr('name'));
                     // Small delay to ensure input gets focus first
                     setTimeout(function() {
                         if (!pickerContainer.is(':visible')) {
                             showPicker();
                         }
                     }, 50);
                 });
            
            // Debug: Log when input is ready
            console.log('WebinoCRM Custom Datepicker: Picker created and events bound for', input.attr('id') || input.attr('name'), 'Container:', pickerContainer.length);
        }
        
        function initializeAllPickers() {
            console.log('WebinoCRM Custom Datepicker: Initializing all pickers...');
            var pickers = $('.webino-jalali-date-picker, .webino-date-picker');
            console.log('WebinoCRM Custom Datepicker: Found', pickers.length, 'datepicker fields');
            
            if (pickers.length === 0) {
                console.warn('WebinoCRM Custom Datepicker: No datepicker fields found!');
                // Try to find by name attribute
                var byName = $('input[name*="date"], input[id*="date"]');
                console.log('WebinoCRM Custom Datepicker: Found', byName.length, 'fields by name/id containing "date"');
            }
            
            pickers.each(function() {
                var $this = $(this);
                var fieldId = $this.attr('id') || $this.attr('name') || 'unknown';
                console.log('WebinoCRM Custom Datepicker: Processing field:', fieldId, 'Active:', $this.data('webino-datepicker-active'));
                
                if (!$this.data('webino-datepicker-active')) {
                    console.log('WebinoCRM Custom Datepicker: Creating picker for', fieldId);
                    try {
                        createPicker($this);
                        console.log('WebinoCRM Custom Datepicker: Successfully created picker for', fieldId);
                    } catch(e) {
                        console.error('WebinoCRM Custom Datepicker: Error creating picker for', fieldId, ':', e);
                    }
                } else {
                    console.log('WebinoCRM Custom Datepicker: Picker already active for', fieldId);
                }
            });
        }
        
        // Initialize immediately when DOM is ready
        console.log('WebinoCRM Custom Datepicker: DOM ready, starting initialization...');
        initializeAllPickers();
        
        // Also initialize after delays to catch dynamically added fields
        setTimeout(function() {
            console.log('WebinoCRM Custom Datepicker: Delayed initialization (100ms)');
            initializeAllPickers();
        }, 100);
        setTimeout(function() {
            console.log('WebinoCRM Custom Datepicker: Delayed initialization (500ms)');
            initializeAllPickers();
        }, 500);
        setTimeout(function() {
            console.log('WebinoCRM Custom Datepicker: Delayed initialization (1000ms)');
            initializeAllPickers();
        }, 1000);
        setTimeout(function() {
            console.log('WebinoCRM Custom Datepicker: Final delayed initialization (2000ms)');
            initializeAllPickers();
        }, 2000);

        // Watch for dynamically added datepicker fields
        const observer = new MutationObserver(function(mutationsList) {
            for (const mutation of mutationsList) {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    // Check if any added node is a datepicker field
                    for (let node of mutation.addedNodes) {
                        if (node.nodeType === 1) { // Element node
                            var $node = $(node);
                            if ($node.hasClass('webino-jalali-date-picker') || $node.hasClass('webino-date-picker') || $node.find('.webino-jalali-date-picker, .webino-date-picker').length > 0) {
                                console.log('WebinoCRM Custom Datepicker: New datepicker field detected, initializing...');
                                initializeAllPickers();
                                break;
                            }
                        }
                    }
                }
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
        
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.webino-datepicker-container').length && !$(e.target).hasClass('webino-jalali-date-picker')) {
                $('.webino-datepicker-container').hide();
            }
        });
    }

    // Initialize the custom datepicker
    console.log('WebinoCRM Custom Datepicker: Starting initialization...');
    console.log('WebinoCRM Custom Datepicker: jQuery version:', $.fn.jquery);
    console.log('WebinoCRM Custom Datepicker: Document ready state:', document.readyState);
    
    try {
        WebinoJalaliDatePicker();
        console.log('WebinoCRM Custom Datepicker: Initialization complete');
    } catch(e) {
        console.error('WebinoCRM Custom Datepicker: Error during initialization:', e);
        console.error('WebinoCRM Custom Datepicker: Stack trace:', e.stack);
    }
    
    // Also expose globally for debugging
    window.WebinoJalaliDatePickerInstance = WebinoJalaliDatePicker;
    console.log('WebinoCRM Custom Datepicker: Exposed globally as window.WebinoJalaliDatePickerInstance');
});