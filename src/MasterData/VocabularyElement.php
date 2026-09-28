<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\MasterData;

class VocabularyElement
{
    /** CBV master data attribute namespace, e.g. self::MDA . 'name'. */
    public const MDA = 'urn:epcglobal:cbv:mda#';

    /**
     * @param array<string, string> $attributes attribute id (full URI) => value;
     *                                          null/empty values are skipped on render
     */
    public function __construct(
        public readonly string $id,
        public readonly array $attributes = [],
    ) {
    }

    /**
     * Convenience for CBV attributes: ['name' => 'Acme'] becomes
     * ['urn:epcglobal:cbv:mda#name' => 'Acme'].
     *
     * @param array<string, string|null> $attributes
     */
    public static function cbv(string $id, array $attributes): self
    {
        $full = [];
        foreach ($attributes as $name => $value) {
            if ($value !== null && $value !== '') {
                $full[self::MDA . $name] = $value;
            }
        }

        return new self($id, $full);
    }
}
