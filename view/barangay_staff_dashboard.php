<?php 
include '../controls/connection.php';

// --- Handle Confirm Report ---
if (isset($_GET['confirm'])) {
    $report_id = intval($_GET['confirm']);
    $user_id = intval($_GET['user_id']);
    
    // 1. Fetch user data
    $user_res = $conn->query("SELECT * FROM users WHERE user_id=$user_id");
    if ($user_row = $user_res->fetch_assoc()) {
        // 2. Insert into archive
        $stmt = $conn->prepare("INSERT INTO archive (firstname, middlename, lastname, fb_link, location, date_created, email, password, contact, role, rating, credit_score, is_verified, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssssiiiis",
            $user_row['firstname'],
            $user_row['middlename'],
            $user_row['lastname'],
            $user_row['fb_link'],
            $user_row['location'],
            $user_row['date_created'],
            $user_row['email'],
            $user_row['password'],
            $user_row['contact'],
            $user_row['role'],
            $user_row['rating'],
            $user_row['credit_score'],
            $user_row['is_verified'],
            $user_row['profile_picture']
        );
        $stmt->execute();
        
        // 3. Optionally delete the user from users table
        $conn->query("DELETE FROM users WHERE user_id=$user_id");

        // 4. Delete the report
        $conn->query("DELETE FROM reports WHERE report_id=$report_id");

        header("Location: barangay_staff_dashboard.php?success=User archived successfully");
        exit();
    }
}

// --- Handle Reject Report ---
if (isset($_GET['reject'])) {
    $report_id = intval($_GET['reject']);
    $conn->query("DELETE FROM reports WHERE report_id=$report_id");
    header("Location: barangay_staff_dashboard.php?success=Report rejected successfully");
    exit();
}


// Fetch pending verification requests
$verification_query = "SELECT v.request_id, v.user_id, v.id_proof, v.supporting_doc, v.status, u.firstname, u.lastname 
                       FROM verification_requests v 
                       JOIN users u ON v.user_id = u.user_id
                       WHERE v.status = 'pending'";
$verification_result = $conn->query($verification_query);

// Fetch pending reports
$report_query = "SELECT r.report_id, r.user_id, r.reason, r.additional_details, r.attachment, r.status, 
                        u.firstname, u.lastname 
                 FROM reports r 
                 JOIN users u ON r.user_id = u.user_id 
                 WHERE r.status = 'pending'";
$report_result = $conn->query($report_query);

// Fetch accepted hires
$hires_query = "SELECT h.*, 
                       e.firstname AS employer_firstname, e.middlename AS employer_middlename, e.lastname AS employer_lastname,
                       l.firstname AS laborer_firstname, l.middlename AS laborer_middlename, l.lastname AS laborer_lastname
                FROM hires h
                JOIN users e ON h.employer_id = e.user_id
                JOIN users l ON h.laborer_id = l.user_id
                WHERE h.status='accepted'
                ORDER BY h.created_at DESC";
