<?php
include("./connection/config.php");
include("./helpers/SystemOperators.php");

$con = connection();$so = new SystemOperators();

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnRegister'])){
    $raw_file_no = "DOC-" . date("Ymd") . "-" . strtoupper($so->randomStringGenerator(8));$file_no     = $so->encrypt($raw_file_no);

    $student_no =$so->encrypt(filter_input(INPUT_POST, 'student_no', FILTER_SANITIZE_SPECIAL_CHARS));
    $fname      =$so->encrypt(filter_input(INPUT_POST, 'firstname', FILTER_SANITIZE_SPECIAL_CHARS));
    $lname      =$so->encrypt(filter_input(INPUT_POST, 'lastname', FILTER_SANITIZE_SPECIAL_CHARS));
    $mname      =$so->encrypt(filter_input(INPUT_POST, 'middlename', FILTER_SANITIZE_SPECIAL_CHARS));
    $year_level =$so->encrypt(filter_input(INPUT_POST, 'year_level', FILTER_SANITIZE_SPECIAL_CHARS));
    $program    =$so->encrypt(filter_input(INPUT_POST, 'program', FILTER_SANITIZE_SPECIAL_CHARS));
    $email      =$so->encrypt(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS));
    $doc_type   =$so->encrypt(filter_input(INPUT_POST, 'doc_type', FILTER_SANITIZE_SPECIAL_CHARS));
    $purpose    =$so->encrypt(filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_SPECIAL_CHARS));
    
    $status     =$so->encrypt('Pending');
    $claiming_area =$so->encrypt('Pending Registrar Assignment');

    $insert_query = "INSERT INTO `document_requests` 
                    (`file_no`, `student_no`, `firstname`, `lastname`, `middlename`, `year_level`, `program`, `email`, `doc_type`, `purpose`, `claiming_area`, `status`) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $con->prepare($insert_query);
    $insert_stmt->bind_param('ssssssssssss',$file_no, $student_no,$fname, $lname,$mname, $year_level,$program, $email,$doc_type, $purpose,$claiming_area,$status);
    
    try {
        $insert_stmt->execute();
        echo "<script> 
                alert('Request submitted successfully!\\nYour Reference/File No. is: " . $raw_file_no . "\\nPlease save this number for tracking.');
                window.location='index.php';
              </script>";
    } catch(mysqli_sql_exception $e) {
        echo "Database Error: " . $e->getMessage();
    }
    
    $insert_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Request Form</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <h2>Student Document Request Form</h2>
    
    <form action="" method="post">
        <div class="form-fields">
            <div class="form-items">
                <label>Student No.:</label>
                <input type="text" name="student_no" required placeholder="2026-0001">
            </div>
            <div class="form-items">
                <label>Last Name:</label>
                <input type="text" name="lastname" required placeholder="Dela Cruz">
            </div>
            <div class="form-items">
                <label>First Name:</label>
                <input type="text" name="firstname" required placeholder="Juan">
            </div>
            <div class="form-items">
                <label>Middle Name:</label>
                <input type="text" name="middlename" placeholder="Santos / Leave a blank if none">
            </div>
            <div class="form-items">
                <label>Year Level:</label>
                <input type="text" name="year_level" placeholder="3rd Year, 4th Year, etc., N/A if not applicable">
            </div>
            <div class="form-items">
                <label>Degree Program / Course:</label>
                <input type="text" name="program" required placeholder="BSIT, BSN, BSBA, etc., "N/A" if not applicable">
            </div>
            <div class="form-items">
                <label>Email:</label>
                <input type="email" name="email" required placeholder="example@school.edu">
            </div>

            <hr>

            <div class="form-items">
                <label>Record Requested Type:</label>
                <select name="doc_type" required>
                    <option value="Cert. of Enrollment">Cert. of Enrollment</option>
                    <option value="Cert. of Registration">Cert. of Registration</option>
                    <option value="Cert. of Grades">Cert. of Grades</option>
                    <option value="Cert. of Graduation">Cert. of Graduation</option>
                    <option value="Form 137 / Transcript">Form 137 / Transcript</option>
                    <option value="Diploma">Diploma</option>
                    <option value="Health Record">Health Record</option>
                </select>
            </div>
            <div class="form-items">
                <label>Purpose:</label>
                <input type="text" name="purpose" required placeholder="Evaluation / Transfer">
            </div>

            <button type="submit" name="btnRegister">Submit Request</button>
        </div>
    </form>

    <br><hr><br>
    // yung part na 'to gawan nyo ng paraan kung pano nyo ilagay sa table na makikita lang ng admin ng mga buong detalyes ng mga submitted requests. Dito rin makikita ng admin kung sino yung nag submit ng request, ano yung request nila, at status ng request nila. //'
    // lipat nyo din yung part sa track.php na makikita lang ng student kung ano status ng request nila, at kung saan nila makukuha yung request nila once na approved na ito. //
    <h2>Submitted Requests Tracking List</h2>
    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>Ref No.</th>
                <th>Student No.</th>
                <th>Student Name</th>
                <th>Program</th>
                <th>File Type</th>
                <th>Purpose</th>
                <th>Claiming Area</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $select_docs = "SELECT * FROM `document_requests` ORDER BY `id` DESC";
            $select_docs_stmt =$con->prepare($select_docs);$select_docs_stmt->execute();
            $doclist =$select_docs_stmt->get_result();

            while($row =$doclist->fetch_assoc()){
                $file_no    =$so->decrypt($row['file_no']);$student_no = $so->decrypt($row['student_no']);
                $fname      =$so->decrypt($row['firstname']);$lname      = $so->decrypt($row['lastname']);
                $program    =$so->decrypt($row['program']);$doc_type   = $so->decrypt($row['doc_type']);
                $purpose    =$so->decrypt($row['purpose']);$claiming_area = $so->decrypt($row['claiming_area']);
                $status     =$so->decrypt($row['status']);
            ?>
            <tr>
                <td><strong><?php echo $file_no; ?></strong></td>
                <td><?php echo $student_no; ?></td>
                <td><?php echo $fname . " " . $lname; ?></td>
                <td><?php echo $program; ?></td>
                <td><?php echo $doc_type; ?></td>
                <td><?php echo $purpose; ?></td>
                <td><?php echo $claiming_area; ?></td>
                <td><?php echo $status; ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>

</body>
</html>