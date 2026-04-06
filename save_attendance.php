<?php
// 1. Connect to Database
$conn = new mysqli("localhost", "root", "", "student_db");

if (isset($_POST['submit_attendance'])) {
    $unit_id = $_POST['unit_id'];
    $statuses = $_POST['status']; // This is the array of student IDs and their status

    // 2. Prepare the Insert Statement
    $stmt = $conn->prepare("INSERT INTO attendance (student_id, unit_id, status) VALUES (?, ?, ?)");

    foreach ($statuses as $student_id => $status) {
        $stmt->bind_param("iis", $student_id, $unit_id, $status);
        $stmt->execute();
    }

    // 3. Redirect back to the dashboard with a success message
    header("Location: admin.php?tab=attendance&status=success");
    exit();
} else {
    // If someone tries to access this file directly, send them back
    header("Location: admin.php");
    exit();
}
?>