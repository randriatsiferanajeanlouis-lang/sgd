<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Horloge fixée à 10 h 00 : les échéances (« aujourd’hui », « dépassé »)
        // ne dépendent plus de l'heure à laquelle la suite est lancée.
        $this->travelTo(now()->setTime(10, 0));

        // Pages React : le texte affiché est dans les données envoyées à la page
        // (props Inertia), et non dans le HTML rendu par le serveur.
        TestResponse::macro('assertPropsSee', function (string ...$texts) {
            $props = json_encode($this->inertiaProps(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($texts as $text) {
                Assert::assertStringContainsString($text, $props, "Texte absent de la page React : {$text}");
            }

            return $this;
        });
        TestResponse::macro('assertPropsDontSee', function (string ...$texts) {
            $props = json_encode($this->inertiaProps(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            foreach ($texts as $text) {
                Assert::assertStringNotContainsString($text, $props, "Texte présent dans la page React : {$text}");
            }

            return $this;
        });
    }
}
