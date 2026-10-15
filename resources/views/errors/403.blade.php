@extends('errors.layout')

@section('code', '403')
@section('title', 'Accès refusé')
@section('message')
    {{ in_array($exception->getMessage(), ['', 'Forbidden', 'This action is unauthorized.'], true)
        ? 'Vous n’avez pas les droits nécessaires pour accéder à cette page.'
        : $exception->getMessage() }}
@endsection
