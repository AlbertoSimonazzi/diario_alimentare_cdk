<?php
declare(strict_types=1);

$LIBRERIA = require __DIR__ . '/alimenti.php';

function valida_macro($v): ?float {
    if ($v === '' || $v === null) return null;
    if (!is_numeric($v)) return null;
    $f = (float)$v;
    return $f >= 0 ? $f : null;
}

function redirect_msg(string $msg, string $tipo, ?string $apri = null): void {
    $url = 'gestisci.php?msg=' . urlencode($msg) . '&tipo=' . urlencode($tipo);
    if ($apri !== null) $url .= '&apri=' . urlencode($apri);
    header('Location: ' . $url);
    exit;
}

$messaggio = '';
$tipo_messaggio = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $azione = $_POST['azione'] ?? '';

    if ($azione === 'cat_aggiungi') {
        $nome = trim((string)($_POST['nome'] ?? ''));
        if ($nome === '') {
            redirect_msg('Il nome della categoria non può essere vuoto.', 'errore');
        }
        if (isset($LIBRERIA[$nome])) {
            redirect_msg('Categoria già esistente.', 'errore');
        }
        $LIBRERIA[$nome] = [];
        salva_libreria($LIBRERIA);
        redirect_msg('Categoria "' . $nome . '" creata.', 'ok', $nome);
    }

    elseif ($azione === 'cat_rinomina') {
        $vecchio = (string)($_POST['vecchio'] ?? '');
        $nuovo   = trim((string)($_POST['nuovo'] ?? ''));
        if (!isset($LIBRERIA[$vecchio])) {
            redirect_msg('Categoria non trovata.', 'errore');
        }
        if ($nuovo === '') {
            redirect_msg('Il nuovo nome non può essere vuoto.', 'errore', $vecchio);
        }
        if ($nuovo === $vecchio) {
            redirect_msg('Nessuna modifica.', 'ok', $vecchio);
        }
        if (isset($LIBRERIA[$nuovo])) {
            redirect_msg('Esiste già una categoria con questo nome.', 'errore', $vecchio);
        }
        $nuova = [];
        foreach ($LIBRERIA as $k => $v) {
            $nuova[$k === $vecchio ? $nuovo : $k] = $v;
        }
        $LIBRERIA = $nuova;
        salva_libreria($LIBRERIA);
        redirect_msg('Categoria rinominata in "' . $nuovo . '".', 'ok', $nuovo);
    }

    elseif ($azione === 'cat_elimina') {
        $nome = (string)($_POST['nome'] ?? '');
        if (!isset($LIBRERIA[$nome])) {
            redirect_msg('Categoria non trovata.', 'errore');
        }
        unset($LIBRERIA[$nome]);
        salva_libreria($LIBRERIA);
        redirect_msg('Categoria "' . $nome . '" eliminata.', 'ok');
    }

    elseif ($azione === 'alim_aggiungi') {
        $cat  = (string)($_POST['categoria'] ?? '');
        $nome = trim((string)($_POST['nome'] ?? ''));
        $kcal = valida_macro($_POST['kcal'] ?? '');
        $p    = valida_macro($_POST['p'] ?? '');
        $g    = valida_macro($_POST['g'] ?? '');
        if (!isset($LIBRERIA[$cat])) {
            redirect_msg('Categoria non trovata.', 'errore');
        }
        if ($nome === '') {
            redirect_msg('Nome alimento mancante.', 'errore', $cat);
        }
        if ($kcal === null || $p === null || $g === null) {
            redirect_msg('Tutti i valori (kcal, proteine, grassi) devono essere numeri ≥ 0.', 'errore', $cat);
        }
        if (isset($LIBRERIA[$cat][$nome])) {
            redirect_msg('Alimento già presente in questa categoria.', 'errore', $cat);
        }
        $LIBRERIA[$cat][$nome] = ['kcal' => (int)round($kcal), 'p' => $p, 'g' => $g];
        salva_libreria($LIBRERIA);
        redirect_msg('Alimento "' . $nome . '" aggiunto.', 'ok', $cat);
    }

    elseif ($azione === 'alim_modifica') {
        $cat       = (string)($_POST['categoria'] ?? '');
        $nome_orig = (string)($_POST['nome_orig'] ?? '');
        $nome      = trim((string)($_POST['nome'] ?? ''));
        $kcal = valida_macro($_POST['kcal'] ?? '');
        $p    = valida_macro($_POST['p'] ?? '');
        $g    = valida_macro($_POST['g'] ?? '');
        if (!isset($LIBRERIA[$cat][$nome_orig])) {
            redirect_msg('Alimento non trovato.', 'errore', $cat);
        }
        if ($nome === '') {
            redirect_msg('Nome alimento mancante.', 'errore', $cat);
        }
        if ($kcal === null || $p === null || $g === null) {
            redirect_msg('Tutti i valori devono essere numeri ≥ 0.', 'errore', $cat);
        }
        if ($nome !== $nome_orig && isset($LIBRERIA[$cat][$nome])) {
            redirect_msg('Esiste già un alimento con questo nome nella categoria.', 'errore', $cat);
        }
        $nuova = [];
        foreach ($LIBRERIA[$cat] as $k => $v) {
            if ($k === $nome_orig) {
                $nuova[$nome] = ['kcal' => (int)round($kcal), 'p' => $p, 'g' => $g];
            } else {
                $nuova[$k] = $v;
            }
        }
        $LIBRERIA[$cat] = $nuova;
        salva_libreria($LIBRERIA);
        redirect_msg('Alimento aggiornato.', 'ok', $cat);
    }

    elseif ($azione === 'alim_elimina') {
        $cat  = (string)($_POST['categoria'] ?? '');
        $nome = (string)($_POST['nome'] ?? '');
        if (!isset($LIBRERIA[$cat][$nome])) {
            redirect_msg('Alimento non trovato.', 'errore', $cat);
        }
        unset($LIBRERIA[$cat][$nome]);
        salva_libreria($LIBRERIA);
        redirect_msg('Alimento "' . $nome . '" eliminato.', 'ok', $cat);
    }

    redirect_msg('Azione non riconosciuta.', 'errore');
}

