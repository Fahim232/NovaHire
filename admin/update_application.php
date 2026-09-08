<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin_login();

// This page manages the legacy `jobregistration` table.
// Only used by admins to update candidate details.
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: showdata.php');
    exit;
}

// Fetch candidate
$stmt = mysqli_prepare($con, "SELECT * FROM jobregistration WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$result) {
    header('Location: showdata.php?error=not_found');
    exit;
}

$success_msg = '';
$error_msg = '';

if (isset($_POST['submit'])) {
    require_csrf();

    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $degree  = trim($_POST['degree'] ?? '');
    $refer   = trim($_POST['refer'] ?? '');
    $plang   = trim($_POST['plang'] ?? '');

    $cv_doc = $result['cv_doc']; // keep existing by default

    if (!empty($_FILES['pdf_file']['name'])) {
        $allowed = ['pdf'];
        $max_size = 67108864; // 64MB

        if ($_FILES['pdf_file']['size'] > $max_size) {
            $error_msg = 'File too large. Maximum 64MB allowed.';
        } else {
            $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $error_msg = 'Only PDF files are allowed.';
            } else {
                $safe_name = 'cv_' . bin2hex(random_bytes(16)) . '.' . $ext;
                $dest = __DIR__ . '/../files/' . $safe_name;
                if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $dest)) {
                    $cv_doc = $safe_name;
                } else {
                    $error_msg = 'Failed to upload file.';
                }
            }
        }
    }

    if (empty($error_msg)) {
        $upd = mysqli_prepare($con, "UPDATE jobregistration SET name=?, phone=?, email=?, degree=?, refer=?, planguage=?, cv_doc=? WHERE id=?");
        mysqli_stmt_bind_param($upd, "sssssssi", $name, $phone, $email, $degree, $refer, $plang, $cv_doc, $id);
        if (mysqli_stmt_execute($upd)) {
            $success_msg = 'Candidate updated successfully.';
            // Refresh data
            $stmt2 = mysqli_prepare($con, "SELECT * FROM jobregistration WHERE id = ?");
            mysqli_stmt_bind_param($stmt2, "i", $id);
            mysqli_stmt_execute($stmt2);
            $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
            mysqli_stmt_close($stmt2);
        } else {
            $error_msg = 'Update failed. Please try again.';
        }
        mysqli_stmt_close($upd);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Update Candidate - NovaHire Admin</title>
    <?php include '../includes/links.php'; ?>
    <?php include 'header.php'; ?>
</head>
<body>
    <div class="container" style="margin-top: 30px; padding-bottom: 50px;">
        <h2 class="mb-4"><i class="fas fa-user-edit mr-2"></i>Update Candidate Details</h2>

        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success_msg); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error_msg); ?>
                <button type="button" class="close" data-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label><strong>Full Name</strong></label>
                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($result['name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><strong>Phone</strong></label>
                            <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($result['phone']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><strong>Email</strong></label>
                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($result['email']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><strong>Degree</strong></label>
                            <input type="text" class="form-control" name="degree" value="<?php echo htmlspecialchars($result['degree']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><strong>Programming Language</strong></label>
                            <input type="text" class="form-control" name="plang" value="<?php echo htmlspecialchars($result['planguage']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><strong>Reference</strong></label>
                            <input type="text" class="form-control" name="refer" value="<?php echo htmlspecialchars($result['refer']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label><strong>Upload New CV (PDF only, max 64MB)</strong></label>
                            <input type="file" class="form-control" name="pdf_file" accept=".pdf">
                            <small class="text-muted">Current: <?php echo htmlspecialchars($result['cv_doc']); ?></small>
                        </div>
                    </div>
                    <input type="hidden" name="MAX_FILE_SIZE" value="67108864">
                    <button type="submit" name="submit" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-save mr-1"></i>Update Candidate
                    </button>
                    <a href="showdata.php" class="btn btn-secondary rounded-pill px-4 ml-2">
                        <i class="fas fa-arrow-left mr-1"></i>Back
                    </a>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
