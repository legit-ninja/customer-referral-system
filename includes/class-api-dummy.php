<?php
// Add this to your main plugin file or create a separate API file

class InterSoccer_Referral_API_Dummy {
    
    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }
    
    public function register_routes() {
        register_rest_route('intersoccer/v1', '/sheets-data', [
            'methods' => 'GET',
            'callback' => [$this, 'get_sheets_data'],
            'permission_callback' => '__return_true' // Make public for demo
        ]);
        
        register_rest_route('intersoccer/v1', '/coach-performance/(?P<coach_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_coach_performance'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
        
        register_rest_route('intersoccer/v1', '/roi-dashboard', [
            'methods' => 'GET',
            'callback' => [$this, 'get_roi_dashboard'],
            'permission_callback' => [$this, 'check_permissions']
        ]);
    }
    
    /**
     * Simulate Google Sheets export data for scalability demo
     */
    public function get_sheets_data($request) {
        // Simulate realistic coach performance data for enterprise pitch
        $demo_data = [
            'metadata' => [
                'title' => 'InterSoccer Coach Performance Dashboard',
                'generated_at' => current_time('c'),
                'total_coaches' => 847,
                'total_referrals' => 12463,
                'total_revenue_impact' => 2847392.50,
                'period' => 'Q3 2025'
            ],
            'summary_metrics' => [
                'coach_acquisition_rate' => '+23.4%',
                'referral_conversion_rate' => '18.7%',
                'customer_ltv_increase' => '+41.2%',
                'coach_retention_rate' => '89.3%',
                'avg_coach_monthly_earnings' => 847.25,
                'roi_multiplier' => 4.7
            ],
            'regional_breakdown' => [
                [
                    'region' => 'Zurich Metro',
                    'coaches' => 243,
                    'referrals' => 3847,
                    'revenue' => 892470.00,
                    'growth_rate' => '+28.3%'
                ],
                [
                    'region' => 'Geneva Area',
                    'coaches' => 156,
                    'referrals' => 2341,
                    'revenue' => 547830.00,
                    'growth_rate' => '+31.7%'
                ],
                [
                    'region' => 'Basel Region',
                    'coaches' => 134,
                    'referrals' => 1987,
                    'revenue' => 456920.00,
                    'growth_rate' => '+19.8%'
                ],
                [
                    'region' => 'Bern District',
                    'coaches' => 118,
                    'referrals' => 1756,
                    'revenue' => 398650.00,
                    'growth_rate' => '+24.1%'
                ],
                [
                    'region' => 'Other Regions',
                    'coaches' => 196,
                    'referrals' => 2532,
                    'revenue' => 551522.50,
                    'growth_rate' => '+15.9%'
                ]
            ],
            'top_performers' => [
                [
                    'coach_name' => 'Thomas M.',
                    'tier' => 'Platinum',
                    'referrals' => 47,
                    'monthly_earnings' => 3247.80,
                    'customer_satisfaction' => 4.9,
                    'location' => 'Zurich'
                ],
                [
                    'coach_name' => 'Sandra W.',
                    'tier' => 'Platinum', 
                    'referrals' => 43,
                    'monthly_earnings' => 2987.40,
                    'customer_satisfaction' => 4.8,
                    'location' => 'Geneva'
                ],
                [
                    'coach_name' => 'Michael S.',
                    'tier' => 'Gold',
                    'referrals' => 31,
                    'monthly_earnings' => 2156.90,
                    'customer_satisfaction' => 4.7,
                    'location' => 'Basel'
                ]
            ],
            'scalability_projections' => [
                'current_month' => [
                    'coaches' => 847,
                    'projected_revenue' => 1250000,
                    'commission_payout' => 187500
                ],
                'next_quarter' => [
                    'coaches' => 1200,
                    'projected_revenue' => 1800000,
                    'commission_payout' => 270000
                ],
                'year_end' => [
                    'coaches' => 2000,
                    'projected_revenue' => 3500000,
                    'commission_payout' => 525000
                ],
                'scaling_efficiency' => [
                    'cost_per_acquisition' => 45.30,
                    'customer_lifetime_value' => 847.20,
                    'roi_timeline' => '3.2 months',
                    'break_even_point' => '12 referrals per coach'
                ]
            ],
            'competitive_advantages' => [
                'automated_tracking' => true,
                'real_time_analytics' => true,
                'multi_tier_commissions' => true,
                'gamification_system' => true,
                'white_label_ready' => true,
                'api_integrations' => ['Salesforce', 'HubSpot', 'Mailchimp', 'Stripe']
            ]
        ];
        
        return rest_ensure_response($demo_data);
    }
    
    /**
     * Get detailed coach performance for individual analysis
     */
    public function get_coach_performance($request) {
        $coach_id = $request['coach_id'];
        
        // Simulate detailed coach data
        $performance_data = [
            'coach_info' => [
                'id' => $coach_id,
                'name' => 'Sample Coach ' . $coach_id,
                'tier' => 'Gold',
                'join_date' => '2025-01-15',
                'location' => 'Zurich',
                'specialization' => ['Youth Training', 'Technical Skills']
            ],
            'monthly_performance' => [
                'referrals_this_month' => 8,
                'conversion_rate' => '22.5%',
                'earnings_this_month' => 1247.80,
                'customer_satisfaction' => 4.6,
                'tier_progress' => '67%' // Progress to next tier
            ],
            'historical_data' => array_map(function($i) {
                return [
                    'month' => date('M Y', strtotime("-$i months")),
                    'referrals' => rand(3, 12),
                    'earnings' => rand(450, 1800),
                    'satisfaction' => round(rand(42, 50) / 10, 1)
                ];
            }, range(0, 11)),
            'customer_feedback' => [
                'recent_reviews' => [
                    'Excellent coaching, very professional!',
                    'My child improved significantly.',
                    'Great communication and flexibility.'
                ],
                'net_promoter_score' => 8.7
            ],
            'goals_tracking' => [
                'monthly_target' => 10,
                'quarterly_target' => 30,
                'yearly_target' => 120,
                'current_progress' => [
                    'monthly' => '80%',
                    'quarterly' => '73%',
                    'yearly' => '45%'
                ]
            ]
        ];
        
        return rest_ensure_response($performance_data);
    }
    
    /**
     * ROI Dashboard data for executive presentations
     */
    public function get_roi_dashboard($request) {
        $roi_data = [
            'executive_summary' => [
                'program_roi' => '347%',
                'payback_period' => '2.1 months',
                'cost_per_acquisition' => 42.50,
                'lifetime_value_increase' => '+156%',
                'coach_satisfaction_rate' => '91.3%',
                'customer_retention_improvement' => '+68%'
            ],
            'financial_impact' => [
                'total_program_investment' => 125000,
                'direct_revenue_generated' => 558750,
                'commission_payouts' => 89400,
                'net_profit_increase' => 344350,
                'cost_savings_vs_traditional_marketing' => 78900
            ],
            'growth_trajectories' => [
                'coach_growth' => [
                    ['period' => 'Jan 2025', 'coaches' => 45, 'active' => 38],
                    ['period' => 'Feb 2025', 'coaches' => 67, 'active' => 59],
                    ['period' => 'Mar 2025', 'coaches' => 89, 'active' => 78],
                    ['period' => 'Apr 2025', 'coaches' => 134, 'active' => 121],
                    ['period' => 'May 2025', 'coaches' => 198, 'active' => 178],
                    ['period' => 'Jun 2025', 'coaches' => 267, 'active' => 241],
                    ['period' => 'Jul 2025', 'coaches' => 345, 'active' => 312],
                    ['period' => 'Aug 2025', 'coaches' => 847, 'active' => 789]
                ],
                'revenue_impact' => [
                    ['period' => 'Q1 2025', 'referral_revenue' => 89750, 'organic_growth' => '+12%'],
                    ['period' => 'Q2 2025', 'referral_revenue' => 234600, 'organic_growth' => '+28%'],
                    ['period' => 'Q3 2025', 'referral_revenue' => 456800, 'organic_growth' => '+41%'],
                    ['period' => 'Q4 2025 (Proj)', 'referral_revenue' => 678900, 'organic_growth' => '+52%']
                ]
            ],
            'market_expansion' => [
                'new_regions_penetrated' => 12,
                'market_share_increase' => '+8.7%',
                'competitor_coaches_acquired' => 23,
                'brand_awareness_lift' => '+34%'
            ],
            'operational_efficiency' => [
                'customer_acquisition_cost_reduction' => '-31%',
                'sales_cycle_shortening' => '-45%',
                'lead_quality_improvement' => '+67%',
                'customer_support_ticket_reduction' => '-28%'
            ],
            'scalability_metrics' => [
                'automation_level' => '87%',
                'manual_intervention_required' => '13%',
                'system_capacity' => '10,000 coaches',
                'current_utilization' => '8.5%',
                'projected_break_even_scale' => '1,200 active coaches'
            ],
            'risk_assessment' => [
                'coach_churn_risk' => 'Low (8.7%)',
                'system_scalability_risk' => 'Very Low',
                'competitive_response_risk' => 'Medium',
                'regulatory_compliance_risk' => 'Low',
                'mitigation_strategies' => [
                    'Enhanced coach onboarding program',
                    'Advanced analytics for early churn detection',
                    'Legal compliance monitoring system',
                    'Competitive intelligence dashboard'
                ]
            ]
        ];
        
        return rest_ensure_response($roi_data);
    }
    
    public function check_permissions($request) {
        return current_user_can('manage_options');
    }
}

