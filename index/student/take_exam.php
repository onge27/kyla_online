<?php
include '../config/database.php';
include '../includes/session.php';
requireStudent();

$exam_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* CHECKS */
$hasTimerMinutes = $conn->query("SHOW COLUMNS FROM exams LIKE 'timer_minutes'")->num_rows > 0;
$hasOptions = $conn->query("SHOW COLUMNS FROM questions LIKE 'options'")->num_rows > 0;
$hasCorrectAnswer = $conn->query("SHOW COLUMNS FROM questions LIKE 'correct_answer'")->num_rows > 0;

/* GET EXAM (UPDATED: NO courses JOIN, uses course_name) */
$stmt = $conn->prepare("
    SELECT * FROM exams
    WHERE id = ?
");
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();

if (!$exam) {
    header('Location: available_exams.php');
    exit();
}

/* CHECK IF ALREADY TAKEN */
$stmt = $conn->prepare("
    SELECT id FROM results
    WHERE exam_id = ? AND student_id = ?
");
$stmt->bind_param("ii", $exam_id, $_SESSION['user_id']);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    header('Location: available_exams.php');
    exit();
}

/* GET QUESTIONS */
$result = $conn->query("SELECT * FROM questions WHERE exam_id = $exam_id ORDER BY id");

$questions = [];

while ($q = $result->fetch_assoc()) {

    if ($q['type'] === 'mcq') {
        $opts = $hasOptions && !empty($q['options'])
            ? json_decode($q['options'], true)
            : null;

        if (!is_array($opts) || count($opts) < 2) {
            continue;
        }
    }

    $questions[] = $q;
}

$question_count = count($questions);

if ($question_count === 0) {
    die("No valid questions found for this exam.");
}

/* SUBMIT EXAM */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $answers = $_POST['answers'] ?? [];
    $score = 0;

    $conn->begin_transaction();

    try {

        $insert = $conn->prepare("
            INSERT INTO student_answers
            (question_id, student_id, answer_text, is_correct)
            VALUES (?, ?, ?, ?)
        ");

        foreach ($questions as $q) {

            $qid = $q['id'];
            $answer = isset($answers[$qid]) ? trim($answers[$qid]) : '';
            $is_correct = 0;

            if ($hasCorrectAnswer && ($q['type'] === 'mcq' || $q['type'] === 'tf')) {

                $correct = $q['correct_answer'] ?? '';

                if ($q['type'] === 'mcq') {
                    $is_correct = ($answer === $correct) ? 1 : 0;
                } else {
                    $is_correct = (strtolower($answer) === strtolower($correct)) ? 1 : 0;
                }

                if ($is_correct === 1) $score++;
            }

            $insert->bind_param("iisi",
                $qid,
                $_SESSION['user_id'],
                $answer,
                $is_correct
            );

            $insert->execute();
        }

        $percentage = ($score / $question_count) * 100;

        $stmt = $conn->prepare("
            INSERT INTO results
            (exam_id, student_id, score, total_questions, percentage)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iiiid",
            $exam_id,
            $_SESSION['user_id'],
            $score,
            $question_count,
            $percentage
        );

        $stmt->execute();

        $conn->commit();

        // clear timer
        echo "<script>localStorage.removeItem('exam_timer_{$exam_id}_{$_SESSION['user_id']}');</script>";

        header("Location: exam_result.php?exam_id=$exam_id");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        die("Submission Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($exam['title']); ?></title>
</head>

<style>
body {
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
    background: linear-gradient(135deg, #020617, #0b1f3a);
    color: #e2f1ff;
    overflow-x: hidden;
}

/* ANIMATION BACKGROUND FLOAT */
body::before {
    content: "";
    position: fixed;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(34,197,94,0.08) 0%, transparent 60%);
    animation: rotateBg 20s linear infinite;
    z-index: -1;
}

@keyframes rotateBg {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* TITLE */
h2 {
    text-align: center;
    margin-top: 20px;
    font-size: 30px;
    color: #22c55e;
    text-shadow: 0 0 15px rgba(34,197,94,0.5);
    animation: floatTitle 3s ease-in-out infinite;
}

@keyframes floatTitle {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-8px); }
}

/* TIMER */
#timer {
    text-align: center;
    font-size: 22px;
    font-weight: bold;
    color: #22c55e;
    margin: 15px auto;
    padding: 10px 20px;
    width: fit-content;
    background: rgba(15,23,42,0.8);
    border: 1px solid rgba(34,197,94,0.4);
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); box-shadow: 0 0 10px rgba(34,197,94,0.3); }
    50% { transform: scale(1.05); box-shadow: 0 0 25px rgba(34,197,94,0.6); }
    100% { transform: scale(1); }
}

/* FORM */
form {
    max-width: 850px;
    margin: auto;
    padding: 10px;
}

