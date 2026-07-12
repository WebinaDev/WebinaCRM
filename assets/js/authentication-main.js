(function () {
    'use strict';
    if (localStorage.getItem("xintradarktheme")) {
        document.querySelector("html").setAttribute("data-theme-mode", "dark")
        document.querySelector("html").setAttribute("data-menu-styles", "dark")
        document.querySelector("html").setAttribute("data-header-styles", "dark")
    }
    // Language-based direction - use webino_language from cookie/localStorage
    try {
        let lang = localStorage.getItem('webino_language');
        if (!lang) {
            // Try to get from cookie
            const cookies = document.cookie.split(';');
            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                if (cookie.startsWith('webino_language=')) {
                    lang = cookie.substring('webino_language='.length);
                    break;
                }
            }
        }
        if (!lang) {
            // Check HTML dir attribute as fallback
            const htmlDir = document.documentElement.getAttribute('dir');
            lang = htmlDir === 'rtl' ? 'fa' : 'en';
        }
        
        const isRTL = lang === 'fa';
        const html = document.querySelector('html');
        if (html) {
            html.setAttribute("dir", isRTL ? "rtl" : "ltr");
            html.setAttribute("lang", isRTL ? "fa" : "en");
            
            const styleLink = document.querySelector("#style");
            if (styleLink) {
                const bootstrapCss = isRTL ? 'bootstrap.rtl.min.css' : 'bootstrap.min.css';
                styleLink.setAttribute("href", "./assets/libs/bootstrap/css/" + bootstrapCss);
            }
        }
    } catch (e) {
        // Silently fail if localStorage/cookie is not available
    }
    if (localStorage.getItem("xintralayout") == "horizontal") {
        document.querySelector("html").setAttribute("data-nav-layout", "horizontal") 
    }
    function localStorageBackup() {

        // if there is a value stored, update color picker and background color
        // Used to retrive the data from local storage
        if (localStorage.primaryRGB) {
            if (document.querySelector('.theme-container-primary')) {
                document.querySelector('.theme-container-primary').value = localStorage.primaryRGB;
            }
            document.querySelector('html').style.setProperty('--primary-rgb', localStorage.primaryRGB);
        }
        if (localStorage.bodyBgRGB && localStorage.bodylightRGB) {
            if (document.querySelector('.theme-container-background')) {
                document.querySelector('.theme-container-background').value = localStorage.bodyBgRGB;
            }
            document.querySelector('html').style.setProperty('--body-bg-rgb', localStorage.bodyBgRGB);
            document.querySelector('html').style.setProperty('--body-bg-rgb2', localStorage.bodylightRGB);
            document.querySelector('html').style.setProperty('--light-rgb', localStorage.bodylightRGB);
            document.querySelector('html').style.setProperty('--form-control-bg', `rgb(${localStorage.bodylightRGB})`);
            document.querySelector('html').style.setProperty('--input-border', "rgba(255,255,255,0.1)");
            let html = document.querySelector('html');
            html.setAttribute('data-theme-mode', 'dark');
            html.setAttribute('data-menu-styles', 'dark');
            html.setAttribute('data-header-styles', 'dark');


        }
        if (localStorage.xintradarktheme) {
            let html = document.querySelector('html');
            html.setAttribute('data-theme-mode', 'dark');
        }
        // Language-based direction in localStorageBackup - use webino_language
        try {
            let lang = localStorage.getItem('webino_language');
            if (!lang) {
                // Try to get from cookie
                const cookies = document.cookie.split(';');
                for (let i = 0; i < cookies.length; i++) {
                    const cookie = cookies[i].trim();
                    if (cookie.startsWith('webino_language=')) {
                        lang = cookie.substring('webino_language='.length);
                        break;
                    }
                }
            }
            if (!lang) {
                // Check HTML dir attribute as fallback
                const htmlDir = document.documentElement.getAttribute('dir');
                lang = htmlDir === 'rtl' ? 'fa' : 'en';
            }
            
            const isRTL = lang === 'fa';
            if (isRTL) {
                let html = document.querySelector('html');
                html.setAttribute('dir', 'rtl');
                html.setAttribute('lang', 'fa');
                document.querySelector("#style")?.setAttribute("href", "./assets/libs/bootstrap/css/bootstrap.rtl.min.css");
                setTimeout(() => {
                    rtlFn();
                }, 10);
            } else {
                let html = document.querySelector('html');
                html.setAttribute('dir', 'ltr');
                html.setAttribute('lang', 'en');
                document.querySelector("#style")?.setAttribute("href", "./assets/libs/bootstrap/css/bootstrap.min.css");
                setTimeout(() => {
                    ltrFn();
                }, 10);
            }
        } catch (e) {
            // Silently fail if localStorage/cookie is not available
        }
    }
    localStorageBackup()

})();


function ltrFn() {
    let html = document.querySelector('html')
    if(!document.querySelector("#style").href.includes('bootstrap.min.css')){
        document.querySelector("#style")?.setAttribute("href", "./assets/libs/bootstrap/css/bootstrap.min.css");
    }
    html.setAttribute("dir", "ltr");
}

function rtlFn() {
    let html = document.querySelector('html');
    html.setAttribute("dir", "rtl");
    document.querySelector("#style")?.setAttribute("href", "./assets/libs/bootstrap/css/bootstrap.rtl.min.css");
}