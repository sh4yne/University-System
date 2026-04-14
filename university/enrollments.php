<?php
// enrollments.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
requireLogin();
$db = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $sid = (int)($_POST['student_id'] ?? 0);
        $sec = (int)($_POST['section_id'] ?? 0);
        if (!$sid || !$sec) { flash('error','Student and section are required.'); }
        else {
            // Capacity check
            $cap = $db->prepare("SELECT max_capacity,status FROM class_sections WHERE section_id=?");
            $cap->execute([$sec]); $secRow = $cap->fetch();
            if (!$secRow) { flash('error','Section not found.'); }
            elseif ($secRow['status'] !== 'Open') { flash('error','Section is not open for enrollment.'); }
            else {
                $cnt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE section_id=? AND status='Enrolled'");
                $cnt->execute([$sec]);
                if ($cnt->fetchColumn() >= $secRow['max_capacity']) { flash('error','Section is at full capacity.'); }
                else {
                    try {
                        $db->prepare("INSERT INTO enrollments(student_id,section_id,enrollment_date,status)VALUES(?,?,CURDATE(),'Enrolled')")->execute([$sid,$sec]);
                        flash('success','Student enrolled successfully.');
                    } catch(PDOException $e){ flash('error','Student is already enrolled in this section.'); }
                }
            }
        }

    } elseif ($action === 'grade') {
        $id  = (int)($_POST['enrollment_id'] ?? 0);
        $mid = $_POST['midterm_grade'] !== '' ? (float)$_POST['midterm_grade'] : null;
        $fin = $_POST['final_grade']   !== '' ? (float)$_POST['final_grade']   : null;
        $db->prepare("UPDATE enrollments SET midterm_grade=?,final_grade=? WHERE enrollment_id=?")->execute([$mid,$fin,$id]);
        flash('success','Grades saved.');

    } elseif ($action === 'drop') {
        $db->prepare("UPDATE enrollments SET status='Dropped' WHERE enrollment_id=?")->execute([(int)($_POST['enrollment_id']??0)]);
        flash('success','Enrollment dropped.');
    }
    header('Location: /university/enrollments.php'); exit;
}

// Fetch for grade modal
$gradeRow = null;
if (isset($_GET['grade'])) {
    $s = $db->prepare("SELECT e.*,CONCAT(st.first_name,' ',st.last_name) AS student_name,c.course_code FROM enrollments e JOIN students st ON e.student_id=st.student_id JOIN class_sections cs ON e.section_id=cs.section_id JOIN courses c ON cs.course_id=c.course_id WHERE e.enrollment_id=?");
    $s->execute([(int)$_GET['grade']]); $gradeRow=$s->fetch();
}

