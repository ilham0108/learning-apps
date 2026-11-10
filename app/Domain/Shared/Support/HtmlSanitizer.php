<?php

namespace App\Domain\Shared\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonySanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Membersihkan HTML dari editor (artikel materi, soal) sebelum disimpan untuk mencegah XSS.
 * Hanya tag format teks yang diizinkan; skrip, atribut event, style, dan iframe dibuang.
 */
class HtmlSanitizer
{
    private readonly SymfonySanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('hr')
            ->allowElement('a', ['href', 'title'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->forceAttribute('a', 'target', '_blank')
            ->withMaxInputLength(500_000);

        $this->sanitizer = new SymfonySanitizer($config);
    }

    public function clean(?string $html): string
    {
        return trim($this->sanitizer->sanitize($html ?? ''));
    }

    /**
     * Apakah HTML berisi teks yang terlihat (bukan hanya tag kosong).
     */
    public function hasVisibleText(?string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html ?? ''))) !== '';
    }
}
