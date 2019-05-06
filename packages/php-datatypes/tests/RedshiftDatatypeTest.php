<?php
namespace Keboola\DataypeTest;

use Keboola\Datatype\Definition\Exception\InvalidCompressionException;
use Keboola\Datatype\Definition\Exception\InvalidLengthException;
use Keboola\Datatype\Definition\Exception\InvalidOptionException;
use Keboola\Datatype\Definition\Exception\InvalidTypeException;
use Keboola\Datatype\Definition\Redshift;

class RedshiftDatatypeTest extends \PHPUnit_Framework_TestCase
{
    public function testValid()
    {
        new Redshift("VARCHAR", ["length" => "50"]);
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
        new Redshift("NUMERIC", ["length" => ""]);
        new Redshift("INT", ["length" => ""]);
        new Redshift("NUMERIC", ["length" => "37,0"]);
        new Redshift("NUMERIC", ["length" => "37,37"]);
        new Redshift("NUMERIC", ["length" => "37"]);
    }

    /**
     * @dataProvider invalidNumericLengths
     * @param $length
     */
    public function testInvalidNumericLengths($length)
    {
        try {
            new Redshift("NUMERIC", ["length" => $length]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidLengthException::class, get_class($e));
        }
    }

    public function testValidVarcharLengths()
    {
        new Redshift("varchar");
        new Redshift("VARCHAR");
        new Redshift("VARCHAR", ["length" => ""]);
        new Redshift("VARCHAR", ["length" => "1"]);
        new Redshift("VARCHAR", ["length" => "65535"]);
    }

    /**
     * @dataProvider invalidVarcharLengths
     * @param $length
     */
    public function testInvalidVarcharLengths($length)
    {
        try {
            new Redshift("VARCHAR", ["length" => $length]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidLengthException::class, get_class($e));
        }
    }

    /**
     * @dataProvider timestampLengths
     * @param $type
     * @param $length
     * @param $expectedValid
     */
    public function testTimestampLengths($type, $length, $expectedValid)
    {
        if (!$expectedValid) {
            $this->expectException(InvalidLengthException::class);
            new Redshift($type, ["length" => $length]);
        } else {
            new Redshift($type, ["length" => $length]);
        }
    }

    public function testValidCharLengths()
    {
        new Redshift("char");
        new Redshift("CHAR");
        new Redshift("CHAR", ["length" => "1"]);
        new Redshift("CHAR", ["length" => "4096"]);
    }

    /**
     * @dataProvider invalidCharLengths
     * @param $length
     */
    public function testInvalidCharLengths($length)
    {
        try {
            new Redshift("CHAR", ["length" => $length]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidLengthException::class, get_class($e));
        }
    }

    public function testValidCompressions()
    {
        new Redshift("VARCHAR", ["compression" => "RAW"]);
        new Redshift("VARCHAR", ["compression" => "raw"]);
        new Redshift("VARCHAR", ["compression" => "BYTEDICT"]);
        new Redshift("INT", ["compression" => "DELTA"]);
        new Redshift("INT", ["compression" => "DELTA32K"]);
        new Redshift("VARCHAR", ["compression" => "LZO"]);
        new Redshift("BIGINT", ["compression" => "MOSTLY8"]);
        new Redshift("BIGINT", ["compression" => "MOSTLY16"]);
        new Redshift("BIGINT", ["compression" => "MOSTLY32"]);
        new Redshift("VARCHAR", ["compression" => "RUNLENGTH"]);
        new Redshift("VARCHAR", ["compression" => "TEXT255"]);
        new Redshift("VARCHAR", ["compression" => "TEXT32K"]);
        new Redshift("VARCHAR", ["compression" => "ZSTD"]);
    }

    /**
     * @dataProvider invalidCompressions
     * @param $type
     * @param $compression
     */
    public function testInvalidCompressions($type, $compression)
    {
        try {
            new Redshift($type, ["compression" => $compression]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidCompressionException::class, get_class($e));
        }
    }

    public function testInvalidOption()
    {
        try {
            new Redshift("NUMERIC", ["myoption" => "value"]);
            $this->fail("Exception not caught");
        } catch (\Exception $e) {
            $this->assertEquals(InvalidOptionException::class, get_class($e));
        }
    }

