<?php

namespace Eclipse\Catalogue\Models;

use Eclipse\Catalogue\Enums\PropertyInputType;
use Eclipse\Catalogue\Enums\PropertyType;
use Eclipse\Catalogue\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Spatie\Translatable\HasTranslations;

class Property extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    protected $table = 'pim_property';

    protected $fillable = [
        'code',
        'name',
        'description',
        'internal_name',
        'is_active',
        'is_global',
        'max_values',
        'enable_sorting',
        'is_filter',
        'type',
        'input_type',
        'is_multilang',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'is_active' => 'boolean',
        'is_global' => 'boolean',
        'enable_sorting' => 'boolean',
        'is_filter' => 'boolean',
        'max_values' => 'integer',
        'is_multilang' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Property $property) {
            $property->validateTypeAndInputType();
            if ($property->code) {
                $property->code = strtolower($property->code);
            }
            // Ensure color type properties are always multilingual
            if ($property->isColorType()) {
                $property->is_multilang = true;
            }
        });

        static::updating(function (Property $property) {
            $property->validateTypeAndInputType();
            if ($property->isDirty('code') && $property->code) {
                $property->code = strtolower($property->code);
            }
            // Ensure color type properties remain multilingual on updates
            if ($property->isColorType()) {
                $property->is_multilang = true;
            }
        });

        static::created(function (Property $property) {
            if ($property->is_global) {
                $property->assignToAllProductTypes();
            }
        });

        static::updated(function (Property $property) {
            if ($property->wasChanged('is_global') && $property->is_global) {
                $property->assignToAllProductTypes();
            }
        });

        static::deleting(function (Property $property) {
            if ($property->isForceDeleting()) {
                // Force delete related values
                $property->values()->forceDelete();
                // Delete pivot rows
                $property->productTypes()->detach();
            }
        });
    }

    protected function validateTypeAndInputType(): void
    {
        if ($this->type && ! empty(trim($this->type))) {
            $validTypes = [PropertyType::LIST->value, PropertyType::COLOR->value, PropertyType::CUSTOM->value];
            if (! in_array($this->type, $validTypes)) {
                throw new InvalidArgumentException("Invalid type '{$this->type}'. Must be one of: ".implode(', ', $validTypes));
            }
        }

        if ($this->type === PropertyType::CUSTOM->value && $this->input_type !== null) {
            $validInputTypes = [
                PropertyInputType::STRING->value,
                PropertyInputType::TEXT->value,
                PropertyInputType::INTEGER->value,
                PropertyInputType::DECIMAL->value,
                PropertyInputType::DATE->value,
                PropertyInputType::DATETIME->value,
                PropertyInputType::FILE->value,
            ];

            if (! in_array($this->input_type, $validInputTypes)) {
                throw new InvalidArgumentException("Invalid input_type '{$this->input_type}'. Must be one of: ".implode(', ', $validInputTypes));
            }
        }
    }

    public function values(): HasMany
    {
        return $this->hasMany(PropertyValue::class);
    }

    public function productTypes(): BelongsToMany
    {
        return $this->belongsToMany(ProductType::class, 'pim_product_type_has_property')
            ->withPivot('sort')
            ->withTimestamps()
            ->orderByPivot('sort');
    }

    public function assignToAllProductTypes(): void
    {
        $existingTypeIds = $this->productTypes()->pluck('pim_product_types.id')->toArray();
        $allTypeIds = ProductType::pluck('id')->toArray();
        $newTypeIds = array_diff($allTypeIds, $existingTypeIds);

        if (! empty($newTypeIds)) {
            $attachData = [];
            foreach ($newTypeIds as $typeId) {
                $attachData[$typeId] = ['sort' => 0];
            }
            $this->productTypes()->attach($attachData);
        }
    }

    public function isCustomType(): bool
    {
        return $this->type === PropertyType::CUSTOM->value;
    }

    public function isListType(): bool
    {
        return $this->type === PropertyType::LIST->value;
    }

    public function isColorType(): bool
    {
        return $this->type === PropertyType::COLOR->value;
    }

    public function supportsMultilang(): bool
    {
        // Color type properties are always multilingual
        if ($this->isColorType()) {
            return true;
        }

        // Only custom type properties can be multilingual based on input type
        if (! $this->isCustomType()) {
            return false;
        }

        return $this->is_multilang && in_array($this->input_type, [
            PropertyInputType::STRING->value,
            PropertyInputType::TEXT->value,
            PropertyInputType::FILE->value,
        ]);
    }

    public function getInputValidationRules(): array
    {
        if (! $this->isCustomType()) {
            return [];
        }

        $rules = [];

        switch ($this->input_type) {
            case PropertyInputType::STRING->value:
                $rules[] = 'string|max:255';
                break;
            case PropertyInputType::TEXT->value:
                $rules[] = 'string|max:65535';
                break;
            case PropertyInputType::INTEGER->value:
                $rules[] = 'integer';
                break;
            case PropertyInputType::DECIMAL->value:
                $rules[] = 'numeric';
                break;
            case PropertyInputType::DATE->value:
                $rules[] = 'date';
                break;
            case PropertyInputType::DATETIME->value:
                $rules[] = 'date';
                break;
            case PropertyInputType::FILE->value:
                $rules[] = 'file';
                if ($this->max_values > 1) {
                    $rules[] = 'array';
                }
                break;
        }

        return $rules;
    }

    public function getFormFieldType(): string
    {
        $valueCount = $this->values()->count();

        if ($this->max_values === 1) {
            return $valueCount < 4 ? 'radio' : 'select';
        } else {
            return $valueCount < 4 ? 'checkbox' : 'multiselect';
        }
    }

    protected static function newFactory(): PropertyFactory
    {
        return PropertyFactory::new();
    }
}
