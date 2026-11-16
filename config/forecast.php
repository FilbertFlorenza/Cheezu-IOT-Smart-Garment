<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Forecast metric catalogue
    |--------------------------------------------------------------------------
    |
    | Metrics drive chart labels, units, ordering and colours. Producers may
    | submit additional metric names; they are stored and displayed using a
    | humanised label. Values are not invented when a metric is absent.
    |
    */
    'metrics' => [
        'cloud' => [
            'cpu_utilization' => ['label' => 'CPU Utilization', 'unit' => '%', 'color' => '#2563eb'],
            'memory_utilization' => ['label' => 'Memory Utilization', 'unit' => '%', 'color' => '#7c3aed'],
            'disk_io_read' => ['label' => 'Disk Read I/O', 'unit' => 'MB/s', 'color' => '#0d9488'],
            'disk_io_write' => ['label' => 'Disk Write I/O', 'unit' => 'MB/s', 'color' => '#f59e0b'],
            'network_in' => ['label' => 'Network In', 'unit' => 'Mbps', 'color' => '#0284c7'],
            'network_out' => ['label' => 'Network Out', 'unit' => 'Mbps', 'color' => '#db2777'],
        ],
        'network' => [
            'network_throughput' => ['label' => 'Network Throughput', 'unit' => 'Mbps', 'color' => '#2563eb'],
            'packet_count' => ['label' => 'Packet Count', 'unit' => 'pps', 'color' => '#7c3aed'],
            'traffic_volume' => ['label' => 'Traffic Volume', 'unit' => 'MB', 'color' => '#0d9488'],
            'latency' => ['label' => 'Latency', 'unit' => 'ms', 'color' => '#f59e0b'],
            'packet_loss' => ['label' => 'Packet Loss', 'unit' => '%', 'color' => '#db2777'],
            'congestion_risk' => ['label' => 'Congestion Risk', 'unit' => '%', 'color' => '#dc2626'],
        ],
    ],

    'max_predictions_per_forecast' => (int) env('FORECAST_MAX_PREDICTIONS', 500),
    'max_horizon_minutes' => (int) env('FORECAST_MAX_HORIZON_MINUTES', 10080),

    'risk_levels' => ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'],
];
