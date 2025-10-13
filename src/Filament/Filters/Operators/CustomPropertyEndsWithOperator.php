<?php

namespace Eclipse\Catalogue\Filament\Filters\Operators;

use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\QueryBuilder\Constraints\Operators\Operator;
use Illuminate\Database\Eloquent\Builder;

class CustomPropertyEndsWithOperator extends Operator
{
    /**
     * Get the name of the operator.
     */
    public function getName(): string
    {
        return 'ends_with';
    }

    /**
     * Get the label of the operator.
     */
    public function getLabel(): string
    {
        return __(
            $this->isInverse() ?
                'filament-tables::filters/query-builder.operators.text.ends_with.label.inverse' :
                'filament-tables::filters/query-builder.operators.text.ends_with.label.direct',
        );
    }

    /**
     * Get the summary of the operator.
     */
    public function getSummary(): string
    {
        return __(
            $this->isInverse() ?
                'filament-tables::filters/query-builder.operators.text.ends_with.summary.inverse' :
                'filament-tables::filters/query-builder.operators.text.ends_with.summary.direct',
            [
                'attribute' => $this->getConstraint()->getAttributeLabel(),
                'text' => $this->getSettings()['text'],
            ],
        );
    }

    /**
     * Get the form schema of the operator.
     *
     * @return array<\Filament\Schemas\Components\Component>
     */
    public function getFormSchema(): array
    {
        return [
            TextInput::make('text')
                ->label(__('filament-tables::filters/query-builder.operators.text.form.text.label'))
                ->required()
                ->columnSpanFull(),
        ];
    }

    /**
     * Apply the operator to the query.
     */
    public function apply(Builder $query, string $qualifiedColumn): Builder
    {
        $text = trim($this->getSettings()['text']);

        if (empty($text)) {
            return $query;
        }

        $constraintName = $this->getConstraint()->getName();
        $propertyId = (int) str_replace('custom_property_', '', $constraintName);

        return $query->whereHas('customPropertyValues', function ($q) use ($propertyId, $text) {
            $q->where('property_id', $propertyId)
                ->whereRaw('REGEXP_REPLACE(value, "<[^>]*>", "") LIKE ?', ["%{$text}"]);
        });
    }
}
