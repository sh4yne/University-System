<?php
// students.php  — full CRUD with search, filters, pagination, modals
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$db = db();

// ── POST ACTIONS ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $fields = ['student_number','first_name','last_name','email','phone','gender',
                   'birth_date','address','department_id','year_level','status','enrollment_date'];
        $data = [];
        foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

        if (!$data['student_number'] || !$data['first_name'] || !$data['last_name'] || !$data['email'] || !$data['department_id']) {
            flash('error', 'Required fields are missing.');
        } else {
            try {
                if ($action === 'add') {
                    $sql = "INSERT INTO students (student_number,first_name,last_name,email,phone,gender,birth_date,address,department_id,year_level,status,enrollment_date)
                            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";
                    $db->prepare($sql)->execute(array_values($data));
                    flash('success', 'Student added successfully.');
                } else {
                    $id = (int)($_POST['student_id'] ?? 0);
                    $sql = "UPDATE students SET student_number=?,first_name=?,last_name=?,email=?,phone=?,gender=?,birth_date=?,address=?,department_id=?,year_level=?,status=?,enrollment_date=? WHERE student_id=?";
                    $db->prepare($sql)->execute([...array_values($data), $id]);
                    flash('success', 'Student updated successfully.');
                }
            } catch (PDOException $e) {
                flash('error', 'Error: ' . ($e->getCode() == 23000 ? 'Student number or email already exists.' : $e->getMessage()));
            }
        }

    } elseif ($action === 'delete') {
        $id = (int)($_POST['student_id'] ?? 0);
        $db->prepare("UPDATE students SET status='Inactive' WHERE student_id=?")->execute([$id]);
        flash('success', 'Student deactivated.');
    }

    header('Location: /university/students.php'); exit;
}

// ── FETCH for Edit modal ────────────────────────────────
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM students WHERE student_id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editData = $stmt->fetch();
}

// ── FILTERS & PAGINATION ────────────────────────────────
$search  = trim($_GET['search']  ?? '');
$deptF   = (int)($_GET['dept']   ?? 0);
$statusF = $_GET['status'] ?? '';
$yearF   = (int)($_GET['year']   ?? 0);
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;

$where  = ['1=1']; $params = [];
if ($search)  { $where[] = "(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_number LIKE ? OR s.email LIKE ?)"; $like="%$search%"; array_push($params,$like,$like,$like,$like); }
if ($deptF)   { $where[] = 's.department_id=?'; $params[] = $deptF; }
if ($statusF) { $where[] = 's.status=?';        $params[] = $statusF; }
if ($yearF)   { $where[] = 's.year_level=?';    $params[] = $yearF; }
$whereSQL = implode(' AND ', $where);

$total = (int)$db->prepare("SELECT COUNT(*) FROM students s WHERE $whereSQL")->execute($params) ? $db->prepare("SELECT COUNT(*) FROM students s WHERE $whereSQL")->execute($params) : 0;
$countStmt = $db->prepare("SELECT COUNT(*) FROM students s WHERE $whereSQL");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT s.*, d.dept_name FROM students s
    JOIN departments d ON s.department_id=d.department_id
    WHERE $whereSQL ORDER BY s.last_name,s.first_name LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$students = $stmt->fetchAll();

$departments = $db->query("SELECT * FROM departments ORDER BY dept_name")->fetchAll();

layoutHead('Students');
layoutSidebar('students.php');
?>

<?php if ($editData): ?>
<script>document.addEventListener('DOMContentLoaded',()=>openModal('edit'));</script>
<?php endif; ?>

<?php flashBanner(); ?>

<div class="page-header">
  <div>
    <h1 class="page-title">Students</h1>
    <p class="page-sub"><?= number_format($total) ?> student<?= $total != 1 ? 's' : '' ?> found</p>
  </div>
  <button class="btn btn-primary" onclick="openModal('add')">+ Add Student</button>
</div>

