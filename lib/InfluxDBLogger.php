<?php
/**
 * php-resque-InfluxDB
 *
 * @package         php-resque-influxdb
 * @author          Chris Boulton <chris@bigcommerce.com>
 * @author          Sean Molenaar <sean@m2mobi.com>
 * @copyright   (c) 2017 Sean Molenaar
 * @license         http://www.opensource.org/licenses/mit-license.php
 */

namespace Resque\Logging;

use InfluxDB\Client;
use InfluxDB\Driver\DriverInterface;
use InfluxDB\Point;

/**
 * Class ResqueInfluxDB
 */
class InfluxDBLogger
{
    const INFLUXDB_TIMER = 'ms';
    const INFLUXDB_COUNTER = 'c';

    /**
     * Prefix to add to metrics submitted to InfluxDB.
     * @var string
     */
    private static $name = 'resque';

    /**
     * Hostname when connecting to InfluxDB.
     * @var string
     */
    private static $host = 'localhost';

    /**
     * Port InfluxDB is running on.
     * @var int
     */
    private static $port = 8086;

    /**
     * Port InfluxDB is running on.
     * @var int
     */
    private static $user = '';

    /**
     * Port InfluxDB is running on.
     * @var int
     */
    private static $password = '';

    /**
     * InfluxDB request driver.
     * @var DriverInterface|null
     */
    private static $driver = NULL;

    /**
     * Register php-resque-Influxdb in php-resque.
     *
     * Register all callbacks in php-resque for when a job is run. This is
     * automatically called at the bottom of this script if the appropriate
     * Resque classes are loaded.
     *
     * @return void
     */
    public static function register()
    {
        // Core php-resque events
        \Resque_Event::listen('afterEnqueue', 'Resque\Logging\InfluxDBLogger::afterEnqueue');
        \Resque_Event::listen('beforeFork', 'Resque\Logging\InfluxDBLogger::beforeFork');
        \Resque_Event::listen('afterPerform', 'Resque\Logging\InfluxDBLogger::afterPerform');
        \Resque_Event::listen('onFailure', 'Resque\Logging\InfluxDBLogger::onFailure');

        // Add support for php-resque-scheduler
        \Resque_Event::listen('afterSchedule', 'Resque\Logging\InfluxDBLogger::afterSchedule');
    }

    /**
     * Set the host/port combination of InfluxDB.
     *
     * @param string $host     Hostname/IP of InfluxDB server.
     * @param int    $port     Port InfluxDB is listening on.
     * @param string $username Username for InfluxDB
     * @param string $password Password for InfluxDB
     *
     * @return void
     */
    public static function setHost($host, $port, $username = '', $password = '')
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
    public static function setDriver($driver)
    {
        self::$driver = $driver;
    }

    /**
     * Override the db for metrics that are submitted to InfluxDB.
     *
     * @param string $db Prefix to use for metrics.
     *
     * @return void
     */
    public static function setDB($db)
    {
        self::$name = $db;
    }

    /**
     * Submit metrics for a queue and job whenever a job is pushed to a queue.
     *
     * @param string $class Class name of the job that was just created.
     * @param array  $args  Arguments passed to the job.
     * @param string $queue Name of the queue the job was created in.
     *
     * @return void
     */
    public static function afterEnqueue($class, $args, $queue)
    {
        //NO-OP
    }

    /**
     * Submit metrics for a queue and job whenever a job is scheduled in php-resque-scheduler.
     *
     * @param \DateTime|int $at    Instance of PHP DateTime object or int of UNIX timestamp.
     * @param string        $queue Name of the queue the job was created in.
     * @param string        $class Class name of the job that was just created.
     * @param array         $args  Arguments passed to the job.
     *
     * @return void
     */
    public static function afterSchedule($at, $queue, $class, $args)
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
     * @param \Resque_Job $job Instance of Resque_Job for the job about to be run.
     *
     * @return void
     */
    public static function beforeFork(\Resque_Job $job)
    {
        $job->influxDBStartTime = microtime(TRUE);

        if (isset($job->payload['queue_time']))
        {
            $job->influxDBTimeInQueue = round(microtime(TRUE) - $job->payload['queue_time']) * 1000;
        }
    }

