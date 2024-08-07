<?php

/**
 * php-resque-InfluxDB
 *
 * SPDX-FileCopyrightText: Copyright 2017 M2mobi B.V., Amsterdam, The Netherlands
 * SPDX-FileCopyrightText: Copyright 2022 Move Agency Group B.V., Zwolle, The Netherlands
 * SPDX-License-Identifier: MIT
 */

declare(strict_types=1);

namespace Resque\Logging;

use DateTime;
use InfluxDB\Client;
use InfluxDB\Database;
use InfluxDB\Driver\DriverInterface;
use InfluxDB\Exception as InfluxDBException;
use InfluxDB\Point;
use Psr\Log\LoggerInterface;
use Resque\Event;
use Resque\JobHandler;
use Throwable;

/**
 * Class ResqueInfluxDB
 */
class InfluxDBLogger
{
    /**
     * Prefix to add to metrics submitted to InfluxDB.
     * @var non-empty-string
     */
    protected static string $db_name = 'resque';

    /**
     * Measurement name in InfluxDB.
     * @var non-empty-string
     */
    protected static string $measurement_name = 'resque';

    /**
     * Prefix to add to metrics submitted to InfluxDB.
     * @var non-empty-string|null
     */
    protected static ?string $retention_policy_name = null;

    /**
     * Default measurement tags.
     * @var array<string, string>
     */
    protected static array $default_tags = [];

    /**
     * Hostname when connecting to InfluxDB.
     * @var non-empty-string
     */
    protected static string $host = 'localhost';

    /**
     * Port InfluxDB is running on.
     * @var int
     */
    protected static int $port = 8086;

    /**
     * The InfluxDB user.
     * @var string
     */
    protected static string $user = '';

    /**
     * Password for the InfluxDB user.
     * @var string
     */
    protected static string $password = '';

    /**
     * InfluxDB request driver.
     * @var DriverInterface|null
     */
    protected static ?DriverInterface $driver = null;

    /**
     * Logger.
     * @var LoggerInterface|null
     */
    private static ?LoggerInterface $logger = null;

    /**
     * Register php-resque-Influxdb in php-resque.
     *
     * Register all callbacks in php-resque for when a job is run. This is
     * automatically called at the bottom of this script if the appropriate
     * Resque classes are loaded.
     *
     * @return void
     */
    public static function register(): void
    {
        // Core php-resque events
        Event::listen('afterEnqueue', 'Resque\Logging\InfluxDBLogger::afterEnqueue');
        Event::listen('beforeFork', 'Resque\Logging\InfluxDBLogger::beforeFork');
        Event::listen('afterPerform', 'Resque\Logging\InfluxDBLogger::afterPerform');
        Event::listen('onFailure', 'Resque\Logging\InfluxDBLogger::onFailure');

        // Add support for php-resque-scheduler
        Event::listen('afterSchedule', 'Resque\Logging\InfluxDBLogger::afterSchedule');
    }

    /**
     * Set the host/port combination of InfluxDB.
     *
     * @param non-empty-string  $host     Hostname/IP of InfluxDB server.
     * @param integer           $port     Port InfluxDB is listening on.
     * @param string            $username Username for InfluxDB
     * @param string            $password Password for InfluxDB
     *
     * @return void
     */
    public static function setHost(string $host, int $port, string $username = '', string $password = ''): void
    {
        self::$host     = $host;
        self::$port     = $port;
        self::$user     = $username;
        self::$password = $password;
    }

