<?php

declare(strict_types=1);

return [

    'outbox_topic' => env('OUTBOX_TOPIC', 'catalog.events'),

    'kafka_brokers' => env('KAFKA_BROKERS', 'kafka:9092'),

];
