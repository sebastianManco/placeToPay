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
                <form method="POST" action="{{url('home/registered')}}">

                       @csrf   
                        
                          <!---nombre--->
                      <div class="form-group">
                            <label for="name"> Full Name </label>
                            <input type="text" class="form-control" name="name" placeholder="" value="{{old('name')}}">
                     </div>

                            <!---Apellido--->

                      <div class="form-group">
                            <label for="lastName"> Last Name</label>
                            <input type="text" class="form-control" name="lastName" placeholder="" value="{{old('lastName')}}">
                      </div>

                            <!---email-->

                     <div class="form-group">
                            <label for="email"> Email</label>
                            <input type="text" class="form-control" name="email" placeholder="" value="{{old('email')}}">
                     </div>

                            <!---telefono-->

                       <div class="form-group">
                            <label for="phone">phone number</label>
                            <input type="text" class="form-control" name="phone" placeholder="" value="{{old('phone')}}">
                     </div>
                            <!---direccion-->

                     <div class="form-group">
                            <label for="direction"> Direction</label>
                            <input type="text" class="form-control" name="direction" placeholder="" value="{{old('direction')}}">
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
                            <label for="Document"> Nº Identification</label>
                                <input type="text" class="form-control" name="Identification" placeholder="" value="{{old('Identification')}}">
                        </div> 
                            <!---usuario--->
                        <div class="form-group">
                            <label for="userName"> User Name</label>
                            <input type="text" class="form-control" name="userName" placeholder="" value="{{old('userName')}}">
                        </div>         
                            <!---contraseña--->

                        <div class="form-group">
                            <label for="password"> Password</label>
                            <input type="password" class="form-control" name="password" placeholder="" value="{{old('password')}}">
                        </div>
                            <!---confirma contraseña--->

                        <div class="form-group">
                            <label for="confirmPassword"> Confirm Password</label>
                            <input type="password" class="form-control" name="confirmPassword" placeholder="" value="{{old('confirmPassword')}}">
                        </div>

                        <button type="submit" class="btn btn-primary"> save</button>
                        <button type="button" class="btn btn-secondary">Cancel</button>
                    
                
                    </form>
                </div>   
            </div>

        </div>

    </body>
</html>