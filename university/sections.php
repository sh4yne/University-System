<?php
// sections.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add' || $action === 'edit') {
        $d=['section_code'=>trim($_POST['section_code']??''),'course_id'=>(int)($_POST['course_id']??0),
            'faculty_id'=>(int)($_POST['faculty_id']??0),'semester'=>trim($_POST['semester']??''),
            'school_year'=>trim($_POST['school_year']??''),'schedule'=>trim($_POST['schedule']??''),
            'max_capacity'=>(int)($_POST['max_capacity']??40),'status'=>trim($_POST['status']??'Open')];
        if(!$d['section_code']||!$d['course_id']||!$d['faculty_id']||!$d['semester']||!$d['school_year']){flash('error','Required fields missing.');}
        else {
            try {
                if($action==='add'){$db->prepare("INSERT INTO class_sections(section_code,course_id,faculty_id,semester,school_year,schedule,max_capacity,status)VALUES(?,?,?,?,?,?,?,?)")->execute(array_values($d));flash('success','Section created.');}
                else{$id=(int)($_POST['section_id']??0);$db->prepare("UPDATE class_sections SET section_code=?,course_id=?,faculty_id=?,semester=?,school_year=?,schedule=?,max_capacity=?,status=? WHERE section_id=?")->execute([...array_values($d),$id]);flash('success','Section updated.');}
            } catch(PDOException $e){flash('error','Section code already exists.');}
        }
    } elseif($action==='delete'){
        $db->prepare("UPDATE class_sections SET status='Cancelled' WHERE section_id=?")->execute([(int)($_POST['section_id']??0)]);
        flash('success','Section cancelled.');
    }
    header('Location: /university/sections.php'); exit;
}

$editData=null;
if(isset($_GET['edit'])){$s=$db->prepare("SELECT * FROM class_sections WHERE section_id=?");$s->execute([(int)$_GET['edit']]);$editData=$s->fetch();}
$semF=$_GET['sem']??'';$syF=trim($_GET['sy']??'');$statusF=$_GET['status']??'';
$where=['1=1'];$params=[];
if($semF){$where[]='cs.semester=?';$params[]=$semF;}
if($syF){$where[]='cs.school_year=?';$params[]=$syF;}
if($statusF){$where[]='cs.status=?';$params[]=$statusF;}
$w=implode(' AND ',$where);
$stmt=$db->prepare("SELECT cs.*,c.course_code,c.course_name,CONCAT(f.first_name,' ',f.last_name) AS instructor,(SELECT COUNT(*) FROM enrollments e WHERE e.section_id=cs.section_id AND e.status='Enrolled') AS enrolled FROM class_sections cs JOIN courses c ON cs.course_id=c.course_id JOIN faculty f ON cs.faculty_id=f.faculty_id WHERE $w ORDER BY cs.school_year DESC,cs.semester,c.course_code");
$stmt->execute($params);$rows=$stmt->fetchAll();
$courses=$db->query("SELECT * FROM courses ORDER BY course_code")->fetchAll();
$faculty=$db->query("SELECT faculty_id,employee_number,CONCAT(first_name,' ',last_name) AS name FROM faculty WHERE status='Active' ORDER BY last_name")->fetchAll();

layoutHead('Sections'); layoutSidebar('sections.php');
if($editData) echo '<script>document.addEventListener("DOMContentLoaded",()=>openModal("edit"));</script>';
flashBanner();
?>
<div class="page-header">
  <div><h1 class="page-title">Class Sections</h1><p class="page-sub">Schedule and section management</p></div>
  <button class="btn btn-primary" onclick="openModal('add')">+ Add Section</button>
