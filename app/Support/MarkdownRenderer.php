<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Санітизований рендер обмеженого markdown у повідомленнях (фаза B1):
 * bold, italic, code, links. Inline-рендер не створює блокових елементів,
 * сирий HTML екранується, небезпечні протоколи посилань вимикаються (XSS).
 * Зберігаємо raw у БД, body_html рендеримо при серіалізації.
 */
class MarkdownRenderer
{
    /**
     * @var array<string, mixed>
     */
    private const array OPTIONS = [
        'html_input' => 'escape',
        'allow_unsafe_links' => false,
        'max_nesting_level' => 10,
    ];

    public static function render(string $raw): string
    {
        return trim(Str::inlineMarkdown($raw, self::OPTIONS));
    }
}
