<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Anti double envoi : chaque formulaire d'envoi porte un jeton à usage unique
 * (champ « _submission »). Le premier envoi le consomme ; un second envoi du
 * même formulaire (double clic, page renvoyée) est refusé.
 * Cache::add est atomique : deux requêtes simultanées ne passent pas toutes les deux.
 */
class SubmissionGuard
{
    public static function alreadySubmitted(Request $request): bool
    {
        $token = (string) $request->input('_submission');

        if (! Str::isUuid($token)) {
            return false;
        }

        return ! Cache::add('submission:'.$request->user()->id.':'.$token, true, now()->addDay());
    }
}
