<?php

declare(strict_types=1);

namespace W3Chess\Chess;

use W3Chess\Config\AppConfig;
use W3Chess\Lang\Translator;

/** Ports piecepointer(): display info (name + image URLs) for a board character. */
final readonly class PieceCatalog
{
    public function __construct(
        private AppConfig $config,
        private Translator $translator,
    ) {
    }

    public function describe(string $piece): ?PieceIcon
    {
        if ($piece === 'E' || $piece === 'e') {
            return new PieceIcon(
                shortName: $this->translator->get('TSHORTE'),
                longName: $this->translator->get('TEMPTY'),
                imageUrl: $this->config->imageUrl($this->config->theme->emptyImage),
                smallImageUrl: '',
                smallSelectedImageUrl: '',
            );
        }

        $images = $this->config->theme->pieceImages[$piece] ?? null;
        if ($images === null) {
            return null;
        }

        $longKey = match (strtoupper($piece)) {
            'R' => 'TLONGR',
            'P' => 'TLONGP',
            'N' => 'TLONGN',
            'K' => 'TLONGK',
            'Q' => 'TLONGQ',
            'B' => 'TLONGB',
        };
        $shortKey = match (strtoupper($piece)) {
            'R' => 'TSHORTR',
            'P' => 'TSHORTP',
            'N' => 'TSHORTN',
            'K' => 'TSHORTK',
            'Q' => 'TSHORTQ',
            'B' => 'TSHORTB',
        };

        return new PieceIcon(
            shortName: $this->translator->get($shortKey),
            longName: $this->translator->get($longKey),
            imageUrl: $this->config->imageUrl($images['full']),
            smallImageUrl: $this->config->imageUrl($images['small']),
            smallSelectedImageUrl: $this->config->imageUrl($images['small_selected']),
        );
    }
}
