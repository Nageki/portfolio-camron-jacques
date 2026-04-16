<?php
session_start();

$questions = [
    "A-t-il des lunettes ?",
    "A-t-il une moustache ?",
    "A-t-il un chapeau ?",
    "A-t-il des cheveux ?",
    "A-t-il une boucle d'oreille ?",
    "A-t-il une barbe ?",
    "A-t-il un nœud papillon ?",
];

if (!isset($_SESSION['answers']) || !is_array($_SESSION['answers']) || count($_SESSION['answers']) !== count($questions)) {
    $_SESSION['answers'] = array_fill(0, count($questions), null);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'reset') {
        $_SESSION['answers'] = array_fill(0, count($questions), null);
    } elseif ($action === 'answer') {
        $questionIndex = filter_input(INPUT_POST, 'question', FILTER_VALIDATE_INT);
        $value = $_POST['value'] ?? null;

        if ($questionIndex !== false && $questionIndex !== null && isset($questions[$questionIndex]) && in_array($value, ['0', '1'], true)) {
            $_SESSION['answers'][$questionIndex] = $value;
        }
    }
}

$answers = $_SESSION['answers'];
$maxLies = 2;

$imageFiles = glob(__DIR__ . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . '*.png') ?: [];
natsort($imageFiles);

$characters = [];
$counter = 1;
foreach ($imageFiles as $filePath) {
    $fileName = basename($filePath);
    $code = pathinfo($fileName, PATHINFO_FILENAME);

    if ($code === '1101100') {
        continue;
    }

    if (preg_match('/^[01]{7}$/', $code) === 1) {
        $characters[] = [
            'name' => 'Personnage ' . $counter,
            'code' => $code,
            'image' => 'img/' . $fileName,
        ];
        $counter++;
    }
}

$characters = array_map(static function (array $character) use ($answers): array {
    $syndrome = [];
    $mismatches = 0;

    foreach ($answers as $index => $answer) {
        if ($answer !== null && $character['code'][$index] !== $answer) {
            $mismatches++;
            $syndrome[] = 'Q' . ($index + 1);
        }
    }

    $character['mismatches'] = $mismatches;
    $character['syndrome'] = $syndrome;

    return $character;
}, $characters);

$compatibleMatches = array_values(array_filter($characters, static fn(array $character): bool => $character['mismatches'] <= $maxLies));
usort($compatibleMatches, static fn(array $a, array $b): int => $a['mismatches'] <=> $b['mismatches']);

$answeredCount = count(array_filter($answers, static fn($answer) => $answer !== null));
$totalQuestions = count($questions);
$allAnswered = $answeredCount === $totalQuestions;
$minMismatches = $compatibleMatches === [] ? null : $compatibleMatches[0]['mismatches'];
$matches = $minMismatches === null
    ? []
    : array_values(array_filter($compatibleMatches, static fn(array $character): bool => $character['mismatches'] === $minMismatches));
$matchCodes = array_map(static fn($character) => $character['code'], $matches);
$hasSingleMatch = count($matches) === 1;

if ($answeredCount === 0) {
    $message = 'Répondez aux 7 questions pour calculer le syndrome (max 2 mensonges).';
} elseif (!$allAnswered) {
    $remaining = $totalQuestions - $answeredCount;
    $message = $answeredCount . '/' . $totalQuestions . ' réponses enregistrées. Répondez encore à ' . $remaining . ' question(s) pour calculer le syndrome.';
} elseif (count($matches) === 0) {
    $message = 'Aucun personnage n’est compatible avec un maximum de 2 mensonges.';
} elseif ($hasSingleMatch) {
    $syndromeText = empty($matches[0]['syndrome'])
        ? 'aucun mensonge détecté'
        : 'mensonges possibles : ' . implode(', ', $matches[0]['syndrome']);
    $message = 'Personnage trouvé : ' . $matches[0]['name'] . ' (' . $matches[0]['code'] . ') — ' . $syndromeText . '.';
} else {
    $message = count($matches) . ' personnages ont le meilleur syndrome avec un maximum de 2 mensonges.';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Qui est-ce ?</title>
<link rel="stylesheet" href="style_jeu.css?v=<?= filemtime(__DIR__ . '/style_jeu.css') ?>">
</head>
<body>
<h1>Qui est-ce ?</h1>
<div id="main">
  <div id="col-gauche">
    <div class="panel-label">PERSONNAGES</div>
    <div id="grille-persos">
      <?php foreach ($characters as $character): ?>
        <?php
          $isMatch = in_array($character['code'], $matchCodes, true);
          $classes = ['perso-card'];
          $cardTitle = '';

          if ($allAnswered && $isMatch) {
              $classes[] = ($hasSingleMatch && $character['mismatches'] === $minMismatches) ? 'winner' : 'match';
              $cardTitle = empty($character['syndrome'])
                  ? 'Syndrome : aucun mensonge'
                  : 'Syndrome : ' . implode(', ', $character['syndrome']);
          }
        ?>
        <div class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($cardTitle, ENT_QUOTES, 'UTF-8') ?>">
          <img src="<?= htmlspecialchars($character['image'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8') ?>">
          <span class="perso-nom"><?= htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div id="col-droite">
    <div class="panel-label">QUESTIONS</div>

    <?php foreach ($questions as $index => $question): ?>
      <?php $currentAnswer = $answers[$index]; ?>
      <div class="question-bloc<?= $currentAnswer !== null ? ' answered' : '' ?>">
        <h2 id="Q<?= $index + 1 ?>"><?= $index + 1 ?>. <?= htmlspecialchars($question, ENT_QUOTES, 'UTF-8') ?></h2>
        <form method="post" class="btn-groupe">
          <input type="hidden" name="action" value="answer">
          <input type="hidden" name="question" value="<?= $index ?>">
          <button id="b<?= $index + 1 ?>O" class="btn-answer <?= $currentAnswer === '1' ? 'is-selected' : '' ?>" type="submit" name="value" value="1">OUI</button>
          <button id="b<?= $index + 1 ?>N" class="btn-answer <?= $currentAnswer === '0' ? 'is-selected' : '' ?>" type="submit" name="value" value="0">NON</button>
        </form>
        <p class="answer-state">
          <?= $currentAnswer === null ? 'Réponse non choisie' : 'Réponse enregistrée : ' . ($currentAnswer === '1' ? 'Oui' : 'Non') ?>
        </p>
      </div>
    <?php endforeach; ?>

    <div class="action-row">
      <form method="post">
        <input type="hidden" name="action" value="reset">
        <input type="submit" value="Cliquez pour rejouer">
      </form>
    </div>

    <div id="message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
  </div>
</div>
<div class="pac-footer">
  <div class="pac-man"></div>
</div>
<script>
window.addEventListener('load', () => {
  const savedScrollY = sessionStorage.getItem('quizScrollY');

  if (savedScrollY !== null) {
    window.scrollTo(0, parseInt(savedScrollY, 10));
    sessionStorage.removeItem('quizScrollY');
  }

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      sessionStorage.setItem('quizScrollY', String(window.scrollY));
    });
  });
});
</script>
</body>
</html>
