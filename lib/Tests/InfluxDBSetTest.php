<?php
/**
 * This file contains the InfluxDBSetTest.php
 *
 * @package \Resque\Logging
 * @author  Sean Molenaar <sean@m2mobi.com>
 */

namespace Resque\Logging\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Resque\Logging\InfluxDBLogger;

/**
 * Class InfluxDBSetTest
 *
 * @covers \Resque\Logging\InfluxDBLogger
 */
class InfluxDBSetTest extends TestCase
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
     * Test if the driver is set correctly.
     *
     * @covers \Resque\Logging\InfluxDBLogger::setDriver
     */
    public function testSetDriver(): void
    {
        InfluxDBLogger::setDriver($this->driver);
        $prop = $this->reflection->getStaticProperties()['driver'] ?? NULL;
        $this->assertSame($this->driver, $prop);
    }

    /**
     * Test if the logger is set correctly.
     *
     * @covers \Resque\Logging\InfluxDBLogger::setLogger
     */
    public function testSetLogger(): void
    {
        InfluxDBLogger::setLogger($this->logger);
        $prop = $this->reflection->getStaticProperties()['logger'] ?? NULL;
        $this->assertSame($this->logger, $prop);
    }

    /**
     * Test if the client info is set correctly.
     *
     * @covers \Resque\Logging\InfluxDBLogger::setHost
     */
    public function testSetHost(): void
    {
        InfluxDBLogger::setHost('some.host', 5400, 'username', 'password');

        $prop = $this->reflection->getStaticProperties()['host'] ?? NULL;
        $this->assertSame('some.host', $prop);

        $prop = $this->reflection->getStaticProperties()['port'] ?? NULL;
        $this->assertSame(5400, $prop);

        $prop = $this->reflection->getStaticProperties()['user'] ?? NULL;
        $this->assertSame('username', $prop);

        $prop = $this->reflection->getStaticProperties()['password'] ?? NULL;
        $this->assertSame('password', $prop);
    }

    /**
     * Test if the db is set correctly.
     *
     * @covers \Resque\Logging\InfluxDBLogger::setDB
     */
    public function testSetDatabase(): void
    {
        InfluxDBLogger::setDB('somedb');

        $prop = $this->reflection->getStaticProperties()['db_name'] ?? NULL;
        $this->assertSame('somedb', $prop);
    }

    /**
     * Test if the name is set correctly.
     *
     * @covers \Resque\Logging\InfluxDBLogger::setMeasurementName
     */
    public function testSetName(): void
    {
        InfluxDBLogger::setMeasurementName('some');

        $prop = $this->reflection->getStaticProperties()['measurement_name'] ?? NULL;
        $this->assertSame('some', $prop);
    }

    /**
     * Test if the tags are set correctly.
     *
     * @covers \Resque\Logging\InfluxDBLogger::setDefaultTags
     */
    public function testSetDefaultTags(): void
    {
        InfluxDBLogger::setDefaultTags(['tag' => 'value']);

        $prop = $this->reflection->getStaticProperties()['default_tags'] ?? NULL;
        $this->assertSame(['tag' => 'value'], $prop);
    }

    /**
     * Test if the jobs are registered correctly.
     *
     * @covers \Resque\Logging\InfluxDBLogger::register
     */
    public function testRegister(): void
    {
        InfluxDBLogger::register();

        $resque_reflection = new \ReflectionClass('\Resque_Event');

        $prop = $resque_reflection->getStaticProperties()['events'] ?? [];
        $this->assertArrayHasKey('afterEnqueue', $prop);
        $this->assertSame('Resque\Logging\InfluxDBLogger::afterEnqueue', $prop['afterEnqueue'][0]);
        $this->assertArrayHasKey('beforeFork', $prop);
        $this->assertSame('Resque\Logging\InfluxDBLogger::beforeFork', $prop['beforeFork'][0]);
        $this->assertArrayHasKey('afterPerform', $prop);
        $this->assertSame('Resque\Logging\InfluxDBLogger::afterPerform', $prop['afterPerform'][0]);
        $this->assertArrayHasKey('onFailure', $prop);
        $this->assertSame('Resque\Logging\InfluxDBLogger::onFailure', $prop['onFailure'][0]);
        $this->assertArrayHasKey('afterSchedule', $prop);
        $this->assertSame('Resque\Logging\InfluxDBLogger::afterSchedule', $prop['afterSchedule'][0]);
    }
}