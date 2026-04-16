<?php
declare(strict_types=1);

$routesFile = __DIR__ . '/routes.json';
$password = 'CHANGE_THIS_TO_A_STRONG_PASSWORD';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$enteredPassword = $_POST['password'] ?? $_GET['password'] ?? '';
$isAllowed = hash_equals($password, (string)$enteredPassword);

$routes = [];
if (is_file($routesFile)) {
    $json = file_get_contents($routesFile);
    $decoded = json_decode((string)$json, true);
    if (is_array($decoded)) {
        $routes = $decoded;
    }
}

ksort($routes);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Redirect Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { font-family: Arial, sans-serif; margin: 30px; max-width: 1000px; }
    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; vertical-align: top; }
    input[type="text"], input[type="url"], textarea { width: 100%; padding: 8px; box-sizing: border-box; }
    button { padding: 10px 16px; margin-top: 12px; }
    .small { color: #666; font-size: 14px; }
    .error { color: #b00020; }
    .ok { color: #0a7a0a; }
  </style>
</head>
<body>

<h1>Redirect Admin</h1>

<?php if (!$isAllowed): ?>
  <form method="post">
    <label for="password">Password</label><br>
    <input type="password" name="password" id="password" required>
    <br>
    <button type="submit">Open admin</button>
  </form>
  <p class="small">Set your password inside <code>admin.php</code>.</p>
<?php else: ?>

  <p class="ok">Logged in.</p>

  <form method="post" action="save.php">
    <input type="hidden" name="password" value="<?= h($enteredPassword) ?>">

    <table>
      <thead>
        <tr>
          <th style="width: 25%;">Short code</th>
          <th style="width: 75%;">Target URL</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($routes as $code => $url): ?>
          <tr>
            <td>
              <input type="text" name="codes[]" value="<?= h((string)$code) ?>">
            </td>
            <td>
              <input type="url" name="urls[]" value="<?= h((string)$url) ?>">
            </td>
          </tr>
        <?php endforeach; ?>

        <?php for ($i = 0; $i < 10; $i++): ?>
          <tr>
            <td>
              <input type="text" name="codes[]" value="">
            </td>
            <td>
              <input type="url" name="urls[]" value="">
            </td>
          </tr>
        <?php endfor; ?>
      </tbody>
    </table>

    <button type="submit">Save routes</button>
  </form>

  <p class="small">
    Use links like:<br>
    <code>https://www.tastenbraille.com/go/page12</code>
  </p>

<?php endif; ?>

</body>
</html>