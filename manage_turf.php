<?php
require_once 'config/db.php';
require_once 'includes/helpers.php';

if (!is_logged_in() || !is_owner()) {
    header("Location: index.html");
    exit();
}

$turf_id = $_GET['id'] ?? 0;

if (!$turf_id) {
    die("Error: No Turf ID provided.");
}

// Fetch Turf Data
try {
    $stmt = $pdo->prepare("SELECT * FROM turfs WHERE id = ? AND owner_id = ?");
    $stmt->execute([$turf_id, $_SESSION['user_id']]);
    $turf = $stmt->fetch();

    if (!$turf) {
        die("Error: Turf not found or you don't have permission.");
    }

    // Fetch Gallery
    $imgStmt = $pdo->prepare("SELECT * FROM turf_images WHERE turf_id = ? ORDER BY is_primary DESC");
    $imgStmt->execute([$turf_id]);
    $gallery = $imgStmt->fetchAll();

    // Fetch Slots
    $slotStmt = $pdo->prepare("SELECT * FROM time_slots WHERE turf_id = ? ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'), start_time ASC");
    $slotStmt->execute([$turf_id]);
    $slots = $slotStmt->fetchAll();

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}

$csrf_token = get_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage <?php echo htmlspecialchars($turf['name']); ?> - TurfKick</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        :root {
            --primary: #1f4037;
            --secondary: #99f2c8;
            --danger: #ff5f6d;
            --glass: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
        }
        body { background: linear-gradient(135deg, #1f4037, #0f2027); color: #fff; font-family: 'Segoe UI', sans-serif; min-height: 100vh; margin: 0; }
        .manage-container { max-width: 1000px; margin: 50px auto; padding: 40px; background: var(--glass); border-radius: 30px; backdrop-filter: blur(20px); border: 1px solid var(--border); }
        .tabs { display: flex; gap: 20px; border-bottom: 1px solid var(--border); margin-bottom: 30px; }
        .tab { padding: 12px 25px; cursor: pointer; opacity: 0.6; transition: 0.3s; font-weight: 600; }
        .tab.active { opacity: 1; border-bottom: 3px solid var(--secondary); color: var(--secondary); }
        .tab-content { display: none; animation: fadeIn 0.5s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 14px; opacity: 0.8; }
        .form-group input, .form-group textarea, .form-group select { 
            width: 100%; padding: 12px; border-radius: 12px; border: 1px solid var(--border); 
            background: rgba(0,0,0,0.2); color: #fff; outline: none; box-sizing: border-box;
        }

        .btn { padding: 12px 25px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-primary { background: var(--secondary); color: var(--primary); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-outline { background: transparent; border: 1px solid #fff; color: #fff; }

        .gallery-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; margin-top: 20px; }
        .gallery-item { position: relative; border-radius: 15px; overflow: hidden; aspect-ratio: 1; border: 1px solid var(--border); }
        .gallery-item img { width: 100%; height: 100%; object-fit: cover; }
        .delete-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 95, 109, 0.4); display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; cursor: pointer; }
        .gallery-item:hover .delete-overlay { opacity: 1; }

        .slot-card { background: rgba(255,255,255,0.03); border: 1px solid var(--border); border-radius: 15px; padding: 15px 20px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; }
        
        /* MULTI-DAY STYLING */
        #multiDaySection { display: none; margin-top: 15px; border-top: 1px solid var(--border); padding-top: 15px; }
        .day-chip { display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.05); padding: 8px 12px; border-radius: 50px; border: 1px solid var(--border); cursor: pointer; font-size: 13px; transition: 0.3s; }
        .day-chip input { display: none; }
        .day-chip.active { background: var(--secondary); color: var(--primary); border-color: var(--secondary); font-weight: bold; }
    </style>
</head>
<body>
    <div class="manage-container">
        <a href="owner_dashboard.html" style="color: var(--secondary); text-decoration: none; font-weight: bold;">← Back to Dashboard</a>
        <h1 style="margin: 20px 0 10px 0;"><?php echo htmlspecialchars($turf['name']); ?></h1>
        
        <div class="tabs">
            <div class="tab active" onclick="switchTab('details', this)">Details</div>
            <div class="tab" onclick="switchTab('gallery', this)">Gallery</div>
            <div class="tab" onclick="switchTab('slots', this)">Slots</div>
            <div class="tab" onclick="switchTab('equipment', this)">Equipment</div>
        </div>

        <!-- DETAILS -->
        <div id="details" class="tab-content active">
            <form id="editTurfForm">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="turf_id" value="<?php echo $turf['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group"><label>Turf Name</label><input type="text" name="name" value="<?php echo htmlspecialchars($turf['name']); ?>"></div>
                <div class="form-group"><label>Price per Hour (₹)</label><input type="number" name="price" value="<?php echo htmlspecialchars($turf['price_per_hour']); ?>"></div>
                <div class="form-group"><label>Location</label><input type="text" name="location" value="<?php echo htmlspecialchars($turf['location']); ?>"></div>
                <div class="form-group"><label>Description</label><textarea name="description" rows="4"><?php echo htmlspecialchars($turf['description']); ?></textarea></div>
                <button type="button" class="btn btn-primary" onclick="saveDetails()">Update Details</button>
            </form>
        </div>

        <!-- GALLERY -->
        <div id="gallery" class="tab-content">
            <form id="uploadForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="turf_id" value="<?php echo $turf['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group"><input type="file" name="image" accept="image/*"></div>
                <button type="button" class="btn btn-outline" onclick="uploadImage()">Upload Photo</button>
            </form>
            <div class="gallery-grid">
                <?php foreach ($gallery as $img): ?>
                    <div class="gallery-item"><img src="<?php echo htmlspecialchars($img['image_path']); ?>"><div class="delete-overlay" onclick="deleteImage(<?php echo $img['id']; ?>)">&times;</div></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SLOTS -->
        <div id="slots" class="tab-content">
            <div style="background: rgba(255,255,255,0.03); padding: 25px; border-radius: 20px; margin-bottom: 30px;">
                <h4 style="margin-top:0;">Add Time Slot</h4>
                <form id="addSlotForm">
                    <input type="hidden" name="action" value="add_bulk_slot">
                    <input type="hidden" name="turf_id" value="<?php echo $turf['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group"><label>Start Time</label><input type="time" id="slotStart"></div>
                        <div class="form-group"><label>End Time</label><input type="time" id="slotEnd"></div>
                    </div>

                    <div class="form-group" id="singleDaySelect">
                        <label>Select Day</label>
                        <select id="slotDay">
                            <option>Monday</option><option>Tuesday</option><option>Wednesday</option>
                            <option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 15px;">
                        <span id="multiToggleBtn" style="color: var(--secondary); cursor: pointer; font-size: 13px; font-weight: bold;" onclick="toggleMultiDay()">+ Apply to multiple days</span>
                    </div>

                    <div id="multiDaySection">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <label>Select Days</label>
                            <span style="font-size: 12px; color: var(--secondary); cursor: pointer;" onclick="selectAllDays(true)">Select All</span>
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                            <?php $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                            foreach($days as $d): ?>
                                <label class="day-chip">
                                    <input type="checkbox" name="days[]" value="<?php echo $d; ?>" onchange="updateChipStyle(this)"> <?php echo $d; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" style="margin-top: 20px;" onclick="addSlot()">Add Slot</button>
                </form>
            </div>

            <h4>Existing Slots</h4>
            <div id="slotsList">
                <?php foreach ($slots as $s): ?>
                    <div class="slot-card">
                        <div><strong><?php echo $s['day_of_week']; ?></strong> <span style="margin-left: 15px; opacity: 0.7;"><?php echo date("h:i A", strtotime($s['start_time'])); ?> - <?php echo date("h:i A", strtotime($s['end_time'])); ?></span></div>
                        <span style="color: var(--danger); cursor: pointer; font-weight: bold;" onclick="deleteSlot(<?php echo $s['id']; ?>)">Remove</span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- EQUIPMENT -->
        <div id="equipment" class="tab-content">
            <div style="background: rgba(255,255,255,0.03); padding: 25px; border-radius: 20px; margin-bottom: 30px;">
                <h4 style="margin-top:0;">Add Equipment</h4>
                <div class="form-group"><label>Item Name (e.g. Bat, Ball)</label><input type="text" id="itemName"></div>
                <div class="form-group"><label>Price (₹)</label><input type="number" id="itemPrice"></div>
                <button type="button" class="btn btn-primary" onclick="addEquipment()">Add Equipment</button>
            </div>
            <h4>Existing Equipment</h4>
            <div id="equipmentList">
                <!-- Loaded via JS -->
            </div>
        </div>
    </div>

    <script>
        function switchTab(id, btn) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(id).classList.add('active');
        }

        let isMultiDay = false;
        function toggleMultiDay() {
            isMultiDay = !isMultiDay;
            document.getElementById('multiDaySection').style.display = isMultiDay ? 'block' : 'none';
            document.getElementById('singleDaySelect').style.display = isMultiDay ? 'none' : 'block';
            document.getElementById('multiToggleBtn').innerText = isMultiDay ? '- Apply to single day' : '+ Apply to multiple days';
        }

        function updateChipStyle(cb) {
            cb.parentElement.classList.toggle('active', cb.checked);
        }

        function selectAllDays(check) {
            document.querySelectorAll('input[name="days[]"]').forEach(cb => {
                cb.checked = check;
                updateChipStyle(cb);
            });
        }

        async function fetchEquipment() {
            const res = await fetch('api/manage_equipment.php?turf_id=<?php echo $turf_id; ?>');
            const data = await res.json();
            const list = document.getElementById('equipmentList');
            if(data.status === 'success') {
                list.innerHTML = data.data.map(item => `
                    <div class="slot-card">
                        <div><strong>${item.name}</strong> <span style="margin-left: 15px; opacity: 0.7;">₹${item.price}</span></div>
                        <span style="color: var(--danger); cursor: pointer; font-weight: bold;" onclick="deleteEquipment(${item.id})">Remove</span>
                    </div>
                `).join('');
            }
        }

        async function addEquipment() {
            const name = document.getElementById('itemName').value;
            const price = document.getElementById('itemPrice').value;
            if(!name || !price) return alert("Fill all fields.");

            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('turf_id', '<?php echo $turf_id; ?>');
            formData.append('name', name);
            formData.append('price', price);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');

            const res = await fetch('api/manage_equipment.php', { method: 'POST', body: formData });
            const data = await res.json();
            alert(data.message);
            if(data.status === 'success') {
                document.getElementById('itemName').value = '';
                document.getElementById('itemPrice').value = '';
                fetchEquipment();
            }
        }

        async function deleteEquipment(id) {
            if(!confirm("Delete this item?")) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('item_id', id);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');
            const res = await fetch('api/manage_equipment.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(data.status === 'success') fetchEquipment();
            else alert(data.message);
        }

        async function addSlot() {
            const start = document.getElementById('slotStart').value;
            const end = document.getElementById('slotEnd').value;
            if(!start || !end) return alert("Select times.");

            let selectedDays = [];
            if (isMultiDay) {
                selectedDays = Array.from(document.querySelectorAll('input[name="days[]"]:checked')).map(cb => cb.value);
                if (selectedDays.length === 0) return alert("Select at least one day.");
            } else {
                selectedDays = [document.getElementById('slotDay').value];
            }
            
            const formData = new FormData();
            formData.append('action', 'add_bulk_slot');
            formData.append('turf_id', '<?php echo $turf_id; ?>');
            formData.append('start_time', start);
            formData.append('end_time', end);
            formData.append('days', JSON.stringify(selectedDays));
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');

            const res = await fetch('api/manage_slots.php', { method: 'POST', body: formData });
            const data = await res.json();
            alert(data.message);
            if(data.status === 'success') location.reload();
        }

        async function deleteSlot(id) {
            if(!confirm("Delete this slot?")) return;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('slot_id', id);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');
            const res = await fetch('api/manage_slots.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(data.status === 'success') location.reload();
            else alert(data.message);
        }

        async function saveDetails() {
            const formData = new FormData(document.getElementById('editTurfForm'));
            const res = await fetch('api/manage_turfs.php', { method: 'POST', body: formData });
            const data = await res.json();
            alert(data.message);
            if(data.status === 'success') location.reload();
        }

        async function uploadImage() {
            const formData = new FormData(document.getElementById('uploadForm'));
            const res = await fetch('api/manage_turfs.php', { method: 'POST', body: formData });
            const data = await res.json();
            alert(data.message);
            if(data.status === 'success') location.reload();
        }

        async function deleteImage(id) {
            if(!confirm("Delete this image?")) return;
            const formData = new FormData();
            formData.append('action', 'delete_image');
            formData.append('image_id', id);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');
            const res = await fetch('api/manage_turfs.php', { method: 'POST', body: formData });
            const data = await res.json();
            if(data.status === 'success') location.reload();
            else alert(data.message);
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchEquipment();
        });
    </script>
</body>
</html>
