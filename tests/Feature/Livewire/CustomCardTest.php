<?php

use Illuminate\Support\HtmlString;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Card;
use Livewire\Livewire;

it('loads custom css using a path', function () {
    Livewire::test(CustomCardWithCssPath::class)
        ->assertOk();

    $css = Pulse::css();

    expect($css)->toContain(<<<'HTML'
        <style>.custom-class {
            color: purple;
        }
        </style>
        HTML);
});

it('loads custom css using a Htmlable', function () {
    Livewire::test(CustomCardWithCssHtmlable::class)
        ->assertOk();

    $css = Pulse::css();

    expect($css)->toContain('<link rel="stylesheet" src="https://example.com/cdn/custom-card.css">');
});

class CustomCardWithCssPath extends Card
{
    public function render()
    {
        return '<div></div>';
    }

    protected function css()
    {
        return __DIR__.'/../../fixtures/custom.css';
    }
}

class CustomCardWithCssHtmlable extends Card
{
    public function render()
    {
        return '<div></div>';
    }

    protected function css()
    {
        return new HtmlString('<link rel="stylesheet" src="https://example.com/cdn/custom-card.css">');
    }
}
