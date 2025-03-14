<?php
/**
 * Test script for InfluxDB 2.x connection
 * Save this file in your project root and run it with:
 * php influxdb2-test.php
 */

// Require autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables if using dotenv
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Configuration (adjust as needed)
$url = getenv('INFLUXDB_URL') ?: 'http://127.0.0.1:8086';
$token = getenv('INFLUXDB_TOKEN') ?: 'DWNBKt9f4rVoZGSVWTu_eqtgBz9ZU0Kx0P8OQGFW6Bi_tPpHjlX6Ds_uMjkge2RPp7LjUFk0nDhAZBzLtlQS2g=='; // Replace with your token
$org = getenv('INFLUXDB_ORG') ?: 'odyorganization';
$bucket = getenv('INFLUXDB_BUCKET') ?: 'logs';

echo "Testing InfluxDB 2.x Connection\n";
echo "------------------------------\n";
echo "URL: $url\n";
echo "Organization: $org\n";
echo "Bucket: $bucket\n";
echo "------------------------------\n\n";

try {
    // Create client with options array
    $client = new InfluxDB2\Client([
        "url" => $url,
        "token" => $token,
        "bucket" => $bucket,
        "org" => $org,
        "precision" => InfluxDB2\Model\WritePrecision::S,
        "debug" => true
    ]);

    // 1. Check health
    echo "Checking InfluxDB health...\n";

    try {
        $health = $client->health();
        echo "Health Status: " . $health->getStatus() . "\n";
        echo "InfluxDB Version: " . $health->getVersion() . "\n\n";
    } catch (Exception $e) {
        echo "Error checking health: " . $e->getMessage() . "\n\n";
    }

    // 2. Write test data
    echo "Writing test data...\n";

    try {
        $writeApi = $client->createWriteApi();

        $point = new InfluxDB2\Point("test_measurement");
        $point->addTag("test_tag", "test_value");
        $point->addField("value", 100);
        $point->time(time());

        $writeApi->write($point);
        $writeApi->close();

        echo "Data written successfully\n\n";
    } catch (Exception $e) {
        echo "Error writing data: " . $e->getMessage() . "\n\n";
    }

    // 3. Query test data
    echo "Querying test data...\n";

    try {
        $queryApi = $client->createQueryApi();
        $query = "from(bucket: \"$bucket\") 
            |> range(start: -1h) 
            |> filter(fn: (r) => r._measurement == \"test_measurement\")";

        $tables = $queryApi->query($query);

        if (empty($tables)) {
            echo "No data found\n";
        } else {
            echo "Data retrieved successfully:\n";

            foreach ($tables as $table) {
                foreach ($table->records as $record) {
                    echo "- Measurement: " . $record->getMeasurement() . "\n";
                    echo "  Time: " . $record->getTime() . "\n";
                    echo "  Field: " . $record->getField() . "\n";
                    echo "  Value: " . $record->getValue() . "\n";
                    echo "  Tags: " . json_encode($record->values) . "\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "Error querying data: " . $e->getMessage() . "\n";
    }

    // Close client
    $client->close();

    echo "\nTest completed.\n";

} catch (Exception $e) {
    echo "Error creating InfluxDB client: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}