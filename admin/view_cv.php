<?php 
include 'dbcon.php'; 

// Check if ID parameter is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('<div style="text-align:center; padding:50px; font-family:Arial;">
         <h2>Error: No User ID Provided</h2>
         <p><a href="javascript:history.back()">Go Back</a></p>
         </div>');
}

$id = mysqli_real_escape_string($con, $_GET['id']);

// First try to get from jobregistration table
$squery = "SELECT * FROM jobregistration WHERE id='$id'";
$result = mysqli_query($con, $squery);

if (!$result) {
    die('<div style="text-align:center; padding:50px; font-family:Arial;">
         <h2>Database Error</h2>
         <p>' . mysqli_error($con) . '</p>
         <p><a href="javascript:history.back()">Go Back</a></p>
         </div>');
}

$row = mysqli_fetch_assoc($result);

if ($row && !empty($row['cv_doc'])) {
    // CV found in jobregistration table
    $cv_file = $row['cv_doc'];
    $cv_path = '../files/' . $cv_file;
    
    // Check if file exists
    if (!file_exists($cv_path)) {
        die('<div style="text-align:center; padding:50px; font-family:Arial;">
             <h2>CV File Not Found</h2>
             <p>File: ' . htmlspecialchars($cv_file) . '</p>
             <p>The CV file does not exist on the server.</p>
             <p><a href="javascript:history.back()">Go Back</a></p>
             </div>');
    }
    
    $file_extension = strtolower(pathinfo($cv_file, PATHINFO_EXTENSION));
    
} else {
    // No CV found, show error
    die('<div style="text-align:center; padding:50px; font-family:Arial;">
         <h2>No CV Found</h2>
         <p>No CV document found for this user.</p>
         <p><a href="javascript:history.back()">Go Back</a></p>
         </div>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View CV - <?php echo htmlspecialchars($row['name']); ?></title>
    <style>
        * {
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Ubuntu', sans-serif;
        }
        body {
            background: #f5f5f5;
        }
        .cv-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .cv-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }
        .cv-header .user-info {
            font-size: 0.9rem;
            opacity: 0.95;
        }
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        .error-message {
            text-align: center;
            padding: 50px;
            background: white;
            margin: 50px auto;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .error-message h2 {
            color: #dc3545;
            margin-bottom: 15px;
        }
        .cv-container {
            width: 100%;
            height: calc(100vh - 60px);
            background: white;
        }
        embed, iframe, object {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <div class="cv-header">
        <div>
            <h3>📄 Viewing CV</h3>
            <div class="user-info">
                Applicant: <strong><?php echo htmlspecialchars($row['name']); ?></strong> | 
                Email: <strong><?php echo htmlspecialchars($row['email']); ?></strong>
            </div>
        </div>
        <a href="javascript:history.back()" class="btn-back">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>
    
    <div class="cv-container">
        <?php if ($file_extension === 'pdf'): ?>
            <embed src="<?php echo htmlspecialchars($cv_path); ?>" type="application/pdf">
        <?php elseif (in_array($file_extension, ['doc', 'docx'])): ?>
            <iframe src="https://docs.google.com/viewer?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $cv_path); ?>&embedded=true"></iframe>
        <?php else: ?>
            <div class="error-message">
                <h2>Unsupported File Format</h2>
                <p>This CV format (<?php echo htmlspecialchars($file_extension); ?>) cannot be displayed in the browser.</p>
                <p><a href="<?php echo htmlspecialchars($cv_path); ?>" download class="btn btn-primary">Download CV</a></p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
