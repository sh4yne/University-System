<?php
// courses.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $d = ['course_code'=>trim($_POST['course_code']??''),'course_name'=>trim($_POST['course_name']??''),
              'units'=>(int)($_POST['units']??3),'department_id'=>(int)($_POST['department_id']??0),
              'description'=>trim($_POST['description']??'')];
        if (!$d['course_code']||!$d['course_name']||!$d['department_id']) { flash('error','Required fields missing.'); }
        else {
            try {
                if ($action==='add') {
                    $db->prepare("INSERT INTO courses(course_code,course_name,units,department_id,description)VALUES(?,?,?,?,?)")->execute(array_values($d));
                    flash('success','Course added.');
                } else {
                    $id=(int)($_POST['course_id']??0);
                    $db->prepare("UPDATE courses SET course_code=?,course_name=?,units=?,department_id=?,description=? WHERE course_id=?")->execute([...array_values($d),$id]);
                    flash('success','Course updated.');
                }
            } catch(PDOException $e){ flash('error','Course code already exists.'); }
        }
    } elseif ($action==='delete') {
        try {
            $db->prepare("DELETE FROM courses WHERE course_id=?")->execute([(int)($_POST['course_id']??0)]);
            flash('success','Course deleted.');
        } catch(PDOException $e){ flash('error','Cannot delete: course has active sections.'); }
    }
    header('Location: /university/courses.php'); exit;
}

$editData=null;
if(isset($_GET['edit'])){$s=$db->prepare("SELECT * FROM courses WHERE course_id=?");$s->execute([(int)$_GET['edit']]);$editData=$s->fetch();}
$search=trim($_GET['search']??''); $deptF=(int)($_GET['dept']??0);
$where=['1=1'];$params=[];
if($search){$where[]="(c.course_code LIKE ? OR c.course_name LIKE ?)";$like="%$search%";array_push($params,$like,$like);}
if($deptF){$where[]='c.department_id=?';$params[]=$deptF;}
$w=implode(' AND ',$where);
$stmt=$db->prepare("SELECT c.*,d.dept_name,(SELECT COUNT(*) FROM class_sections cs WHERE cs.course_id=c.course_id) AS section_count FROM courses c JOIN departments d ON c.department_id=d.department_id WHERE $w ORDER BY c.course_code");
$stmt->execute($params);$rows=$stmt->fetchAll();
$departments=$db->query("SELECT * FROM departments ORDER BY dept_name")->fetchAll();

layoutHead('Courses'); layoutSidebar('courses.php');
if($editData) echo '<script>document.addEventListener("DOMContentLoaded",()=>openModal("edit"));</script>';
flashBanner();
?>
<div class="page-header">
  <div><h1 class="page-title">Courses</h1><p class="page-sub">Course catalog</p></div>
  <button class="btn btn-primary" onclick="openModal('add')">+ Add Course</button>
</div>
<div class="card">
  <form method="GET" class="filters">
    <input type="text" name="search" class="filter-input" placeholder="Search code or name…" value="<?=htmlspecialchars($search)?>">
    <select name="dept" class="filter-select"><option value="">All Departments</option>
      <?php foreach($departments as $d): ?><option value="<?=$d['department_id']?>" <?=$deptF==$d['department_id']?'selected':''?>><?=sanitize($d['dept_name'])?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="/university/courses.php" class="btn btn-outline btn-sm">Reset</a>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Code</th><th>Course Name</th><th>Units</th><th>Department</th><th>Sections</th><th>Description</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><span class="td-mono"><?=sanitize($r['course_code'])?></span></td>
        <td style="font-weight:600"><?=sanitize($r['course_name'])?></td>
        <td><span class="badge badge-gold"><?=$r['units']?> units</span></td>
        <td><?=sanitize($r['dept_name'])?></td>
        <td><span class="badge badge-info"><?=$r['section_count']?> sections</span></td>
        <td style="font-size:12px;color:#64748b;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=sanitize($r['description']??'')?></td>
        <td><div class="td-actions">
          <a href="?edit=<?=$r['course_id']?>" class="btn-icon">✏️ Edit</a>
          <button class="btn-icon btn-del" onclick="confirmDelete(<?=$r['course_id']?>,'<?=sanitize($r['course_code'])?>')">🗑 Delete</button>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="7" class="table-empty">No courses found.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="modal-overlay" id="modal-add"><div class="modal">
  <div class="modal-header"><span class="modal-title">Add Course</span><button class="modal-close" onclick="closeModal('modal-add')">×</button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body"><div class="form-grid">
    <div class="form-group"><label class="form-label">Course Code *</label><input type="text" name="course_code" class="form-control" placeholder="CS101" required></div>
    <div class="form-group"><label class="form-label">Units *</label><input type="number" name="units" class="form-control" min="1" max="6" value="3" required></div>
    <div class="form-group full"><label class="form-label">Course Name *</label><input type="text" name="course_name" class="form-control" required></div>
    <div class="form-group full"><label class="form-label">Department *</label><select name="department_id" class="form-control" required><option value="">— Select —</option>
      <?php foreach($departments as $d): ?><option value="<?=$d['department_id']?>"><?=sanitize($d['dept_name'])?></option><?php endforeach; ?></select></div>
    <div class="form-group full"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button><button type="submit" class="btn btn-primary">Save Course</button></div>
  </form>
</div></div>

<?php if($editData): ?>
<div class="modal-overlay" id="modal-edit"><div class="modal">
  <div class="modal-header"><span class="modal-title">Edit Course</span><button class="modal-close" onclick="window.location='/university/courses.php'">×</button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="course_id" value="<?=$editData['course_id']?>">
  <div class="modal-body"><div class="form-grid">
    <div class="form-group"><label class="form-label">Course Code *</label><input type="text" name="course_code" class="form-control" value="<?=sanitize($editData['course_code'])?>" required></div>
    <div class="form-group"><label class="form-label">Units *</label><input type="number" name="units" class="form-control" min="1" max="6" value="<?=$editData['units']?>" required></div>
    <div class="form-group full"><label class="form-label">Course Name *</label><input type="text" name="course_name" class="form-control" value="<?=sanitize($editData['course_name'])?>" required></div>
    <div class="form-group full"><label class="form-label">Department *</label><select name="department_id" class="form-control" required>
      <?php foreach($departments as $d): ?><option value="<?=$d['department_id']?>" <?=$editData['department_id']==$d['department_id']?'selected':''?>><?=sanitize($d['dept_name'])?></option><?php endforeach; ?></select></div>
    <div class="form-group full"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"><?=sanitize($editData['description']??'')?></textarea></div>
  </div></div>
  <div class="modal-footer"><a href="/university/courses.php" class="btn btn-outline">Cancel</a><button type="submit" class="btn btn-primary">Update Course</button></div>
  </form>
</div></div>
<?php endif; ?>

<div class="modal-overlay" id="modal-delete"><div class="confirm-box">
  <div class="confirm-icon">🗑️</div><div class="confirm-title">Delete Course?</div>
  <div class="confirm-msg" id="confirm-msg">This action cannot be undone.</div>
  <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="course_id" id="delete-id">
  <div class="confirm-btns"><button type="button" class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button><button type="submit" class="btn btn-danger">Delete</button></div>
  </form>
</div></div>
<?php layoutEnd(); ?>