<?php
session_start();
include '../controls/connection.php';
include '../controls/hire_functions.php'; // Include hire functions
require '../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$is_logged_in = isset($_SESSION['user_id']);

// Fetch user details
$sql = "SELECT firstname, middlename, lastname, fb_link, email, location, date_created, contact, 
               COALESCE(is_verified, 0) AS is_verified, profile_picture, role
        FROM users 
        WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
} else {
    echo "User details not found.";
    exit();
}
$stmt->close();

// Fetch posted jobs (for laborers)
$job_sql = "SELECT jobs.job_id, jobs.job_name, user_jobs.job_description, user_jobs.job_image
            FROM jobs
            INNER JOIN user_jobs ON jobs.job_id = user_jobs.job_id
            WHERE user_jobs.user_id = ?";
$job_stmt = $conn->prepare($job_sql);
$job_stmt->bind_param("i", $user_id);
$job_stmt->execute();
$job_result = $job_stmt->get_result();
$job_stmt->close();

// Fetch hire requests for this laborer
$hire_requests = getHiresForUser($conn, $user_id, 'laborer');

// Handle Accept/Decline action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_hire'])) {
    $hire_id = intval($_POST['hire_id']);
    $action = $_POST['action'] === 'accepted' ? 'accepted' : 'declined';

    // Update hire status
    $response_msg = respondToHire($conn, $hire_id, $action);

    // ✅ Send email notification to employer
    sendHireResponseEmail($conn, $hire_id, $action);

    echo "<script>alert('".htmlspecialchars($response_msg)."'); window.location.href='".$_SERVER['PHP_SELF']."';</script>";
}

// ✅ Email notification when hire is accepted or declined
function sendHireResponseEmail($conn, $hire_id, $action) {
    $sql = "SELECT 
                h.employer_id, 
                h.laborer_id, 
                h.meeting_location, 
                h.message,
                u1.firstname AS employer_firstname, 
                u1.lastname AS employer_lastname, 
                u1.email AS employer_email,
                u2.firstname AS laborer_firstname, 
                u2.lastname AS laborer_lastname, 
                u2.email AS laborer_email
            FROM hires h
            JOIN users u1 ON h.employer_id = u1.user_id
            JOIN users u2 ON h.laborer_id = u2.user_id
            WHERE h.id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("sendHireResponseEmail SQL error: " . $conn->error);
        return;
    }

    $stmt->bind_param("i", $hire_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();

        $employer_name = trim($row['employer_firstname'] . ' ' . $row['employer_lastname']);
        $laborer_name  = trim($row['laborer_firstname'] . ' ' . $row['laborer_lastname']);
        $meeting_location = $row['meeting_location'];
        $message = $row['message'];
        $employer_email = $row['employer_email'];

        // Email subject and body
        $subject = ($action === 'accepted')
            ? "Your hire request has been accepted!"
            : "Your hire request has been declined.";

        $status_color = ($action === 'accepted') ? '#28a745' : '#dc3545';
        $status_text  = ucfirst($action);

        // ✅ Professionally styled email body
        $body = "
        <div style='font-family: Arial, sans-serif; background-color:#f8f9fa; padding:20px; border-radius:8px;'>
            <h2 style='color:#007bff;'>Servify Notification</h2>
            <p>Dear <strong>{$employer_name}</strong>,</p>
            <p>Your hire request for <strong>{$laborer_name}</strong> has been 
            <strong style='color:{$status_color};'>{$status_text}</strong>.</p>
            
            <div style='margin-top:15px; padding:10px; background:#fff; border-radius:6px; border:1px solid #ddd;'>
                <p><b>Meeting Location:</b> {$meeting_location}</p>
                <p><b>Message:</b> {$message}</p>
            </div>

            <p style='margin-top:15px;'>Thank you for using <strong>Servify</strong>.</p>
            <hr style='border:none; border-top:1px solid #ccc;'>
            <p style='font-size:12px; color:#555;'>This is an automated message, please do not reply.</p>
        </div>";

        // Send the email
        sendEmail($employer_email, $subject, $body);
    } else {
        error_log("No hire found for ID: $hire_id");
    }
}

