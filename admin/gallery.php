<?php
// Admin Media Gallery CRUD Module
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Enforce admin login
require_admin();

$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_image') {
        $title = trim($_POST['title'] ?? '');
        $is_featured = intval($_POST['is_featured'] ?? 0);

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            try {
                $f_name = $_FILES['photo']['name'];
                $f_tmp = $_FILES['photo']['tmp_name'];
                $f_size = $_FILES['photo']['size'];
                $f_type = $_FILES['photo']['type'];

                if ($f_size > 5 * 1024 * 1024) {
                    throw new Exception("File size limit exceeded. Max 5MB allowed.");
                }

                $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
                $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp'];
                $ext = strtolower(pathinfo($f_name, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed_extensions) || !in_array($f_type, $allowed_mimes)) {
                    throw new Exception("Invalid image format. Allowed: JPG, PNG, WEBP.");
                }

                $dest_dir = __DIR__ . '/../uploads/gallery/';
                if (!file_exists($dest_dir)) {
                    mkdir($dest_dir, 0777, true);
                }

                $new_filename = 'gal_' . md5(uniqid() . $f_name) . '.' . $ext;
                $dest_path = $dest_dir . $new_filename;

                if (move_uploaded_file($f_tmp, $dest_path)) {
                    $db_path = 'uploads/gallery/' . $new_filename;
                    $stmt = $pdo->prepare("INSERT INTO `gallery` (image, title, is_featured) VALUES (?, ?, ?)");
                    $stmt->execute([$db_path, $title, $is_featured]);
                    $message = "Photo uploaded and saved to gallery successfully.";
                } else {
                    throw new Exception("Failed to store file on server.");
                }

            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = "Please select a valid file to upload.";
        }
    } elseif ($action === 'delete_image') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                // Fetch file path to delete from disk
                $stmt = $pdo->prepare("SELECT image FROM `gallery` WHERE id = ?");
                $stmt->execute([$id]);
                $img_path = $stmt->fetchColumn();
                
                if ($img_path && file_exists(__DIR__ . '/../' . $img_path)) {
                    unlink(__DIR__ . '/../' . $img_path);
                }

                $pdo->prepare("DELETE FROM `gallery` WHERE id = ?")->execute([$id]);
                $message = "Image deleted from gallery.";
            } catch (Exception $e) {
                $error = "Failed to remove image: " . $e->getMessage();
            }
        }
    }
}

// Fetch gallery
$gallery = [];
try {
    if ($pdo) {
        $gallery = $pdo->query("SELECT * FROM `gallery` ORDER BY created_at DESC")->fetchAll();
    }
} catch (Exception $e) {}

if (empty($gallery)) {
    $gallery = [
        ['id' => 1, 'image' => 'assets/images/vehicles/sedan.png', 'title' => 'Maruti Dzire Fleet', 'is_featured' => 1],
        ['id' => 2, 'image' => 'assets/images/vehicles/suv.png', 'title' => 'Innova Crysta Luxury', 'is_featured' => 1],
        ['id' => 3, 'image' => 'assets/images/vehicles/ertiga.png', 'title' => 'Ertiga MPV Premium', 'is_featured' => 0],
        ['id' => 4, 'image' => 'assets/images/vehicles/luxury.png', 'title' => 'Mercedes VIP Sedan', 'is_featured' => 1],
        ['id' => 5, 'image' => 'assets/images/vehicles/tempo.png', 'title' => 'Tempo Traveller 17S', 'is_featured' => 0],
        ['id' => 6, 'image' => 'assets/images/vehicles/bus.png', 'title' => 'Executive Tour Coach', 'is_featured' => 0]
    ];
}

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold font-poppins text-white">Media Gallery Manager</h1>
        <p class="text-xs text-slate-400">Upload travel banners, luxury fleet photos, or route sights shown on user galleries</p>
    </div>

    <?php if ($message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-3 rounded-lg text-xs mb-6">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-rose-500/10 border border-rose-500/20 text-rose-400 p-3 rounded-lg text-xs mb-6">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <!-- Upload Form (4 columns) -->
        <div class="lg:col-span-4 bg-slate-900 border border-slate-800 p-5 rounded-xl h-fit">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Upload Media File</h3>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                <input type="hidden" name="action" value="upload_image">
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Image Caption / Title</label>
                    <input type="text" name="title" placeholder="VIP Highway Ride" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400" required>
                </div>
                
                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Featured Placement</label>
                    <select name="is_featured" class="w-full px-3 py-2 bg-slate-950 border border-slate-805 rounded text-white focus:outline-none focus:border-sky-400">
                        <option value="0">Standard Gallery Photo</option>
                        <option value="1">Featured Highlight</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Select Photo (JPG, PNG, WEBP max 5MB)</label>
                    <input type="file" name="photo" class="w-full text-xs text-slate-400 file:mr-3 file:py-1.5 file:px-2.5 file:rounded file:border-0 file:text-[11px] file:font-semibold file:bg-slate-950 file:text-slate-350 hover:file:bg-slate-850 cursor-pointer" required>
                </div>

                <button type="submit" class="w-full py-2 bg-gradient-to-r from-blue-600 to-sky-500 hover:from-blue-500 hover:to-sky-400 text-white font-bold rounded text-xs transition-colors">
                    Upload to Gallery
                </button>
            </form>
        </div>

        <!-- Gallery Grid (8 columns) -->
        <div class="lg:col-span-8 bg-slate-900 border border-slate-800 rounded-xl p-5">
            <h3 class="text-xs font-bold text-white uppercase tracking-wider mb-4">Media Library</h3>
            
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <?php if (!empty($gallery)): ?>
                    <?php foreach ($gallery as $img): ?>
                        <div class="bg-slate-950 border border-slate-850 rounded-lg overflow-hidden relative group">
                            <div class="h-24 bg-slate-900 p-1 flex items-center justify-center">
                                <img src="../<?php echo htmlspecialchars($img['image']); ?>" alt="gallery" class="w-full h-full object-contain" onerror="this.onerror=null; this.src='../assets/images/vehicles/sedan.png';">
                            </div>
                            
                            <div class="p-2 flex justify-between items-center text-[10px]">
                                <span class="truncate text-slate-300 font-medium max-w-[80px]" title="<?php echo htmlspecialchars($img['title']); ?>"><?php echo htmlspecialchars($img['title'] ?: 'Untitled'); ?></span>
                                
                                <form method="POST" onsubmit="return confirm('Remove this image?')">
                                    <input type="hidden" name="action" value="delete_image">
                                    <input type="hidden" name="id" value="<?php echo $img['id']; ?>">
                                    <button type="submit" class="text-rose-500 hover:text-rose-400 font-bold">Delete</button>
                                </form>
                            </div>
                            
                            <?php if ($img['is_featured']): ?>
                                <span class="absolute top-1 left-1 bg-amber-500 text-slate-950 text-[8px] font-bold px-1.5 py-0.5 rounded border border-amber-600/30">FEATURED</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="col-span-4 text-slate-500 text-xs italic text-center py-10">No photos uploaded to the gallery library yet.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