<div class="card">
  <!-- FILTERS -->
  <form method="GET" class="filters">
    <input type="text" name="search" class="filter-input"
           placeholder="Search name, number, email…" value="<?= htmlspecialchars($search) ?>">
    <select name="dept" class="filter-select">
      <option value="">All Departments</option>
      <?php foreach ($departments as $d): ?>
      <option value="<?= $d['department_id'] ?>" <?= $deptF==$d['department_id']?'selected':'' ?>>
        <?= sanitize($d['dept_name']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="filter-select">
      <option value="">All Status</option>
      <?php foreach (['Active','Inactive','Graduated','On Leave'] as $s): ?>
      <option <?= $statusF===$s?'selected':'' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
    <select name="year" class="filter-select">
      <option value="">All Years</option>
      <?php for($y=1;$y<=4;$y++): ?>
      <option value="<?= $y ?>" <?= $yearF==$y?'selected':'' ?>>Year <?= $y ?></option>
      <?php endfor; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="/university/students.php" class="btn btn-outline btn-sm">Reset</a>
  </form>

  <!-- TABLE -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Student #</th><th>Name</th><th>Department</th>
          <th>Year</th><th>Email</th><th>Enrolled</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $s): ?>
        <tr>
          <td><span class="td-mono"><?= sanitize($s['student_number']) ?></span></td>
          <td>
            <div style="font-weight:600"><?= sanitize($s['first_name'].' '.$s['last_name']) ?></div>
            <div style="font-size:12px;color:#94a3b8"><?= sanitize($s['phone'] ?? '') ?></div>
          </td>
          <td><?= sanitize($s['dept_name']) ?></td>
          <td><span class="badge badge-info">Year <?= $s['year_level'] ?></span></td>
          <td style="font-size:13px"><?= sanitize($s['email']) ?></td>
          <td style="font-size:12px;color:#64748b"><?= $s['enrollment_date'] ?></td>
          <td><?php
            $cls = match($s['status']) {
              'Active'=>'success','Graduated'=>'info','On Leave'=>'warning',default=>'muted'
            };
            echo "<span class=\"badge badge-{$cls}\">{$s['status']}</span>";
          ?></td>
          <td>
            <div class="td-actions">
              <a href="?edit=<?= $s['student_id'] ?>" class="btn-icon">✏️ Edit</a>
              <button class="btn-icon btn-del"
                onclick="confirmDelete(<?= $s['student_id'] ?>,'<?= sanitize($s['first_name'].' '.$s['last_name']) ?>')">
                🗑 Delete
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$students): ?>
        <tr><td colspan="8" class="table-empty">No students found. <a href="/university/students.php">Clear filters</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- PAGINATION -->
  <?php if ($totalPages > 1): ?>
  <div class="pagination">
    <span class="info">Showing <?= $offset+1 ?>–<?= min($offset+$perPage,$total) ?> of <?= $total ?></span>
    <?php
    $qs = http_build_query(array_filter(['search'=>$search,'dept'=>$deptF,'status'=>$statusF,'year'=>$yearF]));
    $base = '/university/students.php?'.$qs.'&page=';
    ?>
    <a href="<?= $base.max(1,$page-1) ?>" class="page-link <?= $page<=1?'disabled':'' ?>">← Prev</a>
    <?php for($p=max(1,$page-2); $p<=min($totalPages,$page+2); $p++): ?>
    <a href="<?= $base.$p ?>" class="page-link <?= $p==$page?'active':'' ?>"><?= $p ?></a>
    <?php endfor; ?>
    <a href="<?= $base.min($totalPages,$page+1) ?>" class="page-link <?= $page>=$totalPages?'disabled':'' ?>">Next →</a>
  </div>
  <?php endif; ?>
</div>

