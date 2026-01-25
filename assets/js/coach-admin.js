/* global window, document */

(function () {
    'use strict';

    window.copyReferralLink = function copyReferralLink() {
        const input = document.getElementById('referral-link-input');
        if (!input) return;

        input.select();
        input.setSelectionRange(0, 99999);

        try {
            document.execCommand('copy');
        } catch (e) {
            // ignore
        }

        const btn = (window.event && window.event.target) ? window.event.target : document.querySelector('.copy-link-btn');
        if (!btn) return;

        const originalText = btn.textContent;
        btn.textContent = '✅ Copied!';
        btn.style.background = '#28a745';

        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.background = '#0073aa';
        }, 2000);
    };

    window.copyLink = function copyLink(button) {
        const input = button && button.previousElementSibling ? button.previousElementSibling : null;
        if (!input) return;

        input.select();
        input.setSelectionRange(0, 99999);

        try {
            document.execCommand('copy');
        } catch (e) {
            // ignore
        }

        if (button) {
            const original = button.textContent;
            button.textContent = 'Copied!';
            setTimeout(() => { button.textContent = original; }, 2000);
        }
    };
})();

