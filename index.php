<?php
declare(strict_types=1);

const DATA_FILE = __DIR__ . '/data/diario.json';
const PASTI = ['Colazione', 'Spuntino mattina', 'Pranzo', 'Spuntino pomeriggio', 'Cena', 'Dopo cena'];

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

$messaggio = '';
$tipo_messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['azione'] ?? '') === 'elimina') {
    $id = $_POST['id'] ?? '';
    $dati = carica_dati();
    $dati = array_values(array_filter($dati, fn($v) => ($v['id'] ?? '') !== $id));
    salva_dati($dati);
    header('Location: index.php?giorno=' . urlencode($_POST['data'] ?? date('Y-m-d'))
         . '&msg=' . urlencode('Voce eliminata.') . '&tipo=ok');
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
    <nav class="header-nav">
        <a href="nuova.php?giorno=<?= htmlspecialchars($filtro_data, ENT_QUOTES) ?>" class="header-link header-link-primary">+ Nuova voce</a>
        <a href="gestisci.php" class="header-link">Gestisci libreria &rarr;</a>
    </nav>
</header>

<main class="single">
    <section class="card">
        <h2>Giornata</h2>

        <?php if ($messaggio !== ''): ?>
            <div class="msg <?= htmlspecialchars($tipo_messaggio, ENT_QUOTES) ?>">
                <?= htmlspecialchars($messaggio, ENT_QUOTES) ?>
            </div>
        <?php endif; ?>

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
            <div class="empty">
                Nessuna voce per questa giornata.<br>
                <a href="nuova.php?giorno=<?= htmlspecialchars($filtro_data, ENT_QUOTES) ?>" class="btn-link" style="margin-top:.75rem;display:inline-block">+ Aggiungi la prima voce</a>
            </div>
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

</body>
</html>
