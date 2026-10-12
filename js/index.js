function toggleMenu() {
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');

    if (!hamburger || !navMenu) {
        return;
    }

    setMenuOpen(!navMenu.classList.contains('active'));
}

function setMenuOpen(isOpen) {
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');

    if (!hamburger || !navMenu) {
        return;
    }

    hamburger.classList.toggle('active', isOpen);
    navMenu.classList.toggle('active', isOpen);
    hamburger.setAttribute('aria-expanded', String(isOpen));
    hamburger.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
}

function initScrollAnimations() {
    const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -100px 0px' };
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    document.querySelectorAll('.section-header, .gallery-grid, .derniers-evenements, .telechargements, .footer').forEach(element => {
        observer.observe(element);
    });
}

function openContactModal(event) {
    event.preventDefault();
    const modal = document.getElementById('contactModal');
    if (modal) {
        modal.classList.add('active');
    }
}

function closeContactModal() {
    const modal = document.getElementById('contactModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

window.addEventListener('click', event => {
    const modal = document.getElementById('contactModal');
    if (modal && event.target === modal) {
        modal.classList.remove('active');
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const hamburger = document.querySelector('[data-menu-toggle]');
    if (hamburger) {
        hamburger.addEventListener('click', toggleMenu);
    }

    document.querySelectorAll('[data-contact-open]').forEach(link => {
        link.addEventListener('click', openContactModal);
    });
    document.querySelectorAll('[data-contact-close]').forEach(button => {
        button.addEventListener('click', closeContactModal);
    });

    document.querySelectorAll('.nav a').forEach(link => {
        link.addEventListener('click', () => {
            setMenuOpen(false);
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            setMenuOpen(false);
            closeContactModal();
        }
    });

    window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
            setMenuOpen(false);
        }
    });

    initScrollAnimations();
});
