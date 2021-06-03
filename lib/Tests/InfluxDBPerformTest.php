<?php

/**
 * This file contains the InfluxDBPerformTest.php
 *
 * @package \Resque\Logging
 * @author  Sean Molenaar <sean@m2mobi.com>
 */

namespace Resque\Logging\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Resque\Logging\InfluxDBLogger;

/**
 * Class InfluxDBPerformTest
 *
 * @covers \Resque\Logging\InfluxDBLogger
 */
class InfluxDBPerformTest extends TestCase
{
    /**
     * System under test
     * @var \ReflectionClass
     */
    protected $reflection;

    /**
     * InfluxDB driver.
     * @var \InfluxDB\Driver\DriverInterface
     */
    protected $driver;

    /**
     * Logger.
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = $this->getMockBuilder('InfluxDB\Driver\DriverInterface')
                             ->getMock();
        $this->logger = $this->getMockBuilder('Psr\Log\LoggerInterface')
                             ->getMock();

        InfluxDBLogger::setDriver($this->driver);
        InfluxDBLogger::setLogger($this->logger);

        $this->reflection = new ReflectionClass(InfluxDBLogger::class);
    }

    /**
     * Destroy the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->reflection);
        unset($this->driver);
        unset($this->logger);
        parent::tearDown();
    }

    /**
     * Test afterEnqueue not performing any actions.
     *
     * @covers \Resque\Logging\InfluxDBLogger::afterEnqueue
     */
    public function testAfterEnqueue(): void
    {
        $this->driver->expects($this->never())
                     ->method('write');

        InfluxDBLogger::afterEnqueue('\Resque_Job', [], 'SomeQueue');
    }

    /**
     * Test afterSchedule not performing any actions.
     *
     * @covers \Resque\Logging\InfluxDBLogger::afterSchedule
     */
    public function testAfterSchedule(): void
    {
        $this->driver->expects($this->never())
                     ->method('write');

        InfluxDBLogger::afterSchedule(10000, 'SomeQueue', '\Resque_Job', []);
    }

    /**
     * Test beforeFork only setting a value.
     *
     * @covers \Resque\Logging\InfluxDBLogger::beforeFork
     */
    public function testBeforeFork(): void
    {
        $this->driver->expects($this->never())
                     ->method('write');

        $job = new \Resque_Job('queue', []);
        InfluxDBLogger::beforeFork($job);

        $this->assertTrue(is_float($job->influxDBStartTime));
    }

    /**
     * Test beforeFork setting values based on payload.
     *
     * @covers \Resque\Logging\InfluxDBLogger::beforeFork
     */
    public function testBeforeForkWithQueueTime(): void
    {
        $this->driver->expects($this->never())
                     ->method('write');

        $job = new \Resque_Job('queue', ['queue_time' => 1000]);
        InfluxDBLogger::beforeFork($job);

        $start_time = $job->influxDBStartTime;
        $this->assertTrue(is_float($start_time));
        $this->assertSame($start_time - 1000, $job->influxDBTimeInQueue);
    }

    /**
     * Test afterPerform setting values based on payload.
     *
     * @covers \Resque\Logging\InfluxDBLogger::afterPerform
     */
    public function testAfterPerform(): void
    {
        $this->driver->expects($this->exactly(1))
                     ->method('setParameters')
                     ->with([
                         'url'      => 'write?db=resque&precision=n',
                         'database' => 'resque',
                         'method'   => 'post',
                         'auth'     => ['envUser', 'envPass'],
                     ]);

        $tags = 'class=SomeClass,queue=queue,status=finished';
        $query = "resque,$tags execution_time=863,queue_time=1000i,start_time=1552481660i 1552482605N";
        $this->driver->expects($this->exactly(1))
                     ->method('write')
                     ->with($query);

        $job = new \Resque_Job('queue', [
            'queue_time' => 1000,
            'class'      => 'SomeClass',
        ]);

        $job->influxDBTimeInQueue = 1000;
        $job->influxDBStartTime   = 1552481660;

        InfluxDBLogger::afterPerform($job);
    }

