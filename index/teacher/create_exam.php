<?php
include '../config/database.php';
include '../includes/session.php';
requireTeacher();

$errors = [];
$success = false;

#mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
// Check teacher_id column
$hasTeacherId = $conn->query("SHOW COLUMNS FROM exams LIKE 'teacher_id'")->num_rows > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$course_id = isset($_POST['course_id']) ? (int)$_POST['course_id'] : 0;
    $timer_minutes = (int)$_POST['timer_minutes'];
    $questions = $_POST['questions'] ?? [];

    // 🔥 GET COURSE NAME (AUTO TITLE)
    $stmt = $conn->prepare("SELECT name FROM courses WHERE id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $course = $stmt->get_result()->fetch_assoc();

    $title = $course ? $course['name'] . " Exam" : "Untitled Exam";

    // VALIDATION
    if (empty($course_id) || $timer_minutes <= 0 || empty($questions)) {
        $errors[] = "Please complete all fields and add questions.";
    } elseif (!$hasTeacherId) {
        $errors[] = "Missing exams.teacher_id column.";
    } else {

        $conn->begin_transaction();

        try {

            // INSERT EXAM
            $stmt = $conn->prepare("
                INSERT INTO exams (title, course_id, teacher_id, timer_minutes)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("siii", $title, $course_id, $_SESSION['user_id'], $timer_minutes);
            $stmt->execute();

            $exam_id = $conn->insert_id;

            // INSERT QUESTIONS
            foreach ($questions as $q) {

                $text = trim($q['text']);
                $type = $q['type'] ?? '';

                if (empty($text) || empty($type)) continue;

                $options = null;
                $correct_answer = null;

                // MCQ
                if ($type === 'mcq') {

                    $rawOptions = $q['options'] ?? [];
                    $formattedOptions = [];
                    $letters = range('A', 'H');

                    foreach ($rawOptions as $i => $opt) {
                        if (!empty(trim($opt))) {
                            $formattedOptions[$letters[$i]] = $opt;
                        }
                    }

                    $options = json_encode($formattedOptions);
                    $correct_answer = $q['correct'] ?? null;
                }

                // TRUE/FALSE
                elseif ($type === 'tf') {
                    $correct_answer = $q['correct'] ?? null;
                }

                // INSERT QUESTION
                $stmt = $conn->prepare("
                    INSERT INTO questions (exam_id, question_text, type, options, correct_answer)
                    VALUES (?, ?, ?, ?, ?)
                ");

                $stmt->bind_param("issss", $exam_id, $text, $type, $options, $correct_answer);
                $stmt->execute();
            }

            $conn->commit();
            $success = true;

        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// GET COURSES
$courses = $conn->query("SELECT * FROM courses ORDER BY name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Exam</title>
</head>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #020617, #0f172a);
    color: #e2e8f0;
}

/* TITLE */
h2 {
    text-align: center;
    margin: 20px;
    color: #22c55e;
    animation: float 3s infinite;
}

@keyframes float {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

/* FORM */
form {
    max-width: 900px;
    margin: auto;
    padding: 20px;
}

/* HEADER BOX */
.exam-header {
    background: rgba(15,23,42,0.7);
    padding: 20px;
    border-radius: 15px;
    margin-bottom: 20px;
    border: 1px solid rgba(34,197,94,0.2);
}

/* INPUTS */
select, input {
    width: 100%;
    padding: 12px;
    margin: 10px 0;
    border-radius: 10px;
    border: none;   
    background: rgba(2,6,23,0.8);
    color: white;
    border: 1px solid rgba(34,197,94,0.3);
}

/* QUESTION CARD */
.question {
    position: relative; 
    background: rgba(15,23,42,0.7);
    padding: 20px;
    margin: 15px 0;
    border-radius: 15px;
    border: 1px solid rgba(34,197,94,0.2);
    animation: fadeUp 0.5s ease;
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px);}
    to { opacity: 1; transform: translateY(0);}
}

/* BUTTON */
button {    
    padding: 12px 25px;
    border-radius: 25px;
    border: none;
    background: linear-gradient(45deg, #22c55e, #06b6d4);
    color: white;
    cursor: pointer;
}

button:hover {
    transform: scale(1.05);
}

/* MOBILE */
@media (max-width: 768px) {
    form {
        padding: 10px;
    }
}



.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;

    background: rgba(2, 6, 23, 0.85);
    backdrop-filter: blur(10px);

    display: flex;
    align-items: center;
    justify-content: center;

    animation: fadeIn 0.3s ease;
    z-index: 9999;
}

.modal-box {
    background: linear-gradient(135deg, #0f172a, #020617);
    border: 1px solid rgba(34,197,94,0.3);
    padding: 30px;
    border-radius: 20px;
    text-align: center;
    width: 90%;
    max-width: 400px;

    animation: pop 0.4s ease;
    box-shadow: 0 0 40px rgba(34,197,94,0.2);
}

.checkmark {
    font-size: 50px;
    color: #22c55e;
    margin-bottom: 10px;
    animation: bounce 1s infinite;
}

.modal-box h2 {
    color: #22c55e;
    margin-bottom: 10px;
}

.modal-box p {
    color: #94a3b8;
    margin-bottom: 20px;
}

.modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 20px;
}
.modal-actions .btn,
.modal-actions button {
    flex: 1; /* 👈 makes both equal width */
    padding: 10px 15px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    background: linear-gradient(45deg, #22c55e, #06b6d4);
    color: white;
    text-decoration: none;
}

.modal-actions button {
    background: linear-gradient(45deg, #f97316, #ef4444);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes pop {
    from { transform: scale(0.7); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

.remove-btn {
    margin-top: 10px;
    background: linear-gradient(45deg, #ef4444, #f97316);
    color: white;
    padding: 10px 15px;
    border-radius: 20px;
    border: none;
    cursor: pointer;
    font-size: 13px;
    width: 100%;
    transition: 0.2s ease;
}

.remove-btn:hover {
    transform: scale(1.05);
}

.question-top {
    display: flex;
    gap: 10px;
    align-items: center;
}

.question-top input {
    flex: 1;
}

.remove-btn {
    position: absolute;
    top: -20px;
    right: -10px;

    width: 32px;
    height: 32px;
    padding: 0;

    border-radius: 8px;
    border: none;
    cursor: pointer;

    background: linear-gradient(45deg, #ef4444, #f97316);
    color: white;

    display: flex;
    align-items: center;
    justify-content: center;

    font-size: 14px;
    transition: 0.2s ease;
}

.remove-btn:hover {
    transform: scale(1.1);
}
</style>

<body>
    <br><br>

<div style="text-align:center;">
    <a href="../teacher/dashboard.php" style="
        display:inline-block;
        padding:12px 25px;
        background: linear-gradient(45deg, #f97316, #ef4444);
        color:white;
        text-decoration:none;
        border-radius:25px;
        font-weight:bold;
    ">
        ⬅ Back to Dashboard
    </a>
</div>

<h2>Create Exam</h2>

<?php if (!empty($errors)): ?>
    <div style="color:red; text-align:center;">
        <?php foreach ($errors as $e) echo "<p>$e</p>"; ?>
    </div>
<?php endif; ?>
 
<form method="POST">

    <!-- COURSE -->
   <div class="exam-header">
    <label>Course</label>
    <select name="course_id" required>
    <option value="">Select Course</option>
        <?php while ($c = $courses->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>">
                <?= htmlspecialchars($c['name']) ?>
            </option>
        <?php endwhile; ?>
    </select>

</div>

        <label>Timer (minutes)</label>
        <input type="number" name="timer_minutes" min="1" required>
    </div>

    <!-- QUESTIONS -->
    <div id="questions"></div>

    <button type="button" onclick="addQuestion()">+ Add Question</button>
    <br><br>
    <button type="submit">Create Exam</button>

</form>
<?php if ($success): ?>
<div id="successModal" class="modal-overlay">
    <div class="modal-box">

        <div class="checkmark">✔</div>

        <h2>Exam Created Successfully</h2>
        <p>Your exam has been saved and is now available for students.</p>

        <div class="modal-actions">
            <a href="../teacher/dashboard.php" class="btn">Go to Dashboard</a>
            <button onclick="closeModal()">Create Another</button>
        </div>

    </div>
</div>
<?php endif; ?>
<script>
let qIndex = 0;

function addQuestion() {

    let html = `
    <div class="question" id="q_${qIndex}">

        <!-- ❌ Top-right delete button -->
        <button type="button" class="remove-btn" onclick="removeQuestion(${qIndex})">
            ✖
        </button>

        <input type="text" name="questions[${qIndex}][text]" placeholder="Question..." required>

        <select name="questions[${qIndex}][type]" onchange="changeType(this, ${qIndex})">
            <option value="">Select Type</option>
            <option value="mcq">Multiple Choice</option>
            <option value="tf">True/False</option>
            <option value="essay">Essay</option>
        </select>

        <div id="extra_${qIndex}"></div>

    </div>
    `;

    document.getElementById("questions").insertAdjacentHTML("beforeend", html);
    qIndex++;
}
function changeType(select, index) {

    let container = document.getElementById("extra_" + index);

    if (select.value === "mcq") {

        container.innerHTML = `
            <input type="text" name="questions[${index}][options][]" placeholder="Option A">
            <input type="text" name="questions[${index}][options][]" placeholder="Option B">
            <input type="text" name="questions[${index}][options][]" placeholder="Option C">
            <input type="text" name="questions[${index}][options][]" placeholder="Option D">

            <select name="questions[${index}][correct]">
                <option value="A">Correct: A</option>
                <option value="B">Correct: B</option>
                <option value="C">Correct: C</option>
                <option value="D">Correct: D</option>
            </select>
        `;

    } else if (select.value === "tf") {

        container.innerHTML = `
            <select name="questions[${index}][correct]">
                <option value="true">True</option>
                <option value="false">False</option>
            </select>
        `;

    } else {
        container.innerHTML = "";
    }

}
function closeModal() {
    document.getElementById('successModal').style.display = 'none';
}
function removeQuestion(index) {
    const el = document.getElementById("q_" + index);
    if (el) {
        el.style.transition = "0.3s";
        el.style.opacity = "0";
        el.style.transform = "scale(0.9)";

        setTimeout(() => {
            el.remove();
        }, 300);
    }
}
</script>

</body>
</html>
