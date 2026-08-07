<?php

declare(strict_types=1);

namespace OEMS\Tests\Unit;

use OEMS\App\Services\VenueService;
use OEMS\Core\Logger;
use OEMS\Tests\Support\FakeVenueRepository;
use OEMS\Tests\Support\TestCase;

final class VenueServiceTest extends TestCase
{
    private FakeVenueRepository $venues;

    private VenueService $service;

    protected function setUp(): void
    {
        $this->venues = new FakeVenueRepository();
        $this->service = new VenueService($this->venues);
    }

    public function testCreateRejectsMissingRequiredFieldsWithoutPersisting(): void
    {
        $before = count($this->venues->venues);

        $result = $this->service->create(10, []);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('address_line', $result['errors']);
        $this->assertArrayHasKey('city', $result['errors']);
        $this->assertArrayHasKey('country', $result['errors']);
        $this->assertSame($before, count($this->venues->venues));
    }

    public function testCreateRejectsCoordinateCapacityAndMapUrlBounds(): void
    {
        $input = $this->validInput();
        $input['latitude'] = '-90.0000001';
        $input['longitude'] = '180.0000001';
        $input['capacity'] = '0';
        $input['map_url'] = 'javascript:alert(1)';

        $result = $this->service->create(10, $input);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('latitude', $result['errors']);
        $this->assertArrayHasKey('longitude', $result['errors']);
        $this->assertArrayHasKey('capacity', $result['errors']);
        $this->assertArrayHasKey('map_url', $result['errors']);
    }

    public function testCreateTrimsValuesNormalizesBlanksAndCoercesCapacity(): void
    {
        $input = $this->validInput();
        $input['name'] = '  River Hall  ';
        $input['address_line'] = '  24 River Road  ';
        $input['postal_code'] = '   ';
        $input['latitude'] = '-90';
        $input['longitude'] = '180';
        $input['map_url'] = '   ';
        $input['capacity'] = '250';

        $result = $this->service->create(10, $input);
        $venue = $this->venues->venues[(int) $result['venue_id']];

        $this->assertTrue($result['success']);
        $this->assertSame('River Hall', $venue['name']);
        $this->assertSame('24 River Road', $venue['address_line']);
        $this->assertNull($venue['postal_code']);
        $this->assertSame('-90', $venue['latitude']);
        $this->assertSame('180', $venue['longitude']);
        $this->assertNull($venue['map_url']);
        $this->assertSame(250, $venue['capacity']);
    }

    public function testCreateReportsRepositoryFailure(): void
    {
        $this->venues->failCreate = true;

        $result = $this->service->create(10, $this->validInput());

        $this->assertFalse($result['success']);
        $this->assertFalse($result['not_found']);
        $this->assertSame('The venue could not be created.', $result['errors']['venue'][0]);
    }

    public function testUpdateReturnsNotFoundForCrossOwnerVenueBeforeValidation(): void
    {
        $result = $this->service->update(10, 2, []);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['not_found']);
        $this->assertSame([], $result['errors']);
        $this->assertSame('Foreign Hall', $this->venues->venues[2]['name']);
    }

    public function testUpdatePersistsNormalizedOwnedValuesAndReportsRepositoryFailure(): void
    {
        $input = $this->validInput();
        $input['name'] = '  Updated Hall  ';

        $success = $this->service->update(10, 1, $input);

        $this->assertTrue($success['success']);
        $this->assertSame('Updated Hall', $this->venues->venues[1]['name']);

        $this->venues->failUpdate = true;
        $failure = $this->service->update(10, 1, $input);

        $this->assertFalse($failure['success']);
        $this->assertFalse($failure['not_found']);
        $this->assertSame('The venue could not be updated.', $failure['errors']['venue'][0]);
    }

    public function testDeleteDistinguishesForeignReferencedAndSuccessfulOutcomes(): void
    {
        $foreign = $this->service->delete(10, 2);
        $this->assertFalse($foreign['success']);
        $this->assertTrue($foreign['not_found']);

        $this->venues->referencedVenueIds = [1];
        $referenced = $this->service->delete(10, 1);
        $this->assertFalse($referenced['success']);
        $this->assertFalse($referenced['not_found']);
        $this->assertSame(
            'This venue cannot be deleted while an event uses it.',
            $referenced['errors']['venue'][0],
        );

        $this->venues->referencedVenueIds = [];
        $deleted = $this->service->delete(10, 1);
        $this->assertTrue($deleted['success']);
        $this->assertFalse(array_key_exists(1, $this->venues->venues));
    }

    public function testCaughtVenuePersistenceExceptionsLogOnlySanitizedOwnershipContext(): void
    {
        $logPath = sys_get_temp_dir() . '/oems-venue-log-' . bin2hex(random_bytes(6)) . '.log';
        file_put_contents($logPath, '');
        $service = new VenueService($this->venues, new Logger($logPath));
        $this->venues->throwCreate = true;
        $created = $service->create(10, $this->validInput());
        $this->venues->throwCreate = false;
        $this->venues->throwUpdate = true;
        $updated = $service->update(10, 1, $this->validInput());
        $this->venues->throwDelete = true;
        $deleted = $service->delete(10, 1);
        $log = file_get_contents($logPath);

        $this->assertFalse($created['success']);
        $this->assertFalse($updated['success']);
        $this->assertFalse($deleted['success']);
        $this->assertTrue(is_string($log));
        $this->assertTrue(str_contains($log, 'Venue persistence operation failed.'));
        $this->assertTrue(str_contains($log, '"operation":"create"'));
        $this->assertTrue(str_contains($log, '"operation":"update"'));
        $this->assertTrue(str_contains($log, '"operation":"delete"'));
        $this->assertTrue(str_contains($log, '"user_id":10'));
        $this->assertTrue(str_contains($log, '"venue_id":1'));
        $this->assertFalse(str_contains($log, 'SQL secret'));
        $this->assertFalse(str_contains($log, 'Owned Hall'));

        unlink($logPath);
    }

    private function validInput(): array
    {
        return [
            'name' => 'Owned Hall',
            'address_line' => '12 Lake Road',
            'city' => 'Dhaka',
            'country' => 'Bangladesh',
            'postal_code' => '1205',
            'latitude' => '23.7465',
            'longitude' => '90.3760',
            'map_url' => 'https://example.test/venue',
            'capacity' => '100',
        ];
    }
}
