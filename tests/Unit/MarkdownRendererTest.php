<?php

use App\Support\MarkdownRenderer;

/*
 * Санітизація markdown (фаза B1): обмежений набір — bold, italic, code, links.
 * Сирий HTML екранується, небезпечні посилання вимикаються. Головний ризик — XSS.
 */

it('renders the limited markdown set', function (string $raw, string $html) {
    expect(MarkdownRenderer::render($raw))->toBe($html);
})->with([
    'bold' => ['**жирний**', '<strong>жирний</strong>'],
    'italic' => ['*курсив*', '<em>курсив</em>'],
    'inline code' => ['`code()`', '<code>code()</code>'],
    'link' => ['[сайт](https://example.com)', '<a href="https://example.com">сайт</a>'],
]);

it('escapes raw html instead of rendering it (XSS regression)', function () {
    $html = MarkdownRenderer::render('Inject: <script>alert("xss")</script>');

    expect($html)
        ->not->toContain('<script')
        ->toContain('&lt;script&gt;');
});

it('escapes html event-handler payloads', function () {
    $html = MarkdownRenderer::render('<img src=x onerror=alert(1)>');

    expect($html)->not->toContain('<img');
});

it('disables unsafe link protocols', function () {
    $html = MarkdownRenderer::render('[click](javascript:alert(1))');

    expect($html)->not->toContain('javascript:');
});

it('does not render block-level elements like headings', function () {
    expect(MarkdownRenderer::render('# heading'))->not->toContain('<h1');
});
