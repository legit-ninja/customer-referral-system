jQuery(document).ready(function($) {
    if (typeof Shepherd === 'undefined') {
        console.warn('InterSoccer Coach Tour: Shepherd.js is not available. Skipping intro tour.');
        return;
    }

    if (typeof intersoccer_tour === 'undefined') {
        console.warn('InterSoccer Coach Tour: tour configuration is missing.');
        return;
    }

    const isDashboard = window.location.href.includes('intersoccer-coach-dashboard');
    const tourAlreadyCompleted = Boolean(intersoccer_tour.tour_completed);
    const debugMode = Boolean(intersoccer_tour.debug);

    if (isDashboard && (!tourAlreadyCompleted || debugMode)) {
        const tour = new Shepherd.Tour({
            defaultStepOptions: {
                cancelIcon: { enabled: true },
                classes: 'intersoccer-tour',
                scrollTo: { behavior: 'smooth', block: 'center' }
            },
            useModalOverlay: true
        });

        const steps = [
            {
                id: 'step1',
                text: 'Welcome! Here’s your unique referral link to share with players.',
                attachTo: { element: '#tour-actions .primary', on: 'bottom' },
                buttons: [{ text: 'Next', action: tour.next }]
            },
            {
                id: 'step2',
                text: 'Earn commissions for each player who signs up using your link!',
                attachTo: { element: '#tour-credits', on: 'bottom' },
                buttons: [{ text: 'Back', action: tour.back }, { text: 'Next', action: tour.next }]
            },
            {
                id: 'step3',
                text: 'Track your progress and tier status here.',
                attachTo: { element: '#tour-tier', on: 'bottom' },
                buttons: [{ text: 'Back', action: tour.back }, { text: 'Next', action: tour.next }]
            },
            {
                id: 'step4',
                text: 'Access marketing resources to boost your referrals!',
                attachTo: { element: '#view-resources', on: 'bottom' },
                buttons: [{
                    text: 'Back',
                    action: tour.back
                }, {
                    text: 'Finish',
                    action: () => {
                        $.post({
                            url: intersoccer_tour.ajax_url,
                            data: {
                                action: 'complete_tour',
                                nonce: intersoccer_tour.nonce,
                                user_id: intersoccer_tour.user_id
                            }
                        });
                        tour.complete();
                    }
                }]
            }
        ];

        const validSteps = [];

        steps.forEach((step) => {
            const selector = step.attachTo && step.attachTo.element;
            if (selector && !document.querySelector(selector)) {
                console.warn(`InterSoccer Coach Tour: Skipping step "${step.id}". Selector not found: ${selector}`);
                return;
            }

            tour.addStep(step);
            validSteps.push(step);
        });

        if (validSteps.length > 0) {
            tour.start();
        } else {
            console.warn('InterSoccer Coach Tour: No valid steps were added. Tour will not start.');
        }
    }
});