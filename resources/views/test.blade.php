<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">
        <meta name="author" content="Sebastian Manco Valencia">
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
        <title>{{ $title }}</title>
    </head>
    <body>
        <div class="container mt-4">
            <h1>{{ $title }}</h1>
        </div>
    </body>
</html>
