<?php
namespace App\Helpers;
class Sidebar{

    private $list = [];
    private $activeItem = '';

    public function addItemList($titulo, $url, $icono = '')
    {
        $this->list[] = [
            'titulo' => $titulo,
            'url' => $url,
            'icono' => $icono
        ];
    }

    public function setActivarItem($title)
    {
        $this->activeItem = $title;
    }

    public function render()
    {
        $html = '<div id="sidebar" class="active" aria-label="Navegación principal">
        <div class="sidebar-wrapper active">
        <div class="sidebar-header">
        <img src="'.RUTA_IMAGES.'logo-clinica.png" alt="Clínica del Dolor">
        </div>
        <div class="sidebar-menu">
           <ul class="menu">
           <li class="sidebar-title">Menu</li>';

           foreach ($this->list as $item) {

            $activeClass = ($item['titulo'] == $this->activeItem) ? 'active' : '';
            $currentAttribute = ($activeClass === 'active') ? ' aria-current="page"' : '';

            $html .= '<li class="sidebar-item '.$activeClass.'">
            <a href="'.$item['url'].'" class="sidebar-link" aria-label="'.htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8').'"'.$currentAttribute.'>
                <i data-feather="'.$item['icono'].'" width="20"></i> 
                <span>'.htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8').'</span>
            </a>
        </li> ';
           }

        $html .= '</ul>
        </div>
        <button class="sidebar-toggler btn x" type="button" aria-label="Cerrar menú"><i data-feather="x"></i></button>
        </div>
    </div>';
      
        return $html;
    }
}
