<?php

namespace Eclipse\Catalogue\Forms\Components;

use Closure;
use Eclipse\Core\Models\Locale;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\IconPosition;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class InlineTranslatableField
{
    protected string $name;

    protected string $label;

    protected string $type = 'string';

    protected array $rules = [];

    protected ?string $helperText = null;

    protected bool $required = false;

    protected ?int $maxLength = null;

    protected bool $multiple = false;

    protected int $maxFiles = 1;

    public static function make(string $name): static
    {
        $instance = new static;
        $instance->name = $name;

        return $instance;
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function rules(array $rules): static
    {
        $this->rules = $rules;

        return $this;
    }

    public function helperText(?string $helperText): static
    {
        $this->helperText = $helperText;

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function maxLength(?int $maxLength): static
    {
        $this->maxLength = $maxLength;

        return $this;
    }

    public function multiple(bool $multiple = true): static
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function maxFiles(int $maxFiles): static
    {
        $this->maxFiles = $maxFiles;

        return $this;
    }

    public function getComponent(): Component
    {
        if ($this->type === 'string') {
            return $this->createVerticalLayout();
        }

        return $this->createTabsLayout();
    }

    protected function createTabsLayout(): Group
    {
        $locales = $this->getAvailableLocales();
        $tabs = [];

        foreach ($locales as $locale) {
            $fieldName = "{$this->name}.{$locale}";
            $localePrefix = strtoupper($locale);

            $component = match ($this->type) {
                'textarea', 'text' => $this->createRichEditorComponent($fieldName, $localePrefix, true),
                'file' => $this->createFileComponent($fieldName, $localePrefix, true),
                'string' => $this->createTextInputComponent($fieldName, $localePrefix, false, false),
                default => $this->createTextInputComponent($fieldName, $localePrefix, false, false),
            };

            $tabs[] = Tab::make($localePrefix)
                ->label($localePrefix)
                ->schema([$component])
                ->icon($this->tabIconFor($fieldName))
                ->iconPosition(IconPosition::After)
                ->extraAttributes([
                    'class' => 'text-xs px-2 py-1.5 min-h-[2rem] font-medium',
                ]);
        }

        return Group::make()
            ->schema([
                Placeholder::make('label_'.$this->name)
                    ->label($this->label)
                    ->content('')
                    ->dehydrated(false),
                Tabs::make($this->name.'_tabs')
                    ->tabs($tabs)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'gap-1',
                    ]),
            ])
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'translatable-field-container',
            ]);
    }

    protected function createVerticalLayout(): Group
    {
        $locales = $this->getAvailableLocales();
        $components = [];

        $total = count($locales);
        foreach ($locales as $index => $locale) {
            $fieldName = "{$this->name}.{$locale}";
            $localePrefix = strtoupper($locale);

            $component = $this->createTextInputComponent($fieldName, $localePrefix, $index === 0, $index === $total - 1);
            $components[] = $component;
        }

        return Group::make()
            ->schema($components)
            ->columnSpanFull()
            ->extraAttributes(['class' => 'translatable-field-container']);
    }

    protected function createTextInputComponent(string $fieldName, string $localePrefix, bool $showLabel = false, bool $showHelperText = false): TextInput
    {
        $component = TextInput::make($fieldName)
            ->label($showLabel ? $this->label : false)
            ->prefix($localePrefix)
            ->required($this->required);

        if ($this->maxLength) {
            $component->maxLength($this->maxLength);
        }

        if ($this->helperText && $showHelperText) {
            $component->helperText($this->helperText);
        }

        if (! empty($this->rules)) {
            $component->rules($this->rules);
        }

        return $component;
    }

    protected function createRichEditorComponent(string $fieldName, string $localePrefix, bool $suppressLabel = false): RichEditor
    {
        $component = RichEditor::make($fieldName)
            ->label($suppressLabel ? false : $localePrefix.': '.$this->label)
            ->required($this->required);

        if ($this->maxLength) {
            $component->maxLength($this->maxLength);
        }

        if ($this->helperText) {
            $component->helperText($this->helperText);
        }

        if (! empty($this->rules)) {
            $component->rules($this->rules);
        }

        return $component;
    }

    protected function createFileComponent(string $fieldName, string $localePrefix, bool $suppressLabel = false): FileUpload
    {
        $component = FileUpload::make($fieldName)
            ->label($suppressLabel ? false : $this->label.' ('.$localePrefix.')')
            ->required($this->required);

        if ($this->multiple) {
            $component->multiple()->maxFiles($this->maxFiles);
        }

        if ($this->helperText) {
            $component->helperText($this->helperText);
        }

        if (! empty($this->rules)) {
            $component->rules($this->rules);
        }

        return $component;
    }

    protected function tabIconFor(string $fieldName): Closure
    {
        return fn (Get $get) => $this->valueHasMeaningfulContent($get($fieldName))
            ? new HtmlString(Blade::render(
                '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none">
                    <path d="m4.5 12.75 6 6 9-13.5"
                          stroke="#22c55e" stroke-width="3"
                          stroke-linecap="round" stroke-linejoin="round" />
                </svg>'
            ))
            : new HtmlString(Blade::render(
                '<svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 animate-pulse" viewBox="0 0 24 24" fill="none">
                    <path d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
                          stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>'
            ));
    }

    protected function valueHasMeaningfulContent(mixed $value): bool
    {
        if (is_array($value)) {
            return count(array_filter($value, fn ($v) => ! blank($v))) > 0;
        }

        if (is_string($value)) {
            $text = trim(
                preg_replace('/\xc2\xa0|&nbsp;|\s+/u', ' ',
                    strip_tags($value)
                )
            );

            return $text !== '';
        }

        return filled($value);
    }

    /**
     * Get available locales for the application.
     */
    protected function getAvailableLocales(): array
    {
        if (class_exists(Locale::class)) {
            return Locale::getAvailableLocales()->pluck('id')->toArray();
        }

        return ['en'];
    }
}
