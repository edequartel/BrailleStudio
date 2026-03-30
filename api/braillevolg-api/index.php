<?php
declare(strict_types=1);

/**
 * Eenvoudig leerlingvolgsysteem braille
 * CRUD in één bestand
 * Vereist: PHP met SQLite3 ingeschakeld
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$dbDir = __DIR__ . '/data';
$dbFile = $dbDir . '/braille_leerlingen.sqlite';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0775, true);
}

$db = new SQLite3($dbFile);

// Tabel aanmaken als die nog niet bestaat
$db->exec("
    CREATE TABLE IF NOT EXISTS leerlingen (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        naam TEXT NOT NULL,
        groep_klas TEXT NOT NULL DEFAULT '',
        niveau TEXT NOT NULL DEFAULT '',
        letters_beheerst INTEGER NOT NULL DEFAULT 0,
        woorden_beheerst INTEGER NOT NULL DEFAULT 0,
        leessnelheid TEXT NOT NULL DEFAULT '',
        laatste_toetsdatum TEXT NOT NULL DEFAULT '',
        opmerkingen TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )
");

// updated_at bijwerken bij update
$db->exec("
    CREATE TRIGGER IF NOT EXISTS leerlingen_updated_at
    AFTER UPDATE ON leerlingen
    FOR EACH ROW
    BEGIN
        UPDATE leerlingen
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = OLD.id;
    END;
");

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
}

function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

$actie = post('actie');
$melding = '';
$fout = '';

// Toevoegen
if ($actie === 'toevoegen') {
    $naam = post('naam');
    $groepKlas = post('groep_klas');
    $niveau = post('niveau');
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
            (naam, groep_klas, niveau, letters_beheerst, woorden_beheerst, leessnelheid, laatste_toetsdatum, opmerkingen)
            VALUES
            (:naam, :groep_klas, :niveau, :letters_beheerst, :woorden_beheerst, :leessnelheid, :laatste_toetsdatum, :opmerkingen)
        ");
        $stmt->bindValue(':naam', $naam, SQLITE3_TEXT);
        $stmt->bindValue(':groep_klas', $groepKlas, SQLITE3_TEXT);
        $stmt->bindValue(':niveau', $niveau, SQLITE3_TEXT);
        $stmt->bindValue(':letters_beheerst', $lettersBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':woorden_beheerst', $woordenBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':leessnelheid', $leessnelheid, SQLITE3_TEXT);
        $stmt->bindValue(':laatste_toetsdatum', $laatsteToetsdatum, SQLITE3_TEXT);
        $stmt->bindValue(':opmerkingen', $opmerkingen, SQLITE3_TEXT);
        $stmt->execute();

        header('Location: index.php?melding=leerling_toegevoegd');
        exit;
    }
}

// Bewerken opslaan
if ($actie === 'bewerken_opslaan') {
    $id = (int)post('id', '0');
    $naam = post('naam');
    $groepKlas = post('groep_klas');
    $niveau = post('niveau');
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
        $stmt->bindValue(':letters_beheerst', $lettersBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':woorden_beheerst', $woordenBeheerst, SQLITE3_INTEGER);
        $stmt->bindValue(':leessnelheid', $leessnelheid, SQLITE3_TEXT);
        $stmt->bindValue(':laatste_toetsdatum', $laatsteToetsdatum, SQLITE3_TEXT);
        $stmt->bindValue(':opmerkingen', $opmerkingen, SQLITE3_TEXT);
        $stmt->execute();

        header('Location: index.php?melding=leerling_bijgewerkt');
        exit;
    }
}

// Verwijderen
if ($actie === 'verwijderen') {
    $id = (int)post('id', '0');

    if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM leerlingen WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
        $stmt->execute();
        header('Location: index.php?melding=leerling_verwijderd');
        exit;
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

// Zoeken
$zoek = get('zoek');
if ($zoek !== '') {
    $stmt = $db->prepare("
        SELECT * FROM leerlingen
        WHERE naam LIKE :zoek
           OR groep_klas LIKE :zoek
           OR niveau LIKE :zoek
           OR opmerkingen LIKE :zoek
        ORDER BY naam ASC
    ");
    $stmt->bindValue(':zoek', '%' . $zoek . '%', SQLITE3_TEXT);
    $result = $stmt->execute();
} else {
    $result = $db->query("SELECT * FROM leerlingen ORDER BY naam ASC");
}

// Bewerken ophalen
$bewerkId = (int)get('bewerk', '0');
$bewerkLeerling = null;

if ($bewerkId > 0) {
    $stmt = $db->prepare("SELECT * FROM leerlingen WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $bewerkId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $bewerkLeerling = $res->fetchArray(SQLITE3_ASSOC) ?: null;
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Braille Leerlingvolgsysteem</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body{
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 16px;
            background: #f5f7fa;
            color: #222;
        }
        h1,h2{
            margin-top: 0;
        }
        .card{
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        label{
            display:block;
            font-weight:bold;
            margin-top:12px;
            margin-bottom:4px;
        }
        input[type="text"],
        input[type="number"],
        input[type="date"],
        textarea{
            width:100%;
            padding:10px;
            border:1px solid #ccc;
            border-radius:6px;
            box-sizing:border-box;
        }
        textarea{
            min-height:100px;
            resize:vertical;
        }
        .row{
            display:grid;
            grid-template-columns: 1fr 1fr;
            gap:16px;
        }
        .actions{
            margin-top:16px;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        button, .btn{
            display:inline-block;
            border:none;
            padding:10px 14px;
            border-radius:6px;
            text-decoration:none;
            cursor:pointer;
            font-size:14px;
        }
        button{
            background:#1d4ed8;
            color:#fff;
        }
        .btn-secondary{
            background:#6b7280;
            color:#fff;
        }
        .btn-edit{
            background:#f59e0b;
            color:#fff;
        }
        .btn-delete{
            background:#dc2626;
            color:#fff;
        }
        table{
            width:100%;
            border-collapse:collapse;
            margin-top:10px;
        }
        th, td{
            border:1px solid #ddd;
            padding:10px;
            text-align:left;
            vertical-align:top;
        }
        th{
            background:#eef2f7;
        }
        .melding{
            background:#dcfce7;
            color:#166534;
            padding:12px;
            border-radius:6px;
            margin-bottom:16px;
        }
        .fout{
            background:#fee2e2;
            color:#991b1b;
            padding:12px;
            border-radius:6px;
            margin-bottom:16px;
        }
        .small-form{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
            align-items:end;
        }
        .small-form input[type="text"]{
            max-width:300px;
        }
        @media (max-width: 800px){
            .row{
                grid-template-columns: 1fr;
            }
            table{
                font-size:14px;
            }
        }
    </style>
</head>
<body>

    <h1>Braille Leerlingvolgsysteem</h1>

    <?php if ($melding !== ''): ?>
        <div class="melding"><?= e($melding) ?></div>
    <?php endif; ?>

    <?php if ($fout !== ''): ?>
        <div class="fout"><?= e($fout) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2><?= $bewerkLeerling ? 'Leerling bewerken' : 'Nieuwe leerling toevoegen' ?></h2>

        <form method="post" action="index.php">
            <input type="hidden" name="actie" value="<?= $bewerkLeerling ? 'bewerken_opslaan' : 'toevoegen' ?>">
            <?php if ($bewerkLeerling): ?>
                <input type="hidden" name="id" value="<?= (int)$bewerkLeerling['id'] ?>">
            <?php endif; ?>

            <div class="row">
                <div>
                    <label for="naam">Naam leerling</label>
                    <input type="text" id="naam" name="naam" required
                           value="<?= e($bewerkLeerling['naam'] ?? '') ?>">
                </div>

                <div>
                    <label for="groep_klas">Groep / klas</label>
                    <input type="text" id="groep_klas" name="groep_klas"
                           value="<?= e($bewerkLeerling['groep_klas'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="niveau">Niveau</label>
                    <input type="text" id="niveau" name="niveau"
                           placeholder="bijv. beginner, halfgevorderd, gevorderd"
                           value="<?= e($bewerkLeerling['niveau'] ?? '') ?>">
                </div>

                <div>
                    <label for="laatste_toetsdatum">Laatste toetsdatum</label>
                    <input type="date" id="laatste_toetsdatum" name="laatste_toetsdatum"
                           value="<?= e($bewerkLeerling['laatste_toetsdatum'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="letters_beheerst">Letters beheerst</label>
                    <input type="number" id="letters_beheerst" name="letters_beheerst" min="0"
                           value="<?= e((string)($bewerkLeerling['letters_beheerst'] ?? '0')) ?>">
                </div>

                <div>
                    <label for="woorden_beheerst">Woorden beheerst</label>
                    <input type="number" id="woorden_beheerst" name="woorden_beheerst" min="0"
                           value="<?= e((string)($bewerkLeerling['woorden_beheerst'] ?? '0')) ?>">
                </div>
            </div>

            <div class="row">
                <div>
                    <label for="leessnelheid">Leessnelheid</label>
                    <input type="text" id="leessnelheid" name="leessnelheid"
                           placeholder="bijv. 18 woorden per minuut"
                           value="<?= e($bewerkLeerling['leessnelheid'] ?? '') ?>">
                </div>
            </div>

            <label for="opmerkingen">Opmerkingen</label>
            <textarea id="opmerkingen" name="opmerkingen"><?= e($bewerkLeerling['opmerkingen'] ?? '') ?></textarea>

            <div class="actions">
                <button type="submit"><?= $bewerkLeerling ? 'Opslaan' : 'Toevoegen' ?></button>
                <?php if ($bewerkLeerling): ?>
                    <a class="btn btn-secondary" href="index.php">Annuleren</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Zoeken</h2>
        <form method="get" action="index.php" class="small-form">
            <div>
                <label for="zoek">Zoekterm</label>
                <input type="text" id="zoek" name="zoek" value="<?= e($zoek) ?>" placeholder="naam, klas, niveau...">
            </div>
            <div class="actions">
                <button type="submit">Zoeken</button>
                <a class="btn btn-secondary" href="index.php">Reset</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Overzicht leerlingen</h2>

        <table>
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Groep/klas</th>
                    <th>Niveau</th>
                    <th>Letters</th>
                    <th>Woorden</th>
                    <th>Leessnelheid</th>
                    <th>Laatste toets</th>
                    <th>Opmerkingen</th>
                    <th>Acties</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $heeftRijen = false;
            while ($row = $result->fetchArray(SQLITE3_ASSOC)):
                $heeftRijen = true;
            ?>
                <tr>
                    <td><?= e($row['naam']) ?></td>
                    <td><?= e($row['groep_klas']) ?></td>
                    <td><?= e($row['niveau']) ?></td>
                    <td><?= (int)$row['letters_beheerst'] ?></td>
                    <td><?= (int)$row['woorden_beheerst'] ?></td>
                    <td><?= e($row['leessnelheid']) ?></td>
                    <td><?= e($row['laatste_toetsdatum']) ?></td>
                    <td><?= nl2br(e($row['opmerkingen'])) ?></td>
                    <td>
                        <a class="btn btn-edit" href="index.php?bewerk=<?= (int)$row['id'] ?>">Bewerken</a>

                        <form method="post" action="index.php" style="display:inline;" onsubmit="return confirm('Weet je zeker dat je deze leerling wilt verwijderen?');">
                            <input type="hidden" name="actie" value="verwijderen">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <button type="submit" class="btn-delete">Verwijderen</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>

            <?php if (!$heeftRijen): ?>
                <tr>
                    <td colspan="9">Nog geen leerlingen gevonden.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>