</div>
<div class="card">
  <form method="GET" class="filters">
    <select name="sem" class="filter-select"><option value="">All Semesters</option>
      <?php foreach(['1st','2nd','Summer'] as $s): ?><option <?=$semF===$s?'selected':''?>><?=$s?></option><?php endforeach; ?>
    </select>
    <input type="text" name="sy" class="filter-input" placeholder="School year e.g. 2024-2025" value="<?=htmlspecialchars($syF)?>" style="max-width:200px">
    <select name="status" class="filter-select"><option value="">All Status</option>
      <?php foreach(['Open','Closed','Cancelled','Completed'] as $s): ?><option <?=$statusF===$s?'selected':''?>><?=$s?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="/university/sections.php" class="btn btn-outline btn-sm">Reset</a>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Code</th><th>Course</th><th>Instructor</th><th>Schedule</th><th>Semester</th><th>Enrolled</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): $full=$r['enrolled']>=$r['max_capacity']; ?>
      <tr>
        <td><span class="td-mono"><?=sanitize($r['section_code'])?></span></td>
        <td><div style="font-weight:600"><?=sanitize($r['course_code'])?></div><div style="font-size:12px;color:#94a3b8"><?=sanitize($r['course_name'])?></div></td>
        <td><?=sanitize($r['instructor'])?></td>
        <td style="font-size:12px"><?=sanitize($r['schedule']??'—')?></td>
        <td style="font-size:12px;color:#64748b"><?=$r['semester']?> <?=$r['school_year']?></td>
        <td><span class="badge badge-<?=$full?'danger':'success'?>"><?=$r['enrolled']?>/<?=$r['max_capacity']?></span></td>
        <td><?php $c=match($r['status']){'Open'=>'success','Closed'=>'warning','Completed'=>'info',default=>'muted'};echo "<span class='badge badge-$c'>{$r['status']}</span>";?></td>
        <td><div class="td-actions">
          <a href="?edit=<?=$r['section_id']?>" class="btn-icon">✏️ Edit</a>
          <button class="btn-icon btn-del" onclick="confirmDelete(<?=$r['section_id']?>,'<?=sanitize($r['section_code'])?>')">✕ Cancel</button>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="8" class="table-empty">No sections found.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="modal-add"><div class="modal">
  <div class="modal-header"><span class="modal-title">Add Class Section</span><button class="modal-close" onclick="closeModal('modal-add')">×</button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body"><div class="form-grid">
    <div class="form-group full"><label class="form-label">Section Code *</label><input type="text" name="section_code" class="form-control" placeholder="CS101-A-1S2425" required></div>
    <div class="form-group full"><label class="form-label">Course *</label><select name="course_id" class="form-control" required><option value="">— Select —</option>
      <?php foreach($courses as $c): ?><option value="<?=$c['course_id']?>"><?=sanitize($c['course_code'])?> — <?=sanitize($c['course_name'])?></option><?php endforeach; ?></select></div>
    <div class="form-group full"><label class="form-label">Faculty *</label><select name="faculty_id" class="form-control" required><option value="">— Select —</option>
      <?php foreach($faculty as $f): ?><option value="<?=$f['faculty_id']?>"><?=sanitize($f['name'])?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label class="form-label">Semester *</label><select name="semester" class="form-control"><option>1st</option><option>2nd</option><option>Summer</option></select></div>
    <div class="form-group"><label class="form-label">School Year *</label><input type="text" name="school_year" class="form-control" placeholder="2024-2025" required></div>
    <div class="form-group"><label class="form-label">Schedule</label><input type="text" name="schedule" class="form-control" placeholder="MWF 08:00-09:00"></div>
    <div class="form-group"><label class="form-label">Max Capacity</label><input type="number" name="max_capacity" class="form-control" value="40" min="1"></div>
    <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control"><option>Open</option><option>Closed</option></select></div>
  </div></div>
  <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button><button type="submit" class="btn btn-primary">Create Section</button></div>
  </form>
</div></div>

<?php if($editData): ?>
<div class="modal-overlay" id="modal-edit"><div class="modal">
  <div class="modal-header"><span class="modal-title">Edit Section</span><button class="modal-close" onclick="window.location='/university/sections.php'">×</button></div>
  <form method="POST"><input type="hidden" name="action" value="edit"><input type="hidden" name="section_id" value="<?=$editData['section_id']?>">
  <div class="modal-body"><div class="form-grid">
    <div class="form-group full"><label class="form-label">Section Code *</label><input type="text" name="section_code" class="form-control" value="<?=sanitize($editData['section_code'])?>" required></div>
    <div class="form-group full"><label class="form-label">Course *</label><select name="course_id" class="form-control">
      <?php foreach($courses as $c): ?><option value="<?=$c['course_id']?>" <?=$editData['course_id']==$c['course_id']?'selected':''?>><?=sanitize($c['course_code'])?> — <?=sanitize($c['course_name'])?></option><?php endforeach; ?></select></div>
    <div class="form-group full"><label class="form-label">Faculty *</label><select name="faculty_id" class="form-control">
      <?php foreach($faculty as $f): ?><option value="<?=$f['faculty_id']?>" <?=$editData['faculty_id']==$f['faculty_id']?'selected':''?>><?=sanitize($f['name'])?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label class="form-label">Semester *</label><select name="semester" class="form-control">
      <?php foreach(['1st','2nd','Summer'] as $s): ?><option <?=$editData['semester']===$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></div>
    <div class="form-group"><label class="form-label">School Year *</label><input type="text" name="school_year" class="form-control" value="<?=sanitize($editData['school_year'])?>"></div>
    <div class="form-group"><label class="form-label">Schedule</label><input type="text" name="schedule" class="form-control" value="<?=sanitize($editData['schedule']??'')?>"></div>
    <div class="form-group"><label class="form-label">Max Capacity</label><input type="number" name="max_capacity" class="form-control" value="<?=$editData['max_capacity']?>"></div>
    <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control">
      <?php foreach(['Open','Closed','Cancelled','Completed'] as $s): ?><option <?=$editData['status']===$s?'selected':''?>><?=$s?></option><?php endforeach; ?></select></div>
  </div></div>
  <div class="modal-footer"><a href="/university/sections.php" class="btn btn-outline">Cancel</a><button type="submit" class="btn btn-primary">Update Section</button></div>
  </form>
</div></div>
<?php endif; ?>

<div class="modal-overlay" id="modal-delete"><div class="confirm-box">
  <div class="confirm-icon">⚠️</div><div class="confirm-title">Cancel Section?</div>
  <div class="confirm-msg" id="confirm-msg">This section will be marked as Cancelled.</div>
  <form method="POST"><input type="hidden" name="action" value="delete"><input type="hidden" name="section_id" id="delete-id">
  <div class="confirm-btns"><button type="button" class="btn btn-outline" onclick="closeModal('modal-delete')">Back</button><button type="submit" class="btn btn-danger">Cancel Section</button></div>
  </form>
</div></div>
<?php layoutEnd(); ?>