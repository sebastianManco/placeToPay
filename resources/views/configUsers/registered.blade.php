<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
                <!-- Required meta tags -->
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="description" content="">    
        <meta name="author" content="Sebastian Manco Valencia">
            
        @vite(['resources/sass/app.scss', 'resources/js/app.js'])
        <title>User Register</title>
    </head>
    <body>
        <div class="container">  
                     
            <h1>User Register</h1>
             <div class="row">
                 <div class="col-sm-6">
                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{url('home/registered')}}">

                        @csrf   
                        
                        <!---nombre--->
                        <div class="form-group">
                            <label for="name"> Full Name </label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="" value="{{old('name')}}">
                            @error('name')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <!---Apellido--->
                        <div class="form-group">
                            <label for="lastName"> Last Name</label>
                            <input type="text" class="form-control" id="lastName" name="lastName" placeholder="" value="{{old('lastName')}}">
                            @error('lastName')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <!---email-->
                        <div class="form-group">
                            <label for="email"> Email</label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="" value="{{old('email')}}">
                            @error('email')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <!---telefono-->
                        <div class="form-group">
                            <label for="phone">phone number</label>
                            <input type="text" class="form-control" id="phone" name="phone" placeholder="" value="{{old('phone')}}">
                            @error('phone')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <!---direccion-->
                        <div class="form-group">
                            <label for="direction"> Direction</label>
                            <input type="text" class="form-control" id="direction" name="direction" placeholder="" value="{{old('direction')}}">
                            @error('direction')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>
                        
                        <!--tipo de identificacion-->
                        <label for="cedula"> Type of identification document</label>
                        <div class="form-check-inline">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" value="">C.C
                            </label>
                        </div>
                        <div class="form-check-inline">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" value="">T.E
                            </label>
                        </div>
                        <div class="form-check-inline">
                            <label class="form-check-label">
                                <input type="checkbox" class="form-check-input" value="" >T.I
                            </label>
                        </div>

                        <!---tipo de documento--->
                        <div class="form-group">
                            <label for="identification"> Nº Identification</label>
                            <input type="text" class="form-control" id="identification" name="identification" placeholder="" value="{{old('identification', old('Identification'))}}">
                            @error('identification')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div> 

                        <!---usuario--->
                        <div class="form-group">
                            <label for="userName"> User Name</label>
                            <input type="text" class="form-control" id="userName" name="userName" placeholder="" value="{{old('userName')}}">
                            @error('userName')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>         

                        <!---contraseña--->
                        <div class="form-group">
                            <label for="password"> Password</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="" autocomplete="new-password">
                            @error('password')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <!---confirma contraseña--->
                        <div class="form-group">
                            <label for="password_confirmation"> Confirm Password</label>
                            <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="" autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-primary"> save</button>
                        <button type="button" class="btn btn-secondary">Cancel</button>
                    </form>
                </div>   
            </div>

        </div>

    </body>
</html>