/* QUESTION BOX 3D */
form > div {
    background: rgba(15,23,42,0.85);
    margin: 18px auto;
    padding: 20px;
    border-radius: 15px;
    border: 1px solid rgba(34,197,94,0.25);
    box-shadow: 0 15px 30px rgba(0,0,0,0.6);
    transform: perspective(800px) rotateX(5deg);
    transition: 0.3s ease;
    animation: fadeUp 0.6s ease;
}

/* hover 3D lift */
form > div:hover {
    transform: perspective(800px) rotateX(0deg) translateY(-5px) scale(1.02);
    box-shadow: 0 25px 45px rgba(34,197,94,0.2);
}

@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px) rotateX(20deg); }
    to { opacity: 1; transform: translateY(0) rotateX(5deg); }
}

/* QUESTION TEXT */
form p {
    font-size: 18px;
}

/* OPTIONS */
label {
    display: block;
    margin: 8px 0;
    padding: 10px;
    background: rgba(2,6,23,0.6);
    border-radius: 10px;
    border: 1px solid rgba(34,197,94,0.2);
    cursor: pointer;
    transition: 0.3s;
}

label:hover {
    background: rgba(34,197,94,0.15);
    transform: translateX(6px);
}

/* RADIO */
input[type="radio"] {
    transform: scale(1.2);
    margin-right: 10px;
}

/* TEXTAREA */
textarea {
    width: 100%;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid rgba(34,197,94,0.3);
    background: rgba(2,6,23,0.8);
    color: white;
    outline: none;
    min-height: 100px;
    transition: 0.3s;
}

textarea:focus {
    border-color: #22c55e;
    box-shadow: 0 0 10px rgba(34,197,94,0.4);
}

/* BUTTON */
button {
    display: block;
    margin: 25px auto;
    padding: 12px 30px;
    font-size: 18px;
    background: linear-gradient(45deg, #22c55e, #0ea5e9);
    border: none;
    border-radius: 30px;
    color: white;
    cursor: pointer;
    transition: 0.3s;
    box-shadow: 0 10px 20px rgba(0,0,0,0.5);
}

button:hover {
    transform: scale(1.08) rotateX(10deg);
    box-shadow: 0 15px 30px rgba(34,197,94,0.4);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    form {
        padding: 10px;
    }

    form > div {
        transform: none;
    }

    h2 {
        font-size: 22px;
    }
}
</style>

<body>

<h2><?php echo htmlspecialchars($exam['title']); ?></h2>





<!-- TIMER -->
<div id="timer">
    Time Remaining: <span id="time"></span>
</div>

<form method="POST">

<?php $num = 1; foreach ($questions as $q): ?>

    <div>
        <p><b>Q<?php echo $num++; ?>:</b>
        <?php echo htmlspecialchars($q['question_text']); ?></p>

        <?php if ($q['type'] === 'mcq'): ?>

            <?php $opts = json_decode($q['options'], true); ?>

            <?php if (is_array($opts)): ?>
                <?php foreach ($opts as $k => $opt): ?>
                    <label>
                        <input type="radio"
                               name="answers[<?php echo $q['id']; ?>]"
                               value="<?php echo $k; ?>"
                               required>
                        <?php echo $k . ". " . htmlspecialchars($opt); ?>
                    </label><br>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php elseif ($q['type'] === 'tf'): ?>

            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="true" required> True</label><br>
            <label><input type="radio" name="answers[<?php echo $q['id']; ?>]" value="false" required> False</label>

        <?php else: ?>

            <textarea name="answers[<?php echo $q['id']; ?>]" required></textarea>

        <?php endif; ?>

    </div>

<?php endforeach; ?>

<button type="submit">Submit Exam</button>

</form>

<!-- TIMER SCRIPT (FIXED, SINGLE VERSION ONLY) -->
<script>
let duration = <?php echo (int)$exam['timer_minutes']; ?> * 60;
let key = "exam_timer_<?php echo $exam_id . '_' . $_SESSION['user_id']; ?>";

let startTime = localStorage.getItem(key);

if (!startTime) {
    startTime = Date.now();
    localStorage.setItem(key, startTime);
} else {
    startTime = parseInt(startTime);
}

function updateTimer() {
    let elapsed = Math.floor((Date.now() - startTime) / 1000);
    let remaining = duration - elapsed;

    if (remaining <= 0) {
        document.getElementById("time").innerHTML = "00:00";
        localStorage.removeItem(key);
        alert("Time is up! Auto submitting exam.");
        document.querySelector("form").submit();
        return;
    }

    let minutes = Math.floor(remaining / 60);
    let seconds = remaining % 60;

    seconds = seconds < 10 ? "0" + seconds : seconds;

    document.getElementById("time").innerHTML = minutes + ":" + seconds;
}

updateTimer();
setInterval(updateTimer, 1000);
</script>

</body>
</html>