// ✅ Reusable sendEmail function using PHPMailer
function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;

        // Use your Gmail account & app password here
        $mail->Username   = 'shanekherby2828@gmail.com';
        $mail->Password   = 'gmum gtma drra ffhd';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Sender identity (Servify)
        $mail->setFrom('shanekherby2828@gmail.com', 'Servify Notifications');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Profile</title>
  <link rel="stylesheet" href="../styles/profile.css?v=<?php echo time(); ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { padding-top: 10px; }
  </style>
</head>

<body>

<!-- NAVIGATION BAR -->
<header>
  <div class="header-content">
    <div class="brand"><a href="../index.php">Servify</a></div>
    <div class="menu-container">
      <nav class="wrapper-2" id="menu">
        <p><a href="../view/browse.php">Services</a></p>
        <!-- <p><a href="#">Become a laborer</a></p> -->
        <p class="divider">|</p>

        <?php if ($is_logged_in): ?>
          <p class="profile-wrapper">
            <span class="profile-icon" onclick="toggleProfileMenu()">
              <img src="../<?php echo htmlspecialchars($row['profile_picture']) ?: 'uploads/profile_pics/default.jpg'; ?>" alt="Profile Picture" class="icon">
            </span>
            <div id="profile-menu" class="profile-menu d-none">
              <a href="../view/profile.php" class="user-info-link">
                <div class="user-info">
                  <span><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']); ?></span>
                  <i class="bi bi-pencil-square"></i>
                </div>
              </a>  
              <a href="../view/messages.php"><i class="bi bi-chat-dots"></i> Inbox</a>
              <!--<a href="#"><i class="bi bi-bell"></i> Notifications</a>
              <a href="#"><i class="bi bi-grid"></i> Dashboard</a>-->
              <a href="../controls/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
          </p>
        <?php else: ?>
          <p class="login"><a href="../view/login.php"><i class="bi bi-box-arrow-in-right"></i> Login / Signup</a></p>
        <?php endif; ?>
      </nav>
    </div>
  </div>
</header>

<!-- Edit Labor Modal -->
<div class="modal fade" id="editJobModal" tabindex="-1" aria-labelledby="editJobLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editJobLabel">Edit Labor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="editJobContent">
        <!-- Edit form will load here via AJAX -->
        <div class="text-center py-5 text-muted">Loading...</div>
      </div>
    </div>
  </div>
</div>


<!-- Bottom Navigation (Mobile/Tablet Only) -->
<div class="bottom-nav mobile-only">
  <a href="../index.php">
    <div class="nav-item active" onclick="goToHome()">
      <i class="bi bi-house"></i>
      <span>Home</span>
    </div>
  </a>
  <a href="../view/browse.php">
    <div class="nav-item" onclick="goToServices()">
      <i class="bi bi-search"></i>
      <span>Services</span>
    </div>
  </a>
  
  <div class="nav-item" onclick="toggleMoreMenu()">
    <i class="bi bi-three-dots"></i>
    <span>More</span>
  </div>
</div>


