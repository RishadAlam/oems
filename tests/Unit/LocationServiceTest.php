<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use InvalidArgumentException;
use OEMS\App\Services\LocationService;
use OEMS\Tests\Support\TestCase;

final class LocationServiceTest extends TestCase
{
    public function testPreferenceRoundsCoordinatesAndExpiresAtConfiguredTtl(): void
    {
        $service = new LocationService(1209600, static fn (): int => 1_800_000_000);
        $preference = $service->preference('23.810331', '90.412521', '25', 'Current area', 'device');

        $this->assertSame('23.810', $preference['latitude']);
        $this->assertSame('90.413', $preference['longitude']);
        $this->assertSame(25, $preference['radius']);
        $this->assertSame('Current area', $preference['label']);
        $this->assertSame('device', $preference['source']);
        $this->assertSame(1_801_209_600, $preference['expires_at']);
    }

    public function testPreferenceRejectsInvalidCoordinatesAndNormalizesFallbackFields(): void
    {
        $service = new LocationService();

        foreach ([['91', '90'], ['23.8', '181'], ['north', '90'], ['23.8', []]] as [$latitude, $longitude]) {
            try {
                $service->preference($latitude, $longitude, '7', '   ', 'unknown');
                $this->assertTrue(false, 'Invalid coordinates must not become a location preference.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $preference = $service->preference('-90', '180', '7', '   ', 'unknown');
        $this->assertSame('-90.000', $preference['latitude']);
        $this->assertSame('180.000', $preference['longitude']);
        $this->assertSame(25, $preference['radius']);
        $this->assertSame('Current area', $preference['label']);
        $this->assertSame('manual', $preference['source']);
    }

    public function testInvalidOrExpiredSessionPreferenceIsRejected(): void
    {
        $service = new LocationService(1209600, static fn (): int => 1_800_000_000);

        $this->assertNull($service->fromSession(['latitude' => '91', 'longitude' => '90', 'expires_at' => 1_900_000_000]));
        $this->assertNull($service->fromSession(['latitude' => '23.8', 'longitude' => '90.4', 'expires_at' => 1_799_999_999]));
        $this->assertNull($service->fromSession('not an array'));

        $preference = $service->fromSession([
            'latitude' => '23.810331',
            'longitude' => '90.412521',
            'radius' => '50',
            'label' => '  Office  ',
            'source' => 'device',
            'expires_at' => 1_800_000_001,
        ]);

        $this->assertNotNull($preference);
        $this->assertSame('23.810', $preference['latitude']);
        $this->assertSame('90.413', $preference['longitude']);
        $this->assertSame(50, $preference['radius']);
        $this->assertSame('Office', $preference['label']);
        $this->assertSame('device', $preference['source']);
        $this->assertSame(1_800_000_001, $preference['expires_at']);
    }

    public function testBoundsArePoleSafeAndUseFullLongitudeAtThePole(): void
    {
        $service = new LocationService();
        $bounds = $service->bounds(['latitude' => '90', 'longitude' => '180', 'radius' => 25]);

        $this->assertSame('89.775170', $bounds['latitude_min']);
        $this->assertSame('90.000000', $bounds['latitude_max']);
        $this->assertSame('-180.000000', $bounds['longitude_min']);
        $this->assertSame('180.000000', $bounds['longitude_max']);
    }

    public function testRestrictedDistanceUsesCoarseBand(): void
    {
        $service = new LocationService();

        $this->assertSame('3.4 km away', $service->distanceLabel('3.36', true));
        $this->assertSame('Within 5 km', $service->distanceLabel('3.36', false));
        $this->assertNull($service->distanceLabel('north', true));
        $this->assertNull($service->distanceLabel('-1', false));
    }

    public function testDirectionsUseHttpsCustomUrlOrEncodedCoordinatesAndRejectUnsafeUrls(): void
    {
        $service = new LocationService();

        $this->assertSame('https://maps.example.test/venue?id=42', $service->directionsUrl([
            'map_url' => 'https://maps.example.test/venue?id=42',
            'latitude' => '23.810331',
            'longitude' => '90.412521',
        ]));
        $this->assertSame('https://www.google.com/maps/dir/?api=1&destination=23.810331%2C90.412521', $service->directionsUrl([
            'latitude' => '23.810331',
            'longitude' => '90.412521',
        ]));
        $this->assertNull($service->directionsUrl(['map_url' => 'javascript:alert(1)', 'latitude' => '23', 'longitude' => '90']));
        $this->assertNull($service->directionsUrl(['map_url' => 'data:text/html,nope']));
    }
}