    /**
     * Set the InfluxDB Client driver.
     *
     * @param DriverInterface $driver InfluxDB HTTP driver.
     *
     * @return void
     */
    public static function setDriver(DriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    /**
     * Set the Logger.
     *
     * @param LoggerInterface $logger Logger in case of failure.
     *
     * @return void
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Override the db for metrics that are submitted to InfluxDB.
     *
     * @param non-empty-string $db Prefix to use for metrics.
     *
     * @return void
     */
    public static function setDB(string $db): void
    {
        self::$db_name = $db;
    }

    /**
     * Override the retention policy for metrics that are submitted to InfluxDB.
     *
     * @param non-empty-string $retention_policy Retention policy to use for metrics.
     *
     * @return void
     */
    public static function setRetentionPolicy(string $retention_policy): void
    {
        self::$retention_policy_name = $retention_policy;
    }

    /**
     * Override the measurement name for metrics that are submitted to InfluxDB.
     *
     * @param non-empty-string $name Name to use for measurements.
     *
     * @return void
     */
    public static function setMeasurementName(string $name): void
    {
        self::$measurement_name = $name;
    }

    /**
     * Add a set of tags to all measurements.
     *
     * @param array<string,string> $tags Tags to use.
     *
     * @return void
     */
    public static function setDefaultTags(array $tags): void
    {
        self::$default_tags = $tags;
    }

    /**
     * Submit metrics for a queue and job whenever a job is pushed to a queue.
     *
     * @param string              $class Class name of the job that was just created.
     * @param array<string,mixed> $args  Arguments passed to the job.
     * @param string              $queue Name of the queue the job was created in.
     * @param string              $id    The ID of the job that was queued.
     *
     * @return void
     */
    public static function afterEnqueue(string $class, array $args, string $queue, string $id): void
    {
        //NO-OP
    }

    /**
     * Submit metrics for a queue and job whenever a job is scheduled in php-resque-scheduler.
     *
     * @param DateTime|int        $at    Instance of PHP DateTime object or int of UNIX timestamp.
     * @param string              $queue Name of the queue the job was created in.
     * @param string              $class Class name of the job that was just created.
     * @param array<string,mixed> $args  Arguments passed to the job.
     *
     * @return void
     */
    public static function afterSchedule(DateTime|int $at, string $queue, string $class, array $args): void
    {
        //NO-OP
    }

    /**
     * Begin tracking execution time before forking out to run a job in a php-resque worker
     * and submits the metrics for the duration of a job spend waiting in the queue.
     *
     * Time tracking begins in `beforeFork` to ensure that the time spent for forking
     * and any hooks registered for `beforePerform` is also tracked.
     *
     * @param JobHandler $job Instance of Resque\JobHandler for the job about to be run.
     *
     * @return void
     */
    public static function beforeFork(JobHandler $job): void
    {
        //NO-OP
    }

    /**
     * Submit metrics for a queue and job as soon as job has finished executing successfully.
     *
     * @param JobHandler $job Instance of Resque\JobHandler for the job that's just been executed.
     *
     * @return void
     */
    public static function afterPerform(JobHandler $job): void
    {
        self::sendMetric(
            self::getJobField($job, null),
            [
                             'class'  => $job->payload['class'],
                             'queue'  => $job->queue,
                             'status' => 'finished',
            ]
        );
    }

    /**
     * Submit metrics for a queue and job whenever a job fails to run.
     *
     * @param Throwable  $exception Exception thrown by the job.
     * @param JobHandler $job       Instance of Resque\JobHandler for the job that failed.
     *
     * @return void
     */
    public static function onFailure(Throwable $exception, JobHandler $job): void
    {
        self::sendMetric(
            self::getJobField($job, $exception),
            [
                             'class'     => $job->payload['class'],
                             'queue'     => $job->queue,
                             'exception' => get_class($exception),
                             'status'    => 'failed',
                         ]
        );
    }

    /**
     * Get fields that need to be in influxDB.
     *
     * @param JobHandler $job Instance of Resque\JobHandler for the job that failed.
     * @param Throwable  $e   Exception thrown by the job.
     *
     * @return array<string, bool|float|int|string> Fields relevant for the job.
     */
    private static function getJobField(JobHandler $job, Throwable $e = null): array
    {
        $fields        = [
            'start_time'     => $job->start_time,
            'end_time'       => $job->end_time,
            'pop_time'       => $job->pop_time,
            'execution_time' => $job->end_time - $job->start_time,
            'job_id'         => $job->payload['id'],
        ];

        if (isset($job->payload['queue_time'])) {
            $fields['queue_time'] = $job->pop_time - $job->payload['queue_time'];
        }

        if (!is_null($e)) {
            $fields['error'] = $e->getMessage();
        }

        return $fields;
    }

    /**
     * Return a Client with the InfluxDB host and port to submit metrics to.
     *
     * Looks for environment variable INFLUXDB_HOST before resorting to the host/port
     * combination passed to `register`, or defaulting to localhost.
     * Port is determined in much the same way, however looks for the INFLUXDB_PORT environment variable.
     * Same for INFLUXDB_PASSWORD and INFLUXDB_PASSWORD.
     *
     * If the host variable includes a single colon, the first part of the string
     * is used for the host, and the second part for the port.
     *
     * @return Client Prepared InfluxDB client.
     */
    private static function getInfluxDBClient(): Client
    {
        $host     = self::$host;
        $port     = self::$port;
        $user     = self::$user;
        $password = self::$password;

        if (!empty($_ENV['INFLUXDB_HOST'])) {
            $host = $_ENV['INFLUXDB_HOST'];
        }

        if (!empty($_ENV['INFLUXDB_PORT'])) {
            $port = $_ENV['INFLUXDB_PORT'];
        }

        if (!empty($_ENV['INFLUXDB_USERNAME'])) {
            $user = $_ENV['INFLUXDB_USERNAME'];
        }

        if (!empty($_ENV['INFLUXDB_PASSWORD'])) {
            $password = $_ENV['INFLUXDB_PASSWORD'];
        }

        if (substr_count($host, ':') == 1) {
            list($host, $port) = explode(':', $host);
        }

        $client = new Client($host, $port, $user, $password);

        if (!is_null(self::$driver)) {
            $client->setDriver(self::$driver);
        }

        return $client;
    }

    /**
     * Get a database object
     *
     * @return Database
     */
    private static function getDB(): Database
    {
        $client = self::getInfluxDBClient();
        $db     = $client->selectDB(self::$db_name);

        return $db;
    }

    /**
     * Submit a metric of the given type, name and value to InfluxDB.
     *
     * @param array<string,bool|float|int|string> $fields Key=>Value pair to indicate fields
     * @param array<string,string>                $tags   Array of tags to submit
     *
     * @return bool True if the metric was submitted successfully.
     */
    private static function sendMetric(array $fields, array $tags): bool
    {
        $db    = self::getDB();
        $tags += self::$default_tags;

        try {
            $point = new Point(self::$measurement_name);
            $point->setFields($fields);
            $point->setTags($tags);
            $point->setTimestamp(exec('date +%s%N') ?: sprintf('%d', microtime(true) * 1000000) . '000');

            $db->writePoints(points: [ $point ], retentionPolicy: self::$retention_policy_name);
        } catch (InfluxDBException $exception) {
            if (!is_null(self::$logger)) {
                self::$logger->error($exception->getMessage());
            }

            return false;
        }

        return true;
    }
}
