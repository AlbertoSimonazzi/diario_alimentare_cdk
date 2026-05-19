<?php
declare(strict_types=1);

const DATA_FILE = __DIR__ . '/data/diario.json';
const PASTI = ['Colazione', 'Spuntino mattina', 'Pranzo', 'Spuntino pomeriggio', 'Cena', 'Dopo cena'];

$LIBRERIA = require __DIR__ . '/alimenti.php';

function carica_dati(): array {
    if (!is_file(DATA_FILE)) return [];
    $raw = file_get_contents(DATA_FILE);
    if ($raw === false || $raw === '') return [];
    $dati = json_decode($raw, true);
    return is_array($dati) ? $dati : [];
}

function salva_dati(array $dati): void {
    if (!is_dir(dirname(DATA_FILE))) {
        mkdir(dirname(DATA_FILE), 0775, true);
    }
    file_put_contents(DATA_FILE, json_encode($dati, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function trova_alimento(array $libreria, string $categoria, string $nome): ?array {
    return $libreria[$categoria][$nome] ?? null;
}

$messaggio = '';
$tipo_messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'aggiungi') {
    $categoria = trim((string)($_POST['categoria'] ?? ''));
    $alimento  = trim((string)($_POST['alimento'] ?? ''));
    $grammi    = (float)($_POST['grammi'] ?? 0);
    $pasto     = trim((string)($_POST['pasto'] ?? ''));
    $data_in   = (string)($_POST['data'] ?? date('Y-m-d'));

    $info = trova_alimento($LIBRERIA, $categoria, $alimento);

    if (!in_array($pasto, PASTI, true)) {
        $messaggio = 'Tipo di pasto non valido.';
        $tipo_messaggio = 'errore';
    } elseif ($info === null) {
        $messaggio = 'Alimento non riconosciuto.';
        $tipo_messaggio = 'errore';
    } elseif ($grammi <= 0) {
        $messaggio = 'Inserisci una quantità in grammi maggiore di zero.';
        $tipo_messaggio = 'errore';
    } else {
        $fattore = $grammi / 100.0;
        $voce = [
            'id'        => bin2hex(random_bytes(6)),
            'data'      => $data_in,
            'pasto'     => $pasto,
            'categoria' => $categoria,
            'alimento'  => $alimento,
            'grammi'    => round($grammi, 1),
            'kcal'      => (int)round($info['kcal'] * $fattore),
            'proteine'  => round($info['p'] * $fattore, 1),
            'grassi'    => round($info['g'] * $fattore, 1),
            'note'      => trim((string)($_POST['note'] ?? '')),
            'creato_il' => date('c'),
        ];
        $dati = carica_dati();
        $dati[] = $voce;
        salva_dati($dati);
        header('Location: index.php?giorno=' . urlencode($data_in)
             . '&msg=' . urlencode('Voce aggiunta al diario.') . '&tipo=ok');
        exit;
    }

    // errore di validazione → torna al form con messaggio
    header('Location: nuova.php?giorno=' . urlencode($data_in)
         . '&msg=' . urlencode($messaggio) . '&tipo=' . urlencode($tipo_messaggio));
    exit;
}

if (isset($_GET['msg'])) {
    $messaggio = (string)$_GET['msg'];
    $tipo_messaggio = (string)($_GET['tipo'] ?? '');
}

$filtro_data = $_GET['giorno'] ?? date('Y-m-d');
$libreria_json = json_encode($LIBRERIA, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nuova voce · Diario Alimentare Carnivoro</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Nuova voce</h1>
    <p>Aggiungi un alimento al diario</p>
    <nav class="header-nav">
        <a href="index.php?giorno=<?= htmlspecialchars($filtro_data, ENT_QUOTES) ?>" class="header-link">&larr; Torna al diario</a>
        <a href="gestisci.php" class="header-link">Gestisci libreria &rarr;</a>
    </nav>
</header>

<main class="single">
    <section class="card">
        <?php if ($messaggio !== ''): ?>
            <div class="msg <?= htmlspecialchars($tipo_messaggio, ENT_QUOTES) ?>">
                <?= htmlspecialchars($messaggio, ENT_QUOTES) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="nuova.php" id="form-voce">
            <input type="hidden" name="azione" value="aggiungi">

            <label for="data">Data</label>
            <input type="date" id="data" name="data" value="<?= htmlspecialchars($filtro_data, ENT_QUOTES) ?>" required>

            <label for="pasto">Pasto</label>
            <select id="pasto" name="pasto" required>
                <?php foreach (PASTI as $p): ?>
                    <option value="<?= htmlspecialchars($p, ENT_QUOTES) ?>"><?= htmlspecialchars($p, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="categoria">Categoria</label>
            <select id="categoria" name="categoria" required>
                <option value="">-- scegli categoria --</option>
                <?php foreach (array_keys($LIBRERIA) as $cat): ?>
                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES) ?>"><?= htmlspecialchars($cat, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="alimento">Alimento</label>
            <select id="alimento" name="alimento" required disabled>
                <option value="">-- prima scegli la categoria --</option>
            </select>

            <label for="grammi">Quantità (g)</label>
            <input type="number" id="grammi" name="grammi" min="1" max="3000" step="1" placeholder="es. 200" required>

            <div class="preview" id="preview">
                <b>Valori stimati:</b> <span id="prev-kcal">—</span> kcal ·
                <span id="prev-prot">—</span> g prot ·
                <span id="prev-grass">—</span> g grassi
            </div>

            <label for="note">Note (opzionale)</label>
            <textarea id="note" name="note" placeholder="Es. cottura al sangue, post allenamento"></textarea>

            <div class="form-buttons">
                <a href="index.php?giorno=<?= htmlspecialchars($filtro_data, ENT_QUOTES) ?>" class="btn-link">Annulla</a>
                <button type="submit" class="btn-primary">Aggiungi al diario</button>
            </div>
        </form>
    </section>
</main>

<script>
const LIBRERIA = <?= $libreria_json ?>;

const selCategoria = document.getElementById('categoria');
const selAlimento  = document.getElementById('alimento');
const inpGrammi    = document.getElementById('grammi');
const preview      = document.getElementById('preview');
const prevKcal     = document.getElementById('prev-kcal');
const prevProt     = document.getElementById('prev-prot');
const prevGrass    = document.getElementById('prev-grass');

function popolaAlimenti() {
    const cat = selCategoria.value;
    selAlimento.innerHTML = '';
    if (!cat || !LIBRERIA[cat]) {
        selAlimento.disabled = true;
        const opt = document.createElement('option');
        opt.value = '';
        opt.textContent = '-- prima scegli la categoria --';
        selAlimento.appendChild(opt);
        return;
    }
    selAlimento.disabled = false;
    const optDefault = document.createElement('option');
    optDefault.value = '';
    optDefault.textContent = '-- scegli alimento --';
    selAlimento.appendChild(optDefault);
    for (const nome of Object.keys(LIBRERIA[cat])) {
        const info = LIBRERIA[cat][nome];
        const opt = document.createElement('option');
        opt.value = nome;
        opt.textContent = nome + ' (' + info.kcal + ' kcal / 100g)';
        selAlimento.appendChild(opt);
    }
    aggiornaAnteprima();
}

function aggiornaAnteprima() {
    const cat  = selCategoria.value;
    const nome = selAlimento.value;
    const g    = parseFloat(inpGrammi.value || '0');
    if (!cat || !nome || !LIBRERIA[cat] || !LIBRERIA[cat][nome] || !g) {
        preview.classList.remove('visible');
        return;
    }
    const info = LIBRERIA[cat][nome];
    const f = g / 100;
    prevKcal.textContent  = Math.round(info.kcal * f);
    prevProt.textContent  = (info.p * f).toFixed(1);
    prevGrass.textContent = (info.g * f).toFixed(1);
    preview.classList.add('visible');
}

selCategoria.addEventListener('change', popolaAlimenti);
selAlimento.addEventListener('change', aggiornaAnteprima);
inpGrammi.addEventListener('input', aggiornaAnteprima);
</script>

</body>
</html>
