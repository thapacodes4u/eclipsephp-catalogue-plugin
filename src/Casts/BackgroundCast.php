<?php

namespace Eclipse\Catalogue\Casts;

use Eclipse\Catalogue\Values\Background;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class BackgroundCast implements CastsAttributes
{
    /**
     * Cast the attribute from the database.
     *
     * @param  Model  $model  The model instance.
     * @param  string  $key  The attribute name.
     * @param  mixed  $value  The attribute value.
     * @param  array  $attributes  The attributes array.
     */
    public function get($model, string $key, $value, array $attributes): Background
    {
        if ($value === null || $value === '') {
            return Background::none();
        }

        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return Background::fromArray($decoded);
        }

        $maybeArray = @unserialize($value);
        if (is_array($maybeArray)) {
            return Background::fromArray($maybeArray);
        }

        return Background::none();
    }

    /**
     * Cast the attribute to a database value.
     *
     * @param  Model  $model  The model instance.
     * @param  string  $key  The attribute name.
     * @param  mixed  $value  The attribute value.
     * @param  array  $attributes  The attributes array.
     */
    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof Background) {
            return json_encode($value->toArray());
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return $value === null ? null : (string) $value;
    }
}