    /**
     * Test afterPerform setting values based on payload.
     *
     * @covers \Resque\Logging\InfluxDBLogger::afterPerform
     */
    public function testAfterPerformWithRetentionPolicy(): void
    {
        $this->driver->expects($this->exactly(1))
                     ->method('setParameters')
                     ->with([
                         'url'      => 'write?db=resque&precision=n&rp=autogen',
                         'database' => 'resque',
                         'method'   => 'post',
                         'auth'     => ['envUser', 'envPass'],
                     ]);

        $query = 'resque,class=SomeClass,queue=queue,status=finished execution_time=863,queue_time=1000i,start_time=1552481660i 1552482605N';
        $this->driver->expects($this->exactly(1))
                     ->method('write')
                     ->with($query);

        $job = new \Resque_Job('queue', [
            'queue_time' => 1000,
            'class'      => 'SomeClass',
        ]);

        $job->influxDBTimeInQueue = 1000;
        $job->influxDBStartTime   = 1552481660;

        InfluxDBLogger::setRetentionPolicy('autogen');

        InfluxDBLogger::afterPerform($job);
    }

    /**
     * Test afterPerform using environment for client.
     *
     * @covers \Resque\Logging\InfluxDBLogger::afterPerform
     */
    public function testAfterPerformWithEnv(): void
    {

        $this->driver->expects($this->exactly(1))
                     ->method('setParameters')
                     ->with([
                         'url'      => 'write?db=resque&precision=n',
                         'database' => 'resque',
                         'method'   => 'post',
                         'auth'     => ['envUser', 'envPass'],
                     ]);

        $tags = 'class=SomeClass,queue=queue,status=finished';
        $query = "resque,$tags execution_time=863,queue_time=1000i,start_time=1552481660i 1552482605N";
        $this->driver->expects($this->exactly(1))
                     ->method('write')
                     ->with($query);

        $job = new \Resque_Job('queue', [
            'queue_time' => 1000,
            'class'      => 'SomeClass',
        ]);

        $job->influxDBTimeInQueue = 1000;
        $job->influxDBStartTime   = 1552481660;

        InfluxDBLogger::afterPerform($job);
    }

    /**
     * Test onFailure setting values based on payload.
     *
     * @covers \Resque\Logging\InfluxDBLogger::onFailure
     */
    public function testOnFailure(): void
    {
        $this->driver->expects($this->exactly(1))
                     ->method('setParameters')
                     ->with([
                         'url'      => 'write?db=resque&precision=n',
                         'database' => 'resque',
                         'method'   => 'post',
                         'auth'     => ['envUser', 'envPass'],
                     ]);

        $query  = 'resque,class=SomeClass,queue=queue,exception=Exception,';
        $query .= 'status=failed execution_time=863,error="FAILURE",queue_time=1000i,';
        $query .= 'start_time=1552481660i 1552482605N';

        $this->driver->expects($this->exactly(1))
                     ->method('write')
                     ->with($query);

        $job = new \Resque_Job('queue', [
            'queue_time' => 1000,
            'class'      => 'SomeClass',
        ]);

        $job->influxDBTimeInQueue = 1000;
        $job->influxDBStartTime   = 1552481660;

        $exception = new \Exception('FAILURE');

        InfluxDBLogger::onFailure($exception, $job);
    }

    /**
     * Test any action throwing will get logged.
     *
     * @covers \Resque\Logging\InfluxDBLogger::onFailure
     */
    public function testOnFailureThrowsToLog(): void
    {
        $this->driver->expects($this->once())
                     ->method('setParameters')
                     ->will($this->throwException(new \InfluxDB\Exception('FAILURE')));

        $this->driver->expects($this->never())
                     ->method('write');

        $this->logger->expects($this->once())
                     ->method('error')
                     ->with('FAILURE', []);

        $job = new \Resque_Job('queue', [
            'queue_time' => 1000,
            'class'      => 'SomeClass',
        ]);

        $job->influxDBTimeInQueue = 1000;
        $job->influxDBStartTime   = 1552481660;

        $exception = new \Exception('FAILURE');

        InfluxDBLogger::onFailure($exception, $job);
    }
}

namespace Resque\Logging;

function microtime($get_as_float)
{
    return 1552482523;
}
function exec($cmd)
{
    return '1552482605N';
}
