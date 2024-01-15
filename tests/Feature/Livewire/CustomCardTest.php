<?php

use Illuminate\Contracts\Support\Htmlable;
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

it('loads custom css using a HtmlString', function () {
    Livewire::test(CustomCardWithCssHtmlString::class)
        ->assertOk();

    $css = Pulse::css();

    expect($css)->toContain('<link rel="stylesheet" src="https://example.com/cdn/custom-card.css">');
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

class CustomCardWithCssHtmlString extends Card
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

class CustomCardWithCssHtmlable extends Card
{
    public function render()
    {
        return '<div></div>';
    }

    protected function css()
    {
        return new class implements Htmlable
        {
            public function toHtml()
            {
                return '<link rel="stylesheet" src="https://example.com/cdn/custom-card.css">';
            }
        };
    }
}
