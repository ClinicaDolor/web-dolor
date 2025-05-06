<script type="text/javascript">
$(document).ready(function () {
  $("#search").keyup(function () {
    let searchText = $(this).val();

    if (searchText != "") {
      $.ajax({
        url: "buscar/web",
        method: "get",
        data: {
          query: searchText,
        },
        success: function (response) {

          $("#show-list").html(response);
        },
      });
    } else {
      $("#show-list").html("");
    }
  });

});

document.addEventListener("click", (e) => {
  $("#show-list").html(""); 
$("#search").val(""); 
});

function Limpiar(){
$("#show-list").html(""); 
$("#search").val(""); 
}
</script>
<div class="fixed-top">
<header>
      <nav class="navbar-menu">
        <div class="branding">
         <img src="<?=RUTA_IMAGES;?>logo-clinica.png" class="navbar-logo">
        </div>
        <label for="input-hamburger" class="hamburger "></label>
        <input type="checkbox" id="input-hamburger" hidden>
        <ul class="menu">
          <li><a href="<?=LINK_HOME;?>" class="menu-link">Home</a></li>

          <li><a href="<?=LINK_QUIENES_SOMOS;?>" class="menu-link">¿Quiénes somos?</a></li>
          <li><a href="<?=LINK_CURSOS;?>" class="menu-link">Cursos</a></li>
          <li><a href="" class="menu-link">Cuidados paliativos</a></li>

          <li class="has-dropdown">
            <a class="menu-link">Dolor
              <span class="arrow"></span>
            </a>
            <ul class="submenu">
               <li><a href="<?=LINK_QUEES_DOLOR;?>" class="menu-link">¿Qué es el dolor?</a></li>
               <li><a href="<?=LINK_TIPOS_DOLOR;?>" class="menu-link">Enfermedades que causan dolor</a></li>
               <li><a href="<?=LINK_DOLOR_AGUDO;?>" class="menu-link">Dolor agudo</a></li>               
               <li><a href="<?=LINK_DOLOR_CRONICO;?>" class="menu-link">Dolor cronico</a></li>
               <li><a href="<?=LINK_DOLOR_PERIOPERATORIO;?>" class="menu-link">Dolor perioperatorio</a></li>
               <li><a href="<?=LINK_EVALUACION_DOLOR;?>" class="menu-link">Evaluación del dolor</a></li>               
            </ul>
          </li>
          <li><a href="<?=LINK_CUIDADORES;?>" class="menu-link">Cuidadores</a></li>
          <li class="has-dropdown">
            <a class="menu-link">Tratamientos
              <span class="arrow"></span>
            </a>
            <ul class="submenu">
               <li><a href="<?=LINK_INYECCION_EPIDURAL;?>" class="menu-link">Inyección epidural</a></li>
               <li><a href="<?=LINK_NEUROESTIMULACION;?>" class="menu-link">Neuroestimulación</a></li>
               <li><a href="<?=LINK_TRATAMIENTO_OPIOIDES;?>" class="menu-link">Morfina y otros opioides</a></li>
                 
            </ul>
          </li>
           <li><a href="#ubicacion-contacto" class="menu-link">Ubicación y Contacto</a></li>
          <li><a href="<?=LINK_PACIENTE_ACCESO;?>" class="menu-link">Paciente</a></li>
        </ul>
      </nav>

      </header>
      
<div style="border-bottom: 3px solid #97C586;">
<input type="text" class="form-control border-0 rounded-0 fs-4 fw-light bg-light" id="search" name="search" placeholder="Buscar..." autocomplete="off">
</div>
<div class="col-md-5" style="position: absolute;">
<div id="show-list"></div>
</div>

</div>