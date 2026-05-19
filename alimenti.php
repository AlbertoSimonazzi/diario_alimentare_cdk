<?php
// Loader della libreria alimenti. La fonte di verità è data/alimenti.json,
// modificabile dalla pagina gestisci.php. Struttura: [categoria => [nome => [kcal, p, g]]].
// Valori espressi per 100 g.

const LIBRERIA_FILE = __DIR__ . '/data/alimenti.json';

function salva_libreria(array $libreria): bool {
    if (!is_dir(dirname(LIBRERIA_FILE))) {
        mkdir(dirname(LIBRERIA_FILE), 0775, true);
    }
    $json = json_encode($libreria, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents(LIBRERIA_FILE, $json, LOCK_EX) !== false;
}

if (is_file(LIBRERIA_FILE)) {
    $raw = file_get_contents(LIBRERIA_FILE);
    $dec = json_decode($raw, true);
    if (is_array($dec)) return $dec;
}

return [];
