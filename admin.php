<?php
session_start(); // Ensure session is started for your student_id check
require_once 'config.php'; 
$active_tab = $_GET['tab'] ?? 'dashboard';

// ... the rest of your logic starts here
$st_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 0; 

// Fetch the fee balance for the current student
// Fetch the fee balance for the current student
$st_id = isset($_SESSION['student_id']) ? $_SESSION['student_id'] : 0; 

$fee_query = $conn->query("SELECT balance FROM fees WHERE student_id = '$st_id'");

// Check if we actually got a result back
if ($fee_query && $fee_query->num_rows > 0) {
    $fee_data = $fee_query->fetch_assoc();
    $balance = $fee_data['balance'];
} else {
    // Fallback if no record is found or session is empty
    $balance = 0;
}
// --- HANDLE FEE UPDATES ---
if (isset($_POST['update_fees'])) {
    $st_id = (int)$_POST['student_id'];
    $new_pay = (float)$_POST['amount_paid'];

    // Check if record exists
    $check = $conn->query("SELECT * FROM fees WHERE student_id = $st_id");
    
    if ($check->num_rows > 0) {
        $data = $check->fetch_assoc();
        $total_paid = $data['paid_amount'] + $new_pay;
        $balance = $data['initial_fee'] - $total_paid;
        
        $stmt = $conn->prepare("UPDATE fees SET paid_amount = ?, balance = ? WHERE student_id = ?");
        $stmt->bind_param("ddi", $total_paid, $balance, $st_id);
    } else {
        // If no record exists, create one (Assuming 50,000 is the default fee)
        $initial = 50000; 
        $balance = $initial - $new_pay;
        $stmt = $conn->prepare("INSERT INTO fees (student_id, initial_fee, paid_amount, balance) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iddd", $st_id, $initial, $new_pay, $balance);
    }
    
    $stmt->execute();
    header("Location: admin.php?tab=fees&msg=payment_updated");
    exit();
}
// --- HANDLE ADDING NEW UNIT ---
if (isset($_POST['save_unit'])) {
    $u_name   = trim($_POST['unit_name']);
    $u_code   = trim($_POST['unit_code']);
    $u_year   = (int)$_POST['year'];
    $u_sem    = (int)$_POST['semester'];
    $u_course = (int)$_POST['course_id'];

    if (empty($u_name) || empty($u_code) || $u_year <= 0 || $u_sem <= 0 || $u_course <= 0) {
        echo "<div style='color:red;'>All fields are required and must be valid.</div>";
    } else {
        $check_course = $conn->prepare("SELECT id FROM courses WHERE id = ?");
        $check_course->bind_param("i", $u_course);
        $check_course->execute();
        $res_course = $check_course->get_result();

        if ($res_course->num_rows === 0) {
            echo "<div style='color:red;'>Selected course does not exist.</div>";
        } else {
            $stmt = $conn->prepare("INSERT INTO units (unit_name, unit_code, year, semester, course_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("ssiii", $u_name, $u_code, $u_year, $u_sem, $u_course);

            if ($stmt->execute()) {
                echo "<div style='color:green;'>Unit added successfully!</div>";
            } else {
                echo "<div style='color:red;'>Database Error: " . htmlspecialchars($stmt->error) . "</div>";
            }
        }
    }
}
// 2. HANDLE NEW STUDENT REGISTRATION
// 2. HANDLE NEW STUDENT REGISTRATION
if (isset($_POST['add_student'])) {
    $name = mysqli_real_escape_string($conn, $_POST['full_name']); 
    $reg = mysqli_real_escape_string($conn, $_POST['reg_number']); 
    $email = mysqli_real_escape_string($conn, $_POST['email']); // New Email field
    $yr = (int)$_POST['year']; 
    $sem = (int)$_POST['semester'];
    $course_id = (int)$_POST['course_id']; 

    // Hash the password typed by the admin
    $plain_password = $_POST['password'];
    $hash = password_hash($plain_password, PASSWORD_DEFAULT);

    // 1. Check if Registration Number or Email already exists to prevent crashes
    $check_dup = $conn->prepare("SELECT id FROM students WHERE reg_number = ? OR email = ?");
    $check_dup->bind_param("ss", $reg, $email);
    $check_dup->execute();
    $res_dup = $check_dup->get_result();

    if ($res_dup->num_rows > 0) {
        header("Location: admin.php?tab=students&msg=duplicate_detected");
        exit();
    

    // 2. Insert the student including Email and Password
    $stmt = $conn->prepare("INSERT INTO students (full_name, reg_number, email, password, year, semester, course_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssiii", $name, $reg, $email, $hash, $yr, $sem, $course_id);
    
    if($stmt->execute()){
        $student_id = $conn->insert_id;

        // Create initial Fee record (Ksh 0.00)
        $conn->query("INSERT INTO fees (student_id, paid_amount) VALUES ($student_id, 0.00)");

        // AUTOMATIC UNIT ALLOCATION
        $units_to_assign = $conn->query("SELECT id FROM units WHERE course_id = $course_id AND year = $yr AND semester = $sem");
        
        if($units_to_assign) {
            while($u = $units_to_assign->fetch_assoc()){
                $u_id = $u['id'];
                $conn->query("INSERT INTO student_units (student_id, unit_id) VALUES ($student_id, $u_id)");
            }
        }
        header("Location: admin.php?tab=students&msg=student_added");
        exit();
    } else {
        die("Database error: " . $stmt->error);
    }
}
    
    header("Location: admin.php?tab=students&msg=student_added");
    exit();
}

// 3. FETCH DASHBOARD STATS
$total_students = $conn->query("SELECT id FROM students")->num_rows;
$total_units = $conn->query("SELECT id FROM units")->num_rows;
$total_courses = $conn->query("SELECT id FROM courses")->num_rows;

// ADD THIS NEW LINE HERE:
$total_fees_query = $conn->query("SELECT SUM(balance) as total FROM fees");
$fee_row = $total_fees_query->fetch_assoc();
$total_balance = $fee_row['total'] ?? 0; // This stores the number for the dashboard

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Master SIS | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <style>
        :root { --sidebar-bg: #222d32; --header-green: #00a65a; --bg-light: #ecf0f5; }
        body { font-family: 'Source Sans Pro', sans-serif; margin: 0; display: flex; background: var(--bg-light); overflow-x: hidden; }
        
        .sidebar { width: 230px; background: var(--sidebar-bg); min-height: 100vh; color: #fff; position: fixed; z-index: 100; }
        .sidebar-header { background: #1a2226; padding: 20px; text-align: center; font-weight: bold; font-size: 18px; }
        .user-panel { padding: 15px; background: #1a2226; display: flex; align-items: center; border-bottom: 1px solid #374850; }
        .user-panel img { width: 45px; border-radius: 50%; margin-right: 10px; border: 2px solid #00a65a; }
        
        .nav-header { padding: 10px 20px; font-size: 12px; color: #4b646f; background: #1a2226; letter-spacing: 1px; }
        .nav-item { padding: 12px 20px; color: #b8c7ce; text-decoration: none; 
        display: block; border-left: 3px solid transparent; cursor: pointer; transition: 0.3s; }
        .nav-item:hover, .nav-item.active { background: #1e282c; color: #fff; border-left-color: var(--header-green); }
        .nav-item i { margin-right: 10px; width: 20px; }

        .main-content { flex: 1; margin-left: 230px; min-width: 0; }
        .top-nav { background: var(--header-green); padding: 15px 25px;
         color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .content-wrapper { padding: 25px; }
        
        .tab-content { display: none; animation: fadeIn 0.4s; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .card-row { display: flex; gap: 20px; margin-bottom: 30px; }
        .card { flex: 1; padding: 20px; border-radius: 4px; color: white; position: relative; overflow: hidden; min-height: 100px; }
        .card h3 { font-size: 38px; margin: 0; position: relative; z-index: 2; }
        .card p { margin: 5px 0 0; font-weight: bold; text-transform: uppercase; font-size: 14px; opacity: 0.9; }
        .bg-aqua { background: #00c0ef; } 
        .bg-green { background: #00a65a; } 
        .bg-yellow { background: #f39c12; }
        
        .grid-4 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .box { background: #fff; border-top: 3px solid #d2d6de;
         padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 25px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; background: white; }
        th { background: #f9f9f9; padding: 12px 10px; text-align: left; border-bottom: 2px solid #EEE; color: #444; }
        td { padding: 10px; border-bottom: 1px solid #f4f4f4; color: #666; font-size: 14px; }
        tr:hover { background: #fbfbfb; }
        
        input[type="text"], input[type="number"], select { width: 100%; padding: 10px; margin: 8px 0; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        .mark-input { width: 70px !important; text-align: center; border: 1px solid #00a65a !important; font-weight: bold; }
        .btn-load { background: #00c0ef; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 3px; font-weight: bold; }
        
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:999; }
        .modal-content { background:white; width:450px; margin:10% auto; padding:30px; border-top: 5px solid var(--header-green); border-radius: 4px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .box { 
    background: #fff; 
    border: 1px dashed #444; /* Retro dashed border */
    padding: 20px; 
    margin-bottom: 25px; 
    border-radius: 0; /* Square corners for terminal look */
    box-shadow: 2px 2px 0px #888; /* Simple flat shadow */
}

.box h3 {
    border-bottom: 2px solid #00a65a;
    padding-bottom: 5px;
    font-family: 'Courier New', monospace; /* Terminal font */
}
        /* Sidebar General Link Style (Optional - adjust to match your current look) */
.sidebar a {
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    color: white;
    transition: 0.3s;
}

/* Specific Grey Logout Button */
.logout-btn {
    background-color: #4a4a4a; /* Medium Grey */
    color: #ffffff !important;
    margin-top: 20px; /* Space it away from other links */
    border-radius: 5px;
    text-align: center;
    font-weight: bold;
    border: 1px solid #333;
}

.logout-btn:hover {
    background-color: #333333; /* Darker Grey on hover */
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-header">DIPLOMA ADMIN</div>
        <div class="user-panel">
            <img src="admin.JPG" alt="User">
            <div><p style="margin:0">System Admin</p><small style="color:#00a65a"><i class="fa fa-circle"></i> Online</small></div>
        </div>
        
        <div class="nav-header">MAIN NAVIGATION</div>
        <a onclick="switchTab('dashboard')" id="btn-dashboard" class="nav-item <?php echo ($active_tab == 'dashboard') ? 'active' : ''; ?>"><i class="fa fa-dashboard"></i> Dashboard</a>
        <a onclick="switchTab('students')" id="btn-students" class="nav-item <?php echo ($active_tab == 'students') ? 'active' : ''; ?>"><i class="fa fa-users"></i> Students</a>
        <a onclick="switchTab('courses')" id="btn-courses" class="nav-item <?php echo ($active_tab == 'courses') ? 'active' : ''; ?>"><i class="fa fa-graduation-cap"></i> Courses</a>
        <a onclick="switchTab('units')" id="btn-units" class="nav-item <?php echo ($active_tab == 'units') ? 'active' : ''; ?>"><i class="fa fa-book"></i> Units</a>
        <a onclick="switchTab('attendance')" id="btn-attendance" class="nav-item <?php echo ($active_tab == 'attendance') ? 'active' : ''; ?>"><i class="fa fa-calendar-check-o"></i> Attendance</a>
        <a onclick="switchTab('marks')" id="btn-marks" class="nav-item <?php echo ($active_tab == 'marks') ? 'active' : ''; ?>"><i class="fa fa-file-text-o"></i> Marks</a>
        <a onclick="switchTab('fees')" id="btn-fees" class="nav-item <?php echo ($active_tab == 'fees') ? 'active' : ''; ?>"><i class="fa fa-money"></i> Fees</a>
       
        <div class="nav-header">ACTIONS</div>
        <a onclick="document.getElementById('regModal').style.display='block'" class="nav-item"><i class="fa fa-plus-circle"></i> Register Student</a>
         <div class="logout-wrapper">
    <a href="logout.php" class="logout-box">Logout</a>
</div>
    </div>

    <div class="main-content">
        <div class="top-nav">
            <span><i class="fa fa-bars"></i> Master Student Information System</span>
            <span>Welcome, <strong>Administrator</strong> <i class="fa fa-user-circle" style="margin-left:10px;"></i></span>
        </div>

        <div class="content-wrapper">
            
            <div id="dashboard" class="tab-content <?php echo ($active_tab == 'dashboard') ? 'active' : ''; ?>">
                <h2 style="margin-top:0;">Overview Dashboard</h2>
                <div class="card-row">
                    <div class="card bg-aqua">
                        <h3><?php echo $total_students; ?></h3>
                        <p>Total Students</p>
                        <i class="fa fa-users" style="position:absolute; right:10px; bottom:10px; font-size:50px; opacity:0.2;"></i>
                    </div>
                    <div class="card bg-green">
                        <h3><?php echo $total_courses; ?></h3>
                        <p>Active Courses</p>
                        <i class="fa fa-graduation-cap" style="position:absolute; right:10px; bottom:10px; font-size:50px; opacity:0.2;"></i>
                    </div>
                    <div class="card bg-yellow">
                        <h3><?php echo $total_units; ?></h3>
                        <p>Registered Units</p>
                        <i class="fa fa-book" style="position:absolute; right:10px; bottom:10px; font-size:50px; opacity:0.2;"></i>
                    </div>
                </div>
                <div class="card" style="background: #3980dd;">
    <?php 
        // This calculates the total for the whole school
        $fee_sum = $conn->query("SELECT SUM(balance) as total FROM fees");
        $f_data = $fee_sum->fetch_assoc();
        $total_balance = $f_data['total'] ?? 0;
    ?>
    <h3>KES <?php echo number_format($total_balance, 0); ?></h3>
    <p>Total Fees Owed</p>
    <i class="fa fa-money" style="position:absolute; right:10px; bottom:10px; font-size:50px; opacity:0.2;"></i>
</div>
                <div class="box">
                    <h4>Quick Statistics</h4>
                    <p>The counts above reflect all records currently stored in your SQL database.</p>
                </div>
            </div>

            <div id="students" class="tab-content <?php echo ($active_tab == 'students') ? 'active' : ''; ?>">
                <h2>Student Registry</h2>
                <div class="box">
                    <table>
                        <thead>
                            <tr><th>Reg No</th><th>Full Name</th><th>Year/Sem</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            <?php 
                            $st_list = $conn->query("SELECT * FROM students ORDER BY id DESC");
                            if($st_list && $st_list->num_rows > 0):
                                while($s = $st_list->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($s['reg_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($s['full_name']); ?></td>
                                        <td>Year <?php echo $s['year']; ?>, Sem <?php echo $s['semester']; ?></td>
                                        <td><span style="color:green">● Active</span></td>
                                    </tr>
                                <?php endwhile; 
                            else: ?>
                                <tr><td colspan="4" style="text-align:center;">No students found in database.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="fees" class="tab-content <?php echo ($active_tab == 'fees') ? 'active' : ''; ?>">
    <h2>Fee Management System</h2>
    <div class="box">
        <table>
            <thead>
                <tr>
                    <th>Reg No</th>
                    <th>Name</th>
                    <th>Total (Ksh)</th>
                    <th>Paid (Ksh)</th>
                    <th>Balance (Ksh)</th>
                    <th>Status</th>
                    <th>Post Payment</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // We fetch everything and use 0 as a fallback if the student has no fee record yet
                $sql = "SELECT s.id, s.reg_number, s.full_name, 
                               IFNULL(f.initial_fee, 0) as total, 
                               IFNULL(f.paid_amount, 0) as paid, 
                               IFNULL(f.balance, 0) as bal 
                        FROM students s 
                        LEFT JOIN fees f ON s.id = f.student_id";
                
                $fee_list = $conn->query($sql);

                if ($fee_list): 
                    while($f = $fee_list->fetch_assoc()): 
                        // Logic for the badge
                        $is_cleared = ($f['bal'] <= 0 && $f['total'] > 0);
                        $status_color = $is_cleared ? '#00a65a' : '#f39c12';
                        $status_text = $is_cleared ? 'CLEARED' : 'PENDING';
                ?>
                    <tr>
    <td><?php echo htmlspecialchars($f['reg_number']); ?></td>
    <td><?php echo htmlspecialchars($f['full_name']); ?></td>
    <td><?php echo number_format($f['total'], 2); ?></td>
    <td>KES <?php echo number_format($f['paid'], 0); ?></td>
    <td>
        <p style="color: <?php echo ($f['bal'] > 0) ? '#e74c3c' : '#27ae60'; ?>; margin:0; font-weight:bold;">
            KES <?php echo number_format($f['bal'], 0); ?>
        </p>
    </td>
    <td>
        <span style="background:<?php echo $status_color; ?>; color:white; padding:2px 8px; border-radius:10px; font-size:11px;">
            <?php echo $status_text; ?>
        </span>
    </td>
    <td>
        <form method="POST" action="admin.php?tab=fees" style="margin:0;">
            <input type="hidden" name="student_id" value="<?php echo $f['id']; ?>">
            <input type="number" name="amount_paid" placeholder="Amount" min="0" required style="width:80px; padding:3px;">
            <button type="submit" name="update_fees" style="padding:3px 6px; background:#00a65a; color:white; border:none; border-radius:3px;">Pay</button>
        </form>
    </td>
</tr>
                <?php 
                    endwhile; 
                endif; 
                ?>
            </tbody>
        </table>
    </div>
</div>
            <div id="unitModal" class="modal">
    <div class="modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; margin-bottom:20px;">
            <h3 style="margin:0;">Add New Curriculum Unit</h3>
            <span onclick="document.getElementById('unitModal').style.display='none'" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        
        <form method="POST" action="admin.php?tab=units">
            <label>Unit Name</label>
            <input type="text" name="unit_name" placeholder="e.g. Database Management" required>
            
            <label>Unit Code</label>
            <input type="text" name="unit_code" placeholder="e.g. CIT 2104" required>
            
            <div class="form-grid">
                <div>
                    <label>Year</label>
                    <select name="year" required>
                        <option value="1">Year 1</option>
                        <option value="2">Year 2</option>
                    </select>
                </div>
                <div>
                    <label>Semester</label>
                    <select name="semester" required>
                        <option value="1">Semester 1</option>
                        <option value="2">Semester 2</option>
                    </select>
                </div>
            </div>

            <label>Assign to Course</label>
            <select name="course_id" required>
                <?php 
                $courses = $conn->query("SELECT * FROM courses");
                while($c = $courses->fetch_assoc()) {
                    echo "<option value='".$c['id']."'>".$c['course_name']."</option>";
                }
                ?>
            </select>

            <button type="submit" name="save_unit" class="bg-green" style="width:100%; color:white; border:none; padding:12px; margin-top:15px; cursor:pointer; font-weight:bold; border-radius:3px;">
                SAVE UNIT
            </button>
        </form>
    </div>
</div>

            <div id="units" class="tab-content <?php echo ($active_tab == 'units') ? 'active' : ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Curriculum Units by Course</h2>
                    <button onclick="document.getElementById('unitModal').style.display='block'" class="btn-load" style="background: #00a65a;">+ Add New Unit</button>
                </div>
                
                <?php 
                $course_list = $conn->query("SELECT * FROM courses");
                while($course = $course_list->fetch_assoc()): 
                ?>
                <div class="box" style="border-top: 3px solid #00c0ef;">
                    <h3 style="color: #222; margin-top: 0;"><i class="fa fa-graduation-cap"></i> <?php echo htmlspecialchars($course['course_name']); ?></h3>
                    
                    <div class="grid-4">
                        <?php for($y=1; $y<=2; $y++): for($s=1; $s<=2; $s++): ?>
                        <div style="background: #f9f9f9; padding: 10px; border-radius: 4px; border: 1px solid #ddd;">
                            <h5 style="margin: 0 0 10px 0; color: #00a65a; border-bottom: 1px solid #eee; padding-bottom: 5px;">
                                Year <?php echo $y; ?>, Sem <?php echo $s; ?>
                            </h5>
                            <table style="font-size: 12px;">
                                <?php 
                                $c_id = $course['id'];
                                $units = $conn->query("SELECT * FROM units WHERE course_id=$c_id AND year=$y AND semester=$s");
                                if($units && $units->num_rows > 0):
                                    while($u = $units->fetch_assoc()): ?>
                                        <tr>
                                            <td width="40%"><strong><?php echo htmlspecialchars($u['unit_code']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($u['unit_name']); ?></td>
                                        </tr>
                                    <?php endwhile; 
                                else: ?>
                                    <tr><td colspan="2" style="color:#999; font-style:italic;">No units assigned</td></tr>
                                <?php endif; ?>
                            </table>
                        </div>
                        <?php endfor; endfor; ?>
                    </div>
                </div>
                <hr style="border: 0; border-top: 1px dashed #ccc; margin: 20px 0;">
                <?php endwhile; ?>
            </div>
            <div class="card-row">
    <div class="card" style="background: #39dd91;"> 
        <?php 
            // Calculate total balance across the whole school
            $total_bal_query = $conn->query("SELECT SUM(balance) as total FROM fees");
            $res = $total_bal_query->fetch_assoc();
            $balance = $res['total'] ?? 0; 
        ?>
        <div class="stat-item">
            <h4 style="color: white; margin: 0;">Total Arrears</h4>
            <h3 style="margin: 5px 0;">
                KES <?php echo number_format($balance, 0); ?>
            </h3>
        </div>
        <i class="fa fa-money" style="position:absolute; right:10px; bottom:10px; font-size:50px; opacity:0.2;"></i>
    </div>
</div>

        <div id="attendance" class="tab-content <?php echo ($active_tab == 'attendance') ? 'active' : ''; ?>">
    <h2>Student Attendance</h2>

    <!-- Unit Selection Form -->
    <div class="box">
        <form method="GET" action="admin.php">
            <input type="hidden" name="tab" value="attendance">
            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <div style="flex: 1;">
                    <label style="font-size:12px; font-weight:bold;">Filter by Unit:</label>
                    <select name="unit_filter" required>
                        <option value="">-- Choose Unit --</option>
                        <?php 
                        $stmt_units = $conn->prepare("
                            SELECT u.id, u.unit_code, u.unit_name, c.course_name 
                            FROM units u 
                            JOIN courses c ON u.course_id = c.id 
                            ORDER BY c.course_name, u.year, u.semester
                        ");
                        $stmt_units->execute();
                        $result_units = $stmt_units->get_result();
                        while($u = $result_units->fetch_assoc()): ?>
                            <option value="<?php echo $u['id']; ?>" 
                                <?php echo (isset($_GET['unit_filter']) && $_GET['unit_filter'] == $u['id']) ? 'selected' : ''; ?>>
                                [<?php echo htmlspecialchars($u['course_name']); ?>] 
                                <?php echo htmlspecialchars($u['unit_code']); ?> - <?php echo htmlspecialchars($u['unit_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="btn-load">Load Class List</button>
            </div>
        </form>
    </div>
            

    <!-- Attendance Table -->
    <?php 
    if(isset($_GET['unit_filter']) && !empty($_GET['unit_filter'])): 
        $u_id = (int)$_GET['unit_filter']; 

        // Get unit info
        $stmt_unit = $conn->prepare("SELECT * FROM units WHERE id = ?");
        $stmt_unit->bind_param("i", $u_id);
        $stmt_unit->execute();
        $unit_info = $stmt_unit->get_result()->fetch_assoc();

        if($unit_info) {
            // Get eligible students
            $stmt_students = $conn->prepare("SELECT * FROM students WHERE course_id = ? AND year = ? AND semester = ?");
            $stmt_students->bind_param("iii", $unit_info['course_id'], $unit_info['year'], $unit_info['semester']);
            $stmt_students->execute();
            $eligible = $stmt_students->get_result();
    ?>
    <div class="box">
        <h4>Marking Attendance: <?php echo htmlspecialchars($unit_info['unit_name']); ?></h4>
        <form method="POST" action="save_attendance.php">
            <input type="hidden" name="unit_id" value="<?php echo $u_id; ?>">
            <table>
                <thead>
                    <tr>
                        <th>Reg Number</th>
                        <th>Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($eligible->num_rows > 0): 
                        while($st = $eligible->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($st['reg_number']); ?></td>
                            <td><?php echo htmlspecialchars($st['full_name']); ?></td>
                            <td>
                                <label>
                                    <input type="radio" name="status[<?php echo $st['id']; ?>]" value="Present" checked>
                                    <span style="color:green; font-weight:bold;">P</span>
                                </label>
                                <label style="margin-left:15px;">
                                    <input type="radio" name="status[<?php echo $st['id']; ?>]" value="Absent">
                                    <span style="color:red; font-weight:bold;">A</span>
                                </label>
                            </td>
                        </tr>
                        <?php endwhile; 
                    else: ?>
                        <tr>
                            <td colspan="3" style="text-align:center; color:red;">No students registered for this course/year/semester.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <button type="submit" name="submit_attendance" class="bg-green" 
                    style="width:100%; color:white; border:none; padding:12px; margin-top:15px; cursor:pointer; font-weight:bold; border-radius:3px;">
                SUBMIT ATTENDANCE
            </button>
        </form>
    </div>
    <?php 
        } else {
            echo "<p style='color:red;'>Unit not found!</p>";
        }
    endif; 
    ?>
</div>

            <div id="marks" class="tab-content <?php echo ($active_tab == 'marks') ? 'active' : ''; ?>">
                <h2>Examination Marks</h2>
                <div class="box">
                    <form method="GET" action="admin.php">
                        <input type="hidden" name="tab" value="marks">
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <div style="flex: 1;">
                                <label style="font-size:12px; font-weight:bold;">Select Unit to Grade:</label>
                                <select name="mark_unit_filter">
                                    <option value="">-- Choose Unit --</option>
                                    <?php 
                                    $all_units_m = $conn->query("SELECT u.*, c.course_name FROM units u JOIN courses c ON u.course_id = c.id ORDER BY c.course_name, u.year, u.semester");
                                    while($u = $all_units_m->fetch_assoc()): ?>
                                        <option value="<?php echo $u['id']; ?>" <?php echo (isset($_GET['mark_unit_filter']) && $_GET['mark_unit_filter'] == $u['id']) ? 'selected' : ''; ?>>
                                            [<?php echo htmlspecialchars($u['course_name']); ?>] <?php echo htmlspecialchars($u['unit_code']); ?> - <?php echo htmlspecialchars($u['unit_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn-load">Open Marks Sheet</button>
                        </div>
                    </form>
                </div>

                <?php if(isset($_GET['mark_unit_filter']) && !empty($_GET['mark_unit_filter'])): 
                    $mu_id = (int)$_GET['mark_unit_filter']; 
                    $munit_info = $conn->query("SELECT * FROM units WHERE id = $mu_id")->fetch_assoc();
                    $m_course_context = $munit_info['course_id'];
                    $meligible = $conn->query("SELECT * FROM students WHERE course_id = $m_course_context AND year = {$munit_info['year']} AND semester = {$munit_info['semester']}");
                ?>
                <div class="box">
                    <h4>Grading Sheet: <?php echo htmlspecialchars($munit_info['unit_name']); ?></h4>
                    <form method="POST" action="save_marks.php">
                        <input type="hidden" name="unit_id" value="<?php echo $mu_id; ?>">
                        <table>
                            <thead><tr><th>Reg Number</th><th>Name</th><th width="150">CAT (Max 30)</th><th width="150">Exam (Max 70)</th></tr></thead>
                            <tbody>
                                <?php if($meligible->num_rows > 0): 
                                    while($mst = $meligible->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($mst['reg_number']); ?></td>
                                        <td><?php echo htmlspecialchars($mst['full_name']); ?></td>
                                        <td><input type="number" name="cat[<?php echo $mst['id']; ?>]" class="mark-input" max="30" min="0" required placeholder="0"></td>
                                        <td><input type="number" name="exam[<?php echo $mst['id']; ?>]" class="mark-input" max="70" min="0" required placeholder="0"></td>
                                    </tr>
                                    <?php endwhile; 
                                else: ?>
                                    <tr><td colspan="4" style="text-align:center; color:red;">No students found for this specific course.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <button type="submit" name="submit_marks" class="bg-green" style="width:100%; color:white; border:none; padding:12px; margin-top:15px; cursor:pointer; font-weight:bold; border-radius:3px;">SAVE FINAL MARKS</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <div id="courses" class="tab-content <?php echo ($active_tab == 'courses') ? 'active' : ''; ?>">
                <h2>Available Courses</h2>
                <div class="box">
                    <table>
                        <thead><tr><th>Course Code</th><th>Course Title</th><th>Duration</th></tr></thead>
                        <tbody>
                            <?php $courses = $conn->query("SELECT * FROM courses"); 
                            if($courses) {
                                while($c = $courses->fetch_assoc()): ?>
                                    <tr><td><?php echo htmlspecialchars($c['course_code']); ?></td><td><?php echo htmlspecialchars($c['course_name']); ?></td><td>2 Years</td></tr>
                                <?php endwhile; 
                            } ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <div id="regModal" class="modal">
    <div class="modal-content">
        <h3 style="margin-top:0; color:#00a65a; border-bottom:1px solid #EEE; padding-bottom:10px;">New Student Admission</h3>
        <form method="POST">
            <label>Full Name</label>
            <input type="text" name="full_name" placeholder="Enter Student Name" required>
            
            <label>Registration Number</label>
            <input type="text" name="reg_number" placeholder="SIT/XXX/2026" required>

            <label>Email Address</label>
            <input type="email" name="email" placeholder="student@example.com" required style="width:100%; padding:10px; margin:8px 0; border:1px solid #ccc; border-radius:3px; box-sizing:border-box;">

            <label>Default Password</label>
            <input type="password" name="password" placeholder="Create a password" required style="width:100%; padding:10px; margin:8px 0; border:1px solid #ccc; border-radius:3px; box-sizing:border-box;">

            <label>Select Course</label>
            <select name="course_id" required>
                <option value="">-- Choose Course --</option>
                <?php 
                $course_query = $conn->query("SELECT * FROM courses");
                while($c = $course_query->fetch_assoc()): ?>
                    <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                <?php endwhile; ?>
            </select>

            <div style="display:flex; gap:10px;">
                <div style="flex:1;"><label>Year</label><select name="year"><option value="1">Year 1</option><option value="2">Year 2</option></select></div>
                <div style="flex:1;"><label>Semester</label><select name="semester"><option value="1">Sem 1</option><option value="2">Sem 2</option></select></div>
            </div>

            <button type="submit" name="add_student" style="width:100%; background:var(--header-green); color:white; border:none; padding:12px; margin-top:15px; cursor:pointer; font-weight:bold; border-radius:3px;">CONFIRM ADMISSION</button>
            <button type="button" onclick="document.getElementById('regModal').style.display='none'" style="width:100%; background:none; border:none; color:red; margin-top:10px; cursor:pointer;">Cancel</button>
        </form>
    </div>
</div>

  <script>
function switchTab(tabId) {
    // Hide all contents
    const contents = document.querySelectorAll('.tab-content');
    contents.forEach(c => c.classList.remove('active'));
    
    // Remove active class from all nav items
    const navs = document.querySelectorAll('.nav-item');
    navs.forEach(n => n.classList.remove('active'));
    
    // Show selected
    document.getElementById(tabId).classList.add('active');
    document.getElementById('btn-' + tabId).classList.add('active');
    
    // Update URL without refreshing (optional but professional)
    window.history.pushState({}, '', 'admin.php?tab=' + tabId);
}

// Close modals when clicking outside the box
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        event.target.style.display = "none";
    }
}
</script>
</body>
</html>