<!-- Fullscreen More Menu -->
<div id="more-menu" class="fullscreen-menu d-none">
  <div class="menu-panel">
    <div class="menu-header">
      <h1 class="menu-title">SERVIFY</h1>
      <span class="close-btn" onclick="toggleMoreMenu()">✕</span>
    </div>

    <?php if ($is_logged_in): ?>
      <!-- Logged-in User Menu -->
      <div class="user-section">
        <div class="profile-info" onclick="toggleProfileMenu()">
          <a href="../view/profile.php" class="profile-link">
            <img src="../<?php echo htmlspecialchars($row['profile_picture']) ?: 'uploads/profile_pics/default.jpg'; ?>" alt="Profile Picture" class="icon">
            <h3 class="user-name"><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']); ?></h3>
          </a>
        </div>
        <i class="bi bi-pencil-square edit-icon"></i>
      </div>

      <div class="section-divider"></div>

      <div class="menu-options">
        <a href="../view/messages.php"><i class="bi bi-chat-dots"></i> Inbox</a>
        <a href="#"><i class="bi bi-bell"></i> Notifications</a>
        <a href="#"><i class="bi bi-grid"></i> Dashboard</a>
        <a href="../controls/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>

    <?php else: ?>
      <!-- Non-User Menu -->
      <div class="menu-options">
        <a href="../view/become-laborer.php"><i class="bi bi-person-workspace"></i> Become a laborer</a>
        <a href="../view/login.php"><i class="bi bi-person-circle"></i> Signin / Signup</a>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- CUSTOM PROFILE PAGE -->