    /**
     * Submit metrics for a queue and job as soon as job has finished executing successfully.
     *
     * @param \Resque_Job $job Instance of Resque_Job for the job that's just been executed.
     *
     * @return void
     */
    public static function afterPerform(\Resque_Job $job)
    {
        $executionTime = round(microtime(TRUE) - $job->influxDBStartTime) * 1000;
        self::sendMetric(
            [
                'execution_time' => $executionTime,
                'queue_time'     => isset($job->influxDBTimeInQueue) ? $job->influxDBTimeInQueue : 'null',
                'start_time'     => isset($job->influxDBStartTime) ? $job->influxDBStartTime : 'null',
            ],
            [
                'class'  => $job->payload['class'],
                'queue'  => $job->queue,
                'status' => 'finished',
            ]);
    }

    /**
     * Submit metrics for a queue and job whenever a job fails to run.
     *
     * @param \Exception  $e   Exception thrown by the job.
     * @param \Resque_Job $job Instance of Resque_Job for the job that failed.
     *
     * @return void
     */
    public static function onFailure(\Exception $e, \Resque_Job $job)
    {
        $executionTime = round(microtime(TRUE) - $job->influxDBStartTime) * 1000;
        self::sendMetric(
            [
                'error'          => $e->getMessage(),
                'execution_time' => $executionTime,
                'queue_time'     => isset($job->influxDBTimeInQueue) ? $job->influxDBTimeInQueue : 'null',
                'start_time'     => isset($job->influxDBStartTime) ? $job->influxDBStartTime : 'null',
            ],
            [
                'class'  => $job->payload['class'],
                'queue'  => $job->queue,
                'status' => 'failed',
            ]
        );
    }

    /**
     * Return a tuple containing the InfluxDB host and port to submit metrics to.
     *
     * Looks for environment variable INFLUXDB_HOST before resorting to the host/port
     * combination passed to `register`, or defaulting to localhost.
     * Port is determined in much the same way, however looks for the INFLUXDB_PORT environment variable.
     * Same for INFLUXDB_PASSWORD and INFLUXDB_PASSWORD.
     *
     * If the host variable includes a single colon, the first part of the string
     * is used for the host, and the second part for the port.
     *
     * @return Client Array containing host and port.
     */
    private static function getInfluxDBClient()
    {
        $host     = self::$host;
        $port     = self::$port;
        $user     = self::$user;
        $password = self::$password;

        if (!empty($_ENV['INFLUXDB_HOST']))
        {
            $host = $_ENV['INFLUXDB_HOST'];
        }

        if (!empty($_ENV['INFLUXDB_PORT']))
        {
            $port = $_ENV['INFLUXDB_PORT'];
        }

        if (!empty($_ENV['INFLUXDB_USERNAME']))
        {
            $user = $_ENV['INFLUXDB_USERNAME'];
        }

        if (!empty($_ENV['INFLUXDB_PASSWORD']))
        {
            $password = $_ENV['INFLUXDB_PASSWORD'];
        }

        if (substr_count($host, ':') == 1)
        {
            list($host, $port) = explode(':', $host);
        }

        $client = new Client($host, $port, $user, $password);

        if (!is_null(self::$driver))
        {
            $client->setDriver(self::$driver);
        }

        return $client;
    }

    /**
     * Get a database object
     *
     * @return bool|\InfluxDB\Database
     */
    private static function getDB()
    {
        $client = self::getInfluxDBClient();

        if (empty($client))
        {
            return FALSE;
        }

        $db = $client->selectDB(self::$name);

        return $db;
    }

    /**
     * Submit a metric of the given type, name and value to InfluxDB.
     *
     * @param array $fields Key=>Value pair to indicate fields
     * @param array $tags   Array of tags to submit
     *
     * @return boolean True if the metric was submitted successfully.
     */
    private static function sendMetric($fields, $tags)
    {
        $db = self::getDB();

        $point = new Point('Resque');
        $point->setFields($fields);
        $point->setTags($tags);
        $point->setTimestamp(exec('date +%s%N'));

        $db->writePoints([$point]);

        return TRUE;
    }

}
