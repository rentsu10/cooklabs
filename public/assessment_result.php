<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';
require_once __DIR__ . '/../inc/functions.php';

require_login();
$u = current_user();

$attemptId = intval($_GET['attempt_id'] ?? 0);
if (!$attemptId) {
    header('Location: ../index.php');
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
    <link rel="icon" type="image/png" href="../uploads/images/cooklabs-mini-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>/assets/css/sidebar.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        /* ===== SHARP GEOMETRIC RESULTS STYLE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #eaf2fc;
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .lms-sidebar-container {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            z-index: 1000;
        }

        /* Main Content */
        .results-wrapper {
            margin-left: 280px;
            flex: 1;
            padding: 2rem 2.5rem;
            min-height: 100vh;
            overflow-y: auto;
        }

        /* Result Card */
        .result-card {
            background: #ffffff;
            border: 3px solid #1a4b77;
            box-shadow: 12px 12px 0 #123a5e;
            border-radius: 0px;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .result-header {
            border-bottom: 3px solid #2367a3;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .result-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #07223b;
            margin-bottom: 0.5rem;
        }

        .result-header p {
            color: #1e4465;
            font-size: 1rem;
        }

        /* Score Circle */
        .score-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 2rem 0;
        }

        .score-circle {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: #f0f8ff;
            border: 4px solid #1d6fb0;
            box-shadow: 8px 8px 0 #123a5e;
            position: relative;
        }

        .score-circle.passed {
            border-color: #28a745;
            box-shadow: 8px 8px 0 #166b2c;
        }

        .score-circle.failed {
            border-color: #dc3545;
            box-shadow: 8px 8px 0 #a11717;
        }

        .score-number {
            font-size: 3rem;
            font-weight: 700;
            line-height: 1;
            color: #07223b;
        }

        .score-label {
            font-size: 1rem;
            color: #5f6f82;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .score-status {
            margin-top: 0.5rem;
            padding: 0.3rem 1rem;
            font-weight: 700;
            font-size: 1.2rem;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-item {
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 4px 4px 0 #a0c0e0;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1d6fb0;
            line-height: 1;
        }

        .stat-label {
            color: #1e4465;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Attempts Info */
        .attempts-info {
            background: #e7f3ff;
            border: 2px solid #1d6fb0;
            box-shadow: 4px 4px 0 #123a5e;
            padding: 1rem;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .attempts-info i {
            font-size: 1.5rem;
            color: #1d6fb0;
        }

        .attempts-info p {
            margin: 0;
            color: #07223b;
            font-weight: 500;
        }

        .attempts-info small {
            color: #5f6f82;
        }

        /* Question Review */
        .question-item {
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 4px 4px 0 #a0c0e0;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .question-item.correct {
            border-left: 8px solid #28a745;
        }

        .question-item.incorrect {
            border-left: 8px solid #dc3545;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .question-text {
            font-weight: 600;
            color: #07223b;
            margin: 0;
            font-size: 1.1rem;
        }

        .question-points {
            background: #ffffff;
            border: 2px solid #1a4b77;
            box-shadow: 2px 2px 0 #123a5e;
            padding: 0.3rem 1rem;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .options-list {
            margin-top: 1rem;
        }

        .option-item {
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            border: 1px solid #b8d6f5;
            background: #ffffff;
        }

        .option-item.selected {
            border: 3px solid #1d6fb0;
            background: #e7f3ff;
        }

        .option-item.correct {
            border: 3px solid #28a745;
            background: #f0fff0;
        }

        .option-item.incorrect {
            border: 3px solid #dc3545;
            background: #fff0f0;
        }

        .option-marker {
            display: inline-block;
            width: 24px;
            height: 24px;
            text-align: center;
            line-height: 24px;
            font-weight: 700;
            margin-right: 10px;
        }

        .correct-badge {
            background: #28a745;
            border: 2px solid #1e7e34;
            box-shadow: 2px 2px 0 #166b2c;
            color: white;
            padding: 0.2rem 0.5rem;
            font-size: 0.8rem;
            margin-left: 10px;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: #1661a3;
            border: 3px solid #0c314d;
            box-shadow: 4px 4px 0 #0b263b;
            padding: 0.7rem 2rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 0px;
            transition: all 0.1s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #0b263b;
            background: #1a70b5;
            color: white;
        }

        .btn-success {
            background: #28a745;
            border: 3px solid #1e7e34;
            box-shadow: 4px 4px 0 #166b2c;
            padding: 0.7rem 2rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 0px;
            transition: all 0.1s ease;
            border: none;
        }

        .btn-success:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #166b2c;
            background: #34ce57;
            color: white;
        }

        .btn-secondary {
            background: #5f6f82;
            border: 3px solid #3a4553;
            box-shadow: 4px 4px 0 #2f3844;
            padding: 0.7rem 2rem;
            color: white;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 0px;
            transition: all 0.1s ease;
            border: none;
        }

        .btn-secondary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #2f3844;
            background: #6f7f94;
            color: white;
        }

        .btn-disabled {
            background: #6c757d;
            border: 3px solid #5a6268;
            box-shadow: 4px 4px 0 #404040;
            padding: 0.7rem 2rem;
            color: white;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border-radius: 0px;
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Certificate Badge */
        .certificate-badge {
            background: #ffc107;
            border: 3px solid #b88f1f;
            box-shadow: 4px 4px 0 #8f6f1a;
            padding: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1rem 0;
        }

        .certificate-badge i {
            font-size: 2rem;
            color: #07223b;
        }

        .badge-completed {
            background: #28a745;
            border: 2px solid #1e7e34;
            box-shadow: 2px 2px 0 #166b2c;
            color: white;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-ongoing {
            background: #dc3545;
            border: 2px solid #a71d2a;
            box-shadow: 2px 2px 0 #7a151f;
            color: white;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
            font-weight: 700;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .kitchen-accent {
            opacity: 0.4;
            text-align: center;
            margin-top: 2rem;
        }

        .kitchen-accent i {
            color: #1d6fb0;
            margin: 0 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .results-wrapper {
                margin-left: 0;
                padding: 1rem;
            }
            .lms-sidebar-container {
                position: relative;
                width: 100%;
                height: auto;
            }
        }
    </style>
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

                <a href="../index.php" class="btn-secondary">
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