// Initialize the API dummy
new InterSoccer_API_Dummy();

// Add this JavaScript to your admin dashboard for Steve's demo
?>
<script type="text/javascript">
// Live API Demo Functions for Steve's Presentation
function demonstrateScalability() {
    console.log('🚀 InterSoccer API Demo - Scalability Analysis');
    
    fetch('/wp-json/intersoccer/v1/sheets-data')
        .then(response => response.json())
        .then(data => {
            console.log('📊 Current Scale:', data.metadata);
            console.log('💰 ROI Multiplier:', data.summary_metrics.roi_multiplier + 'x');
            console.log('🎯 Conversion Rate:', data.summary_metrics.referral_conversion_rate);
            console.log('📈 Projected Year-End Coaches:', data.scalability_projections.year_end.coaches);
            
            // Simulate real-time updates
            updateScalabilityDashboard(data);
        });
}

function updateScalabilityDashboard(data) {
    // This would integrate with your dashboard charts
    if (window.performanceChart) {
        window.performanceChart.data.datasets[0].data = data.regional_breakdown.map(r => r.referrals);
        window.performanceChart.update('active');
    }
}

// Simulate real-time coach performance tracking
function trackCoachPerformance(coachId) {
    fetch(`/wp-json/intersoccer/v1/coach-performance/${coachId}`)
        .then(response => response.json())
        .then(data => {
            console.log(`👨‍🏫 Coach ${coachId} Performance:`, data.monthly_performance);
            console.log('🏆 Tier Progress:', data.goals_tracking.current_progress);
            
            // Show real-time notifications
            showPerformanceAlert(data);
        });
}

function showPerformanceAlert(data) {
    if (data.monthly_performance.conversion_rate > '20%') {
        console.log('🎉 High Performance Alert: Coach exceeding targets!');
    }
}

// Demo function for Steve's presentation
function runLiveDemo() {
    console.log('🎬 Starting Live InterSoccer Demo...');
    
    demonstrateScalability();
    
    // Show multiple coach tracking
    [247, 156, 189].forEach((coachId, index) => {
        setTimeout(() => trackCoachPerformance(coachId), index * 1000);
    });
    
    // Simulate ROI calculation
    setTimeout(() => {
        fetch('/wp-json/intersoccer/v1/roi-dashboard')
            .then(response => response.json())
            .then(data => {
                console.log('💎 Program ROI:', data.executive_summary.program_roi);
                console.log('⚡ Payback Period:', data.executive_summary.payback_period);
                console.log('🎯 Net Profit Increase:', data.financial_impact.net_profit_increase.toLocaleString() + ' CHF');
            });
    }, 3000);
}

// Auto-run demo when page loads (for Steve's presentation)
jQuery(document).ready(function($) {
    if (window.location.search.includes('demo=steve')) {
        setTimeout(runLiveDemo, 2000);
    }
});
</script>
                    '