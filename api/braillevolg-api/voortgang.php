<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_authentication();

$melding = '';
$fout = '';
$gekozenLeerlingId = (int)get('leerling', '0');

if ($gekozenLeerlingId <= 0) {
    redirect_with_query('index.php');
}

$gekozenLeerling = fetch_leerling_by_id($db, $gekozenLeerlingId);
if (!$gekozenLeerling) {
    redirect_with_query('index.php');
}

$actie = post('actie');

if ($actie === 'voortgang_toevoegen') {
    $datum = post('datum');
    $onderdeel = post('onderdeel');
    $auteur = post('auteur', current_auth_user());
    $lettersBeheerst = (int)post('letters_beheerst', '0');
    $woordenBeheerst = (int)post('woorden_beheerst', '0');
    $leessnelheid = post('leessnelheid');
    $notitie = post('notitie');

    if ($datum === '') {
        $fout = 'Datum is verplicht voor een voortgangsmoment.';
    } else {
        $stmt = $db->prepare("
            INSERT INTO voortgang
            (leerling_id, datum, onderdeel, auteur, letters_beheerst, woorden_beheerst, leessnelheid, notitie)
            VALUES
            (:leerling_id, :datum, :onderdeel, :auteur, :letters_beheerst, :woorden_beheerst, :leessnelheid, :notitie)
        ");
        $stmt->bindValue(':leerling_id', $gekozenLeerlingId, SQLITE3_INTEGER);
        $stmt->bindValue(':datum', $datum, SQLITE3_TEXT);
        $stmt->bindValue(':onderdeel', $onderdeel, SQLITE3_TEXT);
        $stmt->bindValue(':auteur', $auteur, SQLITE3_TEXT);
        $stmt->bindValue(':letters_beheerst', $lettersBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':woorden_beheerst', $woordenBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':leessnelheid', $leessnelheid, SQLITE3_TEXT);
        $stmt->bindValue(':notitie', $notitie, SQLITE3_TEXT);
        $stmt->execute();
        $newId = (int)$db->lastInsertRowID();
        write_audit_log($db, 'voortgang', $newId, 'create', ['leerling_id' => $gekozenLeerlingId, 'datum' => $datum]);
        sync_leerling_latest_voortgang($db, $gekozenLeerlingId);
        redirect_with_query('voortgang.php', [
            'melding' => 'voortgang_toegevoegd',
            'leerling' => (string)$gekozenLeerlingId,
        ]);
    }
}

if ($actie === 'voortgang_bewerken_opslaan') {
    $id = (int)post('id', '0');
    $datum = post('datum');
    $onderdeel = post('onderdeel');
    $auteur = post('auteur', current_auth_user());
    $lettersBeheerst = (int)post('letters_beheerst', '0');
    $woordenBeheerst = (int)post('woorden_beheerst', '0');
    $leessnelheid = post('leessnelheid');
    $notitie = post('notitie');

    if ($id <= 0 || $datum === '') {
        $fout = 'Ongeldige invoer bij voortgang bewerken.';
    } else {
        $stmt = $db->prepare("
            UPDATE voortgang
            SET
                datum = :datum,
                onderdeel = :onderdeel,
                auteur = :auteur,
                letters_beheerst = :letters_beheerst,
                woorden_beheerst = :woorden_beheerst,
                leessnelheid = :leessnelheid,
                notitie = :notitie
            WHERE id = :id AND leerling_id = :leerling_id
        ");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':leerling_id', $gekozenLeerlingId, SQLITE3_INTEGER);
        $stmt->bindValue(':datum', $datum, SQLITE3_TEXT);
        $stmt->bindValue(':onderdeel', $onderdeel, SQLITE3_TEXT);
        $stmt->bindValue(':auteur', $auteur, SQLITE3_TEXT);
        $stmt->bindValue(':letters_beheerst', $lettersBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':woorden_beheerst', $woordenBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':leessnelheid', $leessnelheid, SQLITE3_TEXT);
        $stmt->bindValue(':notitie', $notitie, SQLITE3_TEXT);
        $stmt->execute();
        write_audit_log($db, 'voortgang', $id, 'update', ['leerling_id' => $gekozenLeerlingId, 'datum' => $datum]);
        sync_leerling_latest_voortgang($db, $gekozenLeerlingId);
        redirect_with_query('voortgang.php', [
            'melding' => 'voortgang_bijgewerkt',
            'leerling' => (string)$gekozenLeerlingId,
        ]);
    }
}

if ($actie === 'voortgang_verwijderen') {
    $id = (int)post('id', '0');
    if ($id > 0) {
        soft_delete_voortgang($db, $id, $gekozenLeerlingId);
        write_audit_log($db, 'voortgang', $id, 'soft_delete', ['leerling_id' => $gekozenLeerlingId]);
        sync_leerling_latest_voortgang($db, $gekozenLeerlingId);
        redirect_with_query('voortgang.php', [
            'melding' => 'voortgang_verwijderd',
            'leerling' => (string)$gekozenLeerlingId,
        ]);
    } else {
        $fout = 'Ongeldige invoer bij voortgang verwijderen.';
    }
}

$queryMelding = get('melding');
if ($queryMelding === 'voortgang_toegevoegd') {
    $melding = 'Voortgang toegevoegd.';
} elseif ($queryMelding === 'voortgang_bijgewerkt') {
    $melding = 'Voortgang bijgewerkt.';
} elseif ($queryMelding === 'voortgang_verwijderd') {
    $melding = 'Voortgang verwijderd.';
}

$voortgangBewerkId = (int)get('bewerk', '0');
$bewerkVoortgang = $voortgangBewerkId > 0 ? fetch_voortgang_by_id($db, $voortgangBewerkId) : null;
if ($bewerkVoortgang && (int)$bewerkVoortgang['leerling_id'] !== $gekozenLeerlingId) {
    $bewerkVoortgang = null;
}

$voortgangForm = [
    'id' => '',
    'datum' => date('Y-m-d'),
    'onderdeel' => '',
    'auteur' => current_auth_user(),
    'letters_beheerst' => (string)($gekozenLeerling['letters_beheerst'] ?? '0'),
    'woorden_beheerst' => (string)($gekozenLeerling['woorden_beheerst'] ?? '0'),
    'leessnelheid' => (string)($gekozenLeerling['leessnelheid'] ?? ''),
    'notitie' => '',
];
if ($bewerkVoortgang) {
    $voortgangForm = [
        'id' => (string)$bewerkVoortgang['id'],
        'datum' => (string)$bewerkVoortgang['datum'],
        'onderdeel' => (string)$bewerkVoortgang['onderdeel'],
        'auteur' => (string)($bewerkVoortgang['auteur'] ?? current_auth_user()),
        'letters_beheerst' => (string)$bewerkVoortgang['letters_beheerst'],
        'woorden_beheerst' => (string)$bewerkVoortgang['woorden_beheerst'],
        'leessnelheid' => (string)$bewerkVoortgang['leessnelheid'],
        'notitie' => (string)$bewerkVoortgang['notitie'],
    ];
}

$voortgangResult = null;
$stmt = $db->prepare("
    SELECT * FROM voortgang
    WHERE leerling_id = :leerling_id
      AND deleted_at = ''
    ORDER BY datum DESC, id DESC
");
$stmt->bindValue(':leerling_id', $gekozenLeerlingId, SQLITE3_INTEGER);
$voortgangResult = $stmt->execute();

$countRes = $db->prepare("SELECT COUNT(*) AS total FROM voortgang WHERE leerling_id = :leerling_id AND deleted_at = ''");
$countRes->bindValue(':leerling_id', $gekozenLeerlingId, SQLITE3_INTEGER);
$countRow = $countRes->execute()->fetchArray(SQLITE3_ASSOC) ?: ['total' => 0];

render_page_start(
    'Braille Aantekeningen',
    ''
);
?>

<?php if ($melding !== ''): ?><div class="melding"><?= e($melding) ?></div><?php endif; ?>
<?php if ($fout !== ''): ?><div class="fout"><?= e($fout) ?></div><?php endif; ?>

<div class="layout">
  <div class="section-title">Gekozen leerling</div>
  <div class="card soft">
    <div class="card-header">
      <div>
        <h2><?= e($gekozenLeerling['naam']) ?></h2>
        <p class="card-subtitle">
          <?= e($gekozenLeerling['groep_klas']) ?><?= $gekozenLeerling['groep_klas'] !== '' && $gekozenLeerling['niveau'] !== '' ? ' · ' : '' ?><?= e($gekozenLeerling['niveau']) ?>
        </p>
      </div>
      <div class="actions">
        <a class="btn btn-secondary" href="index.php">Terug naar leerlingen</a>
        <a class="btn btn-secondary" href="voortgang_pdf.php?leerling=<?= $gekozenLeerlingId ?>">PDF</a>
        <?php if (is_admin()): ?>
        <a class="btn btn-edit" href="index.php?bewerk=<?= $gekozenLeerlingId ?>&leerling=<?= $gekozenLeerlingId ?>">Bewerk leerling</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="summary-grid">
      <div class="summary-tile"><strong><?= (int)$gekozenLeerling['letters_beheerst'] ?></strong><span>Letters beheerst</span></div>
      <div class="summary-tile"><strong><?= (int)$gekozenLeerling['woorden_beheerst'] ?></strong><span>Woorden beheerst</span></div>
      <div class="summary-tile"><strong><?= e($gekozenLeerling['leessnelheid'] ?: '-') ?></strong><span>Leessnelheid</span></div>
      <div class="summary-tile"><strong><?= (int)$countRow['total'] ?></strong><span>Aantal meetmomenten</span></div>
    </div>
  </div>

  <div class="section-title">Aantekeningen en voortgang</div>
  <div class="card soft">
    <div class="card-header">
      <div>
        <h2><?= $bewerkVoortgang ? 'Aantekening bewerken' : 'Nieuwe aantekening' ?></h2>
      </div>
      <?php if ($bewerkVoortgang): ?>
        <div class="actions"><a class="btn btn-secondary" href="voortgang.php?leerling=<?= $gekozenLeerlingId ?>">Nieuw</a></div>
      <?php endif; ?>
    </div>

    <form method="post" action="voortgang.php?leerling=<?= $gekozenLeerlingId ?>">
      <input type="hidden" name="actie" value="<?= $bewerkVoortgang ? 'voortgang_bewerken_opslaan' : 'voortgang_toevoegen' ?>">
      <input type="hidden" name="id" value="<?= e($voortgangForm['id']) ?>">

      <div class="row" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
        <div>
          <label for="datum">Datum</label>
          <input type="date" id="datum" name="datum" value="<?= e($voortgangForm['datum']) ?>" required>
        </div>
        <div>
          <label for="onderdeel">Onderdeel</label>
          <input type="text" id="onderdeel" name="onderdeel" value="<?= e($voortgangForm['onderdeel']) ?>" placeholder="bijv. letters, woorden, toets 3">
        </div>
        <div>
          <label for="auteur">Aangemaakt door</label>
          <input type="text" id="auteur" name="auteur" value="<?= e($voortgangForm['auteur']) ?>">
        </div>
      </div>

      <input type="hidden" id="letters_beheerst" name="letters_beheerst" value="<?= e($voortgangForm['letters_beheerst']) ?>">
      <input type="hidden" id="woorden_beheerst" name="woorden_beheerst" value="<?= e($voortgangForm['woorden_beheerst']) ?>">
      <input type="hidden" id="leessnelheid" name="leessnelheid" value="<?= e($voortgangForm['leessnelheid']) ?>">

      <label for="notitie">Aantekening</label>
      <textarea id="notitie" name="notitie"><?= e($voortgangForm['notitie']) ?></textarea>

      <div class="actions" style="margin-top:20px;">
        <button type="submit"><?= $bewerkVoortgang ? 'Opslaan' : 'Toevoegen' ?></button>
        <?php if ($bewerkVoortgang): ?><a class="btn btn-secondary" href="voortgang.php?leerling=<?= $gekozenLeerlingId ?>">Annuleren</a><?php endif; ?>
      </div>
    </form>
  </div>

  <div class="card soft">
    <div class="card-header">
      <div>
        <h2>Aantekeningen</h2>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Datum</th>
          <th>Onderdeel</th>
          <th>Door</th>
          <th class="notes-col">Aantekening</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php
      $hasRows = false;
      while ($row = $voortgangResult->fetchArray(SQLITE3_ASSOC)):
          $hasRows = true;
      ?>
        <tr>
          <td><?= e($row['datum']) ?></td>
          <td><?= e($row['onderdeel']) ?></td>
          <td><?= e((string)($row['auteur'] ?? '')) ?></td>
          <td class="notes-col"><div class="notes-text"><?= nl2br(e($row['notitie'])) ?></div></td>
          <td class="table-actions">
            <a class="btn btn-edit" href="voortgang.php?leerling=<?= $gekozenLeerlingId ?>&bewerk=<?= (int)$row['id'] ?>">Bewerken</a>
            <form method="post" action="voortgang.php?leerling=<?= $gekozenLeerlingId ?>" class="inline-delete" onsubmit="return confirm('Weet je zeker dat je dit voortgangsmoment wilt verwijderen?');">
              <input type="hidden" name="actie" value="voortgang_verwijderen">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button type="submit" class="btn-delete">Verwijderen</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      <?php if (!$hasRows): ?>
          <tr><td colspan="5">Nog geen aantekeningen of meetmomenten voor deze leerling.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php render_page_end(); ?>
