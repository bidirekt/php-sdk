<!DOCTYPE html>
<html>
<head>
    <title>Pet store</title>
</head>
<body>
<h1>Our pets</h1>
<ul>
    <?php foreach ($pets as $pet): ?>
        <li><?= htmlspecialchars($pet->name) ?> (<?= htmlspecialchars($pet->status) ?>)</li>
    <?php endforeach; ?>
</ul>
</body>
</html>
