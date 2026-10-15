<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Horloge fixée à 10 h 00 : les échéances (« aujourd’hui », « dépassé »)
        // ne dépendent plus de l'heure à laquelle la suite est lancée.
        $this->travelTo(now()->setTime(10, 0));
    }
}
