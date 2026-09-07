<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
            <!-- Required meta tags -->
         <meta charset="utf-8">
         <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
         <meta name="description" content="">    
         <meta name="author" content="Sebastian Manco Valencia">
         
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
        <title>Login</title>
    </head>
<body>
    <form action="POST">
        <div class="form-group">

            <!--- email-->

          <label for="email">Email address:</label>
          <input type="email" class="form-control" placeholder="Enter email" id="email">
        </div>

        <!---contraseña-->

        <div class="form-group">
          <label for="pwd">Password:</label>
          <input type="password" class="form-control" placeholder="Enter password" id="pwd">
        </div>
        <div class="form-group form-check">
          <label class="form-check-label">

            <!--recordar contraseña-->

            <input class="form-check-input" type="checkbox"> Remember me
          </label>
        </div>
        <button type="submit" class="btn btn-primary">Get in</button>
      </form>
</body>
</html>