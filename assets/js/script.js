document.addEventListener('DOMContentLoaded', function () {
    const backLink = document.querySelector('[data-navigation-back]');
    if (backLink) {
        backLink.addEventListener('click', function (event) {
            const previousPage = document.referrer ? new URL(document.referrer) : null;

            if (
                previousPage
                && previousPage.origin === window.location.origin
                && previousPage.href !== window.location.href
            ) {
                event.preventDefault();
                window.history.back();
            }
        });
    }

    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay show';
    overlay.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(overlay);

    window.setTimeout(function () {
        overlay.classList.remove('show');
        overlay.remove();
    }, 650);

    const scrollBtn = document.getElementById('scrollTopBtn');
    if (scrollBtn) {
        window.addEventListener('scroll', function () {
            scrollBtn.classList.toggle('show', window.scrollY > 300);
        });
        scrollBtn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    const togglePassword = document.getElementById('togglePassword');
    const passwordField = document.getElementById('passwordField');
    if (togglePassword && passwordField) {
        togglePassword.addEventListener('click', function () {
            const isHidden = passwordField.type === 'password';
            passwordField.type = isHidden ? 'text' : 'password';
            togglePassword.innerHTML = isHidden ? '<i class="fa-solid fa-eye-slash"></i>' : '<i class="fa-solid fa-eye"></i>';
        });
    }

    const forms = document.querySelectorAll('form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            const loading = document.createElement('div');
            loading.className = 'loading-overlay show';
            loading.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(loading);
            window.setTimeout(function () {
                if (loading.parentNode) {
                    loading.remove();
                }
            }, 1800);
        });
    });

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }

        const shareButton = event.target.closest('[data-share-event]');
        if (shareButton) {
            const shareUrl = shareButton.getAttribute('data-share-url') || window.location.href;
            const eventUrl = new URL(shareUrl, window.location.href).href.split('#')[0];
            const shareData = {
                title: shareButton.getAttribute('data-share-title') || document.title,
                text: shareButton.getAttribute('data-share-text') || '',
                url: eventUrl
            };
            const originalLabel = shareButton.getAttribute('data-share-label') || 'Share';

            if (navigator.share) {
                navigator.share(shareData).catch(function (error) {
                    if (error.name !== 'AbortError') {
                        window.prompt('Copy this event link to share it:', eventUrl);
                    }
                });
                return;
            }

            const copyPromise = navigator.clipboard
                ? navigator.clipboard.writeText(eventUrl)
                : Promise.reject(new Error('Clipboard access is unavailable.'));
            copyPromise.then(function () {
                shareButton.textContent = 'Link copied';
                window.setTimeout(function () {
                    shareButton.innerHTML = originalLabel === 'Share'
                        ? '<i class="fa-solid fa-share-nodes" aria-hidden="true"></i><span class="visually-hidden">Share event</span>'
                        : '<i class="fa-solid fa-share-nodes me-2" aria-hidden="true"></i>' + originalLabel;
                }, 1600);
            }).catch(function () {
                window.prompt('Copy this event link to share it:', eventUrl);
            });
            return;
        }

        const button = event.target.closest('[data-copy-registration-number]');
        if (!button) {
            return;
        }

        const registrationNumber = button.getAttribute('data-copy-registration-number');
        if (!registrationNumber || !navigator.clipboard) {
            button.textContent = 'Copy unavailable';
            return;
        }

        navigator.clipboard.writeText(registrationNumber).then(function () {
            button.textContent = 'Copied';
            window.setTimeout(function () {
                button.textContent = 'Copy';
            }, 1600);
        }).catch(function () {
            button.textContent = 'Copy failed';
        });
    });
});