$search = trim($_GET['search'] ?? '');
$statusF = $_GET['status'] ?? '';
$page = max(1,(int)($_GET['page']??1)); $perPage=12;
$where=['1=1'];$params=[];
if($search){$where[]="(st.first_name LIKE ? OR st.last_name LIKE ? OR st.student_number LIKE ? OR c.course_code LIKE ?)";$like="%$search%";array_push($params,$like,$like,$like,$like);}
if($statusF){$where[]='e.status=?';$params[]=$statusF;}
$w=implode(' AND ',$where);
$cnt=$db->prepare("SELECT COUNT(*) FROM enrollments e JOIN students st ON e.student_id=st.student_id JOIN class_sections cs ON e.section_id=cs.section_id JOIN courses c ON cs.course_id=c.course_id WHERE $w");
$cnt->execute($params);$total=(int)$cnt->fetchColumn();
$totalPages=max(1,ceil($total/$perPage));$offset=($page-1)*$perPage;
$stmt=$db->prepare("SELECT e.*,st.student_number,CONCAT(st.first_name,' ',st.last_name) AS student_name,c.course_code,c.course_name,c.units,cs.section_code,cs.semester,cs.school_year FROM enrollments e JOIN students st ON e.student_id=st.student_id JOIN class_sections cs ON e.section_id=cs.section_id JOIN courses c ON cs.course_id=c.course_id WHERE $w ORDER BY e.enrollment_id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);$rows=$stmt->fetchAll();

$students  = $db->query("SELECT student_id,student_number,CONCAT(first_name,' ',last_name) AS name FROM students WHERE status='Active' ORDER BY last_name")->fetchAll();
$sections  = $db->query("SELECT cs.section_id,cs.section_code,c.course_code,cs.semester,cs.school_year,(SELECT COUNT(*) FROM enrollments e WHERE e.section_id=cs.section_id AND e.status='Enrolled') AS enrolled,cs.max_capacity FROM class_sections cs JOIN courses c ON cs.course_id=c.course_id WHERE cs.status='Open' ORDER BY c.course_code")->fetchAll();

layoutHead('Enrollments'); layoutSidebar('enrollments.php');
if($gradeRow) echo '<script>document.addEventListener("DOMContentLoaded",()=>openModal("grade"));</script>';
flashBanner();

function gradeClass($g): string {
    if ($g===null) return '';
    return $g >= 75 ? 'grade-pass' : 'grade-fail';
}
function computed($mid,$fin): string {
    if ($mid===null||$fin===null) return '—';
    $c = round($mid*0.4+$fin*0.6,2);
    $cls = $c>=75?'grade-pass':'grade-fail';
    return "<span class='$cls'>$c</span>";
}
?>
<div class="page-header">
  <div><h1 class="page-title">Enrollments</h1><p class="page-sub"><?=number_format($total)?> record<?=$total!=1?'s':''?></p></div>
  <button class="btn btn-primary" onclick="openModal('add')">+ Enroll Student</button>
</div>
<div class="card">
  <form method="GET" class="filters">
    <input type="text" name="search" class="filter-input" placeholder="Search student, number, or course…" value="<?=htmlspecialchars($search)?>">
    <select name="status" class="filter-select"><option value="">All Status</option>
      <?php foreach(['Enrolled','Dropped','Completed','Failed'] as $s): ?><option <?=$statusF===$s?'selected':''?>><?=$s?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <a href="/university/enrollments.php" class="btn btn-outline btn-sm">Reset</a>
  </form>
  <div class="table-wrap"><table>
    <thead><tr><th>Student #</th><th>Student</th><th>Course</th><th>Section</th><th>Semester</th><th>Midterm</th><th>Final</th><th>Computed</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($rows as $r): ?>
      <tr>
        <td><span class="td-mono"><?=sanitize($r['student_number'])?></span></td>
        <td style="font-weight:600"><?=sanitize($r['student_name'])?></td>
        <td><span class="td-mono"><?=sanitize($r['course_code'])?></span></td>
        <td style="font-size:12px"><?=sanitize($r['section_code'])?></td>
        <td style="font-size:12px;color:#64748b"><?=$r['semester']?> <?=$r['school_year']?></td>
        <td><span class="<?=gradeClass($r['midterm_grade'])?>"><?=$r['midterm_grade']??'—'?></span></td>
        <td><span class="<?=gradeClass($r['final_grade'])?>"><?=$r['final_grade']??'—'?></span></td>
        <td><?=computed($r['midterm_grade'],$r['final_grade'])?></td>
        <td><?php $c=match($r['status']){'Enrolled'=>'success','Completed'=>'info','Dropped'=>'muted',default=>'danger'};echo "<span class='badge badge-$c'>{$r['status']}</span>"; ?></td>
        <td><div class="td-actions">
          <a href="?grade=<?=$r['enrollment_id']?>" class="btn-icon">📝 Grade</a>
          <?php if($r['status']==='Enrolled'): ?>
          <button class="btn-icon btn-del" onclick="confirmDelete(<?=$r['enrollment_id']?>,'<?=sanitize($r['student_name'])?>')">✕ Drop</button>
          <?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$rows): ?><tr><td colspan="10" class="table-empty">No enrollments found.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
  <?php if($totalPages>1): $qs=http_build_query(array_filter(['search'=>$search,'status'=>$statusF]));$base='/university/enrollments.php?'.$qs.'&page='; ?>
  <div class="pagination"><span class="info"><?=$offset+1?>–<?=min($offset+$perPage,$total)?> of <?=$total?></span>
    <a href="<?=$base.max(1,$page-1)?>" class="page-link <?=$page<=1?'disabled':''?>">← Prev</a>
    <?php for($p=max(1,$page-2);$p<=min($totalPages,$page+2);$p++): ?><a href="<?=$base.$p?>" class="page-link <?=$p==$page?'active':''?>"><?=$p?></a><?php endfor; ?>
    <a href="<?=$base.min($totalPages,$page+1)?>" class="page-link <?=$page>=$totalPages?'disabled':''?>">Next →</a>
  </div>
  <?php endif; ?>
