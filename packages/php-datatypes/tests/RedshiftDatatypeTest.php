<?php
namespace Keboola\DataypeTest;

use Keboola\Datatype\Definition\Exception\InvalidCompressionException;
use Keboola\Datatype\Definition\Exception\InvalidLengthException;
use Keboola\Datatype\Definition\Exception\InvalidTypeException;
use Keboola\Datatype\Definition\Redshift;

class RedshiftDatatypeTest extends \PHPUnit_Framework_TestCase
{
    public function testValid()
    {
        new Redshift("VARCHAR", ['length' => "50"]);
    }

    public function testInvalidType()
    {
        try {
            new Redshift("UNKNOWN");
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidTypeException::class, get_class($e));
        }
    }

    public function testValidNumericLengths()
    {
        new Redshift("numeric");
        new Redshift("NUMERIC");
        new Redshift("NUMERIC", ['length' => ""]);
        new Redshift("INT", ['length' => ""]);
        new Redshift("NUMERIC", ['length' => "38,0"]);
    }

    /**
     * @dataProvider invalidNumericLengths
     * @param $length
     */
    public function testInvalidNumericLengths($length)
    {
        try {
            new Redshift("NUMERIC", ['length' => $length]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidLengthException::class, get_class($e));
        }
    }

    public function testValidVarcharLengths()
    {
        new Redshift("varchar");
        new Redshift("VARCHAR");
        new Redshift("VARCHAR", ['length' => ""]);
        new Redshift("VARCHAR", ['length' => "1"]);
        new Redshift("VARCHAR", ['length' => "65535"]);
    }

    /**
     * @dataProvider invalidVarcharLengths
     * @param $length
     */
    public function testInvalidVarcharLengths($length)
    {
        try {
            new Redshift("VARCHAR", ['length' => $length]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidLengthException::class, get_class($e));
        }
    }

    public function testValidCharLengths()
    {
        new Redshift("char");
        new Redshift("CHAR");
        new Redshift("CHAR", ['length' => "1"]);
        new Redshift("CHAR", ['length' => "4096"]);
    }

    /**
     * @dataProvider invalidCharLengths
     * @param $length
     */
    public function testInvalidCharLengths($length)
    {
        try {
            new Redshift("CHAR", ['length' => $length]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidLengthException::class, get_class($e));
        }
    }

    public function testValidCompressions()
    {
        new Redshift("VARCHAR", ['compression' => "RAW"]);
        new Redshift("VARCHAR", ['compression' => "raw"]);
        new Redshift("VARCHAR", ['compression' => "BYTEDICT"]);
        new Redshift("INT", ['compression' => "DELTA"]);
        new Redshift("INT", ['compression' => "DELTA32K"]);
        new Redshift("VARCHAR", ['compression' => "LZO"]);
        new Redshift("BIGINT", ['compression' => "MOSTLY8"]);
        new Redshift("BIGINT", ['compression' => "MOSTLY16"]);
        new Redshift("BIGINT", ['compression' => "MOSTLY32"]);
        new Redshift("VARCHAR", ['compression' => "RUNLENGTH"]);
        new Redshift("VARCHAR", ['compression' => "TEXT255"]);
        new Redshift("VARCHAR", ['compression' => "TEXT32K"]);
        new Redshift("VARCHAR", ['compression' => "ZSTD"]);
    }

    /**
     * @dataProvider invalidCompressions
     * @param $type
     * @param $compression
     */
    public function testInvalidCompressions($type, $compression)
    {
        try {
            new Redshift($type, ['compression' => $compression]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidCompressionException::class, get_class($e));
        }
    }

    public function testSQLDefinition()
    {
        $datatype = new Redshift("VARCHAR", ['length' => "50", 'nullable' => true, 'compression' => "ZSTD"]);
        $this->assertEquals("VARCHAR(50) ENCODE ZSTD", $datatype->getSQLDefinition());
    }

    public function testToArray()
    {
        $datatype = new Redshift("VARCHAR");
        $this->assertEquals(
            ["type" => "VARCHAR", "length" => "", "nullable" => true, "default" => "NULL", "compression" => ""],
            $datatype->toArray()
        );

        $datatype = new Redshift("VARCHAR", ['length' => "50", 'nullable' => false, 'default' => "", 'compression' => "ZSTD"]);
        $this->assertEquals(
            ["type" => "VARCHAR", "length" => "50", "nullable" => false, 'default' => "", "compression" => "ZSTD"],
            $datatype->toArray()
        );
    }

    public function invalidNumericLengths()
    {
        return [
            ["notANumber"],
            ["10"],
            ["0,0"],
            ["39,0"],
            ["-10,-5"],
            ["-5,-10"],
            ["38,a"],
            ["a,38"],
            ["a,a"],
            ["10,10"],
            ["38,38"]
        ];
    }

    public function invalidVarcharLengths()
    {
        return [
            ["a"],
            ["0"],
            ["65536"],
            ["-1"]
        ];
    }

    public function invalidCharLengths()
    {
        return [
            ["a"],
            ["0"],
            ["4097"],
            ["-1"]
        ];
    }

    public function invalidCompressions()
    {
        return [
            ["BOOLEAN", "BYTEDICT"],
            ["VARCHAR", "DELTA"],
            ["VARCHAR", "DELTA32K"],
            ["VARCHAR", "MOSTLY8"],
            ["VARCHAR", "MOSTLY16"],
            ["VARCHAR", "MOSTLY32"],
            ["NUMERIC", "TEXT255"],
            ["NUMERIC","TEXT32K"]
        ];
    }
}
