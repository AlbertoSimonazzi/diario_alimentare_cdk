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

function strftime_it(string $data): string {
    $giorni = ['Domenica','Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
    $mesi   = ['gennaio','febbraio','marzo','aprile','maggio','giugno','luglio','agosto','settembre','ottobre','novembre','dicembre'];
    $ts = strtotime($data);
    if ($ts === false) return $data;
    return $giorni[(int)date('w', $ts)] . ' ' . (int)date('j', $ts) . ' ' . $mesi[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

function trova_alimento(array $libreria, string $categoria, string $nome): ?array {
    return $libreria[$categoria][$nome] ?? null;
}

$messaggio = '';
$tipo_messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';
    $dati = carica_dati();

    if ($azione === 'aggiungi') {
        $categoria = trim((string)($_POST['categoria'] ?? ''));
        $alimento  = trim((string)($_POST['alimento'] ?? ''));
        $grammi    = (float)($_POST['grammi'] ?? 0);
        $pasto     = trim((string)($_POST['pasto'] ?? ''));

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
                'data'      => $_POST['data'] ?? date('Y-m-d'),
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
            $dati[] = $voce;
            salva_dati($dati);
            $messaggio = 'Voce aggiunta al diario.';
            $tipo_messaggio = 'ok';
        }
    } elseif ($azione === 'elimina') {
        $id = $_POST['id'] ?? '';
        $dati = array_values(array_filter($dati, fn($v) => ($v['id'] ?? '') !== $id));
        salva_dati($dati);
        $messaggio = 'Voce eliminata.';
        $tipo_messaggio = 'ok';
    }

    header('Location: index.php?giorno=' . urlencode($_POST['data'] ?? date('Y-m-d'))
         . '&msg=' . urlencode($messaggio) . '&tipo=' . urlencode($tipo_messaggio));
    exit;
}

if (isset($_GET['msg'])) {
    $messaggio = (string)$_GET['msg'];
    $tipo_messaggio = (string)($_GET['tipo'] ?? '');
}

$dati = carica_dati();
$filtro_data = $_GET['giorno'] ?? date('Y-m-d');

$voci_giorno = array_values(array_filter($dati, fn($v) => ($v['data'] ?? '') === $filtro_data));
usort($voci_giorno, function ($a, $b) {
    $ord = array_flip(PASTI);
    return ($ord[$a['pasto']] ?? 99) <=> ($ord[$b['pasto']] ?? 99);
});

$tot_kcal = 0; $tot_prot = 0.0; $tot_grass = 0.0;
foreach ($voci_giorno as $v) {
    $tot_kcal  += (int)($v['kcal'] ?? 0);
    $tot_prot  += (float)($v['proteine'] ?? 0);
    $tot_grass += (float)($v['grassi'] ?? 0);
}
$rapporto_pg = $tot_grass > 0 ? round($tot_prot / $tot_grass, 2) : null;

$libreria_json = json_encode($LIBRERIA, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diario Alimentare Carnivoro</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Diario Alimentare Carnivoro</h1>
    <p>Categorie carnivore con macro precaricate · proteine, grassi, kcal</p>
</header>

<main>
    <section class="card">
        <h2>Nuova voce</h2>
        <?php if ($messaggio !== ''): ?>
            <div class="msg <?= htmlspecialchars($tipo_messaggio, ENT_QUOTES) ?>">
                <?= htmlspecialchars($messaggio, ENT_QUOTES) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="index.php" id="form-voce">
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

            <button type="submit" class="btn-primary">Aggiungi al diario</button>
        </form>
    </section>

    <section class="card">
        <h2>Giornata</h2>

        <form method="get" action="index.php" class="day-picker">
            <label for="giorno">Visualizza giorno</label>
            <input type="date" id="giorno" name="giorno" value="<?= htmlspecialchars($filtro_data, ENT_QUOTES) ?>" onchange="this.form.submit()">
        </form>

        <?php
            $giorno_prec = date('Y-m-d', strtotime($filtro_data . ' -1 day'));
            $giorno_succ = date('Y-m-d', strtotime($filtro_data . ' +1 day'));
        ?>
        <div class="nav-day">
            <a href="?giorno=<?= $giorno_prec ?>">&larr; <?= date('d/m', strtotime($giorno_prec)) ?></a>
            <a href="?giorno=<?= date('Y-m-d') ?>">Oggi</a>
            <a href="?giorno=<?= $giorno_succ ?>"><?= date('d/m', strtotime($giorno_succ)) ?> &rarr;</a>
        </div>

        <div class="day-title"><?= htmlspecialchars(strftime_it($filtro_data), ENT_QUOTES) ?></div>

        <div class="summary">
            <div class="stat kcal">
                <div class="stat-val"><?= $tot_kcal ?: '—' ?></div>
                <div class="stat-lbl">kcal</div>
            </div>
            <div class="stat prot">
                <div class="stat-val"><?= $tot_prot ? rtrim(rtrim(number_format($tot_prot, 1, '.', ''), '0'), '.') : '—' ?></div>
                <div class="stat-lbl">g proteine</div>
            </div>
            <div class="stat grass">
                <div class="stat-val"><?= $tot_grass ? rtrim(rtrim(number_format($tot_grass, 1, '.', ''), '0'), '.') : '—' ?></div>
                <div class="stat-lbl">g grassi</div>
            </div>
            <div class="stat">
                <div class="stat-val"><?= $rapporto_pg !== null ? number_format($rapporto_pg, 2, '.', '') : '—' ?></div>
                <div class="stat-lbl">P / G</div>
            </div>
        </div>

        <?php if (empty($voci_giorno)): ?>
            <div class="empty">Nessuna voce per questa giornata.</div>
        <?php else: ?>
            <?php
                $raggruppati = [];
                foreach ($voci_giorno as $v) {
                    $raggruppati[$v['pasto']][] = $v;
                }
            ?>
            <?php foreach (PASTI as $p): ?>
                <?php if (empty($raggruppati[$p])) continue; ?>
                <?php
                    $kcal_pasto  = array_sum(array_map(fn($v) => (int)($v['kcal'] ?? 0), $raggruppati[$p]));
                    $prot_pasto  = array_sum(array_map(fn($v) => (float)($v['proteine'] ?? 0), $raggruppati[$p]));
                    $grass_pasto = array_sum(array_map(fn($v) => (float)($v['grassi'] ?? 0), $raggruppati[$p]));
                ?>
                <div class="meal-group">
                    <div class="meal-header">
                        <span><?= htmlspecialchars($p, ENT_QUOTES) ?></span>
                        <span style="font-weight:400;font-size:.8rem;color:var(--muted)">
                            <?= $kcal_pasto ?> kcal ·
                            <?= number_format($prot_pasto, 1, '.', '') ?>P ·
                            <?= number_format($grass_pasto, 1, '.', '') ?>G
                        </span>
                    </div>
                    <?php foreach ($raggruppati[$p] as $v): ?>
                        <div class="meal-item">
                            <div class="meal-content">
                                <p class="meal-title"><?= htmlspecialchars($v['alimento'] ?? '', ENT_QUOTES) ?></p>
                                <p class="meal-sub">
                                    <?= htmlspecialchars($v['categoria'] ?? '', ENT_QUOTES) ?> ·
                                    <?= htmlspecialchars((string)($v['grammi'] ?? '?'), ENT_QUOTES) ?> g
                                </p>
                                <div class="meal-macros">
                                    <span class="m-kcal"><?= (int)($v['kcal'] ?? 0) ?> kcal</span>
                                    <span class="m-prot"><?= number_format((float)($v['proteine'] ?? 0), 1, '.', '') ?> P</span>
                                    <span class="m-grass"><?= number_format((float)($v['grassi'] ?? 0), 1, '.', '') ?> G</span>
                                </div>
                                <?php if (!empty($v['note'])): ?>
                                    <p class="meal-notes"><?= htmlspecialchars($v['note'], ENT_QUOTES) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="meal-right">
                                <form method="post" action="index.php" onsubmit="return confirm('Eliminare questa voce?');" style="display:inline">
                                    <input type="hidden" name="azione" value="elimina">
                                    <input type="hidden" name="id" value="<?= htmlspecialchars($v['id'] ?? '', ENT_QUOTES) ?>">
                                    <input type="hidden" name="data" value="<?= htmlspecialchars($filtro_data, ENT_QUOTES) ?>">
                                    <button type="submit" class="btn-del">elimina</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
