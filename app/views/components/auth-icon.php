<?php
$authIcon = (string)($authIcon ?? '');
$authIcons = [
    'user' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 21a8 8 0 0 0-16 0"></path><circle cx="12" cy="7" r="4"></circle></svg>',
    'users' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
    'lock' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>',
    'arrow-right' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>',
    'shield' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>',
    'file-text' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line></svg>',
    'activity' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>',
    'stethoscope' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 3v5a6 6 0 0 0 12 0V3"></path><path d="M4 3h4M16 3h4"></path><path d="M18 14a4 4 0 1 0 4 4"></path><circle cx="22" cy="18" r="1"></circle></svg>',
];
echo $authIcons[$authIcon] ?? '';