if (isset($_GET['msg'])) {
    $messaggio = (string)$_GET['msg'];
    $tipo_messaggio = (string)($_GET['tipo'] ?? '');
}

$apri = (string)($_GET['apri'] ?? '');

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gestione libreria · Diario Alimentare</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header>
    <h1>Gestione libreria alimenti</h1>
    <p>Aggiungi, modifica ed elimina categorie e alimenti. Valori per 100 g.</p>
    <nav class="header-nav">
        <a href="index.php" class="header-link">&larr; Torna al diario</a>
        <a href="nuova.php" class="header-link header-link-primary">+ Nuova voce</a>
    </nav>
</header>

<main class="gestisci">

    <?php if ($messaggio !== ''): ?>
        <div class="msg <?= h($tipo_messaggio) ?>">
            <?= h($messaggio) ?>
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Nuova categoria</h2>
        <form method="post" action="gestisci.php" class="form-inline">
            <input type="hidden" name="azione" value="cat_aggiungi">
            <input type="text" name="nome" placeholder="Nome categoria" required maxlength="80">
            <button type="submit" class="btn-primary">Aggiungi categoria</button>
        </form>
    </section>

    <?php if (empty($LIBRERIA)): ?>
        <div class="empty">Nessuna categoria. Aggiungine una qui sopra.</div>
    <?php else: ?>
        <?php foreach ($LIBRERIA as $cat => $alimenti): ?>
            <?php $aperta = ($apri === $cat); ?>
            <section class="card categoria">
                <div class="cat-header">
                    <h2><?= h($cat) ?> <span class="cat-count">(<?= count($alimenti) ?>)</span></h2>
                    <div class="cat-actions">
                        <details<?= $aperta ? ' open' : '' ?>>
                            <summary>Rinomina</summary>
                            <form method="post" action="gestisci.php" class="form-inline">
                                <input type="hidden" name="azione" value="cat_rinomina">
                                <input type="hidden" name="vecchio" value="<?= h($cat) ?>">
                                <input type="text" name="nuovo" value="<?= h($cat) ?>" required maxlength="80">
                                <button type="submit">Salva</button>
                            </form>
                        </details>
                        <form method="post" action="gestisci.php" onsubmit="return confirm('Eliminare la categoria <?= h($cat) ?> e tutti i suoi alimenti?\n(Le voci già nel diario restano intatte.)');" style="display:inline">
                            <input type="hidden" name="azione" value="cat_elimina">
                            <input type="hidden" name="nome" value="<?= h($cat) ?>">
                            <button type="submit" class="btn-del">Elimina categoria</button>
                        </form>
                    </div>
                </div>

                <div class="alim-list">
                    <div class="alim-row alim-head">
                        <div>Nome</div>
                        <div class="num">kcal</div>
                        <div class="num">prot</div>
                        <div class="num">grassi</div>
                        <div>Azioni</div>
                    </div>

                    <?php if (empty($alimenti)): ?>
                        <div class="empty-row">Nessun alimento in questa categoria.</div>
                    <?php else: ?>
                        <?php foreach ($alimenti as $nome => $info): ?>
                            <?php
                                $form_id = 'edit-' . md5($cat . '|' . $nome);
                                $del_id  = 'del-'  . md5($cat . '|' . $nome);
                            ?>
                            <div class="alim-row">
                                <form id="<?= $form_id ?>" method="post" action="gestisci.php"></form>
                                <form id="<?= $del_id ?>" method="post" action="gestisci.php"
                                      onsubmit="return confirm('Eliminare <?= h(addslashes($nome)) ?>?');"></form>

                                <input form="<?= $form_id ?>" type="hidden" name="azione" value="alim_modifica">
                                <input form="<?= $form_id ?>" type="hidden" name="categoria" value="<?= h($cat) ?>">
                                <input form="<?= $form_id ?>" type="hidden" name="nome_orig" value="<?= h($nome) ?>">

                                <input form="<?= $del_id ?>" type="hidden" name="azione" value="alim_elimina">
                                <input form="<?= $del_id ?>" type="hidden" name="categoria" value="<?= h($cat) ?>">
                                <input form="<?= $del_id ?>" type="hidden" name="nome" value="<?= h($nome) ?>">

                                <div><input form="<?= $form_id ?>" type="text" name="nome" value="<?= h($nome) ?>" required maxlength="120"></div>
                                <div class="num"><input form="<?= $form_id ?>" type="number" name="kcal" value="<?= h((string)($info['kcal'] ?? 0)) ?>" min="0" step="1" required></div>
                                <div class="num"><input form="<?= $form_id ?>" type="number" name="p" value="<?= h((string)($info['p'] ?? 0)) ?>" min="0" step="0.1" required></div>
                                <div class="num"><input form="<?= $form_id ?>" type="number" name="g" value="<?= h((string)($info['g'] ?? 0)) ?>" min="0" step="0.1" required></div>
                                <div class="row-actions">
                                    <button form="<?= $form_id ?>" type="submit">Salva</button>
                                    <button form="<?= $del_id ?>" type="submit" class="btn-del">Elimina</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php $add_id = 'add-' . md5($cat); ?>
                    <div class="alim-row alim-new">
                        <form id="<?= $add_id ?>" method="post" action="gestisci.php"></form>
                        <input form="<?= $add_id ?>" type="hidden" name="azione" value="alim_aggiungi">
                        <input form="<?= $add_id ?>" type="hidden" name="categoria" value="<?= h($cat) ?>">
                        <div><input form="<?= $add_id ?>" type="text" name="nome" placeholder="nuovo alimento" required maxlength="120"></div>
                        <div class="num"><input form="<?= $add_id ?>" type="number" name="kcal" placeholder="kcal" min="0" step="1" required></div>
                        <div class="num"><input form="<?= $add_id ?>" type="number" name="p" placeholder="prot" min="0" step="0.1" required></div>
                        <div class="num"><input form="<?= $add_id ?>" type="number" name="g" placeholder="grassi" min="0" step="0.1" required></div>
                        <div class="row-actions"><button form="<?= $add_id ?>" type="submit" class="btn-primary btn-small">Aggiungi</button></div>
                    </div>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

</body>
</html>