<div class="custom-profile-page">
  <main class="grid-container">
    <!-- PROFILE SECTION -->
    <div class="profile-section position-relative">
      <div class="profile-row">
        <div class="profile-img">
          <img src="../<?php echo htmlspecialchars($row['profile_picture']) ?: 'uploads/profile_pics/default.jpg'; ?>" alt="Profile Picture">
        </div>

        <div class="profile-info">
          <!-- Name + Verification -->
          <div class="name-verification d-flex align-items-center gap-2 mb-2">
            <h2 class="profile-name mb-0"><?php echo htmlspecialchars($row['firstname'] . ' ' . $row['middlename'] . ' ' . $row['lastname']); ?></h2>
           <span class="verification-badge <?php echo $row['is_verified'] ? 'verified' : 'not-verified'; ?>">
      <?php echo $row['is_verified'] ? 'Verified' : 'Not Verified'; ?>
    </span>
          </div>

          <!--Location -->
          <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['location']); ?></p>
          <p><strong>Member since: </strong> <?php echo date('F j, Y', strtotime($row['date_created'])); ?></p>

          <!-- Action Buttons -->
          <div class="profile-buttons">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editDetailsModal">
              Edit Profile
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Contact info + Hire Requests Section -->
    <?php if ($row['role'] === 'laborer'): ?>
   
      <div class="hire-requests mb-5 left-section">
        <h4>Contact Information</h4>
        <p><strong>Email: </strong> <?php echo htmlspecialchars($row['email']); ?></p>
          <p><strong>Contact: </strong> <?php echo htmlspecialchars($row['contact']); ?></p>
          <p><strong>Facebook: </strong> <a href="<?php echo htmlspecialchars($row['fb_link']); ?>" target="_blank"><?php echo htmlspecialchars($row['fb_link']);?></a></p>
        <br><hr><br>
        <h4>Hire Requests</h4>
        <?php if ($hire_requests->num_rows > 0): ?>
          <ul class="list-group">
            <?php while ($hire = $hire_requests->fetch_assoc()): ?>
              <li class="list-group-item d-flex justify-content-between align-items-center flex-column flex-md-row">
                <div>
                  <strong>From: <?php echo htmlspecialchars($hire['employer_firstname'] . ' ' . $hire['employer_middlename'] . ' ' . $hire['employer_lastname']); ?></strong><br>
                  <strong>Message:</strong> <?php echo htmlspecialchars($hire['message']); ?><br>
                  <strong>Location:</strong> <?php echo htmlspecialchars($hire['meeting_location']); ?><br>
                  <strong>Status:</strong> <?php echo ucfirst($hire['status']); ?>
                </div>
                <?php if ($hire['status'] === 'pending'): ?>
                  <div class="mt-2 mt-md-0">
                    <form action="" method="POST" class="d-inline">
                      <input type="hidden" name="hire_id" value="<?php echo $hire['id']; ?>">
                      <input type="hidden" name="action" value="accepted">
                      <button type="submit" name="respond_hire" class="btn btn-success btn-sm">Accept</button>
                    </form>
                    <form action="" method="POST" class="d-inline">
                      <input type="hidden" name="hire_id" value="<?php echo $hire['id']; ?>">
                      <input type="hidden" name="action" value="declined">
                      <button type="submit" name="respond_hire" class="btn btn-danger btn-sm">Decline</button>
                    </form>
                  </div>
                <?php endif; ?>
              </li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <p>No hire requests at the moment.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>


   <!-- Edit Details Modal -->
    <div class="modal fade" id="editDetailsModal" tabindex="-1" aria-labelledby="editDetailsLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editDetailsLabel">Edit Profile Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form action="../controls/user/update_profile.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
              <div class="row">
                <!-- Your form fields go here -->
                <div class="col-md-6 mb-3">
                  <label>First Name:</label>
                  <input type="text" name="firstname" value="<?php echo htmlspecialchars($row['firstname']); ?>" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label>Middle Name:</label>
                  <input type="text" name="middlename" value="<?php echo htmlspecialchars($row['middlename']); ?>" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                  <label>Last Name:</label>
                  <input type="text" name="lastname" value="<?php echo htmlspecialchars($row['lastname']); ?>" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label>Email:</label>
                  <input type="email" name="email" value="<?php echo htmlspecialchars($row['email']); ?>" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label>Contact:</label>
                  <input type="text" name="contact" value="<?php echo htmlspecialchars($row['contact']); ?>" class="form-control" required>
                </div>
                <div class="col-md-6 mb-3">
                  <label>Facebook Link:</label>
                  <input type="text" name="fb_link" value="<?php echo htmlspecialchars($row['fb_link']); ?>" class="form-control">
                </div>
                <div class="col-md-12 mb-3">
                  <label>Location:</label>
                  <input type="text" name="location" value="<?php echo htmlspecialchars($row['location']); ?>" class="form-control" required>
                </div>
                <div class="col-md-12 mb-3">
                  <label>Profile Picture:</label>
                  <input type="file" name="profile_pic" class="form-control">
                </div>
              </div>
              <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>


    <!-- Show Job List ONLY if user is laborer -->
    <?php if ($row['role'] === 'laborer'): ?>
     <div class="right-section">
      <div class="job-container mb-4">
        <h4>My Posted Jobs</h4>
        <?php if ($job_result->num_rows > 0): ?>
          <ul class="list-group">
            <?php while ($job = $job_result->fetch_assoc()): ?>
              <li class="list-group-item">
                <div class="d-flex justify-content-between align-items-start flex-column flex-md-row">
                  <div class="w-100">
                    <div class="d-flex justify-content-between align-items-center">
                      <strong><?php echo htmlspecialchars($job['job_name']); ?></strong>
                      <!-- Dropdown aligned with job name on mobile/tablet -->
                      <div class="dropdown d-md-none">
                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="jobActionsMobile<?php echo $job['job_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="jobActionsMobile<?php echo $job['job_id']; ?>">
                          <li>
                            <button 
                              type="button" 
                              class="dropdown-item text-warning p-0 " 
                              onclick="openEditJob(<?php echo $job['job_id']; ?>)">
                              Edit
                            </button>
                          </li>
                          <li>
                            <form action="delete_labor.php" method="POST" class="px-3 py-1">
                              <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                              <button type="submit" class="dropdown-item text-danger p-0" onclick="return confirm('Are you sure you want to delete this job?');">Delete</button>
                            </form>
                          </li>
                        </ul>
                      </div>
                    </div>
                    <p class="mb-1"><?php echo htmlspecialchars($job['job_description']); ?></p>
                    <?php if (!empty($job['job_image'])): ?>
                      <img src="http://localhost/servify/uploads/<?php echo htmlspecialchars($job['job_image']); ?>" alt="Job Image" class="img-fluid rounded" style="width: 100px; height: 100px; object-fit: cover;">
                    <?php else: ?>
                      <p class="text-muted">No job image available.</p>
                    <?php endif; ?>
                  </div>

                  <!-- Desktop dropdown -->
                  <!-- Desktop dropdown -->
