@extends('errors.layout')

@section('code', '503')
@section('title', 'Service indisponible')
@section('message')
    {{ isset($exception) && $exception->getMessage() ? $exception->getMessage() : 'L’application est en maintenance. Veuillez réessayer dans quelques minutes.' }}
@endsection
@section('noLink', true)
