<?php
// API route'ları: hepsi /api ile başlar, 'api' middleware grubundadır (CSRF yok).
// Dizi döndürmek JSON yanıt demektir. Modüller kendi API'lerini app/Modules/{Ad}/api.php içinde ekler.

Router::get('/ping', fn() => ['ok' => true, 'time' => date('c')]);
