<?php
function icon($name,$class='') {
 $paths=[
 'play'=>'<path d="m9 5 11 7-11 7V5Z"/>',
 'home'=>'<path d="m3 10 9-7 9 7v10H3V10Z"/><path d="M9 20v-7h6v7"/>',
 'film'=>'<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 3v18M17 3v18M3 8h4M3 16h4M17 8h4M17 16h4"/>',
 'tv'=>'<rect x="3" y="6" width="18" height="15" rx="3"/><path d="m8 2 4 4 4-4"/>',
 'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
 'heart'=>'<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
 'plus'=>'<path d="M12 5v14M5 12h14"/>','close'=>'<path d="m6 6 12 12M6 18 18 6"/>',
 'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>','arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>',
 'filter'=>'<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3"/><circle cx="15" cy="17" r="3"/>',
 'check'=>'<path d="m5 12 4 4L20 5"/>','star'=>'<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2L12 17.3l-5.6 2.9 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
 'chevron'=>'<path d="m9 5 7 7-7 7"/>','info'=>'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7v1"/>',
 'share'=>'<path d="M12 16V3m-5 5 5-5 5 5M5 13v8h14v-8"/>',
 'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'
 ];
 return '<svg class="icon '.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['film']).'</svg>';
}