$hires_result = $conn->query($hires_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Barangay Staff Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="flex h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-teal-800 text-white p-5 flex flex-col">
        <h1 class="text-2xl font-bold mb-6">Barangay Panel</h1>
        <nav class="flex-1">
            <ul class="space-y-2">
                <li><button onclick="showSection('verification')" class="w-full text-left p-2 rounded hover:bg-teal-700">Verification</button></li>
                <li><button onclick="showSection('reports')" class="w-full text-left p-2 rounded hover:bg-teal-700">Reports</button></li>
                <li><button onclick="showSection('hires')" class="w-full text-left p-2 rounded hover:bg-teal-700">Accepted Hires</button></li>
                <li><button onclick="showSection('announcements')" class="w-full text-left p-2 rounded hover:bg-teal-700">Announcements</button></li>
                <li><a href="../controls/logout.php" class="w-full block p-2 rounded hover:bg-teal-700">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="flex-1 p-6 overflow-auto">
        <!-- Verification Section -->
        <div id="verification" class="section hidden">
            <h2 class="text-2xl font-semibold mb-4">Verification Applications</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border p-2">User ID</th>
                            <th class="border p-2">Name</th>
                            <th class="border p-2">ID Proof</th>
                            <th class="border p-2">Supporting Doc</th>
                            <th class="border p-2">Status</th>
                            <th class="border p-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $verification_result->fetch_assoc()): ?>
                        <tr class="border hover:bg-gray-50">
                            <td class="border p-2"><?php echo $row['user_id']; ?></td>
                            <td class="border p-2"><?php echo htmlspecialchars($row['firstname'] . " " . $row['lastname']); ?></td>
                            <td class="border p-2"><a href="uploads/<?php echo $row['id_proof']; ?>" target="_blank" class="text-blue-500 hover:underline">View ID</a></td>
                            <td class="border p-2"><a href="uploads/<?php echo $row['supporting_doc']; ?>" target="_blank" class="text-blue-500 hover:underline">View Doc</a></td>
                            <td class="border p-2"><?php echo ucfirst($row['status']); ?></td>
                            <td class="border p-2 whitespace-nowrap">
                                <a href="view_user.php?user_id=<?php echo $row['user_id']; ?>" class="text-blue-500 hover:underline">View Profile</a> | 
                                <a href="../controls/admin/approve_verification.php?request_id=<?php echo $row['request_id']; ?>" class="text-green-500 hover:underline">Approve</a> | 
                                <a href="../controls/admin/reject_verification.php?request_id=<?php echo $row['request_id']; ?>" class="text-red-500 hover:underline">Reject</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- Reports Table -->
<div id="reports" class="section">
    <h2 class="text-2xl font-semibold mb-4">Pending Reports</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full border border-gray-200 text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="border p-2">Report ID</th>
                    <th class="border p-2">User ID</th>
                    <th class="border p-2">Name</th>
                    <th class="border p-2">Reason</th>
                    <th class="border p-2 w-1/3">Details</th>
                    <th class="border p-2">Attachment</th>
                    <th class="border p-2">Status</th>
                    <th class="border p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $report_result->fetch_assoc()): ?>
                <tr class="border hover:bg-gray-50">
                    <td class="border p-2"><?php echo $row['report_id']; ?></td>
                    <td class="border p-2"><?php echo $row['user_id']; ?></td>
                    <td class="border p-2"><?php echo htmlspecialchars($row['firstname'] . " " . $row['lastname']); ?></td>
                    <td class="border p-2"><?php echo ucfirst($row['reason']); ?></td>
                    <td class="border p-2 break-words"><?php echo htmlspecialchars($row['additional_details']); ?></td>
                    <td class="border p-2 text-center">
                        <?php if (!empty($row['attachment']) && file_exists("uploads/reports/" . basename($row['attachment']))): ?>
                            <a href="uploads/reports/<?php echo basename($row['attachment']); ?>" target="_blank" class="text-blue-500 hover:underline">View Attachment</a>
                        <?php else: ?>
                            <span class="text-gray-400 italic">No attachment</span>
                        <?php endif; ?>
                    </td>
                    <td class="border p-2"><?php echo ucfirst($row['status']); ?></td>
                    <td class="border p-2 whitespace-nowrap">
                        <a href="view_user.php?user_id=<?php echo $row['user_id']; ?>" class="text-blue-500 hover:underline">View Profile</a> | 
                        <a href="?confirm=<?php echo $row['report_id']; ?>&user_id=<?php echo $row['user_id']; ?>&reason=<?php echo $row['reason']; ?>" class="text-green-500 hover:underline ml-1">Confirm</a> | 
                        <a href="?reject=<?php echo $row['report_id']; ?>" class="text-red-500 hover:underline ml-1" onclick="return confirm('Are you sure you want to reject this report?')">Reject</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>


        <!-- Accepted Hires Section -->
        <div id="hires" class="section hidden">
            <h2 class="text-2xl font-semibold mb-4">Accepted Hires</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-200 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border p-2">Employer</th>
                            <th class="border p-2">Laborer</th>
                            <th class="border p-2">Message</th>
                            <th class="border p-2">Meeting Location</th>
                            <th class="border p-2">Status</th>
                            <th class="border p-2">Date/Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($hire = $hires_result->fetch_assoc()): ?>
                        <tr class="border hover:bg-gray-50">
                            <td class="border p-2"><?php echo htmlspecialchars($hire['employer_firstname'] . " " . $hire['employer_middlename'] . " " . $hire['employer_lastname']); ?></td>
                            <td class="border p-2"><?php echo htmlspecialchars($hire['laborer_firstname'] . " " . $hire['laborer_middlename'] . " " . $hire['laborer_lastname']); ?></td>
                            <td class="border p-2"><?php echo htmlspecialchars($hire['message']); ?></td>
                            <td class="border p-2"><?php echo htmlspecialchars($hire['meeting_location']); ?></td>
                            <td class="border p-2"><?php echo ucfirst($hire['status']); ?></td>
                            <td class="border p-2"><?php echo date('F j, Y, g:i A', strtotime($hire['created_at'])); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Announcements Section -->
        <div id="announcements" class="section hidden">
            <h2 class="text-2xl font-semibold mb-4">Barangay Announcements</h2>

            <?php
            // Handle Add, Delete, Edit (same as your original code)
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_announcement'])) {
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                $image_path = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = "uploads/announcements/";
                    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
                    $file_tmp = $_FILES['image']['tmp_name'];
                    $file_name = time() . "_" . basename($_FILES['image']['name']);
                    $file_path = $upload_dir . $file_name;
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg','jpeg','png','gif'])) {
                        move_uploaded_file($file_tmp, $file_path);
                        $image_path = "uploads/announcements/" . $file_name;
                    }
                }
                if (!empty($title) && !empty($content)) {
                    $stmt = $conn->prepare("INSERT INTO barangay_announcements (title, content, image_path) VALUES (?,?,?)");
                    $stmt->bind_param("sss",$title,$content,$image_path);
                    $stmt->execute();
                    echo "<p class='text-green-600 font-medium mb-3'>✅ Announcement added successfully!</p>";
                }
            }

            if (isset($_POST['delete_announcement'])) {
                $id = intval($_POST['announcement_id']);
                $res = $conn->query("SELECT image_path FROM barangay_announcements WHERE announcement_id=$id");
                if ($row = $res->fetch_assoc()) {
                    if (!empty($row['image_path']) && file_exists("../".$row['image_path'])) unlink("../".$row['image_path']);
                }
                $conn->query("DELETE FROM barangay_announcements WHERE announcement_id=$id");
                echo "<p class='text-red-600 font-medium mb-3'>🗑️ Announcement deleted.</p>";
            }

            if (isset($_POST['edit_announcement'])) {
                $id = intval($_POST['announcement_id']);
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                $image_path = $_POST['existing_image'];
                if (isset($_FILES['image']) && $_FILES['image']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = "uploads/announcements/";
                    if (!file_exists($upload_dir)) mkdir($upload_dir,0777,true);
                    $file_tmp = $_FILES['image']['tmp_name'];
                    $file_name = time() . "_" . basename($_FILES['image']['name']);
                    $file_path = $upload_dir.$file_name;
                    move_uploaded_file($file_tmp,$file_path);
                    $image_path = "uploads/announcements/" . $file_name;
                }
                $stmt = $conn->prepare("UPDATE barangay_announcements SET title=?, content=?, image_path=? WHERE announcement_id=?");
                $stmt->bind_param("sssi",$title,$content,$image_path,$id);
                $stmt->execute();
                echo "<p class='text-blue-600 font-medium mb-3'>✏️ Announcement updated successfully!</p>";
            }

            $ann_query = "SELECT * FROM barangay_announcements ORDER BY date_posted DESC";
            $ann_result = $conn->query($ann_query);
            ?>

            <form method="POST" enctype="multipart/form-data" class="mb-6 bg-gray-50 p-4 rounded-lg">
                <h4 class="font-semibold text-lg mb-3">Add New Announcement</h4>
                <div class="mb-3">
                    <label class="block font-medium mb-1">Title</label>
                    <input type="text" name="title" required class="w-full p-2 border border-gray-300 rounded">
                </div>
                <div class="mb-3">
                    <label class="block font-medium mb-1">Content</label>
                    <textarea name="content" required rows="3" class="w-full p-2 border border-gray-300 rounded"></textarea>
                </div>
                <div class="mb-3">
                    <label class="block font-medium mb-1">Image (optional)</label>
                    <input type="file" name="image" accept="image/*" class="w-full p-2 border border-gray-300 rounded">
                </div>
                <button type="submit" name="add_announcement" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">Post Announcement</button>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-200 text-sm">
                    <thead>
                        <tr class="bg-gray-100 text-left">
                            <th class="border p-2">Title</th>
                            <th class="border p-2 w-1/2">Content</th>
                            <th class="border p-2">Image</th>
                            <th class="border p-2">Date</th>
                            <th class="border p-2 text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($ann=$ann_result->fetch_assoc()): ?>
                        <tr class="border hover:bg-gray-50 transition">
                            <td class="border p-2 font-semibold"><?php echo htmlspecialchars($ann['title']); ?></td>
                            <td class="border p-2"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></td>
                            <td class="border p-2 text-center">
    <?php if (!empty($ann['image_path'])): ?>
        <img src="<?php echo '../' . htmlspecialchars($ann['image_path']); ?>" class="w-16 h-16 object-cover rounded mx-auto">
    <?php else: ?>
        <span class="text-gray-400 italic">No image</span>
    <?php endif; ?>
