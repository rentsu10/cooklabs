<?php
require_once __DIR__ . '/../inc/config.php';
require_once __DIR__ . '/../inc/auth.php';

require_login();

// Only admins and proponents can access this page
if (!is_admin() && !is_proponent() && !is_superadmin()) {
    http_response_code(403);
    exit('Access denied');
}

$act = $_GET['act'] ?? '';
$courseId = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$courseId) {
    die('Invalid course ID');
}

// Fetch course info
$stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();
if (!$course) {
    die('Course not found');
}

// Check if user can edit this course
if (!is_admin() && !is_superadmin()) {
    $stmt = $pdo->prepare("SELECT id FROM courses WHERE id = ? AND proponent_id = ?");
    $stmt->execute([$courseId, $_SESSION['user']['id']]);
    if (!$stmt->fetch()) {
        die('Access denied');
    }
}

// Check if assessment already exists for this course
$stmt = $pdo->prepare("SELECT * FROM assessments WHERE course_id = ?");
$stmt->execute([$courseId]);
$assessment = $stmt->fetch();

// Handle form submission for assessment settings and questions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_assessment'])) {
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $timeLimit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
    $attemptsAllowed = !empty($_POST['attempts_allowed']) ? (int)$_POST['attempts_allowed'] : null;
    $passingScore = (int)($_POST['passing_score'] ?? 75);
    
    // Validate
    $errors = [];
    if (empty($title)) $errors[] = 'Title is required';
    if ($passingScore < 0 || $passingScore > 100) $errors[] = 'Passing score must be between 0 and 100';
    
    if (empty($errors)) {
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            if ($assessment) {
                // Update existing assessment
                $stmt = $pdo->prepare("
                    UPDATE assessments 
                    SET title = ?, description = ?, time_limit = ?, attempts_allowed = ?, passing_score = ?, updated_at = NOW()
                    WHERE course_id = ?
                ");
                $stmt->execute([$title, $description, $timeLimit, $attemptsAllowed, $passingScore, $courseId]);
                $assessmentId = $assessment['id'];
                
                // Delete existing questions
                $stmt = $pdo->prepare("DELETE FROM assessment_questions WHERE assessment_id = ?");
                $stmt->execute([$assessmentId]);
                
            } else {
                // Insert new assessment
                $stmt = $pdo->prepare("
                    INSERT INTO assessments (course_id, title, description, time_limit, attempts_allowed, passing_score, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$courseId, $title, $description, $timeLimit, $attemptsAllowed, $passingScore, $_SESSION['user']['id']]);
                $assessmentId = $pdo->lastInsertId();
            }
            
            // Process questions
            $questionCount = 0;
            if (isset($_POST['questions']) && is_array($_POST['questions'])) {
                foreach ($_POST['questions'] as $index => $q) {
                    $questionText = trim($q['text'] ?? '');
                    $optionA = trim($q['option_a'] ?? '');
                    $optionB = trim($q['option_b'] ?? '');
                    $optionC = trim($q['option_c'] ?? '');
                    $optionD = trim($q['option_d'] ?? '');
                    $correctOption = $q['correct_option'] ?? 'A';
                    $points = (int)($q['points'] ?? 1);
                    
                    // Skip empty questions
                    if (empty($questionText) || empty($optionA) || empty($optionB)) {
                        continue;
                    }
                    
                    if ($questionCount >= 50) {
                        $_SESSION['warning'] = 'Maximum 50 questions allowed. Extra questions were ignored.';
                        break;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO assessment_questions (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_option, points, order_num)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$assessmentId, $questionText, $optionA, $optionB, $optionC, $optionD, $correctOption, $points, $index + 1]);
                    $questionCount++;
                }
            }
            
            // Update question count in assessments table
            $stmt = $pdo->prepare("UPDATE assessments SET question_count = ? WHERE id = ?");
            $stmt->execute([$questionCount, $assessmentId]);
            
            $pdo->commit();
            $_SESSION['success'] = 'Assessment and questions saved successfully';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = 'Error saving assessment: ' . $e->getMessage();
        }
        
        header("Location: assessment_crud.php?course_id=$courseId");
        exit;
    } else {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

// Fetch questions if assessment exists
$questions = [];
if ($assessment) {
    $stmt = $pdo->prepare("SELECT * FROM assessment_questions WHERE assessment_id = ? ORDER BY order_num");
    $stmt->execute([$assessment['id']]);
    $questions = $stmt->fetchAll();
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Assessment Builder - <?= htmlspecialchars($course['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #eaf2fc;
            display: flex;
        }
        .lms-sidebar-container {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            z-index: 1000;
        }
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 2rem;
            min-height: 100vh;
        }
        .page-header {
            margin-bottom: 2rem;
        }
        .page-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: #07223b;
            border-left: 8px solid #1d6fb0;
            padding-left: 1.2rem;
            margin: 0 0 0.5rem 0;
        }
        .page-header p {
            color: #1e4465;
            margin-left: 2rem;
            font-size: 1rem;
        }
        .form-card {
            background: white;
            border: 3px solid #1a4b77;
            box-shadow: 12px 12px 0 #123a5e;
            padding: 2rem;
            border-radius: 0;
            max-width: 1000px;
            margin-bottom: 2rem;
        }
        .form-card h3 {
            margin-bottom: 1.5rem;
            color: #07223b;
            border-left: 6px solid #1d6fb0;
            padding-left: 1rem;
            font-size: 1.3rem;
        }
        .form-label {
            font-weight: 600;
            color: #0a314b;
            margin-bottom: 0.3rem;
            display: block;
        }
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #1d6fb0;
            background: white;
            border-radius: 0;
            margin-bottom: 1rem;
            font-family: 'Inter', sans-serif;
        }
        .form-control:focus {
            border-color: #0f4980;
            outline: none;
            background: #f0f8ff;
        }
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        .btn-primary {
            background: #1661a3;
            border: 3px solid #0c314d;
            box-shadow: 4px 4px 0 #0b263b;
            padding: 0.8rem 2rem;
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            border-radius: 0;
            font-size: 1rem;
        }
        .btn-primary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #0b263b;
            background: #1a70b5;
        }
        .btn-secondary {
            background: white;
            border: 3px solid #0f3d5e;
            box-shadow: 4px 4px 0 #123a57;
            padding: 0.8rem 2rem;
            color: #0a314b;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            border-radius: 0;
            font-size: 1rem;
        }
        .btn-secondary:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #123a57;
            background: #f0f8ff;
        }
        .btn-add-question {
            background: #28a745;
            border: 3px solid #1e7e34;
            box-shadow: 4px 4px 0 #166b2c;
            padding: 0.6rem 1.5rem;
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            border-radius: 0;
            font-size: 0.95rem;
        }
        .btn-add-question:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #166b2c;
            background: #34ce57;
        }
        .btn-remove-question {
            background: #b71c1c;
            border: 3px solid #8a1515;
            box-shadow: 4px 4px 0 #5a0e0e;
            padding: 0.5rem 1rem;
            color: white;
            border: none;
            font-weight: 600;
            cursor: pointer;
            border-radius: 0;
            font-size: 0.8rem;
            width: 100%;
        }
        .btn-remove-question:hover {
            transform: translate(-2px, -2px);
            box-shadow: 6px 6px 0 #5a0e0e;
            background: #c62828;
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 2px solid;
            box-shadow: 4px 4px 0 rgba(0,0,0,0.1);
            border-radius: 0;
            max-width: 1000px;
        }
        .alert-success {
            background: #e8f5e9;
            border-color: #2e7d32;
            box-shadow: 4px 4px 0 #1b5e20;
            color: #1b5e20;
        }
        .alert-danger {
            background: #ffebee;
            border-color: #b71c1c;
            box-shadow: 4px 4px 0 #7a1a1a;
            color: #b71c1c;
        }
        .alert-warning {
            background: #fff9e0;
            border-color: #b88f1f;
            box-shadow: 4px 4px 0 #8f6f1a;
            color: #5f4c0e;
        }
        .info-note {
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 3px 3px 0 #a0c0e0;
            padding: 0.8rem;
            margin-top: 1rem;
            color: #1e4465;
        }
        .info-note i {
            color: #1d6fb0;
            margin-right: 0.5rem;
        }
        .question-item {
            background: #f0f8ff;
            border: 2px solid #b8d6f5;
            box-shadow: 3px 3px 0 #a0c0e0;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
        }
        .question-number {
            position: absolute;
            top: -12px;
            left: 10px;
            background: #1d6fb0;
            color: white;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            border: 2px solid #0f4980;
            box-shadow: 2px 2px 0 #0a3458;
        }
        .options-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .button-bar-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 3px solid #2367a3;
        }
        .right-buttons {
            display: flex;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="lms-sidebar-container">
        <?php include __DIR__ . '/../inc/sidebar.php'; ?>
    </div>

    <div class="main-content">
        <div class="page-header">
            <h2>Assessment Builder</h2>
            <p>Course: <strong><?= htmlspecialchars($course['title']) ?></strong></p>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['warning'])): ?>
            <div class="alert alert-warning"><?= $_SESSION['warning']; unset($_SESSION['warning']); ?></div>
        <?php endif; ?>

        <form method="POST" id="assessmentForm">
            <input type="hidden" name="save_assessment" value="1">
            
            <!-- Assessment Settings Card -->
            <div class="form-card">
                <h3><i class="fas fa-file-alt" style="color: #1d6fb0; margin-right: 8px;"></i> Assessment Settings</h3>
                
                <div>
                    <label class="form-label">Assessment Title</label>
                    <input type="text" name="title" class="form-control" 
                           value="<?= $assessment ? htmlspecialchars($assessment['title']) : '' ?>" 
                           placeholder="e.g., Module 1 Final Quiz" required>
                </div>

                <div>
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" class="form-control" 
                              placeholder="Brief description of the assessment"><?= $assessment ? htmlspecialchars($assessment['description']) : '' ?></textarea>
                </div>

                <div class="row">
                    <div>
                        <label class="form-label">Time Limit (minutes)</label>
                        <input type="number" name="time_limit" class="form-control" 
                               value="<?= $assessment ? $assessment['time_limit'] : '' ?>" 
                               placeholder="Leave blank for no limit" min="1">
                    </div>

                    <div>
                        <label class="form-label">Attempts Allowed</label>
                        <input type="number" name="attempts_allowed" class="form-control" 
                               value="<?= $assessment ? $assessment['attempts_allowed'] : '' ?>" 
                               placeholder="Leave blank for unlimited" min="1">
                    </div>

                    <div>
                        <label class="form-label">Passing Score (%)</label>
                        <input type="number" name="passing_score" class="form-control" 
                               value="<?= $assessment ? $assessment['passing_score'] : '75' ?>" 
                               min="0" max="100" required>
                    </div>
                </div>
            </div>

            <!-- Questions Section -->
            <div class="form-card" style="margin-top: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="margin: 0; border-left: 6px solid #1d6fb0; padding-left: 1rem;">
                        <i class="fas fa-list" style="color: #1d6fb0; margin-right: 8px;"></i>
                        Questions (<span id="questionCount">0</span>/50)
                    </h3>
                </div>

                <div id="questionsContainer">
                    <?php if (!empty($questions)): ?>
                        <?php foreach ($questions as $index => $q): ?>
                            <div class="question-item" data-index="<?= $index ?>">
                                <div class="question-number"><?= $index + 1 ?></div>
                                <input type="hidden" name="questions[<?= $index ?>][id]" value="<?= $q['id'] ?>">
                                
                                <div style="margin-bottom: 1rem;">
                                    <label class="form-label">Question <?= $index + 1 ?></label>
                                    <textarea name="questions[<?= $index ?>][text]" class="form-control" rows="2" placeholder="Enter your question here..." required><?= htmlspecialchars($q['question_text']) ?></textarea>
                                </div>

                                <div class="options-grid">
                                    <div>
                                        <label class="form-label">Option A</label>
                                        <input type="text" name="questions[<?= $index ?>][option_a]" class="form-control" placeholder="Enter option A" value="<?= htmlspecialchars($q['option_a']) ?>" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Option B</label>
                                        <input type="text" name="questions[<?= $index ?>][option_b]" class="form-control" placeholder="Enter option B" value="<?= htmlspecialchars($q['option_b']) ?>" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Option C (Optional)</label>
                                        <input type="text" name="questions[<?= $index ?>][option_c]" class="form-control" placeholder="Enter option C" value="<?= htmlspecialchars($q['option_c']) ?>">
                                    </div>
                                    <div>
                                        <label class="form-label">Option D (Optional)</label>
                                        <input type="text" name="questions[<?= $index ?>][option_d]" class="form-control" placeholder="Enter option D" value="<?= htmlspecialchars($q['option_d']) ?>">
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                                    <div>
                                        <label class="form-label">Correct Option</label>
                                        <select name="questions[<?= $index ?>][correct_option]" class="form-control" required>
                                            <option value="A" <?= $q['correct_option'] == 'A' ? 'selected' : '' ?>>A</option>
                                            <option value="B" <?= $q['correct_option'] == 'B' ? 'selected' : '' ?>>B</option>
                                            <option value="C" <?= $q['correct_option'] == 'C' ? 'selected' : '' ?>>C</option>
                                            <option value="D" <?= $q['correct_option'] == 'D' ? 'selected' : '' ?>>D</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label">Points</label>
                                        <input type="number" name="questions[<?= $index ?>][points]" class="form-control" value="<?= $q['points'] ?>" min="1" required>
                                    </div>
                                    <div style="display: flex; align-items: flex-end;">
                                        <button type="button" class="btn-remove-question" onclick="removeQuestion(this)">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div id="noQuestionsMessage" class="info-note" style="text-align: center; padding: 2rem; <?= !empty($questions) ? 'display: none;' : '' ?>">
                    <i class="fas fa-question-circle" style="font-size: 2rem; margin-bottom: 0.5rem; display: block;"></i>
                    <p>No questions yet. Click "Add Question" to start building your assessment.</p>
                </div>

                <!-- Bottom Button Bar -->
                <div class="button-bar-bottom">
                    <button type="button" id="addQuestionBtn" class="btn-add-question">
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                    
                    <div class="right-buttons">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Save Assessment
                        </button>
                        <a href="courses_crud.php" class="btn-secondary" id="cancelBtn">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>
        let questionIndex = <?= count($questions) ?>;
        const maxQuestions = 50;
        let formChanged = false;
        let isSubmitting = false;
        
        // Track form changes
        const form = document.getElementById('assessmentForm');
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('change', () => {
                formChanged = true;
            });
            input.addEventListener('keyup', () => {
                formChanged = true;
            });
        });
        
        // Check if a question has duplicate options
        function hasDuplicateOptions(questionItem) {
            const options = [];
            
            // Get all option inputs for this question
            const optionA = questionItem.querySelector('input[name*="[option_a]"]')?.value.trim();
            const optionB = questionItem.querySelector('input[name*="[option_b]"]')?.value.trim();
            const optionC = questionItem.querySelector('input[name*="[option_c]"]')?.value.trim();
            const optionD = questionItem.querySelector('input[name*="[option_d]"]')?.value.trim();
            
            // Add non-empty options to array
            if (optionA) options.push(optionA);
            if (optionB) options.push(optionB);
            if (optionC) options.push(optionC);
            if (optionD) options.push(optionD);
            
            // Check for duplicates
            return new Set(options).size !== options.length;
        }
        
        // Validate all questions before submit
        function validateQuestions() {
            const questions = document.querySelectorAll('.question-item');
            let hasError = false;
            
            questions.forEach((item, index) => {
                if (hasDuplicateOptions(item)) {
                    alert(`Question ${index + 1} has duplicate options. Each option must be unique.`);
                    hasError = true;
                    
                    // Highlight the question with error
                    item.style.border = '3px solid #b71c1c';
                    item.style.boxShadow = '3px 3px 0 #5a0e0e';
                    
                    // Scroll to the error
                    item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                } else {
                    // Reset styling if no error
                    item.style.border = '2px solid #b8d6f5';
                    item.style.boxShadow = '3px 3px 0 #a0c0e0';
                }
            });
            
            return !hasError;
        }
        
        // Override form submit to validate
        form.addEventListener('submit', function(e) {
            if (!validateQuestions()) {
                e.preventDefault();
                return false;
            }
            isSubmitting = true;
            formChanged = false; // Reset the change tracker
        });
        
        // Add question button
        document.getElementById('addQuestionBtn').addEventListener('click', function() {
            if (questionIndex >= maxQuestions) {
                alert('Maximum 50 questions allowed');
                return;
            }
            
            const container = document.getElementById('questionsContainer');
            const noQuestionsMsg = document.getElementById('noQuestionsMessage');
            
            const questionHtml = `
                <div class="question-item" data-index="${questionIndex}">
                    <div class="question-number">${questionIndex + 1}</div>
                    
                    <div style="margin-bottom: 1rem;">
                        <label class="form-label">Question ${questionIndex + 1}</label>
                        <textarea name="questions[${questionIndex}][text]" class="form-control" rows="2" placeholder="Enter your question here..." required></textarea>
                    </div>

                    <div class="options-grid">
                        <div>
                            <label class="form-label">Option A</label>
                            <input type="text" name="questions[${questionIndex}][option_a]" class="form-control" placeholder="Enter option A" required>
                        </div>
                        <div>
                            <label class="form-label">Option B</label>
                            <input type="text" name="questions[${questionIndex}][option_b]" class="form-control" placeholder="Enter option B" required>
                        </div>
                        <div>
                            <label class="form-label">Option C (Optional)</label>
                            <input type="text" name="questions[${questionIndex}][option_c]" class="form-control" placeholder="Enter option C">
                        </div>
                        <div>
                            <label class="form-label">Option D (Optional)</label>
                            <input type="text" name="questions[${questionIndex}][option_d]" class="form-control" placeholder="Enter option D">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                        <div>
                            <label class="form-label">Correct Option</label>
                            <select name="questions[${questionIndex}][correct_option]" class="form-control" required>
                                <option value="A">A</option>
                                <option value="B">B</option>
                                <option value="C">C</option>
                                <option value="D">D</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Points</label>
                            <input type="number" name="questions[${questionIndex}][points]" class="form-control" value="1" min="1" required>
                        </div>
                        <div style="display: flex; align-items: flex-end;">
                            <button type="button" class="btn-remove-question" onclick="removeQuestion(this)">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', questionHtml);
            questionIndex++;
            formChanged = true;
            
            // Hide the no questions message
            noQuestionsMsg.style.display = 'none';
            
            // Update question count display
            document.getElementById('questionCount').textContent = questionIndex;
        });
        
        function removeQuestion(button) {
            if (confirm('Remove this question?')) {
                const questionItem = button.closest('.question-item');
                questionItem.remove();
                questionIndex--;
                formChanged = true;
                
                // Renumber remaining questions
                const questions = document.querySelectorAll('.question-item');
                questions.forEach((item, idx) => {
                    item.dataset.index = idx;
                    item.querySelector('.question-number').textContent = idx + 1;
                    
                    // Update labels
                    const label = item.querySelector('.form-label');
                    if (label && label.textContent.includes('Question')) {
                        label.textContent = `Question ${idx + 1}`;
                    }
                    
                    // Update all name attributes
                    const textarea = item.querySelector('textarea[name*="[text]"]');
                    const optionA = item.querySelector('input[name*="[option_a]"]');
                    const optionB = item.querySelector('input[name*="[option_b]"]');
                    const optionC = item.querySelector('input[name*="[option_c]"]');
                    const optionD = item.querySelector('input[name*="[option_d]"]');
                    const select = item.querySelector('select[name*="[correct_option]"]');
                    const points = item.querySelector('input[name*="[points]"]');
                    
                    if (textarea) textarea.name = `questions[${idx}][text]`;
                    if (optionA) optionA.name = `questions[${idx}][option_a]`;
                    if (optionB) optionB.name = `questions[${idx}][option_b]`;
                    if (optionC) optionC.name = `questions[${idx}][option_c]`;
                    if (optionD) optionD.name = `questions[${idx}][option_d]`;
                    if (select) select.name = `questions[${idx}][correct_option]`;
                    if (points) points.name = `questions[${idx}][points]`;
                });
                
                // Revalidate all questions after removal to clear any error highlights
                setTimeout(() => {
                    const questions = document.querySelectorAll('.question-item');
                    questions.forEach(item => {
                        if (!hasDuplicateOptions(item)) {
                            item.style.border = '2px solid #b8d6f5';
                            item.style.boxShadow = '3px 3px 0 #a0c0e0';
                        }
                    });
                }, 100);
                
                // Show no questions message if empty
                if (questions.length === 0) {
                    document.getElementById('noQuestionsMessage').style.display = 'block';
                }
                
                // Update question count display
                document.getElementById('questionCount').textContent = questions.length;
            }
        }
        
        // Add real-time validation on option inputs
        document.addEventListener('input', function(e) {
            if (e.target.matches('input[name*="[option_a]"], input[name*="[option_b]"], input[name*="[option_c]"], input[name*="[option_d]"]')) {
                const questionItem = e.target.closest('.question-item');
                if (questionItem) {
                    if (hasDuplicateOptions(questionItem)) {
                        questionItem.style.border = '3px solid #b71c1c';
                        questionItem.style.boxShadow = '3px 3px 0 #5a0e0e';
                    } else {
                        questionItem.style.border = '2px solid #b8d6f5';
                        questionItem.style.boxShadow = '3px 3px 0 #a0c0e0';
                    }
                }
            }
        });
        
        // Page leave warning - only if not submitting
        window.addEventListener('beforeunload', function(e) {
            if (formChanged && !isSubmitting) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
        
        // Cancel button warning
        document.getElementById('cancelBtn').addEventListener('click', function(e) {
            if (formChanged) {
                if (!confirm('You have unsaved changes. Are you sure you want to cancel?')) {
                    e.preventDefault();
                }
            }
        });
        
        // Update question count on page load
        document.addEventListener('DOMContentLoaded', function() {
            const questions = document.querySelectorAll('.question-item');
            questionIndex = questions.length;
            document.getElementById('questionCount').textContent = questionIndex;
        });
    </script>
</body>
</html>