<?php
function uiIcon($name, $class = '') {
    static $icons = ['cross'=>'medical-cross','dashboard'=>'layout-dashboard','patients'=>'users','doctor'=>'stethoscope','calendar'=>'calendar','department'=>'building-hospital','report'=>'chart-bar','history'=>'clipboard-text','logout'=>'logout','arrow'=>'arrow-right','menu'=>'menu-2'];
    $file = $icons[$name] ?? $icons['cross'];
    $svg = file_get_contents(__DIR__ . '/../assets/vendor/tabler-icons/' . $file . '.svg');
    $svg = preg_replace('/class="[^"]*"/', 'class="icon ' . h($class) . '"', $svg, 1);
    return preg_replace('/<svg\b/', '<svg aria-hidden="true"', $svg, 1);
}
