<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$attemptId = intval($_GET['attempt_id'] ?? 0);
if (!$attemptId) {
    header('Location: ../public/course_view.php');
    exit;
}

// Fetch attempt details with assessment and course info
$stmt = $pdo->prepare("
    SELECT 
        aa.*,
        a.title as assessment_title,
        a.description as assessment_description,
        a.passing_score,
        a.time_limit,
        a.attempts_allowed,
        a.total_points as assessment_total_points,
        a.question_count,
        c.id as course_id,
        c.title as course_title
    FROM assessment_attempts aa
    JOIN assessments a ON aa.assessment_id = a.id
    JOIN courses c ON a.course_id = c.id
    WHERE aa.id = ? AND aa.user_id = ?
");
$stmt->execute([$attemptId, $u['id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    die('Assessment attempt not found');
}

// Count total attempts by this user for this assessment
$stmt = $pdo->prepare("
    SELECT COUNT(*) as attempt_count 
    FROM assessment_attempts 
    WHERE assessment_id = ? AND user_id = ? AND status = 'completed'
");
$stmt->execute([$attempt['assessment_id'], $u['id']]);
$attemptCount = $stmt->fetchColumn();

// Fetch all questions and answers for this attempt
$stmt = $pdo->prepare("
    SELECT 
        q.id as question_id,
        q.question_text,
        q.option_a,
        q.option_b,
        q.option_c,
        q.option_d,
        q.correct_option,
        q.points,
        q.order_num,
        aa.selected_option,
        aa.is_correct,
        aa.points_earned
    FROM assessment_answers aa
    JOIN assessment_questions q ON aa.question_id = q.id
    WHERE aa.attempt_id = ?
    ORDER BY q.order_num
");
$stmt->execute([$attemptId]);
$questions = $stmt->fetchAll();

// Calculate statistics
$totalQuestions = count($questions);
$correctAnswers = 0;
$totalPoints = 0;
$earnedPoints = 0;

foreach ($questions as $q) {
    $totalPoints += $q['points'];
    $earnedPoints += $q['points_earned'];
    if ($q['is_correct']) {
        $correctAnswers++;
    }
}

$percentageScore = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100) : 0;
$passed = $percentageScore >= $attempt['passing_score'];

// Determine if retry is allowed
$retryAllowed = false;
$retryMessage = '';

if ($percentageScore == 100) {
    // Perfect score - no retry needed
    $retryAllowed = false;
    $retryMessage = 'Perfect score! No need to retry.';
} elseif ($attempt['attempts_allowed'] > 0 && $attemptCount >= $attempt['attempts_allowed']) {
    // Max attempts reached
    $retryAllowed = false;
    $retryMessage = 'Maximum attempts reached.';
} elseif ($passed && $percentageScore < 100) {
    // Passed but not perfect - can retry if attempts remain
    if ($attempt['attempts_allowed'] == 0 || $attemptCount < $attempt['attempts_allowed']) {
        $retryAllowed = true;
        $retryMessage = 'You passed! You can retry to aim for a perfect score.';
    }
} elseif (!$passed) {
    // Failed - can retry if attempts remain
    if ($attempt['attempts_allowed'] == 0 || $attemptCount < $attempt['attempts_allowed']) {
        $retryAllowed = true;
        $retryMessage = 'You can retry the assessment.';
    }
}

// Format time taken
$timeTaken = '';
if ($attempt['started_at'] && $attempt['completed_at']) {
    $start = new DateTime($attempt['started_at']);
    $end = new DateTime($attempt['completed_at']);
    $interval = $start->diff($end);
    
    $parts = [];
    if ($interval->h > 0) $parts[] = $interval->h . ' hr' . ($interval->h > 1 ? 's' : '');
    if ($interval->i > 0) $parts[] = $interval->i . ' min' . ($interval->i > 1 ? 's' : '');
    if ($interval->s > 0) $parts[] = $interval->s . ' sec' . ($interval->s > 1 ? 's' : '');
    
    $timeTaken = implode(' ', $parts);
}

// Helper function to get option letter
function getOptionLetter($index) {
    return chr(65 + $index); // 0->A, 1->B, 2->C, 3->D
}

// Helper function to get option text
function getOptionText($question, $letter) {
    switch($letter) {
        case 'A': return $question['option_a'];
        case 'B': return $question['option_b'];
        case 'C': return $question['option_c'];
        case 'D': return $question['option_d'];
        default: return '';
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assessment Result - CookLabs LMS</title>
    <link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/assessmentresult.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="results-wrapper">
        <!-- Result Card -->
        <div class="result-card">
            <div class="result-header">
                <h2>Assessment Result</h2>
                <p><?= htmlspecialchars($attempt['assessment_title']) ?></p>
                <p class="text-muted">Course: <?= htmlspecialchars($attempt['course_title']) ?></p>
            </div>

            <!-- Score Circle -->
            <div class="score-container">
                <div class="score-circle <?= $passed ? 'passed' : 'failed' ?>">
                    <span class="score-number"><?= $percentageScore ?>%</span>
                    <span class="score-label">Score</span>
                    <div class="score-status">
                        <?php if($passed): ?>
                            <span class="badge-completed"><i class="fas fa-check-circle"></i> PASSED</span>
                        <?php else: ?>
                            <span class="badge-ongoing"><i class="fas fa-times-circle"></i> FAILED</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-value"><?= $correctAnswers ?>/<?= $totalQuestions ?></div>
                    <div class="stat-label">Correct Answers</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $earnedPoints ?>/<?= $totalPoints ?></div>
                    <div class="stat-label">Points Earned</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $timeTaken ?: 'N/A' ?></div>
                    <div class="stat-label">Time Taken</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?= $attempt['passing_score'] ?>%</div>
                    <div class="stat-label">Passing Score</div>
                </div>
            </div>

            <!-- Attempts Info -->
            <?php if ($attempt['attempts_allowed'] > 0): ?>
            <div class="attempts-info">
                <i class="fas fa-redo-alt"></i>
                <div>
                    <p>Attempt <?= $attemptCount ?> of <?= $attempt['attempts_allowed'] ?></p>
                    <small><?= $retryMessage ?></small>
                </div>
            </div>
            <?php endif; ?>

            <!-- Certificate Badge (if passed) -->
            <?php if($passed): ?>
            <div class="certificate-badge">
                <i class="fas fa-certificate"></i>
                <div>
                    <h5 style="margin: 0; color: #07223b;">Congratulations!</h5>
                    <p style="margin: 0; color: #1e4465;">You have successfully passed the assessment.</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="course_view.php?id=<?= $attempt['course_id'] ?>" class="btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Course
                </a>
                
                <?php if($percentageScore == 100): ?>
                    <!-- Perfect score - no retry button -->
                    <span class="btn-disabled" title="Perfect score achieved!">
                        <i class="fas fa-check-circle"></i> Perfect Score!
                    </span>
                <?php elseif($retryAllowed): ?>
                    <!-- Retry allowed (passed but not perfect, or failed with attempts left) -->
                    <a href="take_assessment.php?id=<?= $attempt['assessment_id'] ?>" class="btn-success">
                        <i class="fas fa-redo"></i> Retry
                    </a>
                <?php elseif(!$retryAllowed && $attempt['attempts_allowed'] > 0 && $attemptCount >= $attempt['attempts_allowed']): ?>
                    <!-- Max attempts reached -->
                    <span class="btn-disabled" title="Maximum attempts reached">
                        <i class="fas fa-ban"></i> Max Attempts Reached
                    </span>
                <?php endif; ?>

                <a href="../public/dashboard.php" class="btn-secondary">
                    <i class="fas fa-home"></i> Go to Dashboard
                </a>
            </div>
        </div>

        <!-- Question Review -->
        <div class="result-card">
            <h5 style="border-left: 6px solid #1d6fb0; padding-left: 1rem; margin-bottom: 1.5rem;">
                <i class="fas fa-clipboard-list"></i> Question Review
            </h5>

            <?php foreach($questions as $index => $q): ?>
            <div class="question-item <?= $q['is_correct'] ? 'correct' : 'incorrect' ?>">
                <div class="question-header">
                    <h6 class="question-text">Question <?= $index + 1 ?>: <?= htmlspecialchars($q['question_text']) ?></h6>
                    <span class="question-points">
                        <?= $q['points_earned'] ?> / <?= $q['points'] ?> points
                    </span>
                </div>

                <div class="options-list">
                    <?php for($i = 0; $i < 4; $i++): 
                        $letter = getOptionLetter($i);
                        $optionText = getOptionText($q, $letter);
                        if(empty($optionText)) continue;
                        
                        $isSelected = ($q['selected_option'] === $letter);
                        $isCorrect = ($q['correct_option'] === $letter);
                        $optionClass = '';
                        
                        if($isSelected && $isCorrect) $optionClass = 'correct selected';
                        elseif($isSelected && !$isCorrect) $optionClass = 'incorrect selected';
                        elseif($isCorrect) $optionClass = 'correct';
                        elseif($isSelected) $optionClass = 'selected';
                    ?>
                        <div class="option-item <?= $optionClass ?>">
                            <span class="option-marker"><?= $letter ?>.</span>
                            <?= htmlspecialchars($optionText) ?>
                            <?php if($isCorrect): ?>
                                <span class="correct-badge"><i class="fas fa-check"></i> Correct Answer</span>
                            <?php endif; ?>
                            <?php if($isSelected && !$isCorrect): ?>
                                <span class="correct-badge" style="background: #dc3545; border-color: #a71d2a; box-shadow: 2px 2px 0 #7a151f;">
                                    <i class="fas fa-times"></i> Your Answer
                                </span>
                            <?php elseif($isSelected && $isCorrect): ?>
                                <span class="correct-badge">
                                    <i class="fas fa-check"></i> Your Answer (Correct)
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Kitchen accent -->
        <div class="kitchen-accent">
            <i class="fas fa-cube"></i>
            <i class="fas fa-utensils"></i>
            <i class="fas fa-cube"></i>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>