    public function testSQLDefinition()
    {
        $datatype = new Redshift("VARCHAR", ["length" => "50", "nullable" => true, "compression" => "ZSTD"]);
        $this->assertEquals("VARCHAR(50) ENCODE ZSTD", $datatype->getSQLDefinition());
    }

    public function testToArray()
    {
        $datatype = new Redshift("VARCHAR");
        $this->assertEquals(
            ["type" => "VARCHAR", "length" => null, "nullable" => true, "compression" => null],
            $datatype->toArray()
        );

        $datatype = new Redshift("VARCHAR", ["length" => "50", "nullable" => false, "compression" => "ZSTD"]);
        $this->assertEquals(
            ["type" => "VARCHAR", "length" => "50", "nullable" => false, "compression" => "ZSTD"],
            $datatype->toArray()
        );
    }

    public function testToMetadata()
    {
        $datatype = new Redshift("VARCHAR", ["length" => "50", "nullable" => false, "default" => "", "compression" => "ZSTD"]);

        $md = $datatype->toMetadata();
        $hasCompression = false;
        foreach ($md as $mdat) {
            $this->assertArrayHasKey("key", $mdat);
            if ($mdat["key"] === "KBC.datatype.compression") {
                $this->assertEquals("ZSTD", $mdat["value"]);
                $hasCompression = true;
            }
        }
        if (!$hasCompression) {
            $this->fail("Redshift datatype metadata should produce compression data if present");
        }

        $datatype = new Redshift("VARCHAR");
        $md = $datatype->toMetadata();
        foreach ($md as $mdat) {
            $this->assertArrayHasKey("key", $mdat);
            if ($mdat["key"] === "KBC.datatyp.compression") {
                $this->fail("Redshift datatype should not produce compression metadata if compression is not set");
            }
        }
    }

    public function testBasetypes()
    {
        foreach (Redshift::TYPES as $type) {
            $basetype = (new Redshift($type))->getBasetype();
            switch ($type) {
                case "SMALLINT":
                case "INT2":
                case "INTEGER":
                case "INT":
                case "INT4":
                case "BIGINT":
                case "INT8":
                    $this->assertEquals("INTEGER", $basetype);
                    break;
                case "DECIMAL":
                case "NUMERIC":
                    $this->assertEquals("NUMERIC", $basetype);
                    break;
                case "REAL":
                case "FLOAT4":
                case "DOUBLE PRECISION":
                case "FLOAT8":
                case "FLOAT":
                    $this->assertEquals("FLOAT", $basetype);
                    break;
                case "BOOLEAN":
                case "BOOL":
                    $this->assertEquals("BOOLEAN", $basetype);
                    break;
                case "DATE":
                    $this->assertEquals("DATE", $basetype);
                    break;
                case "TIMESTAMP":
                case "TIMESTAMP WITHOUT TIME ZONE":
                case "TIMESTAMPTZ":
                case "TIMESTAMP WITH TIME ZONE":
                    $this->assertEquals("TIMESTAMP", $basetype);
                    break;
                default:
                    $this->assertEquals("STRING", $basetype);
                    break;
            }
        }
    }

    public function invalidNumericLengths()
    {
        return [
            ["notANumber"],
            ["0,0"],
            ["38,0"],
            ["-10,-5"],
            ["-5,-10"],
            ["37,a"],
            ["a,37"],
            ["a,a"]
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

    public function timestampLengths()
    {
        return [
            [
                "timestamp",
                "-1",
                false
            ],
            [
                "timestamp",
                "15",
                false
            ],
            [
                "timestamp",
                "abc",
                false
            ],
            [
                "timestamp",
                "8,3",
                false
            ],
            [
                "timestamp",
                null,
                true
            ],
            [
                "timestamp",
                "",
                true
            ],
            [
                "timestamp",
                "8",
                true
            ],
            [
                "timestamptz",
                "-1",
                false
            ],
            [
                "timestamptz",
                "15",
                false
            ],
            [
                "timestamptz",
                "abc",
                false
            ],
            [
                "timestamptz",
                "8,3",
                false
            ],
            [
                "timestamptz",
                null,
                true
            ],
            [
                "timestamptz",
                "",
                true
            ],
            [
                "timestamptz",
                "8",
                true
            ],
        ];
    }
}
