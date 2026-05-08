(function () {
    const savedTheme = localStorage.getItem('theme') || 'light';

    document.documentElement.setAttribute('data-bs-theme', savedTheme);
    document.documentElement.setAttribute('data-custom-theme', savedTheme);

    const savedLang = localStorage.getItem('lang') || 'en';
    document.documentElement.setAttribute('lang', savedLang);
    document.documentElement.setAttribute('custom-lang', savedLang);

    async function loadTranslations(lang = "en") {
        try {
            const response = await fetch(`assets/i18n/${lang}.json`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const translations = await response.json();

            document.querySelectorAll("[data-i18n]").forEach(el => {
                const key = el.getAttribute("data-i18n");

                if (key.startsWith("[placeholder]")) {
                    const cleanKey = key.replace("[placeholder]", "");
                    if (translations[cleanKey]) {
                        el.setAttribute("placeholder", translations[cleanKey]);
                    }
                } else if (key.startsWith("[title]")) {
                    const cleanKey = key.replace("[title]", "");
                    if (translations[cleanKey]) {
                        el.setAttribute("title", translations[cleanKey]);
                    }
                } else if (translations[key]) {
                    el.textContent = translations[key];
                }
            });

            const activeItem = document.querySelector(`.lang-item[data-lang="${lang}"]`);
            if (activeItem) {
                const icon = activeItem.querySelector("iconify-icon").getAttribute("icon");
                const currentIcon = document.querySelector(".current-lang-icon");
                if (currentIcon) currentIcon.setAttribute("icon", icon);

                // Show checkmark
                document.querySelectorAll(".lang-check").forEach(check => check.style.display = "none");
                const checkIcon = activeItem.querySelector(".lang-check");
                if (checkIcon) checkIcon.style.display = "inline";
            }

        } catch (err) {
            console.error("Error loading translations:", err);
        }
    }

    loadTranslations(savedLang);

    window.addEventListener('DOMContentLoaded', function () {
        document.body.style.opacity = '1';
    });
})();


