<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$data['title']?></title>
    <link rel="stylesheet" href="<?=RUTA_CSS;?>bootstrap.css">
    
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon">
    <link rel="stylesheet" href="<?=RUTA_CSS;?>app.css">
</head>

<body>
    <div id="auth">
        
<div class="container">
    <div class="row">
        <div class="col-md-5 col-sm-12 mx-auto">
            <div class="card pt-4">
                <div class="card-body">
                <div class="text-center mb-3">
                        <img src="<?=RUTA_IMAGES;?>logo-clinica.png">
                    </div>         
                        <div class="form-group position-relative has-icon-left">
                            <div class="clearfix">
                                <label for="Pin">PIN de acceso</label>
                            </div>
                            <div class="position-relative">
                                <input type="password" class="form-control" id="Pin">
                                <div class="form-control-icon">
                                    <i data-feather="lock"></i>
                                </div>
                            </div>
                        </div>

                        <div class="clearfix">
                            <button class="btn btn-primary float-end" onclick="loginPaciente()">Entrar</button>
                        </div>   
                        
                        <div class="text-center" id="responseMessage"></div>
                </div>
            </div>
        </div>
    </div>
</div>

    </div>
    <script src="<?=RUTA_JS;?>feather-icons/feather.min.js"></script>
    <script src="<?=RUTA_JS;?>app.js"></script>    
    <script src="<?=RUTA_JS;?>main.js"></script>
    <script src="<?=RUTA_JS;?>login.js"></script>
</body>

</html>
