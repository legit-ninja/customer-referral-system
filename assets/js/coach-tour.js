jQuery(document).ready(function($) {
    if (!intersoccer_tour.tour_completed && window.location.href.includes('intersoccer-coach-dashboard')) {
        const tour = new Shepherd.Tour({
            defaultStepOptions: {
                cancelIcon: { enabled: true },
                classes: 'intersoccer-tour',
                scrollTo: { behavior: 'smooth', block: 'center' }
            },
            useModalOverlay: true
        });

        tour.addStep({
            id: 'step1',
            text: 'Welcome! Here’s your unique referral link to share with players.',
            attachTo: { element: '#tour-actions .primary', on: 'bottom' },
            buttons: [{ text: 'Next', action: tour.next }]
        });

        tour.addStep({
            id: 'step2',
            text: 'Earn commissions for each player who signs up using your link!',
            attachTo: { element: '#tour-credits', on: 'bottom' },
            buttons: [{ text: 'Back', action: tour.back }, { text: 'Next', action: tour.next }]
        });

        tour.addStep({
            id: 'step3',
            text: 'Track your progress and tier status here.',
            attachTo: { element: '#tour-tier', on: 'bottom' },
            buttons: [{ text: 'Back', action: tour.back }, { text: 'Next', action: tour.next }]
        });

        tour.addStep({
            id: 'step4',
            text: 'Access marketing resources to boost your referrals!',
            attachTo: { element: '#tour-resources', on: 'bottom' },
            buttons: [{ text: 'Back', action: tour.back }, { text: 'Finish', action: () => {
                $.post({
                    url: intersoccer_tour.ajax_url,
                    data: {
                        action: 'complete_tour',
                        nonce: intersoccer_tour.nonce,
                        user_id: intersoccer_tour.user_id
                    }
                });
                tour.complete();
            } }]
        });

        tour.start();
    }
});