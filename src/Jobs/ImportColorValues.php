<?php

namespace Eclipse\Catalogue\Jobs;

use Eclipse\Catalogue\Enums\PropertyType;
use Eclipse\Catalogue\Models\Property;
use Eclipse\Catalogue\Models\PropertyValue;
use Eclipse\Catalogue\Values\Background;
use Eclipse\Common\Enums\JobStatus;
use Eclipse\Common\Foundation\Jobs\QueueableJob;
use Exception;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as PhpSpreadsheetException;

class ImportColorValues extends QueueableJob
{
    /**
     * The timeout for the job.
     */
    public int $timeout = 300;

    /**
     * Whether to fail the job on timeout.
     */
    public bool $failOnTimeout = true;

    /**
     * The file path to import.
     */
    private string $filePath;

    /**
     * The property ID to import colors for.
     */
    private int $propertyId;

    /**
     * Import statistics.
     */
    private array $stats = [
        'inserted' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    /**
     * Error messages.
     */
    private array $errors = [];

    /**
     * Create a new job instance.
     */
    public function __construct(string $filePath, int $propertyId)
    {
        parent::__construct();
        $this->filePath = $filePath;
        $this->propertyId = $propertyId;
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     */
    protected function execute(): void
    {
        $property = Property::find($this->propertyId);
        if (! $property || $property->type !== PropertyType::COLOR->value) {
            throw new Exception('Invalid property or property is not a color type.');
        }

        if (! Storage::disk('local')->exists($this->filePath)) {
            throw new Exception('Import file not found.');
        }

        $absolutePath = Storage::disk('local')->path($this->filePath);

        try {
            $this->importFromSpreadsheet($absolutePath, $property);
        } finally {
            // Clean up the temporary file
            Storage::disk('local')->delete($this->filePath);
        }
    }

    /**
     * Import colors from spreadsheet file (CSV or Excel).
     */
    private function importFromSpreadsheet(string $filePath, Property $property): void
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            $highestRow = $worksheet->getHighestRow();

            // Get headers from first row
            $headers = [];
            for ($col = 1; $col <= 2; $col++) {
                $headers[] = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
            }
            $this->validateHeaders($headers);

            // Process data rows
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [
                    'name' => $worksheet->getCellByColumnAndRow(1, $row)->getValue(),
                    'hex' => $worksheet->getCellByColumnAndRow(2, $row)->getValue(),
                ];
                $this->processRow($rowData, $property);
            }
        } catch (PhpSpreadsheetException $e) {
            throw new Exception('Failed to read spreadsheet file: '.$e->getMessage());
        }
    }

    /**
     * Validate file headers.
     */
    private function validateHeaders(array $headers): void
    {
        $headers = array_map('strtolower', array_map('trim', $headers));

        if (! in_array('name', $headers) || ! in_array('hex', $headers)) {
            throw new Exception('Invalid file format. Expected columns: name, hex');
        }
    }

    /**
     * Process a single row of data.
     */
    private function processRow(array $row, Property $property): void
    {
        $name = trim($row['name'] ?? '');
        $hex = trim($row['hex'] ?? '');

        if (empty($name) || empty($hex)) {
            $this->stats['skipped']++;
            $this->errors[] = "Skipped row with empty name or hex: {$name}, {$hex}";

            return;
        }

        // Validate hex color
        if (! $this->isValidHexColor($hex)) {
            $this->stats['errors']++;
            $this->errors[] = "Invalid hex color '{$hex}' for name '{$name}'";

            return;
        }

        // Check for duplicates (by name)
        $existing = PropertyValue::where('property_id', $property->id)
            ->where('value->'.app()->getLocale(), $name)
            ->first();

        if ($existing) {
            $this->stats['skipped']++;
            $this->errors[] = "Skipped duplicate name: {$name}";

            return;
        }

        try {
            // Create the color background object
            $color = Background::solid($hex);

            // Create the property value
            PropertyValue::create([
                'property_id' => $property->id,
                'value' => [app()->getLocale() => $name],
                'color' => $color,
                'sort' => PropertyValue::where('property_id', $property->id)->max('sort') + 1,
            ]);

            $this->stats['inserted']++;
        } catch (Exception $e) {
            $this->stats['errors']++;
            $this->errors[] = "Failed to create property value for '{$name}': ".$e->getMessage();
        }
    }

    /**
     * Validate hex color format.
     */
    private function isValidHexColor(string $hex): bool
    {
        // Remove # if present
        $hex = ltrim($hex, '#');

        // Check if it's a valid hex color (3 or 6 characters)
        return preg_match('/^[0-9A-Fa-f]{3}$|^[0-9A-Fa-f]{6}$/', $hex) === 1;
    }

    /**
     * Get the notification title based on the job status.
     */
    protected function getNotificationTitle(): string
    {
        if ($this->status === JobStatus::COMPLETED) {
            return __('eclipse-catalogue::property-value.notifications.import_completed.title');
        }

        if ($this->status === JobStatus::FAILED) {
            return __('eclipse-catalogue::property-value.notifications.import_failed.title');
        }

        return parent::getNotificationTitle();
    }

    /**
     * Get the notification body based on the job status.
     */
    protected function getNotificationBody(): string
    {
        if ($this->status === JobStatus::COMPLETED) {
            $body = __('eclipse-catalogue::property-value.notifications.import_completed.body', [
                'inserted' => $this->stats['inserted'],
                'skipped' => $this->stats['skipped'],
                'errors' => $this->stats['errors'],
            ]);

            if (! empty($this->errors) && $this->stats['errors'] > 0) {
                $body .= "\n\n".__('eclipse-catalogue::property-value.notifications.import_completed.errors').":\n";
                $body .= implode("\n", array_slice($this->errors, 0, 5));
                if (count($this->errors) > 5) {
                    $body .= "\n... and ".(count($this->errors) - 5).' more errors.';
                }
            }

            return $body;
        }

        if ($this->status === JobStatus::FAILED) {
            $message = $this->exception?->getMessage();

            return $message ?: __('eclipse-catalogue::property-value.notifications.import_queued.body');
        }

        return parent::getNotificationBody();
    }
}
