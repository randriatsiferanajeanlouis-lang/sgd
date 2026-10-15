<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

/**
 * Règle de recherche commune à toutes les listes :
 * une seule lettre tapée → les valeurs qui commencent par cette lettre ;
 * deux caractères ou plus → les valeurs qui contiennent le texte.
 */
class Search
{
    /** Nombre de propositions affichées sous la barre de recherche. */
    public const SUGGESTIONS = 8;

    /**
     * Motif LIKE correspondant au texte tapé (les jokers % et _ saisis sont neutralisés).
     */
    public static function pattern(string $term): string
    {
        $term = trim($term);
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        return mb_strlen($term) === 1 ? $escaped.'%' : '%'.$escaped.'%';
    }

    /**
     * Propositions instantanées : les premiers résultats de la requête déjà
     * filtrée (et donc déjà limitée aux droits de l'utilisateur).
     *
     * @param  callable(Model): array{label: string, detail?: ?string, url: string}  $present
     */
    public static function suggestions(Builder $query, callable $present): JsonResponse
    {
        $results = $query->limit(self::SUGGESTIONS)->get();

        return response()->json([
            'results' => $results->map(fn (Model $model) => $present($model))->values(),
        ]);
    }

    public static function wantsSuggestions(): bool
    {
        return request()->boolean('suggest') && request()->filled('search');
    }
}
