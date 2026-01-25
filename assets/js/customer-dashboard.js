/* global window, document, navigator */

(function () {
    'use strict';

    // Copy referral code function
    function copyReferralCode() {
        const codeInput = document.getElementById('referral-code');
        const copyBtn = document.getElementById('copy-code-btn');

        if (!codeInput || !copyBtn) {
            return;
        }

        codeInput.select();
        codeInput.setSelectionRange(0, 99999); // For mobile devices

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(codeInput.value).then(function () {
                    copyBtn.classList.add('copied');
                    setTimeout(() => copyBtn.classList.remove('copied'), 2000);
                });
            } else {
                document.execCommand('copy');
                copyBtn.classList.add('copied');
                setTimeout(() => copyBtn.classList.remove('copied'), 2000);
            }
        } catch (err) {
            document.execCommand('copy');
            copyBtn.classList.add('copied');
            setTimeout(() => copyBtn.classList.remove('copied'), 2000);
        }
    }

    // Copy referral link function
    function copyReferralLink() {
        const linkInput = document.getElementById('referral-link');
        const copyBtn = document.getElementById('copy-link-btn');

        if (!linkInput || !copyBtn) {
            return;
        }

        linkInput.select();
        linkInput.setSelectionRange(0, 99999); // For mobile devices

        try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(linkInput.value).then(function () {
                    copyBtn.classList.add('copied');
                    setTimeout(() => copyBtn.classList.remove('copied'), 2000);
                });
            } else {
                document.execCommand('copy');
                copyBtn.classList.add('copied');
                setTimeout(() => copyBtn.classList.remove('copied'), 2000);
            }
        } catch (err) {
            document.execCommand('copy');
            copyBtn.classList.add('copied');
            setTimeout(() => copyBtn.classList.remove('copied'), 2000);
        }
    }

    // Hide theme's post metadata, navigation, and related content
    function hideThemeElements() {
        try {
            const accountContent = document.querySelector('.woocommerce-MyAccount-content');
            if (!accountContent) return;

            // Protect our referral section - never hide anything inside it
            const referralDashboard = document.querySelector('.intersoccer-customer-dashboard');
            const referralSection = document.querySelector('.referral-section');

            // Find and hide post navigation (only if not in our referral section)
            const postNav = Array.from(document.querySelectorAll('nav')).find(nav =>
                nav.textContent && nav.textContent.includes('Post navigation') &&
                (!referralDashboard || !referralDashboard.contains(nav))
            );
            if (postNav) {
                postNav.style.display = 'none';
                postNav.style.visibility = 'hidden';
                // Also hide parent if it's a wrapper
                if (postNav.parentElement && postNav.parentElement !== accountContent &&
                    !postNav.parentElement.classList.contains('woocommerce-MyAccount-content') &&
                    (!referralDashboard || !referralDashboard.contains(postNav.parentElement))) {
                    postNav.parentElement.style.display = 'none';
                    postNav.parentElement.style.visibility = 'hidden';
                }
            }

            // Hide "You May Also Like" section - target all related_wrap sections and filter
            const allRelatedSections = Array.from(document.querySelectorAll('section.related_wrap'));
            allRelatedSections.forEach(section => {
                // Never hide anything that contains our referral section
                if (referralDashboard && section.contains(referralDashboard)) {
                    return;
                }
                if (referralSection && section.contains(referralSection)) {
                    return;
                }

                // Check if this section contains "You May Also Like"
                const h3 = section.querySelector('h3');
                if (h3 && h3.textContent && h3.textContent.includes('You May Also Like')) {
                    // Use setProperty with important flag to ensure it takes precedence
                    section.style.setProperty('display', 'none', 'important');
                    section.style.setProperty('visibility', 'hidden', 'important');
                }
            });

            // Hide theme-generated social share buttons (not our referral section buttons)
            // Only check divs that are siblings of accountContent, not children
            const article = document.querySelector('.woocommerce-account article');
            if (article) {
                const allDivs = Array.from(article.children).filter(el => el.tagName === 'DIV');
                allDivs.forEach(div => {
                    // Skip accountContent and anything inside it (which includes our referral section)
                    if (div === accountContent || accountContent.contains(div)) {
                        return;
                    }

                    // Never hide anything that contains our referral section
                    if (referralDashboard && div.contains(referralDashboard)) {
                        return;
                    }
                    if (referralSection && div.contains(referralSection)) {
                        return;
                    }

                    // Check if this div contains theme share buttons (not our referral buttons)
                    const hasThemeShare = div.querySelector('a[href*="twitter.com/intent/tweet"]') &&
                        div.querySelector('a[href*="facebook.com/sharer"]') &&
                        !div.querySelector('.social-share-buttons'); // Not our buttons

                    if (hasThemeShare) {
                        div.style.display = 'none';
                        div.style.visibility = 'hidden';
                    }
                });
            }

            // Ensure our referral section is always visible
            if (referralDashboard) {
                referralDashboard.style.display = '';
                referralDashboard.style.visibility = '';
            }
            if (referralSection) {
                referralSection.style.display = '';
                referralSection.style.visibility = '';
            }
        } catch (e) {
            // eslint-disable-next-line no-console
            console.error('Error hiding theme elements:', e);
        }
    }

    // Keep these available for existing inline onclick handlers.
    window.copyReferralCode = copyReferralCode;
    window.copyReferralLink = copyReferralLink;

    // Run immediately and also on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideThemeElements);
    } else {
        hideThemeElements();
    }

    // Also run after delays to catch dynamically added content
    setTimeout(hideThemeElements, 100);
    setTimeout(hideThemeElements, 500);
    setTimeout(hideThemeElements, 1000);
})();

