<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

if (is_authenticated()) {
    redirect_with_query('index.php');
}

$fout = '';

if (post('actie') === 'inloggen') {
    $username = post('username');
    $password = post('password');

    if (authenticate_user($username, $password)) {
        redirect_with_query('index.php');
    }

    $fout = 'Onjuiste gebruikersnaam of wachtwoord.';
}

render_page_start(
    'BrailleVolg Login',
    'Log in om leerlingen en aantekeningen te bekijken of te wijzigen.'
);
?>

<?php if ($fout !== ''): ?><div class="fout"><?= e($fout) ?></div><?php endif; ?>

<div class="layout">
  <div class="auth-shell">
    <div class="card auth-card">
      <div class="card-header">
        <div>
          <h2>Inloggen</h2>
          <p class="card-subtitle">Alleen geautoriseerde gebruikers mogen BrailleVolg bekijken of aanpassen.</p>
        </div>
      </div>

      <form method="post" action="login.php">
        <input type="hidden" name="actie" value="inloggen">

        <label for="username">Gebruikersnaam</label>
        <input type="text" id="username" name="username" autocomplete="username" required>

        <label for="password">Wachtwoord</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>

        <div class="actions" style="margin-top:20px;">
          <button type="submit">Inloggen</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php render_page_end(); ?>