</td>


                            <td class="border p-2 text-gray-600"><?php echo date('F j, Y, g:i A', strtotime($ann['date_posted'])); ?></td>
                            <td class="border p-2 text-center">
                                <button type="button" onclick="toggleEditForm(<?php echo $ann['announcement_id']; ?>)" class="text-blue-600 hover:text-blue-800 font-medium mr-3">Edit</button>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="announcement_id" value="<?php echo $ann['announcement_id']; ?>">
                                    <button type="submit" name="delete_announcement" class="text-red-600 hover:text-red-800 font-medium" onclick="return confirm('Delete this announcement?');">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <tr id="editForm<?php echo $ann['announcement_id']; ?>" class="hidden bg-gray-50">
                            <td colspan="5" class="p-4">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="announcement_id" value="<?php echo $ann['announcement_id']; ?>">
                                    <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($ann['image_path']); ?>">
                                    <div class="mb-3">
                                        <label class="block font-medium mb-1">Title</label>
                                        <input type="text" name="title" value="<?php echo htmlspecialchars($ann['title']); ?>" required class="w-full p-2 border border-gray-300 rounded">
                                    </div>
                                    <div class="mb-3">
                                        <label class="block font-medium mb-1">Content</label>
                                        <textarea name="content" rows="3" required class="w-full p-2 border border-gray-300 rounded"><?php echo htmlspecialchars($ann['content']); ?></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="block font-medium mb-1">Image (optional)</label>
                                        <input type="file" name="image" accept="image/*" class="w-full p-2 border border-gray-300 rounded">
                                    </div>
                                    <button type="submit" name="edit_announcement" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Update</button>
                                    <button type="button" onclick="toggleEditForm(<?php echo $ann['announcement_id']; ?>)" class="bg-gray-300 hover:bg-gray-400 px-3 py-1 rounded ml-2">Cancel</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
function showSection(id) {
    document.querySelectorAll('.section').forEach(s => s.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');
}
function toggleEditForm(id) {
    const form = document.getElementById('editForm'+id);
    form.classList.toggle('hidden');
}
showSection('verification'); // Show default
</script>
</body>
</html>
