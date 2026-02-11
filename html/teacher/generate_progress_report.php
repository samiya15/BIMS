<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("SELECT id, category, assigned_class_id FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

/* ---------- GET STUDENTS BASED ON TEACHER CATEGORY ---------- */
$students = [];

if ($teacher['category'] == 'Head Teacher') {
    // Head teacher sees all students
    $students_stmt = $pdo->query("
        SELECT s.id, s.admission_number, s.first_name, s.last_name, 
               cl.name as class_name, ct.name as curriculum_name
        FROM students s
        LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
        LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
        WHERE s.status = 1
        ORDER BY ct.id, cl.level_order, s.last_name, s.first_name
    ");
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($teacher['category'] == 'Class Teacher' && $teacher['assigned_class_id']) {
    // Class teacher sees only their assigned class
    $students_stmt = $pdo->prepare("
        SELECT s.id, s.admission_number, s.first_name, s.last_name, 
               cl.name as class_name, ct.name as curriculum_name
        FROM students s
        LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
        LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
        WHERE s.class_level_id = ? AND s.status = 1
        ORDER BY s.last_name, s.first_name
    ");
    $students_stmt->execute([$teacher['assigned_class_id']]);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Subject teacher sees all students
    $students_stmt = $pdo->query("
        SELECT s.id, s.admission_number, s.first_name, s.last_name, 
               cl.name as class_name, ct.name as curriculum_name
        FROM students s
        LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
        LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
        WHERE s.status = 1
        ORDER BY ct.id, cl.level_order, s.last_name, s.first_name
    ");
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
}

$current_year = (int)date('Y');
$years = range($current_year - 5, $current_year);
$terms = ['Term 1', 'Term 2', 'Term 3'];
$assessments = ['Opener', 'Mid-Term', 'End-Term'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Progress Report</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .report-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .report-card h1 {
            color: white;
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .report-card p {
            color: rgba(255,255,255,0.9);
            font-size: 16px;
        }
        
        .form-section {
            background: #f9f9f9;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .form-section h3 {
            color: #0b1c2d;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }
        
        .search-box::before {
            content: '🔍';
            position: absolute;
            right: 15px;
            top: 12px;
            font-size: 18px;
        }
        
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .student-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .student-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }
        
        .student-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff, #e8edff);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .student-name {
            font-weight: 600;
            color: #0b1c2d;
            margin-bottom: 5px;
        }
        
        .student-details {
            font-size: 12px;
            color: #666;
        }
        
        .period-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .generate-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s;
        }
        
        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .generate-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="my_profile.php">My Profile</a>
    <a href="generate_progress_report.php" class="active">Progress Reports</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="report-card">
            <h1>📊 Generate Progress Report</h1>
            <p>Compare student performance across assessment periods</p>
        </div>

        <form method="GET" action="view_progress_report.php" id="progressForm">
            <!-- STEP 1: SELECT STUDENT -->
            <div class="form-section">
                <h3>📌 Step 1: Select Student</h3>
                
                <div class="search-box">
                    <input type="text" 
                           id="studentSearch" 
                           placeholder="Search by name or admission number..."
                           onkeyup="filterStudents()">
                </div>
                
                <div class="students-grid" id="studentsGrid">
                    <?php foreach ($students as $student): ?>
                        <div class="student-card" 
                             data-id="<?php echo $student['id']; ?>"
                             data-name="<?php echo strtolower($student['first_name'] . ' ' . $student['last_name']); ?>"
                             data-admission="<?php echo strtolower($student['admission_number']); ?>"
                             onclick="selectStudent(<?php echo $student['id']; ?>, this)">
                            <div class="student-name">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            </div>
                            <div class="student-details">
                                Adm: <?php echo htmlspecialchars($student['admission_number']); ?><br>
                                <?php echo htmlspecialchars($student['curriculum_name'] . ' - ' . $student['class_name']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <input type="hidden" name="student_id" id="student_id" required>
            </div>

            <!-- STEP 2: SELECT CURRENT PERIOD -->
            <div class="form-section">
                <h3>📅 Step 2: Select Current Assessment Period</h3>
                
                <div class="info-box">
                    ℹ️ This is the assessment you want to analyze. The system will automatically compare it to the previous assessment.
                </div>
                
                <div class="period-grid">
                    <div>
                        <label>Academic Year</label>
                        <select name="current_year" required>
                            <?php foreach (array_reverse($years) as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo $year == $current_year ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Term</label>
                        <select name="current_term" required>
                            <?php foreach ($terms as $term): ?>
                                <option value="<?php echo $term; ?>"><?php echo $term; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Assessment</label>
                        <select name="current_assessment" required>
                            <?php foreach ($assessments as $assessment): ?>
                                <option value="<?php echo $assessment; ?>"><?php echo $assessment; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- INFO SECTION -->
            <div class="info-box">
                <strong>💡 How it works:</strong><br>
                The system will automatically find the previous assessment to compare with:<br>
                • Same term: Opener → Mid-Term → End-Term<br>
                • Different term: Previous term's End-Term<br>
                • Different year: Previous year's Term 3 End-Term
            </div>

            <!-- GENERATE BUTTON -->
            <button type="submit" class="generate-btn" id="generateBtn" disabled>
                📊 Generate Progress Report
            </button>
        </form>

        <a href="../teacher_dashboard.php" class="button button-yellow" style="margin-top: 20px;">← Back to Dashboard</a>
    </div>
</div>

<script>
let selectedStudentId = null;

function selectStudent(studentId, element) {
    // Remove selection from all cards
    document.querySelectorAll('.student-card').forEach(card => {
        card.classList.remove('selected');
    });
    
    // Add selection to clicked card
    element.classList.add('selected');
    
    // Set student ID
    selectedStudentId = studentId;
    document.getElementById('student_id').value = studentId;
    
    // Enable generate button
    document.getElementById('generateBtn').disabled = false;
}

function filterStudents() {
    const searchTerm = document.getElementById('studentSearch').value.toLowerCase();
    const cards = document.querySelectorAll('.student-card');
    
    cards.forEach(card => {
        const name = card.getAttribute('data-name');
        const admission = card.getAttribute('data-admission');
        
        if (name.includes(searchTerm) || admission.includes(searchTerm)) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Form validation
document.getElementById('progressForm').addEventListener('submit', function(e) {
    if (!selectedStudentId) {
        e.preventDefault();
        alert('Please select a student first!');
    }
});
</script>

</body>
</html>