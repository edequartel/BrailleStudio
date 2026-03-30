<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_authentication();

$actie = post('actie');
$melding = '';
$fout = '';

if ($actie === 'toevoegen') {
    $naam = post('naam');
    $groepKlas = post('groep_klas');
    $niveau = post('niveau');
    $doelstellingen = post('doelstellingen');
    $lettersBeheerst = (int)post('letters_beheerst', '0');
    $woordenBeheerst = (int)post('woorden_beheerst', '0');
    $leessnelheid = post('leessnelheid');
    $laatsteToetsdatum = post('laatste_toetsdatum');
    $opmerkingen = post('opmerkingen');

    if ($naam === '') {
        $fout = 'Naam is verplicht.';
    } else {
        $stmt = $db->prepare("
            INSERT INTO leerlingen
            (naam, groep_klas, niveau, doelstellingen, letters_beheerst, woorden_beheerst, leessnelheid, laatste_toetsdatum, opmerkingen)
            VALUES
            (:naam, :groep_klas, :niveau, :doelstellingen, :letters_beheerst, :woorden_beheerst, :leessnelheid, :laatste_toetsdatum, :opmerkingen)
        ");
        $stmt->bindValue(':naam', $naam, SQLITE3_TEXT);
        $stmt->bindValue(':groep_klas', $groepKlas, SQLITE3_TEXT);
        $stmt->bindValue(':niveau', $niveau, SQLITE3_TEXT);
        $stmt->bindValue(':doelstellingen', $doelstellingen, SQLITE3_TEXT);
        $stmt->bindValue(':letters_beheerst', $lettersBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':woorden_beheerst', $woordenBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':leessnelheid', $leessnelheid, SQLITE3_TEXT);
        $stmt->bindValue(':laatste_toetsdatum', $laatsteToetsdatum, SQLITE3_TEXT);
        $stmt->bindValue(':opmerkingen', $opmerkingen, SQLITE3_TEXT);
        $stmt->execute();

        redirect_with_query('index.php', [
            'melding' => 'leerling_toegevoegd',
            'leerling' => (string)$db->lastInsertRowID(),
        ]);
    }
}

if ($actie === 'bewerken_opslaan') {
    $id = (int)post('id', '0');
    $naam = post('naam');
    $groepKlas = post('groep_klas');
    $niveau = post('niveau');
    $doelstellingen = post('doelstellingen');
    $lettersBeheerst = (int)post('letters_beheerst', '0');
    $woordenBeheerst = (int)post('woorden_beheerst', '0');
    $leessnelheid = post('leessnelheid');
    $laatsteToetsdatum = post('laatste_toetsdatum');
    $opmerkingen = post('opmerkingen');

    if ($id <= 0 || $naam === '') {
        $fout = 'Ongeldige invoer bij bewerken.';
    } else {
        $stmt = $db->prepare("
            UPDATE leerlingen
            SET
                naam = :naam,
                groep_klas = :groep_klas,
                niveau = :niveau,
                doelstellingen = :doelstellingen,
                letters_beheerst = :letters_beheerst,
                woorden_beheerst = :woorden_beheerst,
                leessnelheid = :leessnelheid,
                laatste_toetsdatum = :laatste_toetsdatum,
                opmerkingen = :opmerkingen
            WHERE id = :id
        ");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->bindValue(':naam', $naam, SQLITE3_TEXT);
        $stmt->bindValue(':groep_klas', $groepKlas, SQLITE3_TEXT);
        $stmt->bindValue(':niveau', $niveau, SQLITE3_TEXT);
        $stmt->bindValue(':doelstellingen', $doelstellingen, SQLITE3_TEXT);
        $stmt->bindValue(':letters_beheerst', $lettersBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':woorden_beheerst', $woordenBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':leessnelheid', $leessnelheid, SQLITE3_TEXT);
        $stmt->bindValue(':laatste_toetsdatum', $laatsteToetsdatum, SQLITE3_TEXT);
        $stmt->bindValue(':opmerkingen', $opmerkingen, SQLITE3_TEXT);
        $stmt->execute();

        redirect_with_query('index.php', [
            'melding' => 'leerling_bijgewerkt',
            'leerling' => (string)$id,
        ]);
    }
}

if ($actie === 'verwijderen') {
    $id = (int)post('id', '0');
    if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM leerlingen WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        redirect_with_query('index.php', ['melding' => 'leerling_verwijderd']);
    } else {
        $fout = 'Ongeldig ID voor verwijderen.';
    }
}

$queryMelding = get('melding');
if ($queryMelding === 'leerling_toegevoegd') {
    $melding = 'Leerling toegevoegd.';
} elseif ($queryMelding === 'leerling_bijgewerkt') {
    $melding = 'Leerling bijgewerkt.';
} elseif ($queryMelding === 'leerling_verwijderd') {
    $melding = 'Leerling verwijderd.';
}

$zoek = get('zoek');
$bewerkId = (int)get('bewerk', '0');
$bewerkLeerling = $bewerkId > 0 ? fetch_leerling_by_id($db, $bewerkId) : null;

if ($zoek !== '') {
    $stmt = $db->prepare("
        SELECT * FROM leerlingen
        WHERE naam LIKE :zoek
           OR groep_klas LIKE :zoek
           OR niveau LIKE :zoek
           OR doelstellingen LIKE :zoek
           OR opmerkingen LIKE :zoek
        ORDER BY naam ASC
    ");
    $stmt->bindValue(':zoek', '%' . $zoek . '%', SQLITE3_TEXT);
    $result = $stmt->execute();
} else {
    $result = $db->query("SELECT * FROM leerlingen ORDER BY naam ASC");
}

$studentCountRes = $db->querySingle("SELECT COUNT(*) FROM leerlingen");
$progressCountRes = $db->querySingle("SELECT COUNT(*) FROM voortgang");

render_page_start(
    'Braille Leerlingen',
    'Beheer leerlingen hier. Kies daarna een leerling om op de aparte pagina aantekeningen en voortgang per datum vast te leggen.'
);
?>

<?php if ($melding !== ''): ?><div class="melding"><?= e($melding) ?></div><?php endif; ?>
<?php if ($fout !== ''): ?><div class="fout"><?= e($fout) ?></div><?php endif; ?>

<div class="layout">
  <div class="section-title">Overzicht</div>
  <div class="card soft">
    <div class="summary-grid">
      <div class="summary-tile"><strong><?= (int)$studentCountRes ?></strong><span>Leerlingen</span></div>
      <div class="summary-tile"><strong><?= (int)$progressCountRes ?></strong><span>Vorderingen</span></div>
      <div class="summary-tile"><strong><?= $bewerkLeerling ? e($bewerkLeerling['naam']) : '-' ?></strong><span>Bewerken</span></div>
      <div class="summary-tile"><strong><?= $bewerkLeerling ? e($bewerkLeerling['laatste_toetsdatum'] ?: '-') : '-' ?></strong><span>Laatste toets</span></div>
    </div>
  </div>

  <div class="section-title">Leerlingenbeheer</div>
  <div class="grid-2">
    <div class="card soft">
      <div class="card-header">
        <div>
          <h2><?= $bewerkLeerling ? 'Leerling bewerken' : 'Nieuwe leerling' ?></h2>
          <p class="card-subtitle">Voeg een leerling toe of werk de basisgegevens bij.</p>
        </div>
        <?php if ($bewerkLeerling): ?>
          <div class="actions"><a class="btn btn-secondary" href="index.php">Nieuw</a></div>
        <?php endif; ?>
      </div>

      <form method="post" action="index.php">
        <input type="hidden" name="actie" value="<?= $bewerkLeerling ? 'bewerken_opslaan' : 'toevoegen' ?>">
        <?php if ($bewerkLeerling): ?><input type="hidden" name="id" value="<?= (int)$bewerkLeerling['id'] ?>"><?php endif; ?>

        <div class="row">
          <div>
            <label for="naam">Naam leerling</label>
            <input type="text" id="naam" name="naam" required value="<?= e($bewerkLeerling['naam'] ?? '') ?>">
          </div>
          <div>
            <label for="groep_klas">Groep / klas</label>
            <input type="text" id="groep_klas" name="groep_klas" value="<?= e($bewerkLeerling['groep_klas'] ?? '') ?>">
          </div>
        </div>

        <div class="row">
          <div>
            <label for="niveau">Niveau</label>
            <input type="text" id="niveau" name="niveau" value="<?= e($bewerkLeerling['niveau'] ?? '') ?>">
          </div>
        </div>

        <input type="hidden" id="laatste_toetsdatum" name="laatste_toetsdatum" value="<?= e($bewerkLeerling['laatste_toetsdatum'] ?? '') ?>">
        <input type="hidden" id="letters_beheerst" name="letters_beheerst" value="<?= e((string)($bewerkLeerling['letters_beheerst'] ?? '0')) ?>">
        <input type="hidden" id="woorden_beheerst" name="woorden_beheerst" value="<?= e((string)($bewerkLeerling['woorden_beheerst'] ?? '0')) ?>">

        <label for="opmerkingen">Opmerkingen</label>
        <textarea id="opmerkingen" name="opmerkingen"><?= e($bewerkLeerling['opmerkingen'] ?? '') ?></textarea>

        <label for="doelstellingen">Doelstellingen</label>
        <textarea id="doelstellingen" name="doelstellingen"><?= e($bewerkLeerling['doelstellingen'] ?? '') ?></textarea>

        <input type="hidden" id="leessnelheid" name="leessnelheid" value="<?= e($bewerkLeerling['leessnelheid'] ?? '') ?>">

        <div class="actions" style="margin-top:20px;">
          <button type="submit"><?= $bewerkLeerling ? 'Opslaan' : 'Toevoegen' ?></button>
          <?php if ($bewerkLeerling): ?><a class="btn btn-secondary" href="index.php">Annuleren</a><?php endif; ?>
        </div>
      </form>
    </div>

    <div class="card soft">
      <div class="card-header">
        <div>
          <h2>Zoeken</h2>
          <p class="card-subtitle">Zoek een leerling in de lijst hieronder.</p>
        </div>
        <div class="actions"><a class="btn btn-secondary" href="index.php">Reset</a></div>
      </div>

      <form method="get" action="index.php" class="actions" style="align-items:end;">
        <div style="min-width:min(100%,320px); flex:1 1 320px;">
          <label for="zoek">Zoekterm</label>
          <input type="text" id="zoek" name="zoek" value="<?= e($zoek) ?>" placeholder="naam, klas, niveau...">
        </div>
        <button type="submit">Zoeken</button>
      </form>
      <div class="empty">Gebruik in de lijst direct de knoppen voor bewerken, aantekeningen en verwijderen.</div>
    </div>
  </div>

  <div class="section-title">Leerlingenlijst</div>
  <div class="card">
    <div class="card-header">
      <div>
        <h2>Alle leerlingen</h2>
        <p class="card-subtitle">Beheer leerlingen hier en open per leerling de aparte pagina voor aantekeningen en voortgang.</p>
      </div>
    </div>
    <table>
      <thead>
        <tr>
          <th>Naam</th>
          <th>Groep/klas</th>
          <th>Niveau</th>
          <th>Laatste toets</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php
      $hasRows = false;
      while ($row = $result->fetchArray(SQLITE3_ASSOC)):
          $hasRows = true;
      ?>
        <tr>
          <td><?= e($row['naam']) ?></td>
          <td><?= e($row['groep_klas']) ?></td>
          <td><?= e($row['niveau']) ?></td>
          <td><?= e($row['laatste_toetsdatum']) ?></td>
          <td class="table-actions">
            <a class="btn btn-success" href="voortgang.php?leerling=<?= (int)$row['id'] ?>">Aantekeningen</a>
            <a class="btn btn-edit" href="index.php?bewerk=<?= (int)$row['id'] ?>">Bewerken</a>
            <form method="post" action="index.php" class="inline-delete" onsubmit="return confirm('Weet je zeker dat je deze leerling wilt verwijderen?');">
              <input type="hidden" name="actie" value="verwijderen">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <button type="submit" class="btn-delete">Verwijderen</button>
            </form>
          </td>
        </tr>
      <?php endwhile; ?>
      <?php if (!$hasRows): ?>
        <tr><td colspan="5">Nog geen leerlingen gevonden.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php render_page_end(); ?>
