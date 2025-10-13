<?php

namespace Eclipse\Catalogue\Factories;

use Eclipse\Catalogue\Models\ProductType;
use Eclipse\Core\Models\Locale;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductTypeFactory extends Factory
{
    protected $model = ProductType::class;

    public function definition(): array
    {
        $baseName = $this->faker->words(2, true);

        $locales = $this->getAvailableLocales();

        $translatedNames = [];
        foreach ($locales as $locale) {
            if ($locale === 'en') {
                $translatedNames[$locale] = $baseName;
            } else {
                $translatedNames[$locale] = strtoupper($locale).': '.$baseName;
            }
        }

        return [
            'name' => $translatedNames,
            'code' => $this->faker->unique()->regexify('[A-Z]{2}[0-9]{3}'),
        ];
    }

    /**
     * Get available locales for the application.
     */
    protected function getAvailableLocales(): array
    {
        if (class_exists(Locale::class)) {
            return Locale::getAvailableLocales()
                ->pluck('id')
                ->toArray();
        }

        return ['en'];
    }

    public function withName(string|array $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => is_string($name) ? ['en' => $name] : $name,
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }
}
