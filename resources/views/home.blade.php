<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">    
    <meta name="author" content="Sebastian Manco Valencia">
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])
      <title>Home</title>

        <!-- Styles -->
        <style>

        </style>
    </head>
    <body>
        <header>
            <div class="container">
                <h1>MercaTodo</h1>
                <nav class="navbar navbar-expand-sm bg-light justify-content-end">
    
                    <ul class="nav justify-content-end">
                    <li class="nav-item">
                        <a class="nav-link active" href="/home/login">login</a>
                    </li>
                    <li class="nav-item">
                            <a class="nav-link active" href="/home/register">Register</a>
                       
                    </li>
                    </ul>   
                </nav>   
            </div>

        </header>

    </body>
</html>