<!-- ADD / EDIT MODAL -->
<div class="modal-overlay" id="modal-add">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title">Add New Student</span>
    <button class="modal-close" onclick="closeModal('modal-add')">×</button>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="add">
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Student Number *</label>
          <input type="text" name="student_number" class="form-control" placeholder="2024-CS-001" required>
        </div>
        <div class="form-group">
          <label class="form-label">Year Level *</label>
          <select name="year_level" class="form-control" required>
            <?php for($y=1;$y<=4;$y++): ?><option value="<?=$y?>">Year <?=$y?></option><?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">First Name *</label>
          <input type="text" name="first_name" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name *</label>
          <input type="text" name="last_name" class="form-control" required>
        </div>
        <div class="form-group full">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-control">
            <option value="">— Select —</option>
            <option>Male</option><option>Female</option><option>Other</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Birth Date</label>
          <input type="date" name="birth_date" class="form-control">
        </div>
        <div class="form-group">
          <label class="form-label">Department *</label>
          <select name="department_id" class="form-control" required>
            <option value="">— Select —</option>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['department_id'] ?>"><?= sanitize($d['dept_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option>Active</option><option>Inactive</option><option>Graduated</option><option>On Leave</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Enrollment Date</label>
          <input type="date" name="enrollment_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group full">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Student</button>
    </div>
  </form>
</div>
</div>

<!-- EDIT MODAL -->
<?php if ($editData): ?>
<div class="modal-overlay" id="modal-edit">
<div class="modal">
  <div class="modal-header">
    <span class="modal-title">Edit Student</span>
    <button class="modal-close" onclick="window.location='/university/students.php'">×</button>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="edit">
    <input type="hidden" name="student_id" value="<?= $editData['student_id'] ?>">
    <div class="modal-body">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Student Number *</label>
          <input type="text" name="student_number" class="form-control" value="<?= sanitize($editData['student_number']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Year Level *</label>
          <select name="year_level" class="form-control">
            <?php for($y=1;$y<=4;$y++): ?><option value="<?=$y?>" <?=$editData['year_level']==$y?'selected':''?>>Year <?=$y?></option><?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">First Name *</label>
          <input type="text" name="first_name" class="form-control" value="<?= sanitize($editData['first_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Last Name *</label>
          <input type="text" name="last_name" class="form-control" value="<?= sanitize($editData['last_name']) ?>" required>
        </div>
        <div class="form-group full">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" value="<?= sanitize($editData['email']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= sanitize($editData['phone'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-control">
            <option value="">— Select —</option>
            <?php foreach (['Male','Female','Other'] as $g): ?>
            <option <?= $editData['gender']===$g?'selected':'' ?>><?= $g ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Birth Date</label>
          <input type="date" name="birth_date" class="form-control" value="<?= $editData['birth_date'] ?? '' ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Department *</label>
          <select name="department_id" class="form-control" required>
            <?php foreach ($departments as $d): ?>
            <option value="<?= $d['department_id'] ?>" <?= $editData['department_id']==$d['department_id']?'selected':'' ?>><?= sanitize($d['dept_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <?php foreach(['Active','Inactive','Graduated','On Leave'] as $s): ?>
            <option <?= $editData['status']===$s?'selected':'' ?>><?= $s ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Enrollment Date</label>
          <input type="date" name="enrollment_date" class="form-control" value="<?= $editData['enrollment_date'] ?? '' ?>">
        </div>
        <div class="form-group full">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="2"><?= sanitize($editData['address'] ?? '') ?></textarea>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <a href="/university/students.php" class="btn btn-outline">Cancel</a>
      <button type="submit" class="btn btn-primary">Update Student</button>
    </div>
  </form>
</div>
</div>
<?php endif; ?>

<!-- DELETE CONFIRM -->
<div class="modal-overlay" id="modal-delete">
<div class="confirm-box">
  <div class="confirm-icon">⚠️</div>
  <div class="confirm-title">Deactivate Student?</div>
  <div class="confirm-msg" id="confirm-msg">This will set the student's status to Inactive.</div>
  <form method="POST">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="student_id" id="delete-id">
    <div class="confirm-btns">
      <button type="button" class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button>
      <button type="submit" class="btn btn-danger">Deactivate</button>
    </div>
  </form>
</div>
</div>

<?php layoutEnd(); ?>