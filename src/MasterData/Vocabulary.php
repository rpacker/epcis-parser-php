<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\MasterData;

use Rpacker\EpcisParser\CBV\VocabularyType;

class Vocabulary
{
    /**
     * @param VocabularyElement[] $elements
     */
    public function __construct(
        public readonly VocabularyType|string $type,
        public readonly array $elements = [],
    ) {
    }

    public function typeUri(): string
    {
        return $this->type instanceof VocabularyType ? $this->type->value : $this->type;
    }
}
