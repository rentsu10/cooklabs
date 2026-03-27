<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

$userId = $_SESSION['user']['id'];
$assessmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$assessmentId) {
    die('Invalid assessment ID');
}

// Fetch assessment details
$stmt = $pdo->prepare("
    SELECT a.*, c.title as course_title, c.id as course_id,
           (SELECT SUM(points) FROM assessment_questions WHERE assessment_id = a.id) as total_points
    FROM assessments a
    JOIN courses c ON a.course_id = c.id
    WHERE a.id = ?
");
$stmt->execute([$assessmentId]);
$assessment = $stmt->fetch();

if (!$assessment) {
    die('Assessment not found');
}

// Check if user is enrolled in the course
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $assessment['course_id']]);
if (!$stmt->fetch()) {
    die('You must be enrolled in this course to take the assessment');
}

// Fetch all questions
$stmt = $pdo->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY order_num");
$stmt->execute([$assessmentId]);
$questions = $stmt->fetchAll();

if (empty($questions)) {
    die('No questions found for this assessment');
}

// Check for existing in-progress attempt
$stmt = $pdo->prepare("
    SELECT * FROM assessment_attempts 
    WHERE assessment_id = ? AND user_id = ? AND status = 'in_progress'
    ORDER BY started_at DESC LIMIT 1
");
$stmt->execute([$assessmentId, $userId]);
$activeAttempt = $stmt->fetch();

// Handle start new attempt or resume
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_assessment'])) {
    // Check attempt limits
    if ($assessment['attempts_allowed']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM assessment_attempts WHERE assessment_id = ? AND user_id = ?");
        $stmt->execute([$assessmentId, $userId]);
        $attemptCount = $stmt->fetchColumn();
        
        if ($attemptCount >= $assessment['attempts_allowed']) {
            $_SESSION['error'] = 'Maximum attempts reached';
            header("Location: course_view.php?id={$assessment['course_id']}");
            exit;
        }
    }
    
    // Create new attempt
    $stmt = $pdo->prepare("
        INSERT INTO assessment_attempts (assessment_id, user_id, attempt_number, status, started_at)
        VALUES (?, ?, ?, 'in_progress', NOW())
    ");
    $attemptNumber = ($activeAttempt ? $activeAttempt['attempt_number'] + 1 : 1);
    $stmt->execute([$assessmentId, $userId, $attemptNumber]);
    $attemptId = $pdo->lastInsertId();
    
    header("Location: take_assessment.php?id=$assessmentId&attempt=$attemptId");
    exit;
}

$attemptId = isset($_GET['attempt']) ? (int)$_GET['attempt'] : ($activeAttempt ? $activeAttempt['id'] : 0);

if (!$attemptId) {
    // Show start screen
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Start Assessment - <?= htmlspecialchars($assessment['title']) ?></title>
        <link rel="icon" type="image/png" href="../uploads/images/ieti-logo.png">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Inter', sans-serif;
                background: #eaf2fc;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .start-card {
                background: white;
                border: 3px solid #1a4b77;
                box-shadow: 16px 16px 0 #123a5e;
                padding: 3rem;
                max-width: 600px;
                width: 90%;
                border-radius: 0;
            }
            h1 {
                font-size: 2rem;
                font-weight: 700;
                color: #07223b;
                border-left: 8px solid #1d6fb0;
                padding-left: 1.2rem;
                margin-bottom: 1.5rem;
            }
            .info-item {
                background: #f0f8ff;
                border: 2px solid #b8d6f5;
                box-shadow: 4px 4px 0 #a0c0e0;
                padding: 1rem;
                margin-bottom: 1rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .info-label {
                font-weight: 600;
                color: #0a314b;
            }
            .info-value {
                font-weight: 700;
                color: #1d6fb0;
            }
            .btn-start {
                background: #28a745;
                border: 3px solid #1e7e34;
                box-shadow: 6px 6px 0 #166b2c;
                padding: 1rem 2rem;
                color: white;
                font-weight: 700;
                font-size: 1.2rem;
                cursor: pointer;
                border: none;
                width: 100%;
                border-radius: 0;
                transition: all 0.1s ease;
            }
            .btn-start:hover {
                transform: translate(-2px, -2px);
                box-shadow: 8px 8px 0 #166b2c;
                background: #34ce57;
            }
            .warning-note {
                background: #fff9e0;
                border: 2px solid #b88f1f;
                box-shadow: 4px 4px 0 #8f6f1a;
                padding: 1rem;
                margin-top: 1.5rem;
                color: #5f4c0e;
            }
        </style>
    </head>
    <body>
        <div class="start-card">
            <h1><?= htmlspecialchars($assessment['title']) ?></h1>
            
            <div class="info-item">
                <span class="info-label">Course:</span>
                <span class="info-value"><?= htmlspecialchars($assessment['course_title']) ?></span>
            </div>
            
            <div class="info-item">
                <span class="info-label">Questions:</span>
                <span class="info-value"><?= count($questions) ?></span>
            </div>
            
            <?php if ($assessment['time_limit']): ?>
            <div class="info-item">
                <span class="info-label">Time Limit:</span>
                <span class="info-value"><?= $assessment['time_limit'] ?> minutes</span>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <span class="info-label">Passing Score:</span>
                <span class="info-value"><?= $assessment['passing_score'] ?>%</span>
            </div>
            
            <?php if ($assessment['attempts_allowed']): ?>
            <div class="info-item">
                <span class="info-label">Attempts Allowed:</span>
                <span class="info-value"><?= $assessment['attempts_allowed'] ?></span>
            </div>
            <?php endif; ?>
            
            <div class="warning-note">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Important:</strong> Once you start, you must complete the assessment in one sitting. 
                Do not refresh or close the browser.
            </div>
            
            <form method="POST">
                <input type="hidden" name="start_assessment" value="1">
                <button type="submit" class="btn-start mt-4">
                    <i class="fas fa-play-circle"></i> Start Assessment
                </button>
            </form>
            
            <div class="text-center mt-3">
                <a href="course_view.php?id=<?= $assessment['course_id'] ?>" class="text-muted">← Back to Course</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Fetch attempt details
$stmt = $pdo->prepare("
    SELECT * FROM assessment_attempts 
    WHERE id = ? AND user_id = ? AND status = 'in_progress'
");
$stmt->execute([$attemptId, $userId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    $_SESSION['error'] = 'No active assessment found';
    header("Location: course_view.php?id={$assessment['course_id']}");
    exit;
}

// Fetch saved answers
$stmt = $pdo->prepare("SELECT question_id, selected_option FROM assessment_answers WHERE attempt_id = ?");
$stmt->execute([$attemptId]);
$savedAnswers = [];
while ($row = $stmt->fetch()) {
    $savedAnswers[$row['question_id']] = $row['selected_option'];
}

// Handle answer submission via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_answer'])) {
    $questionId = (int)$_POST['question_id'];
    $selectedOption = $_POST['selected_option'] ?? '';
    
    // Get question details
    $stmt = $pdo->prepare("SELECT * FROM assessment_questions WHERE id = ?");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch();
    
    if ($question) {
        $isCorrect = ($selectedOption === $question['correct_option']);
        
        // Check if answer exists
        $stmt = $pdo->prepare("SELECT id FROM assessment_answers WHERE attempt_id = ? AND question_id = ?");
        $stmt->execute([$attemptId, $questionId]);
        
        if ($stmt->fetch()) {
            // Update existing answer
            $stmt = $pdo->prepare("
                UPDATE assessment_answers 
                SET selected_option = ?, is_correct = ?, points_earned = ?, answered_at = NOW()
                WHERE attempt_id = ? AND question_id = ?
            ");
            $stmt->execute([$selectedOption, $isCorrect, $isCorrect ? $question['points'] : 0, $attemptId, $questionId]);
        } else {
            // Insert new answer
            $stmt = $pdo->prepare("
                INSERT INTO assessment_answers (attempt_id, question_id, selected_option, is_correct, points_earned)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$attemptId, $questionId, $selectedOption, $isCorrect, $isCorrect ? $question['points'] : 0]);
        }
        
        // Recalculate total score
        $stmt = $pdo->prepare("
            SELECT SUM(points_earned) as earned, COUNT(*) as answered 
            FROM assessment_answers WHERE attempt_id = ?
        ");
        $stmt->execute([$attemptId]);
        $stats = $stmt->fetch();
        
        $stmt = $pdo->prepare("
            UPDATE assessment_attempts 
            SET earned_points = ?, 
                score = ROUND((? / ?) * 100, 2)
            WHERE id = ?
        ");
        $stmt->execute([$stats['earned'], $stats['earned'], $assessment['total_points'], $attemptId]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    
    // Calculate final score
    $stmt = $pdo->prepare("
        SELECT SUM(points_earned) as earned FROM assessment_answers WHERE attempt_id = ?
    ");
    $stmt->execute([$attemptId]);
    $earned = $stmt->fetchColumn();
    
    $score = round(($earned / $assessment['total_points']) * 100, 2);
    $passed = $score >= $assessment['passing_score'] ? 1 : 0;
    
    // Update attempt
    $stmt = $pdo->prepare("
        UPDATE assessment_attempts 
        SET status = 'completed', completed_at = NOW(), 
            earned_points = ?, score = ?
        WHERE id = ?
    ");
    $stmt->execute([$earned, $score, $attemptId]);
    
    // Redirect to results page
    header("Location: assessment_result.php?attempt_id=$attemptId");
    exit;
}

// Handle time expiry auto-submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['time_expired'])) {
    // Same as submit_assessment but forced
    $stmt = $pdo->prepare("
        SELECT SUM(points_earned) as earned FROM assessment_answers WHERE attempt_id = ?
    ");
    $stmt->execute([$attemptId]);
    $earned = $stmt->fetchColumn();
    
    $score = round(($earned / $assessment['total_points']) * 100, 2);
    
    $stmt = $pdo->prepare("
        UPDATE assessment_attempts 
        SET status = 'timeout', completed_at = NOW(), 
            earned_points = ?, score = ?
        WHERE id = ?
    ");
    $stmt->execute([$earned, $score, $attemptId]);
    
    header("Location: assessment_result.php?attempt_id=$attemptId");
    exit;
}

// Calculate answered count
$answeredCount = count($savedAnswers);
$totalQuestions = count($questions);

// Randomize questions
shuffle($questions);
$firstQuestion = $questions[0];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taking Assessment - <?= htmlspecialchars($assessment['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #1a2b3e;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .assessment-header {
            background: #0a1a2a;
            border-bottom: 3px solid #1d6fb0;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }
        .assessment-title {
            font-size: 1.2rem;
            font-weight: 600;
        }
        .timer {
            background: #1661a3;
            border: 2px solid #0c314d;
            box-shadow: 3px 3px 0 #0b263b;
            padding: 0.5rem 1.5rem;
            font-weight: 700;
            font-size: 1.3rem;
        }
        .progress-info {
            background: #1d6fb0;
            border: 2px solid #0f4980;
            box-shadow: 3px 3px 0 #0a3458;
            padding: 0.5rem 1.5rem;
        }
        .main-content {
            flex: 1;
            display: flex;
            padding: 2rem;
            gap: 2rem;
            min-height: 0;
        }
        .question-section {
            flex: 2;
            background: white;
            border: 3px solid #1a4b77;
            box-shadow: 8px 8px 0 #123a5e;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 3px solid #2367a3;
        }
        .question-number {
            background: #1d6fb0;
            color: white;
            padding: 0.3rem 1rem;
            font-weight: 700;
            border: 2px solid #0f4980;
            box-shadow: 2px 2px 0 #0a3458;
        }
        .question-points {
            color: #1d6fb0;
            font-weight: 700;
        }
        .question-text {
            font-size: 1.3rem;
            font-weight: 600;
            color: #07223b;
            margin-bottom: 2rem;
        }
        .options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .option-item {
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 3px 3px 0 #a0c0e0;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.1s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .option-item:hover {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #a0c0e0;
        }
        .option-item.selected {
            background: #c6e0ff;
            border-color: #1d6fb0;
            box-shadow: 3px 3px 0 #1d6fb0;
        }
        .option-letter {
            background: white;
            border: 2px solid #1d6fb0;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #1d6fb0;
        }
        .option-item.selected .option-letter {
            background: #1d6fb0;
            color: white;
        }
        .nav-section {
            flex: 1;
            background: white;
            border: 3px solid #1a4b77;
            box-shadow: 8px 8px 0 #123a5e;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
        }
        .nav-title {
            font-weight: 700;
            color: #07223b;
            margin-bottom: 1rem;
            border-left: 6px solid #1d6fb0;
            padding-left: 1rem;
        }
        .question-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 0.4rem;
            margin-bottom: 1.5rem;
        }
        .question-nav-btn {
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 2px 2px 0 #a0c0e0;
            width: 100%;
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.1s ease;
            color: #07223b;
            font-size: 0.8rem;
        }
        .question-nav-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: 4px 4px 0 #a0c0e0;
        }
        .question-nav-btn.answered {
            background: #28a745;
            border-color: #1e7e34;
            color: white;
            box-shadow: 2px 2px 0 #166b2c;
        }
        .question-nav-btn.current {
            border: 3px solid #1d6fb0;
            transform: scale(1.05);
        }
        .nav-buttons {
            display: flex;
            gap: 1rem;
            margin-top: auto;
        }
        .btn-nav {
            flex: 1;
            background: #1661a3;
            border: 2px solid #0c314d;
            box-shadow: 3px 3px 0 #0b263b;
            color: white;
            padding: 0.8rem;
            cursor: pointer;
            border: none;
            font-weight: 600;
            border-radius: 0;
            transition: all 0.1s ease;
        }
        .btn-nav:hover:not(:disabled) {
            transform: translate(-2px, -2px);
            box-shadow: 5px 5px 0 #0b263b;
            background: #1a70b5;
        }
        .btn-nav:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn-submit {
            background: #28a745;
            border: 3px solid #1e7e34;
            box-shadow: 4px 4px 0 #166b2c;
            color: white;
            padding: 1rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            border-radius: 0;
            transition: all 0.1s ease;
            margin-top: 1rem;
        }
        .btn-submit:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #166b2c;
            background: #34ce57;
        }
        .answered-count {
            color: #1d6fb0;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="assessment-header">
        <div class="assessment-title">
            <i class="fas fa-file-alt"></i> <?= htmlspecialchars($assessment['title']) ?>
        </div>
        <div class="progress-info">
            <i class="fas fa-check-circle"></i> <span id="answeredCount"><?= $answeredCount ?></span>/<?= $totalQuestions ?> answered
        </div>
        <?php if ($assessment['time_limit']): ?>
        <div class="timer" id="timer">
            <?= $assessment['time_limit'] ?>:00
        </div>
        <?php endif; ?>
    </div>

    <div class="main-content">
        <div class="question-section" id="questionSection">
            <div class="question-header">
                <span class="question-number" id="questionNumber">Question 1 of <?= $totalQuestions ?></span>
                <span class="question-points" id="questionPoints"><?= $firstQuestion['points'] ?> points</span>
            </div>
            <div class="question-text" id="questionText">
                <?= htmlspecialchars($firstQuestion['question_text']) ?>
            </div>
            <div class="options" id="optionsContainer">
                <!-- Options will be populated by JavaScript -->
            </div>
        </div>

        <div class="nav-section">
            <div class="nav-title">Question Navigator</div>
            <div class="question-grid" id="questionGrid">
                <!-- Grid will be populated by JavaScript -->
            </div>
            
            <div class="nav-buttons">
                <button class="btn-nav" id="prevBtn" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <button class="btn-nav" id="nextBtn">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <button class="btn-submit" id="submitBtn">
                <i class="fas fa-check-circle"></i> Submit Assessment
            </button>
        </div>
    </div>

    <script>
        const questions = <?= json_encode($questions) ?>;
        const totalQuestions = <?= $totalQuestions ?>;
        const savedAnswers = <?= json_encode($savedAnswers) ?>;
        let currentIndex = 0;
        let answers = {...savedAnswers};
        let autoSaveTimer;
        let timeLeft = <?= $assessment['time_limit'] ? $assessment['time_limit'] * 60 : 0 ?>;
        let formChanged = false;
        let isSubmitting = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            renderQuestion(currentIndex);
            renderQuestionGrid();
            updateAnsweredCount();
            
            // Start timer if time limit exists
            <?php if ($assessment['time_limit']): ?>
            startTimer();
            <?php endif; ?>
            
            // Auto-save every 10 seconds
            autoSaveTimer = setInterval(autoSave, 10000);
            
            // Warn on page leave
            window.addEventListener('beforeunload', function(e) {
                if (formChanged && !isSubmitting) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved answers. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
        });

        function renderQuestion(index) {
            const q = questions[index];
            document.getElementById('questionNumber').textContent = `Question ${index + 1} of ${totalQuestions}`;
            document.getElementById('questionPoints').textContent = `${q.points} points`;
            document.getElementById('questionText').textContent = q.question_text;
            
            const options = ['A', 'B', 'C', 'D'];
            const optionsHtml = options.map(letter => {
                if (!q[`option_${letter.toLowerCase()}`]) return '';
                const isSelected = answers[q.id] === letter;
                return `
                    <div class="option-item ${isSelected ? 'selected' : ''}" onclick="selectOption('${letter}')">
                        <div class="option-letter">${letter}</div>
                        <div>${q[`option_${letter.toLowerCase()}`]}</div>
                    </div>
                `;
            }).join('');
            
            document.getElementById('optionsContainer').innerHTML = optionsHtml;
            
            // Update navigation buttons
            document.getElementById('prevBtn').disabled = index === 0;
            document.getElementById('nextBtn').disabled = index === totalQuestions - 1;
            
            // Update grid current highlight
            document.querySelectorAll('.question-nav-btn').forEach((btn, i) => {
                if (i === index) {
                    btn.classList.add('current');
                } else {
                    btn.classList.remove('current');
                }
            });
        }

        function selectOption(letter) {
            const q = questions[currentIndex];
            const questionId = q.id;
            
            // Update UI
            document.querySelectorAll('.option-item').forEach(opt => {
                const optLetter = opt.querySelector('.option-letter').textContent;
                if (optLetter === letter) {
                    opt.classList.add('selected');
                } else {
                    opt.classList.remove('selected');
                }
            });
            
            // Update answers
            answers[questionId] = letter;
            formChanged = true;
            
            // Update grid
            const gridBtn = document.querySelector(`.question-nav-btn[data-index="${currentIndex}"]`);
            if (gridBtn) {
                gridBtn.classList.add('answered');
            }
            
            // Save immediately
            saveAnswer(questionId, letter);
            updateAnsweredCount();
        }

        function saveAnswer(questionId, letter) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'save_answer': '1',
                    'question_id': questionId,
                    'selected_option': letter
                })
            }).catch(error => console.error('Error saving:', error));
        }

        function autoSave() {
            if (formChanged) {
                console.log('Auto-saving...');
                formChanged = false;
            }
        }

        function renderQuestionGrid() {
            const grid = document.getElementById('questionGrid');
            let html = '';
            
            questions.forEach((q, index) => {
                const answered = answers[q.id] ? 'answered' : '';
                html += `<div class="question-nav-btn ${answered}" data-index="${index}" onclick="goToQuestion(${index})">${index + 1}</div>`;
            });
            
            grid.innerHTML = html;
        }

        function goToQuestion(index) {
            currentIndex = index;
            renderQuestion(currentIndex);
        }

        function updateAnsweredCount() {
            const count = Object.keys(answers).length;
            document.getElementById('answeredCount').textContent = count;
        }

        document.getElementById('prevBtn').addEventListener('click', function() {
            if (currentIndex > 0) {
                currentIndex--;
                renderQuestion(currentIndex);
            }
        });

        document.getElementById('nextBtn').addEventListener('click', function() {
            if (currentIndex < totalQuestions - 1) {
                currentIndex++;
                renderQuestion(currentIndex);
            }
        });

        document.getElementById('submitBtn').addEventListener('click', function() {
            const answered = Object.keys(answers).length;
            if (answered < totalQuestions) {
                if (!confirm(`You have only answered ${answered} out of ${totalQuestions} questions. Are you sure you want to submit?`)) {
                    return;
                }
            } else {
                if (!confirm('Are you sure you want to submit your assessment?')) {
                    return;
                }
            }
            
            isSubmitting = true;
            clearInterval(autoSaveTimer);
            
            const form = document.createElement('form');
            form.method = 'POST';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'submit_assessment';
            input.value = '1';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        });

        function startTimer() {
            const timerDisplay = document.getElementById('timer');
            
            const timer = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    // Auto-submit when time expires
                    const form = document.createElement('form');
                    form.method = 'POST';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'time_expired';
                    input.value = '1';
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                    return;
                }
                
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                
                // Warning when 5 minutes left
                if (timeLeft === 300) {
                    alert('5 minutes remaining!');
                }
            }, 1000);
        }
    </script>
</body>
</html>