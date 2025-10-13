<?php

use Eclipse\Catalogue\Enums\PropertyType;
use Eclipse\Catalogue\Jobs\ImportColorValues;
use Eclipse\Catalogue\Models\Property;
use Eclipse\Catalogue\Models\PropertyValue;
use Eclipse\Catalogue\Values\Background;
use Eclipse\Common\Enums\JobStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->migrate();
    Storage::fake('local');
});

function runImportJob(ImportColorValues $job): void
{
    $ref = new ReflectionClass($job);
    $method = $ref->getMethod('execute');
    $method->setAccessible(true);
    $method->invoke($job);
}

it('uploads valid file and imports all values', function () {
    Queue::fake();

    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);

    // Create a test CSV file directly in storage
    $csvContent = "name,hex\nRed,#FF0000\nGreen,#00FF00\nBlue,#0000FF";
    $filePath = 'temp/color-imports/test-colors.csv';
    Storage::disk('local')->put($filePath, $csvContent);

    // Dispatch the job
    $job = new ImportColorValues($filePath, $property->id);
    runImportJob($job);

    // Assert that all values were imported
    expect(PropertyValue::where('property_id', $property->id)->count())->toBe(3);

    // Check that colors are properly stored
    $redValue = PropertyValue::where('property_id', $property->id)
        ->where('value->en', 'Red')
        ->first();
    expect($redValue->color->color)->toBe('#FF0000');
    expect($redValue->getColor())->toBe('background-color: #FF0000;');
});

it('handles duplicates gracefully by skipping them', function () {
    Queue::fake();

    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);

    // Create an existing property value
    PropertyValue::factory()->create([
        'property_id' => $property->id,
        'value' => ['en' => 'Red'],
        'color' => Background::solid('#FF0000'),
    ]);

    // Create a CSV with duplicate name
    $csvContent = "name,hex\nRed,#FF0000\nGreen,#00FF00";
    $file = UploadedFile::fake()->createWithContent('colors.csv', $csvContent);
    $filePath = $file->store('temp/color-imports', 'local');

    // Dispatch the job
    $job = new ImportColorValues($filePath, $property->id);
    runImportJob($job);

    // Should still have only 2 values (1 existing + 1 new)
    expect(PropertyValue::where('property_id', $property->id)->count())->toBe(2);

    // Check that the job stats reflect the skip
    $reflection = new ReflectionClass($job);
    $statsProperty = $reflection->getProperty('stats');
    $statsProperty->setAccessible(true);
    $stats = $statsProperty->getValue($job);

    expect($stats['inserted'])->toBe(1);
    expect($stats['skipped'])->toBe(1);
});

it('reports errors for invalid hex colors without crashing', function () {
    Queue::fake();

    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);

    // Create a CSV with invalid hex colors
    $csvContent = "name,hex\nRed,#FF0000\nInvalid,invalid_hex\nGreen,#00FF00\nBad,not_a_color";
    $file = UploadedFile::fake()->createWithContent('colors.csv', $csvContent);
    $filePath = $file->store('temp/color-imports', 'local');

    // Dispatch the job
    $job = new ImportColorValues($filePath, $property->id);
    runImportJob($job);

    // Should have only 2 valid values
    expect(PropertyValue::where('property_id', $property->id)->count())->toBe(2);

    // Check that the job stats reflect the errors
    $reflection = new ReflectionClass($job);
    $statsProperty = $reflection->getProperty('stats');
    $statsProperty->setAccessible(true);
    $stats = $statsProperty->getValue($job);

    expect($stats['inserted'])->toBe(2);
    expect($stats['errors'])->toBe(2);
});

it('shows correct counts in notification after import', function () {
    Queue::fake();

    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);

    // Create a CSV with mixed valid/invalid data
    $csvContent = "name,hex\nRed,#FF0000\nGreen,#00FF00\nInvalid,invalid_hex\nBlue,#0000FF";
    $file = UploadedFile::fake()->createWithContent('colors.csv', $csvContent);
    $filePath = $file->store('temp/color-imports', 'local');

    // Dispatch the job
    $job = new ImportColorValues($filePath, $property->id);
    runImportJob($job);

    // Check notification body contains correct counts
    $reflection = new ReflectionClass($job);
    $statsProperty = $reflection->getProperty('stats');
    $statsProperty->setAccessible(true);
    $stats = $statsProperty->getValue($job);

    // Set job status to completed to get notification body
    $statusProperty = $reflection->getProperty('status');
    $statusProperty->setAccessible(true);
    $statusProperty->setValue($job, JobStatus::COMPLETED);

    // Access protected method via reflection
    $method = $reflection->getMethod('getNotificationBody');
    $method->setAccessible(true);
    $notificationBody = $method->invoke($job);

    expect($notificationBody)->toContain('3 inserted');
    expect($notificationBody)->toContain('0 skipped');
    expect($notificationBody)->toContain('1 errors');
});

it('runs queue job independently without blocking UI', function () {
    Queue::fake();

    $property = Property::factory()->create(['type' => PropertyType::COLOR->value]);

    $csvContent = "name,hex\nRed,#FF0000";
    $file = UploadedFile::fake()->createWithContent('colors.csv', $csvContent);
    $filePath = $file->store('temp/color-imports', 'local');

    // Dispatch the job to queue
    ImportColorValues::dispatch($filePath, $property->id);

    // Assert the job was dispatched to queue (not executed immediately)
    Queue::assertPushed(ImportColorValues::class, function ($job) use ($filePath, $property) {
        // Access private properties via reflection
        $reflection = new ReflectionClass($job);
        $filePathProperty = $reflection->getProperty('filePath');
        $filePathProperty->setAccessible(true);
        $propertyIdProperty = $reflection->getProperty('propertyId');
        $propertyIdProperty->setAccessible(true);

        return $filePathProperty->getValue($job) === $filePath &&
               $propertyIdProperty->getValue($job) === $property->id;
    });

    // Verify no values were created yet (job is queued, not executed)
    expect(PropertyValue::where('property_id', $property->id)->count())->toBe(0);
});
