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
            self::Text => 'Text',
            self::Numeric => 'Čísla',
            self::Boolean => 'Logické',
            self::Selection => 'Výběr',
            self::DateTime => 'Datum a čas',
            self::Media => 'Média',
            self::Structured => 'Strukturované',
            self::Taxonomy => 'Taxonomie',
            self::Presentation => 'Prezentační',
        };
    }
}
