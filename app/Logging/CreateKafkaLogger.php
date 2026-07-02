<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\Level;
use Psr\Log\LoggerInterface;
use Ticketarget\Logging\LoggerFactory;

/**
 * Laravel custom-channel factory that hands logging off to the shared
 * ticketarget/logging package, keeping every service's log shape identical.
 */
final class CreateKafkaLogger
{
    public function __invoke(array $config): LoggerInterface
    {
        $factory = new LoggerFactory(
            service: (string) env('APP_NAME', 'event-service'),
            environment: (string) env('APP_ENV', 'production'),
            kafkaBrokers: (string) env('KAFKA_BROKERS', ''),
            kafkaTopic: (string) env('KAFKA_LOG_TOPIC', 'logs.app'),
            level: Level::fromName(ucfirst((string) ($config['level'] ?? 'debug'))),
        );

        return $factory->create('event-service');
    }
}
