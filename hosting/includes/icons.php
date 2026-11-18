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
 'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4M17 3v4M3 10h18M8 14h2M14 14h2M8 18h2"/>',
 'tag'=>'<path d="M3 3h8l10 10-8 8L3 11V3Z"/><circle cx="7.5" cy="7.5" r="1"/>',
 'grid'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
 'chevron-down'=>'<path d="m6 9 6 6 6-6"/>',
 'mail'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
 'shield'=>'<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>',
 'document'=>'<path d="M14 3H5v18h14V8l-5-5Z"/><path d="M14 3v5h5M8 12h8M8 16h6"/>',
 'cookie'=>'<path d="M21 12a9 9 0 1 1-9-9 5 5 0 0 0 5 5 4 4 0 0 0 4 4Z"/><path d="M8 9h.01M7 15h.01M12 17h.01M13 12h.01"/>',
 'globe'=>'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
 'users'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3M16 5a3 3 0 0 1 0 6m1 3a5 5 0 0 1 4 5v2"/>',
 'captions'=>'<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M10 10H8v4h2m6-4h-2v4h2"/>',
 'clapper'=>'<path d="M3 10h18v11H3zM3 10l-1-4 17-4 1 4-17 4Zm4-5 3 3m4-5 3 3"/>',
 'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'
 ];
 return '<svg class="icon '.e($class).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['film']).'</svg>';
}
