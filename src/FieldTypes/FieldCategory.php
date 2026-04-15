<?php

declare(strict_types=1);

namespace MiPress\Core\FieldTypes;

enum FieldCategory: string
{
    case Text = 'text';
    case Numeric = 'numeric';
    case Boolean = 'boolean';
    case Selection = 'selection';
    case DateTime = 'datetime';
    case Media = 'media';
    case Structured = 'structured';
    case Taxonomy = 'taxonomy';
    case Presentation = 'presentation';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('mipress::admin.field_types.categories.text'),
            self::Numeric => __('mipress::admin.field_types.categories.numeric'),
            self::Boolean => __('mipress::admin.field_types.categories.boolean'),
            self::Selection => __('mipress::admin.field_types.categories.selection'),
            self::DateTime => __('mipress::admin.field_types.categories.datetime'),
            self::Media => __('mipress::admin.field_types.categories.media'),
            self::Structured => __('mipress::admin.field_types.categories.structured'),
            self::Taxonomy => __('mipress::admin.field_types.categories.taxonomy'),
            self::Presentation => __('mipress::admin.field_types.categories.presentation'),
        };
    }
}
