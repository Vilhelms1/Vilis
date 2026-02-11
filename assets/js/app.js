const body = document.body;

const translations = {
    lv: {
        'hero.title': 'Profesionāla mācību platforma tehnikumiem',
        'hero.subtitle': 'Koncentrēta uz profesijām un gala eksāmenu sagatavošanu.',
        'news.empty': 'Jaunumi tiks pievienoti drīzumā',
        'news.emptyBody': 'Sekojiet līdzi jaunākajai informācijai par kursiem un pasākumiem.',
        'login.subtitle': 'Mācību pārvaldības sistēma',
        'login.loginBtn': 'Pieslēgties',
        'login.registerBtn': 'Reģistrēties',
        'login.username': 'Lietotājvārds vai e-pasts',
        'login.password': 'Parole',
        'register.firstName': 'Vārds',
        'register.lastName': 'Uzvārds',
        'register.username': 'Lietotājvārds',
        'register.email': 'E-pasts',
        'register.password': 'Parole',
        'register.confirm': 'Apstipriniet paroli'
    },
    en: {
        'hero.title': 'A professional learning platform for technical schools',
        'hero.subtitle': 'Focused on professions and final exam preparation.',
        'news.empty': 'News will appear soon',
        'news.emptyBody': 'Follow the latest updates about courses and events.',
        'login.subtitle': 'Learning management system',
        'login.loginBtn': 'Login',
        'login.registerBtn': 'Register',
        'login.username': 'Username or email',
        'login.password': 'Password',
        'register.firstName': 'First name',
        'register.lastName': 'Last name',
        'register.username': 'Username',
        'register.email': 'Email',
        'register.password': 'Password',
        'register.confirm': 'Confirm password'
    }
};

function applyTranslations(lang) {
    document.querySelectorAll('[data-i18n]').forEach(el => {
        const key = el.getAttribute('data-i18n');
        if (translations[lang] && translations[lang][key]) {
            el.textContent = translations[lang][key];
        }
    });
    body.setAttribute('data-lang', lang);
}

function initLanguageToggle() {
    const stored = localStorage.getItem('lang');
    const initial = stored || body.getAttribute('data-lang') || 'lv';
    applyTranslations(initial);

    document.querySelectorAll('[data-lang-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const current = body.getAttribute('data-lang') || 'lv';
            const next = current === 'lv' ? 'en' : 'lv';
            localStorage.setItem('lang', next);
            applyTranslations(next);
        });
    });
}

function initThemeToggle() {
    const stored = localStorage.getItem('theme');
    const initial = stored || body.getAttribute('data-theme') || 'light';
    body.setAttribute('data-theme', initial);

    document.querySelectorAll('[data-theme-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const next = body.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            body.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
        });
    });
}

function initSlider() {
    const track = document.querySelector('.slider-track');
    if (!track) {
        return;
    }
    let index = 0;
    const slides = track.querySelectorAll('.slide');
    if (slides.length <= 1) {
        return;
    }

    setInterval(() => {
        index = (index + 1) % slides.length;
        track.style.transform = `translateX(-${index * 100}%)`;
    }, 6000);
}

function switchTab(tabName, trigger) {
    // Remove active from all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    
    // Add active to selected tab content
    const target = document.getElementById(tabName);
    if (target) {
        target.classList.add('active');
    }
    
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    if (trigger) {
        trigger.classList.add('active');
    } else if (typeof event !== 'undefined' && event?.target) {
        event.target.classList.add('active');
    } else {
        const fallback = document.querySelector(`.tab-btn[data-tab="${tabName}"]`);
        if (fallback) fallback.classList.add('active');
    }
}

function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    window.addEventListener('scroll', () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initLanguageToggle();
    initSlider();
    initNavbarScroll();
});