<div class="dropdown mt-2 mt-md-0 d-none d-md-block ms-md-3">
  <button class="btn btn-outline-secondary btn-sm" type="button" id="jobActionsDesktop<?php echo $job['job_id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="bi bi-three-dots-vertical"></i>
  </button>
  <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="jobActionsDesktop<?php echo $job['job_id']; ?>">
    <li>
      <button 
        type="button" 
        class="dropdown-item text-warning" 
        onclick="openEditJob(<?php echo $job['job_id']; ?>)">
        Edit
      </button>
    </li>
    <li>
      <button 
        type="submit" 
        form="deleteJobForm<?php echo $job['job_id']; ?>" 
        class="dropdown-item text-danger">
        Delete
      </button>
      <!-- Hidden form -->
      <form id="deleteJobForm<?php echo $job['job_id']; ?>" action="delete_labor.php" method="POST" class="d-none">
        <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
      </form>
    </li>
  </ul>
</div>

                </div>
              </li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <p class="text-muted">No jobs posted yet.</p>
        <?php endif; ?>
      </div>

      <!-- Add Labor -->
      <div class="text-center mb-4">
        <form action="add_labor.php" method="POST">
          <button type="submit" class="btn btn-primary">+ Add Labor</button>
        </form>
      </div>
      <?php endif; ?>

    <!-- Account Verification Section -->
    <hr><br>
    <div class="verification-container mb-5">
      <h4>Account Verification</h4>
      <div class="alert alert-info">
        <strong>Verification Status:</strong> 
        <?php echo ($row['is_verified'] == 1) ? '✅ Verified' : '❌ Not Verified'; ?>
      </div>

      <?php if ($row['is_verified'] == 0): ?>
        <p>Your account has not been verified yet. Please upload the required documents for verification.</p>
        <form action="../controls/user/upload_verification.php" method="POST" enctype="multipart/form-data">
          <label for="id_proof">Primary ID (Barangay ID if laborer):</label>
          <input type="file" name="id_proof" id="id_proof" class="form-control mb-2" required>
          <label for="supporting_doc">Supporting document (e.g. Birth Certificate, other Government Issued ID, etc.)</label>
          <input type="file" name="supporting_doc" id="supporting_doc" class="form-control mb-2" required>
          <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
          <button type="submit" class="btn btn-success">Upload Documents</button>
        </form>

      <?php endif; ?>
    </div>
   </div>
  </main>
</div>
<script>
function openEditJob(jobId) {
  const modalBody = document.getElementById('editJobContent');
  modalBody.innerHTML = '<div class="text-center py-5 text-muted">Loading...</div>';

  // Fetch edit form via AJAX
  fetch('edit_labor_content.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'job_id=' + jobId
  })
  .then(response => response.text())
  .then(html => {
    modalBody.innerHTML = html;
    const modal = new bootstrap.Modal(document.getElementById('editJobModal'));
    modal.show();
  })
  .catch(err => {
    modalBody.innerHTML = '<div class="text-danger text-center py-5">Error loading edit form.</div>';
    console.error(err);
  });
}
</script>



<script>
  function toggleEditForm() {
    const form = document.getElementById('editDetailsForm');
    form.style.display = (form.style.display === 'none' || form.style.display === '') ? 'block' : 'none';
  }
</script>


<script>
  function toggleMenu() {
    const menu = document.getElementById('menu');
    menu.classList.toggle('active');
  }
</script>

<script>
  // Toggle profile dropdown
  function toggleProfileMenu() {
    const menu = document.getElementById('profile-menu');
    menu.classList.toggle('d-none');
  }

  // Toggle fullscreen "More" menu
  function toggleMoreMenu() {
    const menu = document.getElementById('more-menu');
    menu.classList.toggle('d-none');
  }

  // Navigation actions
  function goToHome() {
    window.location.href = '../view/home.php';
  }

  function goToServices() {
    window.location.href = '../view/services.php';
  }
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>