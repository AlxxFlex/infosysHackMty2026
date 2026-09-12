<?php

return [
    'simulation' => [
        'duration_min' => 120,
        'default_start_at' => '2026-09-12T18:00:00-06:00',
        'real_seconds_per_sim_minute' => 1,
        'speed_multiplier' => (float) env('SIMULATION_SPEED_MULTIPLIER', 1),
        'max_concurrent_orders' => (int) env('MAX_CONCURRENT_ORDERS', 2),
        'planning_horizon_min' => 45,
        'cost_per_km_mxn' => (float) env('DEFAULT_COST_PER_KM_MXN', 1.25),
        'vehicle' => env('SIMULATION_VEHICLE', 'motorcycle'),
        'initial_position' => [
            'lat' => (float) env('SIMULATION_INITIAL_LAT', 25.675),
            'lon' => (float) env('SIMULATION_INITIAL_LON', -100.310),
        ],
        'timezone' => env('SIMULATION_TIMEZONE', 'America/Monterrey'),
    ],

    'scoring' => [
        'weights' => [
            'hourly_profit' => 0.30,
            'net_profit' => 0.20,
            'destination_value' => 0.15,
            'batch_potential' => 0.15,
            'pickup_efficiency' => 0.10,
            'reliability' => 0.10,
        ],
        'priority_thresholds' => [
            'high' => 80.0,
            'medium' => 55.0,
        ],
        'safe_slack_min' => 10,
    ],

    'optimization' => [
        'max_candidates' => 100,
        'max_candidate_set_size' => 2,
        'batch_size' => 2,
        'time_budget_ms' => 300,
    ],

    'routing' => [
        'provider' => env('ROUTING_PROVIDER', 'osrm'),
        'base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org'),
        'profile' => env('OSRM_PROFILE', 'driving'),
        'timeout_seconds' => (int) env('OSRM_TIMEOUT_SECONDS', 2),
        'retries' => (int) env('OSRM_RETRIES', 2),
        'retry_sleep_ms' => (int) env('OSRM_RETRY_SLEEP_MS', 100),
        'cache_ttl_seconds' => (int) env('OSRM_CACHE_TTL_SECONDS', 120),
        'cache_ttl_simulated_minutes' => 60,
        'road_factor' => (float) env('ROAD_FACTOR', 1.25),
        'traffic_bucket_size' => 0.10,
    ],

    'demand' => [
        'max_position_bonus_mxn' => (float) env('MAX_POSITION_BONUS_MXN', 20),
    ],

    'explanation' => [
        'provider' => env('LLM_PROVIDER', 'none'),
        'timeout_seconds' => (int) env('LLM_TIMEOUT_SECONDS', 5),
        'max_tokens' => (int) env('LLM_MAX_TOKENS', 180),
    ],

    'benchmark' => [
        'default_seeds' => [1, 2, 3],
        'default_count' => 3,
        'algorithm_version' => env('COURIER_ALGORITHM_VERSION', 'v1'),
    ],
];