</div>

<!-- ENROLL MODAL -->
<div class="modal-overlay" id="modal-add"><div class="modal">
  <div class="modal-header"><span class="modal-title">Enroll Student</span><button class="modal-close" onclick="closeModal('modal-add')">×</button></div>
  <form method="POST"><input type="hidden" name="action" value="add">
  <div class="modal-body"><div class="form-grid">
    <div class="form-group full"><label class="form-label">Student *</label><select name="student_id" class="form-control" required><option value="">— Select student —</option>
      <?php foreach($students as $s): ?><option value="<?=$s['student_id']?>"><?=sanitize($s['student_number'])?> — <?=sanitize($s['name'])?></option><?php endforeach; ?></select></div>
    <div class="form-group full"><label class="form-label">Section *</label><select name="section_id" class="form-control" required><option value="">— Select section —</option>
      <?php foreach($sections as $s): ?><option value="<?=$s['section_id']?>"><?=sanitize($s['section_code'])?> (<?=$s['course_code']?>) — <?=$s['enrolled']?>/<?=$s['max_capacity']?> enrolled</option><?php endforeach; ?></select></div>
  </div>
  <p style="font-size:12px;color:#94a3b8;margin-top:8px">Capacity and duplicate checks are enforced automatically.</p>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-outline" onclick="closeModal('modal-add')">Cancel</button><button type="submit" class="btn btn-primary">Enroll</button></div>
  </form>
</div></div>

<!-- GRADE MODAL -->
<?php if($gradeRow): ?>
<div class="modal-overlay" id="modal-grade"><div class="modal">
  <div class="modal-header"><span class="modal-title">Enter Grades</span><button class="modal-close" onclick="window.location='/university/enrollments.php'">×</button></div>
  <form method="POST"><input type="hidden" name="action" value="grade"><input type="hidden" name="enrollment_id" value="<?=$gradeRow['enrollment_id']?>">
  <div class="modal-body">
    <p style="margin-bottom:16px;font-size:13px;color:#64748b">
      Student: <strong style="color:#0f172a"><?=sanitize($gradeRow['student_name'])?></strong> &nbsp;|&nbsp;
      Course: <strong style="color:#0f172a"><?=sanitize($gradeRow['course_code'])?></strong>
    </p>
    <div class="form-grid">
      <div class="form-group"><label class="form-label">Midterm Grade (0–100)</label>
        <input type="number" name="midterm_grade" class="form-control" min="0" max="100" step="0.01" value="<?=$gradeRow['midterm_grade']??''?>" placeholder="0.00"></div>
      <div class="form-group"><label class="form-label">Final Grade (0–100)</label>
        <input type="number" name="final_grade" class="form-control" min="0" max="100" step="0.01" value="<?=$gradeRow['final_grade']??''?>" placeholder="0.00"></div>
    </div>
    <p style="font-size:12px;color:#94a3b8;margin-top:10px">Computed = 40% Midterm + 60% Final. Passing grade: 75.</p>
  </div>
  <div class="modal-footer"><a href="/university/enrollments.php" class="btn btn-outline">Cancel</a><button type="submit" class="btn btn-success">Save Grades</button></div>
  </form>
</div></div>
<?php endif; ?>

<!-- DROP CONFIRM -->
<div class="modal-overlay" id="modal-delete"><div class="confirm-box">
  <div class="confirm-icon">⚠️</div><div class="confirm-title">Drop Enrollment?</div>
  <div class="confirm-msg" id="confirm-msg">This will change the status to Dropped.</div>
  <form method="POST"><input type="hidden" name="action" value="drop"><input type="hidden" name="enrollment_id" id="delete-id">
  <div class="confirm-btns"><button type="button" class="btn btn-outline" onclick="closeModal('modal-delete')">Cancel</button><button type="submit" class="btn btn-danger">Drop</button></div>
  </form>
</div></div>
<?php layoutEnd(); ?>